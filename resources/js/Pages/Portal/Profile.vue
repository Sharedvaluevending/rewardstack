<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    user: Object,
    stats: Object,
    featuredBadges: Array,
    favoriteBusiness: Object,
    favoriteGame: Object,
});

const showEditModal = ref(false);
const avatarUploading = ref(false);
const avatarInput = ref(null);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    default_city: props.user?.preferences?.default_city || '',
    default_region: props.user?.preferences?.default_region || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveProfile = () => {
    form.put('/portal/profile', {
        onSuccess: () => showEditModal.value = false,
    });
};

const updatePassword = () => {
    passwordForm.put('/account/password', {
        onSuccess: () => passwordForm.reset(),
    });
};

// Handle avatar upload
const triggerAvatarUpload = () => {
    avatarInput.value?.click();
};

const handleAvatarChange = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
        alert('Please upload a valid image (JPEG, PNG, GIF, or WebP)');
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Image must be less than 2MB');
        return;
    }

    avatarUploading.value = true;
    
    const formData = new FormData();
    formData.append('avatar', file);
    
    router.post('/portal/profile/avatar', formData, {
        onFinish: () => {
            avatarUploading.value = false;
            if (avatarInput.value) avatarInput.value.value = '';
        },
    });
};

const removeAvatar = () => {
    if (confirm('Remove your logo?')) {
        router.delete('/portal/profile/avatar');
    }
};

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};

const showOnboardingAgain = () => {
    if (typeof window !== 'undefined') {
        window.localStorage?.removeItem('portal_onboarding_v1_completed');
    }
    router.visit('/portal?show_onboarding=1');
};
</script>

