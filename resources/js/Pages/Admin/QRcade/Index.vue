<script setup>
import { Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    stats: Object,
    recentPlays: Array,
    topGames: Array,
});

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};
</script>

<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">🎮 QRcade Admin</h1>
                    <p class="text-gray-400 mt-1">Manage games, packs, and platform analytics</p>
                </div>
                <div class="flex gap-3">
                    <Link href="/admin/qrcade/games" class="btn-secondary">Manage Games</Link>
                    <Link href="/admin/qrcade/packs" class="btn-primary">Game Packs</Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-8">
                <div class="glass-card p-4 text-center">
                    <div class="text-2xl font-bold text-purple-400">{{ stats.total_games }}</div>
                    <div class="text-gray-400 text-sm">Total Games</div>
                </div>
                <div class="glass-card p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(stats.total_plays) }}</div>
                    <div class="text-gray-400 text-sm">Total Plays</div>
                </div>
                <div class="glass-card p-4 text-center">
                    <div class="text-2xl font-bold text-blue-400">{{ formatNumber(stats.plays_today) }}</div>
                    <div class="text-gray-400 text-sm">Plays Today</div>
                </div>
                <div class="glass-card p-4 text-center">
                    <div class="text-2xl font-bold text-amber-400">{{ formatNumber(stats.rewards_given) }}</div>
                    <div class="text-gray-400 text-sm">Rewards Given</div>
                </div>
                <div class="glass-card p-4 text-center">
                    <div class="text-2xl font-bold text-pink-400">{{ stats.active_subscriptions }}</div>
                    <div class="text-gray-400 text-sm">Active Packs</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Top Games -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">🏆 Top Games</h2>
                    <div class="space-y-3">
                        <div v-for="(game, index) in topGames" :key="game.game_id"
                            class="flex items-center gap-4 p-3 bg-white/5 rounded-lg">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold"
                                :class="index < 3 ? 'bg-amber-500/20 text-amber-400' : 'bg-white/10 text-gray-400'">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium">{{ game.game?.name }}</div>
                                <div class="text-gray-400 text-sm">{{ game.game?.tier }} tier</div>
                            </div>
                            <div class="text-purple-400 font-bold">{{ formatNumber(game.plays) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">⚡ Recent Activity</h2>
                    <div class="space-y-3">
                        <div v-for="play in recentPlays" :key="play.id"
                            class="flex items-center gap-3 p-3 bg-white/5 rounded-lg">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white">
                                🎮
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white text-sm truncate">
                                    {{ play.user?.name || 'Guest' }} played {{ play.game?.name }}
                                </div>
                                <div class="text-gray-400 text-xs">{{ play.business?.name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-purple-400 font-bold">{{ play.score }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                <Link href="/admin/qrcade/games" class="glass-card p-6 hover:bg-white/10 transition-colors">
                    <div class="text-2xl mb-2">🎮</div>
                    <h3 class="text-white font-semibold">Manage Games</h3>
                    <p class="text-gray-400 text-sm">Add, edit, or remove games</p>
                </Link>
                <Link href="/admin/qrcade/packs" class="glass-card p-6 hover:bg-white/10 transition-colors">
                    <div class="text-2xl mb-2">📦</div>
                    <h3 class="text-white font-semibold">Game Packs</h3>
                    <p class="text-gray-400 text-sm">Bundle games for businesses</p>
                </Link>
                <Link href="/admin/qrcade/seasonal" class="glass-card p-6 hover:bg-white/10 transition-colors">
                    <div class="text-2xl mb-2">🎄</div>
                    <h3 class="text-white font-semibold">Seasonal</h3>
                    <p class="text-gray-400 text-sm">Manage seasonal releases</p>
                </Link>
                <Link href="/admin/qrcade/analytics" class="glass-card p-6 hover:bg-white/10 transition-colors">
                    <div class="text-2xl mb-2">📊</div>
                    <h3 class="text-white font-semibold">Analytics</h3>
                    <p class="text-gray-400 text-sm">Platform-wide stats</p>
                </Link>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
.btn-primary {
    @apply px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-500 text-white rounded-lg font-medium hover:opacity-90;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg font-medium hover:bg-white/20;
}
</style>

