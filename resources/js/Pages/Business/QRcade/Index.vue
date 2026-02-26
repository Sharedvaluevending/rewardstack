<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    stats: Object,
    recentPlays: Array,
    topPlayers: Array,
    enabledGames: Array,
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
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                        <span class="text-4xl">🎮</span>
                        QRcade
                    </h1>
                    <p class="text-gray-400 mt-1">Manage your games, rewards, and leaderboards</p>
                </div>
                <div class="flex gap-3">
                    <Link href="/business/qrcade/games" class="btn-secondary">
                        Manage Games
                    </Link>
                    <Link href="/business/qrcade/how-to" class="btn-secondary">
                        How To
                    </Link>
                    <Link href="/business/qrcade/analytics" class="btn-primary">
                        View Analytics
                    </Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="glass-card p-6 text-center">
                    <div class="text-3xl font-bold text-emerald-400">{{ formatNumber(stats.total_plays) }}</div>
                    <div class="text-gray-400 text-sm mt-1">Total Plays</div>
                </div>
                <div class="glass-card p-6 text-center">
                    <div class="text-3xl font-bold text-blue-400">{{ formatNumber(stats.unique_players) }}</div>
                    <div class="text-gray-400 text-sm mt-1">Unique Players</div>
                </div>
                <div class="glass-card p-6 text-center">
                    <div class="text-3xl font-bold text-purple-400">{{ stats.win_rate }}%</div>
                    <div class="text-gray-400 text-sm mt-1">Win Rate</div>
                </div>
                <div class="glass-card p-6 text-center">
                    <div class="text-3xl font-bold text-amber-400">{{ stats.rewards_redeemed }}</div>
                    <div class="text-gray-400 text-sm mt-1">Rewards Redeemed</div>
                </div>
                <div class="glass-card p-6 text-center">
                    <div class="text-3xl font-bold text-emerald-300">{{ formatNumber(stats.punches_awarded || 0) }}</div>
                    <div class="text-gray-400 text-sm mt-1">Punches Awarded</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Active Games -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <span>🕹️</span> Active Games
                    </h2>
                    <div v-if="enabledGames.length" class="space-y-3">
                        <div v-for="bg in enabledGames" :key="bg.id" 
                            class="flex items-center gap-3 p-3 bg-white/5 rounded-lg">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
                                {{ bg.game?.icon || '🎮' }}
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium">{{ bg.game?.name }}</div>
                                <div class="text-gray-400 text-xs">{{ bg.game?.tier }} tier</div>
                            </div>
                            <div class="text-emerald-400 text-xs">Active</div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <p>No games enabled yet</p>
                        <Link href="/business/qrcade/games" class="text-primary-400 hover:underline mt-2 inline-block">
                            Enable games →
                        </Link>
                    </div>
                </div>

                <!-- Top Players This Week -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <span>🏆</span> Top Players This Week
                    </h2>
                    <div v-if="topPlayers.length" class="space-y-3">
                        <div v-for="(player, index) in topPlayers" :key="player.user_id"
                            class="flex items-center gap-3 p-3 bg-white/5 rounded-lg">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-lg"
                                :class="{
                                    'bg-yellow-500/20 text-yellow-400': index === 0,
                                    'bg-gray-400/20 text-gray-300': index === 1,
                                    'bg-amber-600/20 text-amber-500': index === 2,
                                    'bg-white/10 text-gray-400': index > 2
                                }">
                                {{ index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium">{{ player.user?.name || 'Anonymous' }}</div>
                                <div class="text-gray-400 text-xs">{{ player.games_played }} games</div>
                            </div>
                            <div class="text-primary-400 font-bold">{{ formatNumber(player.total_score) }}</div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <p>No players yet this week</p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <span>⚡</span> Recent Activity
                    </h2>
                    <div v-if="recentPlays.length" class="space-y-3">
                        <div v-for="play in recentPlays" :key="play.id"
                            class="flex items-center gap-3 p-3 bg-white/5 rounded-lg">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-sm font-bold">
                                {{ play.user?.name?.charAt(0) || '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white text-sm truncate">
                                    {{ play.user?.name || 'Guest' }} played {{ play.game?.name }}
                                </div>
                                <div class="text-gray-400 text-xs">
                                    Score: {{ play.score }} • {{ play.result === 'win' ? '🎉 Won!' : 'Completed' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <p>No recent activity</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                <Link href="/business/qrcade/games" 
                    class="glass-card p-6 hover:bg-white/10 transition-colors group">
                    <div class="text-2xl mb-2">🎮</div>
                    <h3 class="text-white font-semibold group-hover:text-primary-400 transition-colors">Manage Games</h3>
                    <p class="text-gray-400 text-sm mt-1">Enable/disable games and set limits</p>
                </Link>
                <Link href="/business/qrcade/rewards" 
                    class="glass-card p-6 hover:bg-white/10 transition-colors group">
                    <div class="text-2xl mb-2">🎁</div>
                    <h3 class="text-white font-semibold group-hover:text-primary-400 transition-colors">Configure Rewards</h3>
                    <p class="text-gray-400 text-sm mt-1">Set up prizes and win conditions</p>
                </Link>
                <Link href="/business/qrcade/schedule" 
                    class="glass-card p-6 hover:bg-white/10 transition-colors group">
                    <div class="text-2xl mb-2">📅</div>
                    <h3 class="text-white font-semibold group-hover:text-primary-400 transition-colors">Schedule Games</h3>
                    <p class="text-gray-400 text-sm mt-1">Set availability by day/time</p>
                </Link>
                <Link href="/business/qrcade/leaderboards" 
                    class="glass-card p-6 hover:bg-white/10 transition-colors group">
                    <div class="text-2xl mb-2">🏆</div>
                    <h3 class="text-white font-semibold group-hover:text-primary-400 transition-colors">Leaderboards</h3>
                    <p class="text-gray-400 text-sm mt-1">Manage player rankings and prizes</p>
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
    @apply px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-500 text-white rounded-lg font-medium hover:opacity-90 transition-opacity;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg font-medium hover:bg-white/20 transition-colors;
}
</style>

