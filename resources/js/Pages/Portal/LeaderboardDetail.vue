<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    leaderboard: Object,
    topEntries: Array,
    myEntry: Object,
    nearbyEntries: Array,
    periodInfo: Object,
    backUrl: String,
});

const backUrl = computed(() => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('back') || props.backUrl || '/portal/leaderboards';
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

const getRankColor = (rank) => {
    if (rank === 1) return 'text-yellow-400';
    if (rank === 2) return 'text-gray-300';
    if (rank === 3) return 'text-amber-600';
    return 'text-gray-400';
};

const nowTs = ref(Date.now());
let timerId = null;

const periodEndDate = computed(() => {
    return props.periodInfo?.period_end ? new Date(props.periodInfo.period_end) : null;
});

const formattedPeriodEnd = computed(() => {
    return periodEndDate.value ? periodEndDate.value.toLocaleString() : null;
});

const challengeOver = computed(() => props.periodInfo?.challenge_over === true);

const periodEnded = computed(() => {
    if (!periodEndDate.value) return false;
    return periodEndDate.value.getTime() - nowTs.value <= 0;
});

const timeRemaining = computed(() => {
    if (!periodEndDate.value) return null;
    const diffMs = periodEndDate.value.getTime() - nowTs.value;
    if (diffMs <= 0) {
        if (challengeOver.value) return 'Challenge Over';
        return 'Period Ended — resetting soon';
    }
    const totalSeconds = Math.floor(diffMs / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const parts = [];
    if (days > 0) parts.push(`${days}d`);
    if (hours > 0 || days > 0) parts.push(`${hours}h`);
    if (minutes > 0 || hours > 0 || days > 0) parts.push(`${minutes}m`);
    parts.push(`${seconds}s`);
    return parts.join(' ');
});

onMounted(() => {
    if (periodEndDate.value) {
        timerId = setInterval(() => {
            nowTs.value = Date.now();
        }, 1000);
    }
});

onBeforeUnmount(() => {
    if (timerId) {
        clearInterval(timerId);
        timerId = null;
    }
});
</script>

<template>
    <PortalLayout>
        <Head :title="`${leaderboard.name} - Leaderboard`" />

        <!-- Challenge Over Banner -->
        <div v-if="challengeOver && periodEnded" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏁</span>
                <div>
                    <p class="text-red-300 font-semibold text-sm">This leaderboard challenge has ended.</p>
                    <p class="text-gray-400 text-xs mt-1">Final scores are shown below. Winners have been notified.</p>
                </div>
            </div>
        </div>

        <!-- Period Ended Banner (recurring leaderboard, will reset) -->
        <div v-else-if="periodEnded && !challengeOver" class="mb-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
            <div class="flex items-center gap-3">
                <span class="text-2xl">⏳</span>
                <div>
                    <p class="text-amber-300 font-semibold text-sm">This period has ended.</p>
                    <p class="text-gray-400 text-xs mt-1">Winners are being selected and a new period will start soon. Scan the QR code to play again!</p>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="mb-6">
            <Link :href="backUrl" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                ← Back to Game Selection
            </Link>
            <h1 class="text-2xl font-bold text-white">{{ leaderboard.name }}</h1>
            <p v-if="leaderboard.description" class="text-gray-400 text-sm mt-1">{{ leaderboard.description }}</p>
            <div class="flex items-center gap-4 mt-2 text-sm text-gray-400">
                <span v-if="leaderboard.game">🎮 {{ leaderboard.game.name }}</span>
                <span>🔄 {{ periodInfo.reset_frequency === 'never' ? 'All-Time' : periodInfo.reset_frequency }}</span>
                <span v-if="formattedPeriodEnd && !periodEnded">📅 Ends: {{ formattedPeriodEnd }}</span>
                <span v-if="formattedPeriodEnd && periodEnded">📅 Ended: {{ formattedPeriodEnd }}</span>
                <span v-if="timeRemaining && !periodEnded" class="text-amber-300">⏳ {{ timeRemaining }}</span>
            </div>
        </div>

        <!-- My Ranking Card -->
        <div v-if="myEntry" class="mb-6 bg-gradient-to-r from-purple-500/20 to-pink-500/20 border border-purple-500/30 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-xl bg-white/10 flex items-center justify-center">
                        <span :class="['text-2xl font-bold', getRankColor(myEntry.rank)]">
                            {{ getRankBadge(myEntry.rank) }}
                        </span>
                    </div>
                    <div>
                        <div class="text-white font-semibold">Your Rank</div>
                        <div class="text-gray-300 text-sm">Score: {{ formatScore(myEntry.score) }}</div>
                        <div v-if="myEntry.rank_change !== 0" class="text-xs mt-1"
                            :class="myEntry.rank_change > 0 ? 'text-emerald-400' : 'text-red-400'">
                            {{ myEntry.rank_change > 0 ? '↑' : '↓' }} {{ Math.abs(myEntry.rank_change) }} from last period
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-gray-400 text-sm">Games Played</div>
                    <div class="text-white font-semibold">{{ myEntry.games_played || 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Top Rankings -->
        <div class="glass-card overflow-hidden mb-6">
            <div class="p-4 border-b border-white/10">
                <h2 class="text-lg font-semibold text-white">Top Rankings</h2>
                <p class="text-gray-400 text-xs mt-1">Current period: {{ periodInfo.current_period }}</p>
            </div>

            <div class="divide-y divide-white/10">
                <div v-for="entry in topEntries" :key="entry.id" 
                    class="p-4 hover:bg-white/5 transition-colors"
                    :class="{ 'bg-purple-500/10': myEntry && entry.id === myEntry.id }">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                                <span :class="['text-lg font-bold', getRankColor(entry.rank)]">
                                    {{ getRankBadge(entry.rank) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium truncate">
                                    {{ entry.user?.name || 'Anonymous' }}
                                    <span v-if="myEntry && entry.id === myEntry.id" class="text-purple-400 text-sm ml-2">(You)</span>
                                </div>
                                <div class="text-gray-400 text-xs">{{ entry.games_played || 0 }} games played</div>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-white font-semibold">{{ formatScore(entry.score) }}</div>
                            <div class="text-gray-400 text-xs">{{ entry.games_played || 0 }} games</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!topEntries.length" class="p-12 text-center">
                <div class="text-4xl mb-4">🏆</div>
                <h3 class="text-white font-semibold text-lg mb-2">No rankings yet</h3>
                <p class="text-gray-400">Be the first to play and appear on the leaderboard!</p>
            </div>
        </div>

        <!-- Nearby Rankings (if user is not in top 10) -->
        <div v-if="nearbyEntries && nearbyEntries.length" class="glass-card overflow-hidden">
            <div class="p-4 border-b border-white/10">
                <h2 class="text-lg font-semibold text-white">Near You</h2>
                <p class="text-gray-400 text-xs mt-1">Players around your rank</p>
            </div>

            <div class="divide-y divide-white/10">
                <div v-for="entry in nearbyEntries" :key="entry.id" 
                    class="p-4 hover:bg-white/5 transition-colors"
                    :class="{ 'bg-purple-500/10': entry.id === myEntry?.id }">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                                <span :class="['text-lg font-bold', getRankColor(entry.rank)]">
                                    {{ getRankBadge(entry.rank) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium truncate">
                                    {{ entry.user?.name || 'Anonymous' }}
                                    <span v-if="entry.id === myEntry?.id" class="text-purple-400 text-sm ml-2">(You)</span>
                                </div>
                                <div class="text-gray-400 text-xs">{{ entry.games_played || 0 }} games played</div>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-white font-semibold">{{ formatScore(entry.score) }}</div>
                            <div class="text-gray-400 text-xs">{{ entry.games_played || 0 }} games</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>
