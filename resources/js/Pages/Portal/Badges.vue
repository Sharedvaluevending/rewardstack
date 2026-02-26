<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    earnedBadges: Array,
    availableBadges: Array,
    badgesByCategory: Object,
    totalEarned: Number,
    totalAvailable: Number,
    featuredBadges: Array,
});

const activeTab = ref('earned');

const categories = {
    achievement: { name: 'Achievements', icon: '🏆' },
    milestone: { name: 'Milestones', icon: '🎯' },
    streak: { name: 'Streaks', icon: '🔥' },
    explorer: { name: 'Explorer', icon: '🗺️' },
    champion: { name: 'Champion', icon: '👑' },
    seasonal: { name: 'Seasonal', icon: '🎄' },
    special: { name: 'Special', icon: '⭐' },
};

const getRarityColor = (rarity) => {
    return {
        common: 'border-gray-500 bg-gray-500/10',
        uncommon: 'border-green-500 bg-green-500/10',
        rare: 'border-blue-500 bg-blue-500/10',
        epic: 'border-purple-500 bg-purple-500/10',
        legendary: 'border-yellow-500 bg-yellow-500/10',
    }[rarity] || 'border-gray-500 bg-gray-500/10';
};

const getRarityTextColor = (rarity) => {
    return {
        common: 'text-gray-400',
        uncommon: 'text-green-400',
        rare: 'text-blue-400',
        epic: 'text-purple-400',
        legendary: 'text-yellow-400',
    }[rarity] || 'text-gray-400';
};

const toggleFeature = (userBadge) => {
    useForm({}).post(`/portal/badges/${userBadge.id}/feature`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <PortalLayout>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">🏅 My Badges</h1>
            <p class="text-gray-400 text-sm mt-1">{{ totalEarned }} of {{ totalAvailable }} earned</p>
        </div>

        <!-- Progress -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 mb-6 border border-white/10">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-300">Collection Progress</span>
                <span class="text-white">{{ totalAvailable > 0 ? Math.round((totalEarned / totalAvailable) * 100) : 0 }}%</span>
            </div>
            <div class="h-3 bg-white/10 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500"
                    :style="{ width: `${totalAvailable > 0 ? (totalEarned / totalAvailable) * 100 : 0}%` }"></div>
            </div>
        </div>

        <!-- Featured Badges Quick Select -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">⭐ Featured Badges</h2>
            <div class="flex gap-3">
                <div v-for="ub in featuredBadges" :key="ub.id"
                    class="flex-1 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-xl p-3 text-center border border-purple-500/30 relative">
                    <button @click="toggleFeature(ub)"
                        class="absolute top-1 right-1 p-1 text-yellow-400 hover:text-yellow-300">
                        ⭐
                    </button>
                    <div class="text-3xl mb-1">{{ ub.badge?.icon || '🏅' }}</div>
                    <div class="text-white text-xs font-medium truncate">{{ ub.badge?.name }}</div>
                </div>
                <div v-for="i in Math.max(0, 3 - featuredBadges.length)" :key="`empty-${i}`"
                    class="flex-1 bg-white/5 rounded-xl p-3 text-center border border-dashed border-white/20 flex flex-col items-center justify-center">
                    <div class="text-gray-500 text-xs">Tap ⭐ on a badge<br>to feature it</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button @click="activeTab = 'earned'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'earned' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                Earned ({{ totalEarned }})
            </button>
            <button @click="activeTab = 'available'"
                :class="['px-4 py-2 rounded-full text-sm font-medium transition-colors',
                    activeTab === 'available' ? 'bg-purple-500 text-white' : 'bg-white/10 text-gray-300']">
                Available
            </button>
        </div>

        <!-- Earned Badges -->
        <div v-if="activeTab === 'earned'">
            <div v-if="earnedBadges.length" class="space-y-6">
                <div v-for="(badges, category) in badgesByCategory" :key="category">
                    <h3 class="text-white font-medium mb-3 flex items-center gap-2">
                        <span>{{ categories[category]?.icon || '🏅' }}</span>
                        {{ categories[category]?.name || category }}
                    </h3>
                    <div class="grid grid-cols-3 gap-3">
                        <button v-for="ub in badges" :key="ub.id"
                            @click="toggleFeature(ub)"
                            :class="['relative rounded-xl p-4 text-center border-2 transition-all',
                                getRarityColor(ub.badge?.rarity),
                                ub.is_featured ? 'ring-2 ring-yellow-500' : '']">
                            <div v-if="ub.is_new" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></div>
                            <div class="text-3xl mb-2">{{ ub.badge?.icon || '🏅' }}</div>
                            <div class="text-white text-xs font-medium truncate">{{ ub.badge?.name }}</div>
                            <div :class="['text-xs mt-1', getRarityTextColor(ub.badge?.rarity)]">
                                {{ ub.badge?.rarity }}
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <div class="text-5xl mb-4">🏅</div>
                <h3 class="text-white font-semibold text-lg mb-2">No badges yet</h3>
                <p class="text-gray-400 text-sm">Play games to earn badges!</p>
            </div>
        </div>

        <!-- Available Badges -->
        <div v-if="activeTab === 'available'">
            <div v-if="availableBadges.length" class="grid grid-cols-3 gap-3">
                <div v-for="badge in availableBadges" :key="badge.id"
                    :class="['rounded-xl p-4 text-center border-2 opacity-50',
                        getRarityColor(badge.rarity)]">
                    <div class="text-3xl mb-2 grayscale">{{ badge.icon || '🏅' }}</div>
                    <div class="text-white text-xs font-medium truncate">{{ badge.name }}</div>
                    <div :class="['text-xs mt-1', getRarityTextColor(badge.rarity)]">
                        {{ badge.rarity }}
                    </div>
                    <div class="text-gray-500 text-xs mt-2">🔒 Locked</div>
                </div>
            </div>
            <div v-else class="text-center py-12">
                <div class="text-5xl mb-4">🎉</div>
                <h3 class="text-white font-semibold text-lg">All badges earned!</h3>
            </div>
        </div>
    </PortalLayout>
</template>

