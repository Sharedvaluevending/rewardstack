<script setup>
import { computed, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const props = defineProps({
    reward: Object,
    userAvatar: Object,
});

const claimForm = useForm({});

const statusLabel = computed(() => {
    const s = props.reward?.status;
    if (s === 'available') return { text: 'Available', cls: 'bg-purple-500/20 text-purple-300 border-purple-500/30' };
    if (s === 'claimed') return { text: 'Active', cls: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' };
    if (s === 'redeemed') return { text: 'Redeemed', cls: 'bg-white/10 text-gray-300 border-white/10' };
    if (s === 'expired') return { text: 'Expired', cls: 'bg-amber-500/20 text-amber-300 border-amber-500/30' };
    return { text: String(s || 'Unknown'), cls: 'bg-white/10 text-gray-300 border-white/10' };
});

const isAvailable = computed(() => props.reward?.status === 'available');
const isClaimed = computed(() => props.reward?.status === 'claimed');

const displayCode = computed(() => {
    const code = props.reward?.reward_code;
    return (typeof code === 'string' && code.startsWith('UP-')) ? code : '';
});

const copyText = async (text) => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        // fallback: do nothing silently
    }
};

const formatCurrency = (amount) => {
    if (!amount) return null;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const displayValue = computed(() => {
    const r = props.reward;
    if (r.reward_type === 'percentage') return `${parseFloat(r.discount_value)}% Off`;
    if (r.reward_type === 'fixed') return `$${parseFloat(r.discount_value)} Off`;
    if (r.reward_type === 'free_item') return `Free: ${r.free_item}`;
    return r.description || 'Reward';
});
</script>

<template>
    <PortalLayout>
        <Head title="My Reward" />

        <div class="max-w-md mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <Link href="/portal/scans" class="text-gray-400 hover:text-white flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Scans</span>
                </Link>
                <span class="px-3 py-1 rounded-full text-xs font-semibold border" :class="statusLabel.cls">
                    {{ statusLabel.text }}
                </span>
            </div>

            <div class="glass-card overflow-hidden">
                <!-- Header with Business Branding (Matching Promotion style) -->
                <div 
                    class="p-6 text-center border-b border-white/10"
                    :style="{ background: `linear-gradient(135deg, ${reward.business?.primary_color || '#7C3AED'}40, ${reward.business?.primary_color || '#7C3AED'}20)` }"
                >
                    <!-- Business Logo -->
                    <div v-if="reward.business?.logo_url" class="w-20 h-20 rounded-2xl bg-white mx-auto mb-4 p-2 shadow-lg">
                        <img :src="reward.business.logo_url" :alt="reward.business.name" class="w-full h-full object-contain" />
                    </div>
                    <div v-else class="w-20 h-20 rounded-2xl bg-white/20 mx-auto mb-4 flex items-center justify-center">
                        <span class="text-3xl font-bold text-white">{{ reward.business?.name?.charAt(0) || '🎁' }}</span>
                    </div>
                    
                    <h2 class="text-xl font-semibold text-white">{{ reward.business?.name || 'Business' }}</h2>
                    
                    <!-- User Avatar Badge -->
                    <div v-if="userAvatar" class="mt-4 flex items-center justify-center gap-2">
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/20 backdrop-blur-sm border border-white/10">
                            <div v-if="userAvatar.avatar_url" class="w-6 h-6 rounded-full overflow-hidden bg-white">
                                <img :src="userAvatar.avatar_url" :alt="userAvatar.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-6 h-6 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white text-[10px] font-bold">
                                {{ userAvatar.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </div>
                            <span class="text-white text-xs font-medium">{{ userAvatar.name }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Reward Value (Matching Promotion style) -->
                    <div class="text-center mb-8">
                        <p class="text-5xl font-bold text-white mb-2">{{ displayValue }}</p>
                        <h1 class="text-xl text-gray-300">{{ reward.description || 'Reward' }}</h1>
                        <p v-if="reward.expires_at" class="text-gray-500 text-xs mt-3">
                            Expires: {{ new Date(reward.expires_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                        </p>
                    </div>

                    <!-- QR Code and Redemption Section -->
                    <div v-if="(isClaimed || isAvailable || (reward.status === 'expired' && (displayCode || reward.qr_image_url)))" class="mb-8 p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="flex flex-col items-center gap-4">
                            <!-- QR Image -->
                            <div v-if="reward.qr_image_url" class="w-full max-w-[340px] bg-white p-4 rounded-lg shadow-lg aspect-square flex items-center justify-center">
                                <img 
                                    :src="reward.qr_image_url" 
                                    alt="Reward QR Code" 
                                    class="w-full h-full object-contain"
                                />
                            </div>
                            <div v-else-if="displayCode" class="w-full max-w-[340px] bg-white/10 rounded-lg aspect-square flex items-center justify-center border border-white/5">
                                <div class="text-center p-6">
                                    <div class="w-16 h-16 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                                    <p class="text-gray-400 text-sm">Generating secure QR code...</p>
                                </div>
                            </div>

                            <!-- Code Display -->
                            <div class="text-center w-full">
                                <div class="text-xs text-gray-400 mb-2">Redemption Code</div>
                                <div class="text-3xl font-bold text-white font-mono tracking-wider px-6 py-3 rounded-lg border-2 mb-3 break-all uppercase"
                                    :style="{ backgroundColor: `${reward.business?.primary_color || '#0ea5e9'}20`, borderColor: `${reward.business?.primary_color || '#0ea5e9'}4D` }">
                                    <span v-if="displayCode">{{ displayCode }}</span>
                                    <span v-else>Redeem via QR</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <button
                                        v-if="displayCode"
                                        type="button"
                                        class="w-full px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm font-semibold transition-colors"
                                        @click="copyText(displayCode)"
                                    >
                                        📋 Copy Code
                                    </button>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ displayCode ? 'Show staff this QR code or code to redeem' : 'Show staff this QR code to redeem' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-4">
                        <div v-if="isAvailable" class="bg-purple-500/10 border border-purple-500/20 rounded-xl p-4 text-center">
                            <p class="text-purple-300 text-sm font-medium">Claim your reward first!</p>
                            <p class="text-gray-400 text-xs mt-1">Claiming locks the reward to your account so you can redeem it with staff later.</p>
                        </div>
                        
                        <div v-else-if="isClaimed" class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-4 text-center">
                            <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-2">
                                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-emerald-400 font-bold text-lg">Ready to Redeem!</p>
                            <p class="text-gray-400 text-sm mt-1">Show this screen to a staff member to save</p>
                        </div>

                        <button
                            v-if="isAvailable"
                            type="button"
                            @click="claimForm.post(`/portal/rewards/${reward.id}/claim`, { preserveScroll: true })"
                            :disabled="claimForm.processing"
                            class="w-full py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold text-lg hover:from-emerald-600 hover:to-green-700 shadow-lg shadow-emerald-500/25 transition-all disabled:opacity-50"
                        >
                            <span v-if="claimForm.processing" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Claiming...
                            </span>
                            <span v-else>🎁 Claim Reward</span>
                        </button>

                        <div v-if="reward.status === 'redeemed'" class="bg-white/10 rounded-xl p-6 text-center">
                            <div class="w-16 h-16 rounded-full bg-white/5 mx-auto mb-3 flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <p class="text-white font-semibold">Redeemed</p>
                            <p class="text-gray-400 text-sm mt-1">This reward was redeemed on {{ new Date(reward.redeemed_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Footer (Matching Promotion style) -->
                <div class="p-4 bg-white/5 text-center">
                    <p class="text-gray-500 text-[10px] uppercase tracking-widest">
                        Powered by <span class="text-primary-400 font-semibold">Revenue QR</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <div class="max-w-md mx-auto mt-4">
            <div v-if="page.props?.flash?.success" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl p-4 text-sm animate-fade-in">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props?.flash?.error" class="bg-amber-500/10 border border-amber-500/20 text-amber-300 rounded-xl p-4 text-sm animate-fade-in">
                {{ page.props.flash.error }}
            </div>
        </div>
    </PortalLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}

@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out forwards;
}
</style>


