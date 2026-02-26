<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\GamePlay;
use App\Models\Business;
use App\Services\LocationLockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalGameController extends Controller
{
    protected LocationLockService $locationService;

    public function __construct(LocationLockService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Games Overview
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get game history stats
        $gameStats = GamePlay::where('user_id', $user->id)
            ->selectRaw('game_id, COUNT(*) as plays, MAX(score) as best_score, AVG(score) as avg_score')
            ->groupBy('game_id')
            ->with('game')
            ->orderByDesc('plays')
            ->get();

        // Recent plays
        $recentPlays = GamePlay::where('user_id', $user->id)
            ->with(['game', 'business'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Portal/Games', [
            'gameStats' => $gameStats,
            'recentPlays' => $recentPlays,
            'totalGamesPlayed' => $user->total_games_played,
            'favoriteGame' => $user->favoriteGame,
        ]);
    }

    /**
     * Find Nearby Games
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius' => 'nullable|integer|min:100|max:10000',
        ]);

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;
        $radius = $validated['radius'] ?? 1000; // Default 1km

        // If no location provided, render page with empty state prompting user to share location
        if (!$latitude || !$longitude) {
            return Inertia::render('Portal/GamesNearby', [
                'businesses' => [],
                'userLocation' => null,
                'radius' => $radius,
            ]);
        }

        $nearbyBusinesses = $this->locationService->findNearbyBusinesses(
            $latitude,
            $longitude,
            $radius
        );

        // Get businesses with active games
        $businessesWithGames = $nearbyBusinesses->filter(function ($business) {
            return $business->businessGames()->where('is_enabled', true)->exists();
        })->map(function ($business) {
            $business->games_count = $business->businessGames()->where('is_enabled', true)->count();
            $business->formatted_distance = $this->locationService->formatDistance($business->distance);
            return $business;
        });

        return Inertia::render('Portal/GamesNearby', [
            'businesses' => $businessesWithGames,
            'userLocation' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'radius' => $radius,
        ]);
    }

    /**
     * Game History
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $plays = GamePlay::where('user_id', $user->id)
            ->with(['game', 'business', 'gameReward'])
            ->latest()
            ->paginate(20);

        // Calculate totals
        $totals = [
            'total_plays' => $user->total_games_played,
            'total_wins' => $user->total_wins,
            'total_score' => $user->lifetime_score,
            'best_score' => $user->highest_score,
        ];

        return Inertia::render('Portal/GamesHistory', [
            'plays' => $plays,
            'totals' => $totals,
        ]);
    }
}

