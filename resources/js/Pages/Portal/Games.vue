<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    gameStats: Array,
    recentPlays: Array,
    totalGamesPlayed: Number,
    favoriteGame: Object,
});

const activeTab = ref('stats');
</script>

<template>
    <Head title="My Games" />
    <PortalLayout>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">🎮 My Games</h1>
            <p class="text-gray-400 text-sm mt-1">{{ totalGamesPlayed }} games played</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 flex-wrap">
            <button @click="activeTab = 'stats'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'stats' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                Game Stats
            </button>
            <button @click="activeTab = 'recent'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'recent' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                Recent
            </button>
            <Link href="/portal/leaderboards"
                class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                🏆 Leaderboards
            </Link>
            <Link href="/portal/games/nearby"
                class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                📍 Nearby
            </Link>
        </div>

        <!-- Progress Quick Links -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <Link href="/portal/levels" class="glass-card p-4 text-left hover:bg-white/10 transition-colors">
                <div class="text-xs text-gray-400">Progress</div>
                <div class="text-white font-semibold">Levels</div>
                <div class="text-xs text-gray-500 mt-1">Track your XP and rank.</div>
            </Link>
            <Link href="/portal/badges" class="glass-card p-4 text-left hover:bg-white/10 transition-colors">
                <div class="text-xs text-gray-400">Collection</div>
                <div class="text-white font-semibold">Badges</div>
                <div class="text-xs text-gray-500 mt-1">Show off achievements.</div>
            </Link>
        </div>

        <!-- Favorite Game -->
        <div v-if="favoriteGame" class="bg-gradient-to-r from-purple-600/30 to-pink-600/30 rounded-2xl p-4 mb-6 border border-purple-500/30">
            <div class="text-purple-300 text-xs font-medium mb-2">⭐ FAVORITE GAME</div>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                    🎮
                </div>
                <div>
                    <div class="text-white font-semibold text-lg">{{ favoriteGame.name }}</div>
                    <div class="text-gray-400 text-sm">{{ favoriteGame.type }}</div>
                </div>
            </div>
        </div>

        <!-- Game Stats Tab -->
        <div v-if="activeTab === 'stats'">
            <div v-if="gameStats.length" class="space-y-3">
                <div v-for="stat in gameStats" :key="stat.game_id"
                    class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-xl">
                            🎮
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-medium">{{ stat.game?.name }}</div>
                            <div class="text-gray-400 text-sm">{{ stat.plays }} plays</div>
                        </div>
                        <div class="text-right">
                            <div class="text-purple-400 font-bold">{{ Math.round(stat.avg_score) }}</div>
                            <div class="text-gray-400 text-xs">avg score</div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-white/10 text-sm">
                        <span class="text-gray-400">Best Score</span>
                        <span class="text-emerald-400 font-bold">{{ stat.best_score }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <div class="text-5xl mb-4">🎮</div>
                <h3 class="text-white font-semibold text-lg mb-2">No games yet</h3>
                <p class="text-gray-400 text-sm">Scan a QR code at a participating business to play!</p>
            </div>
        </div>

        <!-- Recent Tab -->
        <div v-if="activeTab === 'recent'">
            <div v-if="recentPlays.length" class="space-y-3">
                <div v-for="play in recentPlays" :key="play.id"
                    class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
                            🎮
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-medium">{{ play.game?.name }}</div>
                            <div class="text-gray-400 text-sm truncate">{{ play.business?.name }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-purple-400 font-bold">{{ play.score }}</div>
                            <div :class="['text-xs font-medium',
                                play.result === 'win' ? 'text-emerald-400' : 'text-gray-400']">
                                {{ play.result === 'win' ? '🎉 WON' : 'DONE' }}
                            </div>
                        </div>
                    </div>
                    <div v-if="play.is_personal_best" class="mt-2 text-center">
                        <span class="px-2 py-1 rounded-full bg-amber-500/20 text-amber-400 text-xs font-medium">
                            🏆 Personal Best!
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <p class="text-gray-400">No recent games</p>
            </div>
        </div>
    </PortalLayout>
</template>

