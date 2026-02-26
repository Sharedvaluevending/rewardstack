<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    user: Object,
    levelProgress: Number,
    xpNeededForNextLevel: Number,
    accessiblePromotions: Array,
    upcomingPromotions: Array,
    earnedBadges: Array,
    availableBadges: Array,
    featuredBadges: Array,
});

const getRarityColor = (rarity) => {
    return {
        common: 'border-gray-500 bg-gray-500/10',
        uncommon: 'border-green-500 bg-green-500/10',
        rare: 'border-blue-500 bg-blue-500/10',
        epic: 'border-purple-500 bg-purple-500/10',
        legendary: 'border-yellow-500 bg-yellow-500/10',
    }[rarity] || 'border-gray-500 bg-gray-500/10';
};
</script>

<template>
    <PortalLayout>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Levels & Badges 🏆</h1>
            <p class="text-gray-400 text-sm mt-1">Track your progress and unlock exclusive promotions</p>
        </div>

        <!-- Current Level Display -->
        <div class="mb-6 glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-3xl font-bold text-white">
                            {{ user?.level || 1 }}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">Level {{ user?.level || 1 }}</h2>
                            <p class="text-gray-400 text-sm">{{ user?.xp || 0 }} XP</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-gray-400">Progress to Level {{ (user?.level || 1) + 1 }}</span>
                    <span class="text-white font-medium">{{ levelProgress }}%</span>
                </div>
                <div class="h-3 bg-gray-700 rounded-full overflow-hidden">
                    <div 
                        class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-500"
                        :style="{ width: levelProgress + '%' }"
                    ></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">{{ xpNeededForNextLevel }} XP needed for next level</p>
            </div>
        </div>

        <!-- Level Exclusive Promotions Section -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">⭐ Level Exclusive Promotions</h2>
            
            <div class="mb-4 p-4 rounded-xl bg-purple-500/10 border border-purple-500/30">
                <p class="text-gray-300 text-sm mb-2">
                    Some businesses offer level-exclusive promotions that require you to reach a certain level to access. 
                    Your level is based on your app-wide XP (from scanning QR codes, playing games, and redeeming promotions across all businesses), 
                    not XP specific to any single store.
                </p>
                <p class="text-gray-400 text-xs">
                    Not all businesses offer level-exclusive promotions. Ask your local business or look for QR codes marked with level requirements (⭐).
                </p>
            </div>

            <!-- Accessible Promotions -->
            <div v-if="accessiblePromotions && accessiblePromotions.length > 0" class="mb-4">
                <h3 class="text-md font-medium text-white mb-2">Available to You</h3>
                <div class="grid grid-cols-1 gap-3">
                    <Link 
                        v-for="promo in accessiblePromotions" 
                        :key="promo.id"
                        :href="`/promo/${promo.qr_code.code}`"
                        class="glass-card p-4 hover:bg-white/10 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-white font-semibold">{{ promo.promotion.name }}</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                        Level {{ promo.qr_code.required_level }}+
                                    </span>
                                </div>
                                <p class="text-gray-400 text-sm">{{ promo.business.name }}</p>
                                <p class="text-emerald-400 text-sm mt-1">{{ promo.promotion.display_value }}</p>
                            </div>
                            <div class="text-purple-400">→</div>
                        </div>
                    </Link>
                </div>
            </div>
            <div v-else class="mb-4 p-4 rounded-xl bg-white/5 border border-white/10 text-center">
                <p class="text-gray-400 text-sm">No level-exclusive promotions available yet</p>
            </div>

            <!-- Upcoming Promotions -->
            <div v-if="upcomingPromotions && upcomingPromotions.length > 0">
                <h3 class="text-md font-medium text-white mb-2">Almost There</h3>
                <div class="grid grid-cols-1 gap-3">
                    <div 
                        v-for="promo in upcomingPromotions" 
                        :key="promo.id"
                        class="glass-card p-4 opacity-60"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-white font-semibold">{{ promo.promotion.name }}</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-500/20 text-gray-400 border border-gray-500/30 flex items-center gap-1">
                                        🔒 Level {{ promo.qr_code.required_level }}+
                                    </span>
                                </div>
                                <p class="text-gray-400 text-sm">{{ promo.business.name }}</p>
                                <p class="text-gray-500 text-sm mt-1">
                                    Level up {{ promo.qr_code.required_level - (user?.level || 1) }} more time{{ promo.qr_code.required_level - (user?.level || 1) !== 1 ? 's' : '' }} to unlock
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Badges Section -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-white">Badges</h2>
                <Link href="/portal/badges" class="text-sm text-purple-400 hover:text-purple-300">
                    View All →
                </Link>
            </div>

            <!-- Featured Badges -->
            <div v-if="featuredBadges && featuredBadges.length > 0" class="mb-4">
                <div class="flex gap-3">
                    <div v-for="ub in featuredBadges.slice(0, 3)" :key="ub.id"
                        class="flex-1 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-xl p-3 text-center border border-purple-500/30">
                        <div class="text-3xl mb-1">{{ ub.badge?.icon || '🏅' }}</div>
                        <div class="text-white text-xs font-medium truncate">{{ ub.badge?.name }}</div>
                    </div>
                    <div v-for="i in Math.max(0, 3 - featuredBadges.length)" :key="`empty-${i}`"
                        class="flex-1 bg-white/5 rounded-xl p-3 text-center border border-dashed border-white/20 flex flex-col items-center justify-center">
                        <div class="text-gray-500 text-xs">Tap ⭐ on a badge<br>to feature it</div>
                    </div>
                </div>
            </div>

            <!-- Recent Badges -->
            <div v-if="earnedBadges && earnedBadges.length > 0" class="grid grid-cols-3 gap-3">
                <div v-for="ub in earnedBadges.slice(0, 6)" :key="ub.id"
                    :class="['rounded-xl p-4 text-center border-2', getRarityColor(ub.badge?.rarity)]">
                    <div class="text-3xl mb-2">{{ ub.badge?.icon || '🏅' }}</div>
                    <div class="text-white text-xs font-medium truncate">{{ ub.badge?.name }}</div>
                </div>
            </div>
            <div v-else class="p-8 rounded-xl bg-white/5 border border-white/10 text-center">
                <div class="text-5xl mb-4">🏅</div>
                <h3 class="text-white font-semibold text-lg mb-2">No badges yet</h3>
                <p class="text-gray-400 text-sm">Play games to earn badges!</p>
            </div>
        </div>

        <!-- XP Sources Section -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">How to Earn XP</h2>
            <div class="glass-card p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center text-xl">📱</div>
                        <div>
                            <p class="text-white font-medium text-sm">Scanning QR codes</p>
                            <p class="text-gray-400 text-xs">10 XP per scan</p>
                        </div>
                    </div>
                    <span class="text-blue-400 font-semibold">+10 XP</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center text-xl">🎮</div>
                        <div>
                            <p class="text-white font-medium text-sm">Playing games</p>
                            <p class="text-gray-400 text-xs">50-100 XP (score-based)</p>
                        </div>
                    </div>
                    <span class="text-purple-400 font-semibold">+50-100 XP</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-xl">🎫</div>
                        <div>
                            <p class="text-white font-medium text-sm">Redeeming promotions</p>
                            <p class="text-gray-400 text-xs">25 XP per redemption</p>
                        </div>
                    </div>
                    <span class="text-emerald-400 font-semibold">+25 XP</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center text-xl">🏅</div>
                        <div>
                            <p class="text-white font-medium text-sm">Earning badges</p>
                            <p class="text-gray-400 text-xs">100-500 XP (depending on rarity)</p>
                        </div>
                    </div>
                    <span class="text-yellow-400 font-semibold">+100-500 XP</span>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

