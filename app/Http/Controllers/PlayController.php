<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\QRCode;
use App\Models\Scan;
use App\Services\GameService;
use App\Services\LocationLockService;
use App\Services\ScanService;
use App\Services\BusinessCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Jenssegers\Agent\Agent;

class PlayController extends Controller
{
    protected GameService $gameService;
    protected LocationLockService $locationService;

    public function __construct(GameService $gameService, LocationLockService $locationService)
    {
        $this->gameService = $gameService;
        $this->locationService = $locationService;
    }

    /**
     * Game Selection after QR Scan
     */
    public function index(Request $request, string $code)
    {
        $qrCode = QRCode::where('code', $code)
            ->with([
                'business:id,name,logo_path,primary_color,secondary_color',
                'games:id,name,slug,type,time_limit,config,description,thumbnail,tier'
            ])
            ->firstOrFail();

        // Prevent redirect loops:
        // - /promo/{code} redirects to /play/{code} for QR codes with active games
        // - If /play/{code} redirects back to /promo/{code}, the browser hits "too many redirects"
        //
        // Treat QRcade types (and any QR with active games attached) as game-enabled, even if the
        // legacy boolean column wasn't set correctly.
        $hasGames = $qrCode->hasActiveGames() || in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true);

        if (!$qrCode->game_enabled && !$hasGames) {
            return redirect()->route('promotion.show', ['code' => $code, 'no_game_redirect' => 1]);
        }

        $qrCodeGames = \App\Models\QRCodeGame::where('qr_code_id', $qrCode->id)
            ->where('is_active', true)
            ->with(['game', 'promotion:id,name,discount_type,discount_value'])
            ->get()
            ->filter(function ($qrCodeGame) {
                $game = $qrCodeGame->game;
                if (!$game || !$game->isAvailable()) {
                    return false;
                }
                return $qrCodeGame->isAvailableNow();
            })
            ->values();

        // Get available games from the QR code game list (avoids mismatches when multiple games are attached)
        $games = $qrCodeGames
            ->pluck('game')
            ->filter()
            ->values();

        $promotionIds = collect();
        foreach ($qrCodeGames as $qrCodeGame) {
            if (!empty($qrCodeGame->promotion_id)) {
                $promotionIds->push((int) $qrCodeGame->promotion_id);
            }
            $tierRewards = is_array($qrCodeGame->tier_rewards) ? $qrCodeGame->tier_rewards : [];
            foreach ($tierRewards as $pid) {
                if ($pid) {
                    $promotionIds->push((int) $pid);
                }
            }
        }
        $promotionMap = \App\Models\Promotion::whereIn('id', $promotionIds->filter()->unique()->values()->all())
            ->get(['id', 'name', 'discount_type', 'discount_value'])
            ->keyBy('id')
            ->map(fn ($promo) => [
                'id' => $promo->id,
                'name' => $promo->name,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
            ]);
        
        // Ensure games is a collection
        if (!($games instanceof \Illuminate\Support\Collection)) {
            $games = collect($games);
        }
        $locationRequirements = $this->locationService->getRequirements($qrCode);

        // For normal QRcade (play-to-win), if there's only one game available, skip the chooser page.
        // This prevents confusion with leaderboard UI and matches expected "scan -> play" flow.
        if ($games->count() === 1) {
            $game = $games->first();
            if ($game && isset($game->slug)) {
                return redirect()->to('/play/' . $qrCode->code . '/game/' . $game->slug);
            }
        }

