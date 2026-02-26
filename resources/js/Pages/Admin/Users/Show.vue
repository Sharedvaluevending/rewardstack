<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

defineProps({
    user: Object,
});

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString();
};

const getRoleColor = (role) => {
    const colors = {
        admin: 'bg-red-500/20 text-red-400',
        business: 'bg-blue-500/20 text-blue-400',
        employee: 'bg-purple-500/20 text-purple-400',
        user: 'bg-gray-500/20 text-gray-400',
        customer: 'bg-emerald-500/20 text-emerald-400',
    };
    return colors[role] || 'bg-gray-500/20 text-gray-400';
};

const getRewardStatusColor = (status) => {
    const colors = {
        available: 'bg-emerald-500/20 text-emerald-400',
        claimed: 'bg-blue-500/20 text-blue-400',
        redeemed: 'bg-purple-500/20 text-purple-400',
        expired: 'bg-gray-500/20 text-gray-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};

const toggleStatus = (user) => {
    router.post(`/admin/users/${user.id}/toggle`);
};
</script>

<template>
    <Head :title="`${user?.name || 'User'} - Admin`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/users" class="hover:text-white">Users</Link>
                    <span>/</span>
                    <span class="text-white">{{ user?.name }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-xl">
                        {{ user?.name?.charAt(0) }}
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white">{{ user?.name }}</h1>
                        <p class="text-gray-400 mt-1">{{ user?.email }}</p>
                    </div>
                </div>
            </div>
            <Link href="/admin/users" class="btn-secondary">← Back to Users</Link>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-emerald-400">{{ user?.total_games_played ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Games Played</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-blue-400">{{ user?.gamePlays?.length ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Game Sessions</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-amber-400">{{ user?.gameRewards?.length ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Rewards Won</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-purple-400">{{ user?.highest_score ?? 0 }}</div>
                <div class="text-gray-400 text-sm">High Score</div>
            </div>
        </div>

        <!-- User Info & Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">User Details</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Role</span>
                        <span :class="['px-2 py-1 rounded text-xs font-medium capitalize', getRoleColor(user?.role)]">
                            {{ user?.role }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Status</span>
                        <button
                            @click="toggleStatus(user)"
                            :class="user?.email_verified_at ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
                            class="px-2 py-1 rounded text-xs font-medium"
                        >
                            {{ user?.email_verified_at ? 'Enabled' : 'Disabled' }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Level</span>
                        <span class="text-gray-300">{{ user?.level ?? 1 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">XP</span>
                        <span class="text-gray-300">{{ user?.xp ?? 0 }}</span>
                    </div>
                    <div v-if="user?.created_at" class="flex items-center justify-between">
                        <span class="text-gray-400">Joined</span>
                        <span class="text-gray-300">{{ formatDate(user.created_at) }}</span>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Business</h2>
                <div v-if="user?.business" class="p-4 rounded-xl bg-white/5">
                    <p class="text-white font-medium">{{ user.business.name }}</p>
                    <Link :href="`/admin/businesses/${user.business.id}`" class="text-primary-400 hover:text-primary-300 text-sm">
                        View business →
                    </Link>
                </div>
                <div v-else class="text-gray-500">No business associated</div>
            </div>
        </div>

        <!-- Game Plays & Rewards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Recent Game Plays</h2>
                <div v-if="user?.gamePlays?.length" class="space-y-3">
                    <div v-for="play in user.gamePlays.slice(0, 10)" :key="play.id" class="p-3 rounded-xl bg-white/5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-white font-medium">Score: {{ play.score }}</div>
                                <div class="text-gray-400 text-sm">{{ play.result }} • {{ formatDate(play.completed_at) }}</div>
                            </div>
                            <span v-if="play.is_personal_best" class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-400">
                                PB
                            </span>
                        </div>
                    </div>
                    <p v-if="user.gamePlays.length > 10" class="text-gray-500 text-sm">
                        + {{ user.gamePlays.length - 10 }} more
                    </p>
                </div>
                <div v-else class="text-gray-500">No game plays</div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Recent Rewards</h2>
                <div v-if="user?.gameRewards?.length" class="space-y-3">
                    <div v-for="reward in user.gameRewards.slice(0, 10)" :key="reward.id" class="p-3 rounded-xl bg-white/5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-white font-medium">{{ reward.reward_type }} ({{ reward.tier }})</div>
                                <div class="text-gray-400 text-sm">{{ formatDate(reward.claimed_at) }}</div>
                            </div>
                            <span :class="['px-2 py-0.5 rounded text-xs', getRewardStatusColor(reward.status)]">
                                {{ reward.status }}
                            </span>
                        </div>
                    </div>
                    <p v-if="user.gameRewards.length > 10" class="text-gray-500 text-sm">
                        + {{ user.gameRewards.length - 10 }} more
                    </p>
                </div>
                <div v-else class="text-gray-500">No rewards</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
</style>