<template>
    <PortalLayout>
        <!-- Profile Header -->
        <div class="bg-gradient-to-br from-purple-600/30 to-pink-600/30 rounded-2xl p-6 mb-6 border border-purple-500/30">
            <div class="flex items-center gap-4 mb-4">
                <!-- Avatar/Logo with Upload -->
                <div class="relative group">
                    <div 
                        @click="triggerAvatarUpload"
                        :class="[
                            'w-20 h-20 rounded-full flex items-center justify-center cursor-pointer overflow-hidden transition-all',
                            user.avatar_url 
                                ? 'bg-white p-1' 
                                : 'bg-gradient-to-br from-purple-500 to-pink-500 text-3xl font-bold text-white'
                        ]"
                    >
                        <img 
                            v-if="user.avatar_url" 
                            :src="user.avatar_url" 
                            :alt="user.name"
                            class="w-full h-full object-cover rounded-full"
                        />
                        <span v-else>{{ user.name?.charAt(0).toUpperCase() }}</span>
                        
                        <!-- Upload overlay -->
                        <div class="absolute inset-0 bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <svg v-if="!avatarUploading" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg v-else class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Remove button (only show if has avatar) -->
                    <button 
                        v-if="user.avatar_url"
                        @click.stop="removeAvatar"
                        class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white text-xs hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100"
                    >
                        ✕
                    </button>
                    
                    <!-- Hidden file input -->
                    <input 
                        ref="avatarInput"
                        type="file" 
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        @change="handleAvatarChange"
                        class="hidden"
                    />
                </div>

                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-white">{{ user.name }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2 py-0.5 rounded-full bg-purple-500/30 text-purple-300 text-xs font-medium">
                            Level {{ stats.level }}
                        </span>
                        <span class="text-gray-400 text-sm">{{ formatNumber(stats.xp) }} XP</span>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Tap logo to upload your own</p>
                </div>
                <button @click="showEditModal = true"
                    class="p-2 bg-white/10 rounded-lg hover:bg-white/20 transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </button>
            </div>

            <!-- Level Progress -->
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-300">Level Progress</span>
                    <span class="text-white">{{ stats.level_progress }}%</span>
                </div>
                <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500"
                        :style="{ width: stats.level_progress + '%' }"></div>
                </div>
            </div>
        </div>

        <!-- Featured Badges -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-white">Featured Badges</h2>
                <Link href="/portal/badges" class="text-purple-400 text-sm">All badges →</Link>
            </div>
            <div class="flex gap-3">
                <div v-for="ub in featuredBadges" :key="ub.id"
                    class="flex-1 bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                    <div class="text-3xl mb-2">{{ ub.badge?.icon || '🏅' }}</div>
                    <div class="text-white text-sm font-medium">{{ ub.badge?.name }}</div>
                </div>
                <Link v-for="i in (3 - featuredBadges.length)" :key="`empty-${i}`"
                    href="/portal/badges"
                    class="flex-1 bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-dashed border-white/20 flex flex-col items-center justify-center">
                    <div class="text-2xl text-gray-500">+</div>
                    <div class="text-gray-500 text-xs">Add badge</div>
                </Link>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">📊 My Stats</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-white">{{ formatNumber(stats.total_games) }}</div>
                    <div class="text-gray-400 text-sm">Games Played</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(stats.total_wins) }}</div>
                    <div class="text-gray-400 text-sm">Wins</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-purple-400">{{ stats.win_rate }}%</div>
                    <div class="text-gray-400 text-sm">Win Rate</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-amber-400">{{ stats.highest_score }}</div>
                    <div class="text-gray-400 text-sm">Highest Score</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-pink-400">{{ stats.current_streak }}🔥</div>
                    <div class="text-gray-400 text-sm">Current Streak</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-cyan-400">{{ stats.best_streak }}</div>
                    <div class="text-gray-400 text-sm">Best Streak</div>
                </div>
            </div>
        </div>

        <!-- Achievements -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">🏆 Achievements</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-white">{{ stats.total_badges }}</div>
                    <div class="text-gray-400 text-sm">Total Badges</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="text-2xl font-bold text-white">{{ stats.badge_points }}</div>
                    <div class="text-gray-400 text-sm">Badge Points</div>
                </div>
            </div>
        </div>

        <!-- Rewards Summary -->
        <div>
            <h2 class="text-lg font-semibold text-white mb-3">💰 Rewards</h2>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10 space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-400">Rewards Won</span>
                    <span class="text-white font-medium">{{ stats.total_rewards_won }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Rewards Redeemed</span>
                    <span class="text-white font-medium">{{ stats.total_rewards_redeemed }}</span>
                </div>
                <div class="flex justify-between pt-3 border-t border-white/10">
                    <span class="text-gray-400">Total Savings</span>
                    <span class="text-emerald-400 font-bold">${{ Number(stats.total_savings || 0).toFixed(2) }}</span>
                </div>
            </div>
        </div>

        <!-- Email Subscriptions -->
        <div class="mt-6">
            <h2 class="text-lg font-semibold text-white mb-3">📬 Email Subscriptions</h2>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10 flex items-center justify-between gap-3">
                <div>
                    <div class="text-white font-medium">Manage subscriptions</div>
                    <div class="text-gray-400 text-sm mt-1">Control which businesses can email you offers.</div>
                </div>
                <Link
                    href="/portal/subscriptions"
                    class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors border border-white/10 text-white font-semibold text-sm"
                >
                    Open →
                </Link>
            </div>
        </div>

        <!-- Password -->
        <div class="mt-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">🔒 Password</h2>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <div class="grid gap-3">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Current Password</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">New Password</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Confirm New Password</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                        />
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500">Minimum 8 characters.</p>
                        <button
                            @click="updatePassword"
                            :disabled="passwordForm.processing"
                            class="px-4 py-2 rounded-lg bg-primary-500 text-white text-sm hover:bg-primary-600 disabled:opacity-50"
                        >
                            Update Password
                        </button>
                    </div>
                    <div v-if="passwordForm.errors?.current_password" class="text-xs text-red-400">
                        {{ passwordForm.errors.current_password }}
                    </div>
                    <div v-if="passwordForm.errors?.password" class="text-xs text-red-400">
                        {{ passwordForm.errors.password }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Re-show onboarding popups -->
        <div class="mt-8">
            <button
                type="button"
                @click="showOnboardingAgain"
                class="w-full py-3 bg-white/5 border border-white/10 rounded-xl text-gray-300 hover:bg-white/10 transition-colors flex items-center justify-center gap-2"
            >
                <span>Re-show onboarding popups</span>
            </button>
        </div>

        <!-- Logout -->
        <div class="mt-4">
            <Link href="/logout" method="post" as="button"
                class="w-full py-3 bg-white/5 border border-white/10 rounded-xl text-gray-300 hover:bg-white/10 transition-colors">
                Sign Out
            </Link>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-slate-800 rounded-2xl p-6 w-full max-w-md border border-white/10">
                <h2 class="text-xl font-semibold text-white mb-4">Edit Profile</h2>
                <form @submit.prevent="saveProfile" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                        <input type="text" v-model="form.name"
                            class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" v-model="form.email"
                            class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Default City</label>
                            <input
                                type="text"
                                v-model="form.default_city"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="City"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">State / Province</label>
                            <input
                                type="text"
                                v-model="form.default_region"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="State / Province"
                            >
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        This is used as your default location for browsing businesses on the portal home page.
                    </p>
                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="showEditModal = false"
                            class="flex-1 px-4 py-2 bg-white/10 text-white rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 px-4 py-2 bg-purple-500 text-white rounded-lg">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
</template>