        // Only show leaderboards on the dedicated leaderboard QR type.
        // Otherwise, existing business leaderboards can make a normal QRcade feel like a "leaderboard mode" QR.
        $leaderboards = collect();
        if (($qrCode->type ?? null) === 'qrcade_leaderboard') {
            $gameIds = $games->pluck('id')->filter()->values()->all();
            
            $leaderboards = \App\Models\Leaderboard::active()
                ->where('business_id', $qrCode->business_id)
                ->where(function ($query) use ($gameIds) {
                    // Location-based leaderboards (all games)
                    $query->where('type', \App\Models\Leaderboard::TYPE_LOCATION);
                    
                    // Game-specific leaderboards for available games
                    if (!empty($gameIds)) {
                        $query->orWhere(function ($q) use ($gameIds) {
                            $q->where('type', \App\Models\Leaderboard::TYPE_GAME_SPECIFIC)
                                ->whereIn('game_id', $gameIds);
                        });
                    }
                })
                // Exclude non-resetting leaderboards whose period has already ended
                ->where(function ($query) {
                    $query->where('reset_frequency', '!=', \App\Models\Leaderboard::RESET_NEVER)
                        ->orWhereNull('current_period_end')
                        ->orWhere('current_period_end', '>', now());
                })
                ->with(['game:id,name', 'promotion:id,ends_at'])
                ->limit(5)
                ->get()
                // Also filter out leaderboards whose attached promotion has expired
                ->filter(function ($lb) {
                    if ($lb->promotion && $lb->promotion->ends_at && $lb->promotion->ends_at->isPast()) {
                        return false;
                    }
                    return true;
                })
                ->values()
                ->map(function ($lb) {
                    try {
                        $topEntries = $lb->getTopEntries(3);
                        return [
                            'id' => $lb->id,
                            'name' => $lb->name,
                            'game' => $lb->game ? ['name' => $lb->game->name] : null,
                            'top_scores' => $topEntries->map(function ($e, $index) {
                                return [
                                    'rank' => $e->rank ?? ($index + 1),
                                    'score' => $e->score ?? 0,
                                ];
                            }),
                        ];
                    } catch (\Exception $e) {
                        \Log::error('Error loading leaderboard entries', [
                            'leaderboard_id' => $lb->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        return [
                            'id' => $lb->id,
                            'name' => $lb->name,
                            'game' => $lb->game ? ['name' => $lb->game->name] : null,
                            'top_scores' => [],
                        ];
                    }
                });
        }

        return Inertia::render('Play/GameSelect', [
            'qrCode' => $qrCode,
            'business' => $qrCode->business ? [
                'id' => $qrCode->business->id,
                'name' => $qrCode->business->name,
                'logo_url' => $qrCode->business->logo_url,
                'primary_color' => $qrCode->business->primary_color,
                'secondary_color' => $qrCode->business->secondary_color,
            ] : null,
            // Plan gate: Pro/Enterprise can remove platform branding from customer-facing pages.
            'showPlatformBranding' => $qrCode->business
                ? !($qrCode->business->canAccess('remove_branding') || $qrCode->business->canAccess('white_label'))
                : true,
            'games' => $games,
            'qrCodeGames' => $qrCodeGames->map(fn ($qrCodeGame) => [
                'id' => $qrCodeGame->id,
                'game_id' => $qrCodeGame->game_id,
                'win_mode' => $qrCodeGame->win_mode,
                'prize_config' => $qrCodeGame->prize_config,
                'promotion' => $qrCodeGame->promotion ? [
                    'id' => $qrCodeGame->promotion->id,
                    'name' => $qrCodeGame->promotion->name,
                    'discount_type' => $qrCodeGame->promotion->discount_type,
                    'discount_value' => $qrCodeGame->promotion->discount_value,
                ] : null,
                'tier_rewards' => $qrCodeGame->tier_rewards,
                'score_tiers' => $qrCodeGame->score_tiers,
            ]),
            'promotionMap' => $promotionMap,
            'locationRequirements' => $locationRequirements,
            'leaderboards' => $leaderboards,
            'gameSelectUrl' => '/play/' . $qrCode->code,
        ]);
    }

    /**
     * Game Play Page
     */
    public function game(Request $request, string $code, Game $game)
    {
        $qrCode = QRCode::where('code', $code)
            ->with('business:id,name,logo_path,primary_color,secondary_color')
            ->firstOrFail();

        // Check if this is a daily puzzle and if user already completed it today
        $dailyPuzzleStatus = $this->gameService->hasCompletedDailyPuzzle(
            $request->user(),
            $game,
            $qrCode->business_id
        );

        $qrCodeGame = \App\Models\QRCodeGame::where('qr_code_id', $qrCode->id)
            ->where('game_id', $game->id)
            ->with(['promotion:id,name,discount_type,discount_value'])
            ->first();

        $promotionIds = collect();
        if ($qrCodeGame?->promotion_id) {
            $promotionIds->push((int) $qrCodeGame->promotion_id);
        }
        $tierRewards = is_array($qrCodeGame?->tier_rewards) ? $qrCodeGame->tier_rewards : [];
        foreach ($tierRewards as $pid) {
            if ($pid) {
                $promotionIds->push((int) $pid);
            }
        }
        $promotionMap = \App\Models\Promotion::whereIn('id', $promotionIds->filter()->unique()->values()->all())
            ->get(['id', 'name', 'discount_type', 'discount_value'])
            ->keyBy('id')
            ->map(fn ($promo) => [
                'id' => $promo->id,
                'name' => $promo->name,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
            ]);

        return Inertia::render('Play/Game', [
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'name' => $qrCode->name,
                'type' => $qrCode->type,
            ],
            'business' => $qrCode->business ? [
                'id' => $qrCode->business->id,
                'name' => $qrCode->business->name,
                'logo_url' => $qrCode->business->logo_url,
                'primary_color' => $qrCode->business->primary_color,
                'secondary_color' => $qrCode->business->secondary_color,
            ] : null,
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'type' => $game->type,
                'time_limit' => $game->time_limit,
                'config' => $game->config,
                'description' => $game->description,
                'thumbnail' => $game->thumbnail,
            ],
            'locationRequirements' => $this->locationService->getRequirements($qrCode),
            'dailyPuzzleStatus' => $dailyPuzzleStatus,
            'qrCodeGame' => $qrCodeGame ? [
                'id' => $qrCodeGame->id,
                'game_id' => $qrCodeGame->game_id,
                'win_mode' => $qrCodeGame->win_mode,
                'prize_config' => $qrCodeGame->prize_config,
                'promotion' => $qrCodeGame->promotion ? [
                    'id' => $qrCodeGame->promotion->id,
                    'name' => $qrCodeGame->promotion->name,
                    'discount_type' => $qrCodeGame->promotion->discount_type,
                    'discount_value' => $qrCodeGame->promotion->discount_value,
                ] : null,
                'tier_rewards' => $qrCodeGame->tier_rewards,
                'score_tiers' => $qrCodeGame->score_tiers,
            ] : null,
            'promotionMap' => $promotionMap,
        ]);
    }

    /**
     * Start a Game Session
     */
    public function startSession(Request $request, string $code, string $gameSlug)
    {
        try {
            // Force JSON response for API calls
            $request->headers->set('Accept', 'application/json');

            // Manually resolve game by slug (some clients may accidentally send JSON)
            $normalizedGameSlug = $gameSlug;
            if (is_string($normalizedGameSlug) && strlen($normalizedGameSlug) > 0 && $normalizedGameSlug[0] === '{') {
                $decoded = json_decode($normalizedGameSlug, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $normalizedGameSlug = $decoded['slug'] ?? $decoded['id'] ?? $gameSlug;
                }
            }

            $game = is_numeric($normalizedGameSlug)
                ? Game::where('id', (int) $normalizedGameSlug)->firstOrFail()
                : Game::where('slug', $normalizedGameSlug)->firstOrFail();

            $qrCode = QRCode::where('code', $code)->firstOrFail();

        $locationData = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
            'wifi_ssid' => 'nullable|string',
            'nfc_tag' => 'nullable|string',
            'is_practice' => 'nullable|boolean',
        ]);

        $isPractice = (bool) ($locationData['is_practice'] ?? false);
        unset($locationData['is_practice']);

        $session = $this->gameService->startSession(
            $qrCode,
            $game,
            $request->user(),
            $locationData,
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        // Mark session as practice mode if requested
        if ($isPractice) {
            $session->update(['is_practice' => true]);
        }

        if ($session->location_status === GameSession::LOCATION_FAILED) {
            return response()->json([
                'success' => false,
                'error' => 'Location verification failed. Please ensure you are at the business location.',
            ], 422);
        }

            // Fun-only mode: allow play, but disable rewards + tracking if user has reached reward limits.
            $funOnlyStatus = $this->gameService->getFunOnlyStatus($qrCode, $game, $request->user());
            if (($funOnlyStatus['fun_only'] ?? false)) {
                $session->update([
                    'fun_only' => true,
                    'fun_only_reason' => $funOnlyStatus['reason'] ?? 'Rewards disabled for this QR code.',
                ]);
            }

            // If the player jumped directly to /play/{code}, ensure a scan record exists
            // so Recent Activity shows the play attempt (even fun_only sessions).
            // Fun-only still records the scan so the user sees it in their activity feed;
            // business analytics (recordScan) are only bumped for non-fun-only sessions
            // to avoid inflating scan-to-redeem conversion rates.
            $user = $request->user();
            if ($user && in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true)) {
                $scanType = $qrCode->type === 'qrcade_leaderboard'
                    ? Scan::TYPE_QRCADE_LEADERBOARD
                    : Scan::TYPE_QRCADE_GAME;

                $recentScanExists = Scan::where('user_id', $user->id)
                    ->where('qr_code_id', $qrCode->id)
                    ->where('scan_type', $scanType)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->exists();

                if (!$recentScanExists) {
                    $agent = new Agent();
                    $agent->setUserAgent($request->userAgent());
                    $sessionId = $request->session()->getId();

                    $scan = Scan::create([
                        'qr_code_id' => $qrCode->id,
                        'business_id' => $qrCode->business_id,
                        'user_id' => $user->id,
                        'scan_type' => $scanType,
                        'session_id' => $sessionId,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
                        'browser' => $agent->browser(),
                        'os' => $agent->platform(),
                        'scanned_at' => now(),
                    ]);

                    if ($scan->wasRecentlyCreated && !$session->fun_only) {
                        $qrCode->recordScan($sessionId);
                        app(\App\Services\BusinessCustomerService::class)->recordScan($scan, true);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'session' => [
                    'token' => $session->session_token,
                    'expires_at' => $session->expires_at->toISOString(),
                    'location_verified' => $session->location_status === GameSession::LOCATION_VERIFIED,
                    'fun_only' => (bool) $session->fun_only,
                    'fun_only_reason' => $session->fun_only_reason,
                    'is_practice' => (bool) $session->is_practice,
                ],
            ])->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to start game session: ' . $e->getMessage()
            ], 500)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Mark Session as Playing
     */
    public function startPlaying(Request $request, GameSession $session)
    {
        if (!$session->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Session expired or invalid',
            ], 422);
        }

        // Accept practice mode flag from the client (set when user clicks "Practice Run" or "Play for Score")
        $isPractice = (bool) $request->input('is_practice', false);
        if ($isPractice !== (bool) $session->is_practice) {
            $session->update(['is_practice' => $isPractice]);
        }

        // Idempotency: avoid creating duplicate GamePlay records if the client retries or double-fires the start event.
        $existingPlay = $session->gamePlays()->latest()->first();
        if ($existingPlay) {
            return response()->json([
                'success' => true,
                'play_id' => $existingPlay->id,
                'fun_only' => (bool) $session->fun_only,
                'fun_only_reason' => $session->fun_only_reason,
                'is_practice' => (bool) $session->is_practice,
            ]);
        }

        // If the user is logged in (same browser context), attach this session to them
        // so XP + game stats update reliably.
        $user = $request->user();
        if ($user && !$session->user_id) {
            $session->update(['user_id' => $user->id]);
        }

        // Server-side play limit enforcement (daily/weekly/cooldown).
        // If limits are exceeded, the game still starts but in fun-only mode
        // so leaderboard/rewards aren't affected by unlimited plays.
        if ($user && $session->business_id && $session->game_id && !$session->fun_only) {
            $businessGame = \App\Models\BusinessGame::where('business_id', $session->business_id)
                ->where('game_id', $session->game_id)
                ->first();

            if ($businessGame) {
                $playCheck = $businessGame->canUserPlay($user);
                if (!$playCheck['allowed']) {
                    $session->update([
                        'fun_only' => true,
                        'fun_only_reason' => $playCheck['reason'] ?? 'Play limit reached',
                    ]);
                }
            }
        }

        $gamePlay = $this->gameService->startPlaying($session);

        if ($user && !$gamePlay->user_id) {
            $gamePlay->update(['user_id' => $user->id]);
        }

        return response()->json([
            'success' => true,
            'play_id' => $gamePlay->id,
            'fun_only' => (bool) $session->fun_only,
            'fun_only_reason' => $session->fun_only_reason,
            'is_practice' => (bool) $session->is_practice,
        ]);
    }

    /**
     * Submit Game Score
     */
    public function submitScore(Request $request, GameSession $session)
    {
        // Force JSON response for API calls
        $request->headers->set('Accept', 'application/json');

        // Session is resolved via route model binding

        $validated = $request->validate([
            'score' => 'required|integer|min:0',
            'game_data' => 'nullable|array',
        ]);

        // Lock the latest play row so duplicate score submissions cannot double-increment stats.
        // This prevents "played 1 game but total shows 2" even under race conditions.
        $gamePlay = DB::transaction(function () use ($session) {
            $latest = $session->gamePlays()->latest()->first();
            if (!$latest) {
                return null;
            }
            return \App\Models\GamePlay::where('id', $latest->id)->lockForUpdate()->first();
        });

        if (!$gamePlay) {
            return response()->json([
                'success' => false,
                'error' => 'No active game play found',
            ], 422);
        }

        // Idempotency: if score was already submitted for this play, return the stored result without re-processing.
        if ($gamePlay->completed_at) {
            $data = is_array($gamePlay->game_data) ? $gamePlay->game_data : [];
            $tokenCode = $data['_user_promo_token_code'] ?? null;

            return response()->json([
                'success' => true,
                'result' => [
                    'score' => (int) ($gamePlay->score ?? $validated['score']),
                    'result' => $gamePlay->result,
                    'tier' => $gamePlay->reward_tier,
                    'won' => ($gamePlay->result === \App\Models\GamePlay::RESULT_WIN),
                    'reward' => null,
                    'punch_card' => null,
                    'promotion_qr_code' => null,
                    'user_promo_token' => $tokenCode ? ['code' => $tokenCode] : null,
                    'is_high_score' => (bool) $gamePlay->is_high_score,
                    'is_personal_best' => (bool) $gamePlay->is_personal_best,
                    'badges_earned' => [],
                    'xp_earned' => 0,
                ],
            ], 200, ['Content-Type' => 'application/json']);
        }

        // If the user is logged in (same browser context), attach play to them.
        // This fixes "games played not increasing / no XP" when session was started anonymously.
        $user = $request->user();
        if ($user) {
            if (!$session->user_id) {
                $session->update(['user_id' => $user->id]);
            }
            if (!$gamePlay->user_id) {
                $gamePlay->update(['user_id' => $user->id]);
            }
        }

        // Validate daily puzzle submissions to prevent gaming
        $dailyValidation = $this->gameService->validateDailyPuzzle(
            $gamePlay,
            $validated['game_data'] ?? []
        );

        if (!$dailyValidation['valid']) {
            return response()->json([
                'success' => false,
                'error' => $dailyValidation['reason'] ?? 'Invalid daily puzzle submission',
                'already_completed' => true,
            ], 422);
        }

        $result = DB::transaction(function () use ($gamePlay, $validated) {
            // Re-lock inside the scoring transaction to ensure we still own the lock.
            $locked = \App\Models\GamePlay::where('id', $gamePlay->id)->lockForUpdate()->first();
            return $this->gameService->submitScore(
                $locked,
                $validated['score'],
                $validated['game_data'] ?? []
            );
        });

        // Ensure a Recent Activity scan exists for this play (even on losses),
        // so users can replay or view rewards from the portal list.
        // Skip scan creation for play-only (fun_only) sessions — no reward configured.
        $isFunOnly = (bool) $gamePlay->gameSession?->fun_only;
        if (!$isFunOnly) {
            $this->ensureGameScanForPlay($request, $gamePlay);
        }

        return response()->json([
            'success' => true,
            'result' => $result,
        ], 200, ['Content-Type' => 'application/json']);
    }

    protected function ensureGameScanForPlay(Request $request, GamePlay $gamePlay): void
    {
        $qrCode = QRCode::find($gamePlay->qr_code_id);
        if (!$qrCode || !in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true)) {
            return;
        }

        $scanType = $qrCode->type === 'qrcade_leaderboard'
            ? Scan::TYPE_QRCADE_LEADERBOARD
            : Scan::TYPE_QRCADE_GAME;

        $userId = $request->user()?->id ?: $gamePlay->user_id;
        $sessionId = $request->session()->getId();

        $existsQuery = Scan::where('qr_code_id', $qrCode->id)
            ->where('scan_type', $scanType);
        if ($userId) {
            $existsQuery->where('user_id', $userId);
        } else {
            $existsQuery->whereNull('user_id')->where('session_id', $sessionId);
        }

        if ($existsQuery->exists()) {
            return;
        }

        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $qrCode->business_id,
            'user_id' => $userId,
            'scan_type' => $scanType,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
            'browser' => $agent->browser(),
            'os' => $agent->platform(),
            'scanned_at' => now(),
        ]);

        if ($scan->wasRecentlyCreated) {
            $qrCode->recordScan($sessionId);
            app(BusinessCustomerService::class)->recordScan($scan, true);
            if (!$userId) {
                app(ScanService::class)->storeAnonymousScan($scan);
            }
        }
    }

    /**
     * Verify Location (AJAX)
     */
    public function verifyLocation(Request $request)
    {
        $validated = $request->validate([
            'qr_code_id' => 'required|exists:qr_codes,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        $qrCode = QRCode::findOrFail($validated['qr_code_id']);

        $verified = $this->locationService->verify($qrCode, $validated);

        return response()->json([
            'verified' => $verified,
            'requirements' => $this->locationService->getRequirements($qrCode),
        ]);
    }
}

