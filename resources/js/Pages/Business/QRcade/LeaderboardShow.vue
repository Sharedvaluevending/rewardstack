<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    leaderboard: Object,
    topEntries: Array,
    allEntries: Array,
    promotions: Array,
    periodKey: String,
    periodInfo: Object,
});

const formatScore = (score) => {
    return new Intl.NumberFormat().format(score);
};

const getRankBadge = (rank) => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return `#${rank}`;
};
</script>

<template>
    <MainLayout>
        <Head :title="`${leaderboard.name} - Rankings`" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <Link href="/business/qrcade/leaderboards" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                    ← Back to Leaderboards
                </Link>
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-white">{{ leaderboard.name }}</h1>
                        <p v-if="leaderboard.description" class="text-gray-400 mt-1">{{ leaderboard.description }}</p>
                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-400">
                            <span v-if="leaderboard.game">🎮 {{ leaderboard.game.name }}</span>
                            <span>🔄 {{ periodInfo.reset_frequency === 'never' ? 'All-Time' : periodInfo.reset_frequency }}</span>
                            <span v-if="periodInfo.period_end">📅 Ends: {{ new Date(periodInfo.period_end).toLocaleDateString() }}</span>
                        </div>
                    </div>
                    <Link :href="`/business/qrcade/leaderboards/${leaderboard.id}/edit`"
                        class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20">
                        ⚙️ Settings
                    </Link>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="glass-card p-4">
                    <div class="text-gray-400 text-sm">Total Players</div>
                    <div class="text-2xl font-bold text-white mt-1">{{ allEntries.length }}</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-gray-400 text-sm">Top Score</div>
                    <div class="text-2xl font-bold text-white mt-1">
                        {{ topEntries.length > 0 ? formatScore(topEntries[0].score) : '0' }}
                    </div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-gray-400 text-sm">Average Score</div>
                    <div class="text-2xl font-bold text-white mt-1">
                        {{ allEntries.length > 0 ? formatScore(Math.round(allEntries.reduce((sum, e) => sum + e.score, 0) / allEntries.length)) : '0' }}
                    </div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-gray-400 text-sm">Status</div>
                    <div class="text-2xl font-bold mt-1"
                        :class="leaderboard.is_active ? 'text-emerald-400' : 'text-gray-400'">
                        {{ leaderboard.is_active ? 'Active' : 'Paused' }}
                    </div>
                </div>
            </div>

            <!-- Rankings Table -->
            <div class="glass-card overflow-hidden">
                <div class="p-6 border-b border-white/10">
                    <h2 class="text-xl font-semibold text-white">Rankings</h2>
                    <p class="text-gray-400 text-sm mt-1">Current period: {{ periodKey }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-white/5">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Rank</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Player</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Score</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Games Played</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Wins</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Win Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <tr v-for="entry in topEntries" :key="entry.id" class="hover:bg-white/5">
                                <td class="px-6 py-4">
                                    <span class="text-lg font-bold text-white">{{ getRankBadge(entry.rank) }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-primary-500/20 flex items-center justify-center">
                                            <span class="text-primary-400 text-sm font-medium">
                                                {{ entry.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="text-white font-medium">{{ entry.user?.name || 'Anonymous' }}</div>
                                            <div class="text-gray-400 text-xs">{{ entry.user?.email || '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-white font-semibold">{{ formatScore(entry.score) }}</span>
                                </td>
                                <td class="px-6 py-4 text-gray-300">{{ entry.games_played || 0 }}</td>
                                <td class="px-6 py-4 text-gray-300">{{ entry.wins || 0 }}</td>
                                <td class="px-6 py-4 text-gray-300">
                                    {{ entry.games_played > 0 ? Math.round((entry.wins / entry.games_played) * 100) : 0 }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!topEntries.length" class="p-12 text-center">
                        <div class="text-4xl mb-4">🏆</div>
                        <h3 class="text-white font-semibold text-lg mb-2">No rankings yet</h3>
                        <p class="text-gray-400">Players need to play games to appear on the leaderboard</p>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>

