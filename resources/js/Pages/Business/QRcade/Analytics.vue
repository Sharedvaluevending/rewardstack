<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    dailyStats: Array,
    gameStats: Array,
    peakHours: Array,
    summary: Object,
    rewardStats: Object,
    tieredConfigured: Boolean,
});

const selectedPeriod = ref(30);

const changePeriod = (period) => {
    selectedPeriod.value = period;
    router.get('/business/qrcade/analytics', { days: period }, {
        preserveState: false,
        replace: true,
    });
};

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};

const chartHeight = 150;
const maxPlaysValue = computed(() => {
    const peak = Math.max(...props.dailyStats.map(d => d.total_plays || 0));
    return Math.max(peak * 1.15, 1);
});

// Grid labels for Y-axis
const yAxisLabels = computed(() => {
    const max = maxPlaysValue.value;
    return [
        { label: formatNumber(Math.round(max)), top: '0%' },
        { label: formatNumber(Math.round(max * 0.5)), top: '50%' },
        { label: '0', top: '100%' }
    ];
});

// Get bar height as percentage
const getBarHeight = (value, max) => {
    if (value === 0) return 0;
    return Math.max((value / max) * 100, 2);
};
</script>

<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <Link href="/business/qrcade" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to QRcade
                    </Link>
                    <h1 class="text-3xl font-bold text-white">QRcade Analytics</h1>
                    <p class="text-gray-400 mt-1">Track game performance and player engagement</p>
                </div>
                <div class="flex gap-2">
                    <button v-for="period in [7, 30, 90]" :key="period"
                        @click="changePeriod(period)"
                        :class="['px-4 py-2 rounded-lg font-medium transition-colors',
                            selectedPeriod === period 
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white/10 text-gray-300 hover:bg-white/20']">
                        {{ period }}D
                    </button>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-8">
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(summary.total_plays) }}</div>
                    <div class="text-gray-400 text-sm">Total Plays</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-blue-400">{{ formatNumber(summary.unique_players) }}</div>
                    <div class="text-gray-400 text-sm">Unique Players</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-purple-400">{{ formatNumber(summary.avg_score) }}</div>
                    <div class="text-gray-400 text-sm">Avg Score</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-amber-400">{{ summary.win_rate }}%</div>
                    <div class="text-gray-400 text-sm">Win Rate</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-pink-400">{{ formatNumber(rewardStats.total_given) }}</div>
                    <div class="text-gray-400 text-sm">Rewards Given</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-emerald-300">{{ formatNumber(summary.punches_awarded || 0) }}</div>
                    <div class="text-gray-400 text-sm">Punches Awarded</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-green-300">{{ formatNumber(summary.punch_card_wins || 0) }}</div>
                    <div class="text-gray-400 text-sm">Punch Wins</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-cyan-400">${{ rewardStats.total_value_redeemed }}</div>
                    <div class="text-gray-400 text-sm">Value Redeemed</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 mb-8">
                <!-- Plays Over Time Chart -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Plays Over Time</h2>
                    <div class="relative" :style="{ height: chartHeight + 'px' }">
                        <!-- Y-Axis Labels -->
                        <div class="absolute -left-2 top-0 bottom-0 w-full flex flex-col justify-between pointer-events-none pr-2">
                            <div v-for="label in yAxisLabels" :key="label.top" 
                                class="flex items-center w-full"
                                :style="{ position: 'absolute', top: label.top, width: '100%' }">
                                <span class="text-[9px] text-gray-500 font-mono pr-2 bg-[#121212] z-10">{{ label.label }}</span>
                                <div class="flex-1 h-[1px] bg-white/5 w-full"></div>
                            </div>
                        </div>

                        <div class="absolute inset-0 flex items-end justify-between gap-1 ml-10 h-full">
                            <div v-for="(day, index) in dailyStats" :key="index"
                                class="flex-1 h-full flex items-end group relative"
                            >
                                <div 
                                    class="w-full bg-gradient-to-t from-primary-500/30 to-primary-500/80 border-t border-primary-500/50 rounded-t transition-all group-hover:from-primary-500/50 group-hover:to-primary-500 shadow-[0_0_10px_rgba(59,130,246,0.1)]"
                                    :style="{ height: `${getBarHeight(day.total_plays, maxPlaysValue)}%` }"
                                ></div>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 border border-white/10 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-20">
                                    {{ day.date }}: {{ day.total_plays }} plays
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="dailyStats.length" class="flex justify-between mt-4 text-[10px] text-gray-500 font-mono uppercase tracking-wider ml-10">
                        <span>{{ dailyStats[0]?.date }}</span>
                        <span>{{ dailyStats[dailyStats.length - 1]?.date }}</span>
                    </div>
                </div>

                <!-- Rewards Breakdown -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Rewards Breakdown</h2>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-400">Redemption Rate</span>
                                <span class="text-white">{{ rewardStats.redemption_rate }}%</span>
                            </div>
                            <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400"
                                    :style="{ width: rewardStats.redemption_rate + '%' }"></div>
                            </div>
                        </div>
                        <div v-if="tieredConfigured" class="grid grid-cols-3 gap-4 text-center">
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="text-yellow-400 font-bold">{{ rewardStats.by_tier?.gold || 0 }}</div>
                                <div class="text-xs text-gray-400">Gold</div>
                            </div>
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="text-gray-300 font-bold">{{ rewardStats.by_tier?.silver || 0 }}</div>
                                <div class="text-xs text-gray-400">Silver</div>
                            </div>
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="text-amber-600 font-bold">{{ rewardStats.by_tier?.bronze || 0 }}</div>
                                <div class="text-xs text-gray-400">Bronze</div>
                            </div>
                        </div>
                        <div v-else class="grid grid-cols-2 gap-4 text-center">
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="text-white font-bold">{{ rewardStats.by_tier?.untiered || 0 }}</div>
                                <div class="text-xs text-gray-400">Single reward</div>
                            </div>
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="text-white font-bold">{{ rewardStats.by_tier?.participation || 0 }}</div>
                                <div class="text-xs text-gray-400">Participation</div>
                            </div>
                        </div>

                        <!-- Punch Card Breakdown (not counted in GameReward stats) -->
                        <div class="pt-4 border-t border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-400 text-sm">Punch Cards</span>
                                <span class="text-white text-sm font-medium">
                                    {{ summary.punches_awarded || 0 }} punches
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div class="bg-white/5 rounded-lg p-3">
                                    <div class="text-emerald-300 font-bold">{{ summary.punch_card_wins || 0 }}</div>
                                    <div class="text-xs text-gray-400">Wins</div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-3">
                                    <div class="text-emerald-300 font-bold">{{ summary.punch_card_players || 0 }}</div>
                                    <div class="text-xs text-gray-400">Players</div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-3">
                                    <div class="text-emerald-300 font-bold">{{ summary.punch_card_promotions || 0 }}</div>
                                    <div class="text-xs text-gray-400">Promos</div>
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs mt-2">
                                Punch card activity is tracked via game plays (not “Rewards Given”).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Game Performance -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Game Performance</h2>
                    <div class="space-y-4">
                        <div v-for="gs in gameStats" :key="gs.game_id" 
                            class="flex items-center gap-4 p-3 bg-white/5 rounded-lg">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
                                🎮
                            </div>
                            <div class="flex-1">
                                <div class="text-white font-medium">{{ gs.game?.name }}</div>
                                <div class="text-gray-400 text-sm">{{ gs.plays }} plays</div>
                            </div>
                            <div class="text-right">
                                <div class="text-primary-400 font-bold">{{ Math.round(gs.avg_score) }}</div>
                                <div class="text-gray-400 text-xs">avg score</div>
                            </div>
                        </div>
                        <div v-if="!gameStats.length" class="text-center py-8 text-gray-400">
                            No game data yet
                        </div>
                    </div>
                </div>

                <!-- Peak Hours -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Peak Playing Hours</h2>
                    <div class="space-y-2">
                        <div v-for="ph in peakHours.slice(0, 8)" :key="ph.hour"
                            class="flex items-center gap-3">
                            <span class="w-16 text-gray-400 text-sm">
                                {{ ph.hour > 12 ? (ph.hour - 12) + ' PM' : ph.hour + ' AM' }}
                            </span>
                            <div class="flex-1 h-4 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-primary-500 to-accent-500"
                                    :style="{ width: (ph.plays / (peakHours[0]?.plays || 1) * 100) + '%' }"></div>
                            </div>
                            <span class="w-12 text-right text-white text-sm">{{ ph.plays }}</span>
                        </div>
                        <div v-if="!peakHours.length" class="text-center py-8 text-gray-400">
                            No hourly data yet
                        </div>
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

