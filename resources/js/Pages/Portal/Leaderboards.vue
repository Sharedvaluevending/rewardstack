<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    publicLeaderboards: Array,
    myRankings: Array,
    stats: Object,
});

const activeTab = ref('my');

const getMedalEmoji = (rank) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return `#${rank}`;
};

const getRankColor = (rank) => {
    if (rank === 1) return 'text-yellow-400';
    if (rank === 2) return 'text-gray-300';
    if (rank === 3) return 'text-amber-600';
    return 'text-gray-400';
};
</script>

<template>
    <Head title="Leaderboards" />
    <PortalLayout>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">🏆 Leaderboards</h1>
            <p class="text-gray-400 text-sm mt-1">Compete with other players!</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-3 text-center border border-white/10">
                <div class="text-xl font-bold text-yellow-400">{{ stats.best_rank ? '#' + stats.best_rank : '-' }}</div>
                <div class="text-gray-400 text-xs">Best Rank</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-3 text-center border border-white/10">
                <div class="text-xl font-bold text-purple-400">{{ stats.total_leaderboards }}</div>
                <div class="text-gray-400 text-xs">Leaderboards</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-3 text-center border border-white/10">
                <div class="text-xl font-bold text-emerald-400">{{ stats.total_wins }}</div>
                <div class="text-gray-400 text-xs">Current #1s</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button @click="activeTab = 'my'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'my' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                My Rankings
            </button>
            <button @click="activeTab = 'explore'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'explore' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                Explore
            </button>
        </div>

        <!-- My Rankings -->
        <div v-if="activeTab === 'my'">
            <div v-if="myRankings.length" class="space-y-3">
                <Link v-for="entry in myRankings" :key="entry.id"
                    :href="`/portal/leaderboards/${entry.leaderboard?.id}`"
                    class="block bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10 hover:border-purple-500/50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                            <span :class="['text-lg font-bold', getRankColor(entry.rank)]">
                                {{ getMedalEmoji(entry.rank) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-medium truncate">{{ entry.leaderboard?.name }}</div>
                            <div class="text-gray-400 text-sm">Score: {{ entry.score }}</div>
                        </div>
                        <div class="text-right">
                            <div v-if="entry.rank_change !== 0"
                                :class="['text-sm font-medium',
                                    entry.rank_change > 0 ? 'text-emerald-400' : 'text-red-400']">
                                {{ entry.rank_change > 0 ? '↑' : '↓' }} {{ Math.abs(entry.rank_change) }}
                            </div>
                            <div class="text-gray-400 text-xs">{{ entry.games_played }} games</div>
                        </div>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <div class="text-5xl mb-4">🏆</div>
                <h3 class="text-white font-semibold text-lg mb-2">No rankings yet</h3>
                <p class="text-gray-400 text-sm">Play games to appear on leaderboards!</p>
            </div>
        </div>

        <!-- Explore -->
        <div v-if="activeTab === 'explore'">
            <div v-if="publicLeaderboards.length" class="space-y-3">
                <Link v-for="lb in publicLeaderboards" :key="lb.id"
                    :href="`/portal/leaderboards/${lb.id}`"
                    class="block bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10 hover:border-purple-500/50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-xl">
                            🏆
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-medium">{{ lb.name }}</div>
                            <div class="text-gray-400 text-sm">{{ lb.game?.name || 'All Games' }}</div>
                        </div>
                        <div class="text-purple-400 text-xl font-bold">→</div>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <p class="text-gray-400">No public leaderboards available</p>
            </div>
        </div>
    </PortalLayout>
</template>

