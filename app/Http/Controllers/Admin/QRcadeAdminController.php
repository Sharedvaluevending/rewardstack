<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePack;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\BusinessGame;
use App\Models\BusinessGamePack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QRcadeAdminController extends Controller
{
    /**
     * QRcade Admin Dashboard
     */
    public function index()
    {
        $stats = [
            'total_games' => Game::count(),
            'active_games' => Game::where('is_active', true)->count(),
            'total_packs' => GamePack::count(),
            'total_plays' => GamePlay::count(),
            'plays_today' => GamePlay::whereDate('created_at', today())->count(),
            'plays_this_week' => GamePlay::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
            'rewards_given' => GameReward::count(),
            'rewards_redeemed' => GameReward::where('status', 'redeemed')->count(),
            'active_subscriptions' => BusinessGamePack::where('status', 'active')->count(),
        ];

        $recentPlays = GamePlay::with(['user', 'game', 'business'])
            ->latest()
            ->limit(10)
            ->get();

        $topGames = GamePlay::selectRaw('game_id, COUNT(*) as plays')
            ->with('game')
            ->groupByRaw('game_id')
            ->orderByDesc('plays')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/QRcade/Index', [
            'stats' => $stats,
            'recentPlays' => $recentPlays,
            'topGames' => $topGames,
        ]);
    }

    /**
     * Games Management
     */
    public function games()
    {
        $games = Game::withCount(['gamePlays', 'businessGames'])
            ->orderBy('tier')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/QRcade/Games', [
            'games' => $games,
            'tiers' => Game::tiers(),
            'gameTypes' => Game::gameTypes(),
        ]);
    }

    /**
     * Store New Game
     */
    public function storeGame(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:games',
            'description' => 'nullable|string',
            'type' => 'required|string',
            'tier' => 'required|in:basic,pro,premium,seasonal',
            'category' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:0',
            'min_score' => 'integer|min:0',
            'max_score' => 'nullable|integer',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        Game::create($validated);

        return back()->with('success', 'Game created successfully');
    }

    /**
     * Update Game
     */
    public function updateGame(Request $request, Game $game)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tier' => 'required|in:basic,pro,premium,seasonal',
            'time_limit' => 'nullable|integer|min:0',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $game->update($validated);

        return back()->with('success', 'Game updated successfully');
    }

    /**
     * Game Packs Management
     */
    public function packs()
    {
        $packs = GamePack::withCount(['games', 'businessGamePacks'])
            ->orderBy('sort_order')
            ->get();

        $games = Game::where('is_active', true)->get();

        return Inertia::render('Admin/QRcade/Packs', [
            'packs' => $packs,
            'games' => $games,
        ]);
    }

    /**
     * Store New Pack
     */
    public function storePack(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:game_packs',
            'description' => 'nullable|string',
            'type' => 'required|in:subscription,one_time',
            'category' => 'nullable|string',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'price_one_time' => 'nullable|numeric|min:0',
            'game_ids' => 'required|array',
            'game_ids.*' => 'exists:games,id',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $pack = GamePack::create($validated);
        $pack->games()->sync($request->game_ids);

        return back()->with('success', 'Game pack created successfully');
    }

    /**
     * Seasonal Games Management
     */
    public function seasonal()
    {
        $seasonalGames = Game::where('is_seasonal', true)
            ->orderBy('season_start')
            ->get();

        $upcomingSeasons = $seasonalGames->filter(fn($g) => $g->season_start && $g->season_start->isFuture());
        $activeSeasons = $seasonalGames->filter(fn($g) => $g->isAvailable());
        $pastSeasons = $seasonalGames->filter(fn($g) => $g->season_end && $g->season_end->isPast());

        return Inertia::render('Admin/QRcade/Seasonal', [
            'upcomingSeasons' => $upcomingSeasons,
            'activeSeasons' => $activeSeasons,
            'pastSeasons' => $pastSeasons,
        ]);
    }

    /**
     * Platform-wide Analytics
     */
    public function analytics(Request $request)
    {
        $days = max(1, min(90, (int) ($request->input('days') ?? 30)));
        $startDate = now()->subDays($days);

        // Daily plays
        $dailyPlays = GamePlay::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as plays, SUM(score) as total_score')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // Revenue from game packs
        $packRevenue = BusinessGamePack::where('status', 'active')
            ->with('gamePack')
            ->get()
            ->groupBy('game_pack_id')
            ->map(fn($group) => [
                'pack' => $group->first()->gamePack,
                'subscribers' => $group->count(),
                'monthly_revenue' => $group->count() * ($group->first()->gamePack->price_monthly ?? 0),
            ]);

        // Top businesses by game engagement
        $topBusinesses = GamePlay::where('created_at', '>=', $startDate)
            ->selectRaw('business_id, COUNT(*) as plays, COUNT(DISTINCT user_id) as players')
            ->with('business')
            ->groupBy('business_id')
            ->orderByDesc('plays')
            ->limit(10)
            ->get();

        // Game performance
        $gamePerformance = GamePlay::where('created_at', '>=', $startDate)
            ->selectRaw('game_id, COUNT(*) as plays, AVG(score) as avg_score, MAX(score) as high_score')
            ->with('game')
            ->groupBy('game_id')
            ->orderByDesc('plays')
            ->get();

        return Inertia::render('Admin/QRcade/Analytics', [
            'dailyPlays' => $dailyPlays,
            'packRevenue' => $packRevenue,
            'topBusinesses' => $topBusinesses,
            'gamePerformance' => $gamePerformance,
            'summary' => [
                'total_plays' => GamePlay::where('created_at', '>=', $startDate)->count(),
                'unique_players' => (int) GamePlay::where('created_at', '>=', $startDate)->count(DB::raw('DISTINCT user_id')),
                'rewards_given' => GameReward::where('created_at', '>=', $startDate)->count(),
                'total_reward_value' => GameReward::where('created_at', '>=', $startDate)->sum('discount_value'),
            ],
        ]);
    }
}

