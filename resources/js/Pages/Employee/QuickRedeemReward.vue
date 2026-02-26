<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    reward: Object,
    business: Object,
    customer: Object,
    employee: Object,
    canRedeem: Boolean,
    redeemMessage: String,
});

const userRole = computed(() => page.props.auth?.user?.role);
const backLabel = computed(() => {
    switch (userRole.value) {
        case 'business':
        case 'admin':
            return 'dashboard';
        case 'employee':
            return 'redemption terminal';
        default:
            return 'home';
    }
});

const processing = ref(false);
const result = ref(null);
const error = ref(null);

const getCsrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el?.content || null;
};

// Handle redemption
const handleRedeem = async () => {
    processing.value = true;
    error.value = null;

    try {
        const csrf = getCsrfToken();
        if (!csrf) {
            error.value = 'Session error (missing CSRF token). Please refresh and try again.';
            return;
        }

        const response = await fetch(`/redeem/reward/${props.reward.reward_code}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        });

        let data = null;
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            if (response.status === 401) {
                error.value = 'You are not logged in. Please log in and try again.';
                return;
            }
            if (response.status === 419) {
                error.value = 'Session expired. Please refresh the page and try again.';
                return;
            }
            error.value = `Redeem failed (HTTP ${response.status}). Please try again.`;
            return;
        }

        if (data.success) {
            result.value = data.reward;
        } else {
            error.value = data.message;
        }
    } catch (err) {
        error.value = 'Something went wrong. Please try again.';
    } finally {
        processing.value = false;
    }
};

// Get back URL based on user role
const getBackUrl = computed(() => {
    const role = userRole.value;
    switch (role) {
        case 'business':
            return '/business/dashboard';
        case 'employee':
            return '/employee/redeem';
        case 'admin':
            return '/admin/dashboard';
        default:
            return '/';
    }
});

// Done - go back to appropriate dashboard based on user role
const handleDone = () => {
    router.visit(getBackUrl.value);
};
</script>

<template>
    <Head title="Staff Reward Redemption" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-emerald-900/30 to-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 bg-emerald-500/20 text-emerald-400 px-4 py-2 rounded-full text-sm font-medium mb-4">
                    <span>👤</span>
                    <span>Staff Redemption Mode</span>
                </div>
                
                <!-- Business Logo -->
                <div v-if="business.logo_url" class="w-16 h-16 rounded-xl bg-white mx-auto mb-3 p-2">
                    <img :src="business.logo_url" :alt="business.name" class="w-full h-full object-contain" />
                </div>
                <h1 class="text-xl font-bold text-white">{{ business.name }}</h1>
                <p class="text-gray-400 text-sm">{{ employee.name }}</p>
            </div>

            <!-- Success State -->
            <div v-if="result" class="bg-white/10 backdrop-blur-lg rounded-2xl border-2 border-emerald-500/50 p-6">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-emerald-500/20 mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-emerald-400 mb-2">Reward Redeemed! ✓</h2>
                    <p class="text-white text-lg mb-4">{{ result.display_value }}</p>
                    
                    <div v-if="customer" class="mb-6 p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-xs mb-1">Customer</p>
                        <p class="text-white font-semibold">{{ customer.name }}</p>
                        <p v-if="customer.email" class="text-gray-400 text-sm">{{ customer.email }}</p>
                    </div>

                    <button @click="handleDone"
                        class="w-full py-3 bg-emerald-500 text-white font-semibold rounded-xl hover:bg-emerald-600 transition-all">
                        Done
                    </button>
                </div>
            </div>

            <!-- Redemption Form -->
            <div v-else class="bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 overflow-hidden">
                
                <!-- Reward Preview -->
                <div class="p-4 bg-white/5 border-b border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                            🎁
                        </div>
                        <div class="flex-1">
                            <p class="text-white font-semibold">{{ reward.description || 'Game Reward' }}</p>
                            <p class="text-2xl font-bold text-emerald-400">{{ reward.display_value }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Error Message -->
                    <div v-if="error" class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                        <p class="text-red-400 text-center text-sm">{{ error }}</p>
                    </div>

                    <!-- Cannot Redeem Warning -->
                    <div v-if="!canRedeem" class="mb-4 p-4 rounded-xl bg-amber-500/20 border border-amber-500/30">
                        <p class="text-amber-400 text-center text-sm">{{ redeemMessage }}</p>
                    </div>

                    <!-- Customer Info -->
                    <div v-if="customer" class="mb-6 p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-xs mb-2">Customer</p>
                        <p class="text-white font-semibold">{{ customer.name }}</p>
                        <p v-if="customer.email" class="text-gray-400 text-sm">{{ customer.email }}</p>
                    </div>

                    <!-- Redeem Button -->
                    <button
                        @click="handleRedeem"
                        :disabled="processing || !canRedeem"
                        class="w-full py-4 rounded-xl font-bold text-lg transition-all"
                        :class="[
                            processing || !canRedeem
                                ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                : 'bg-gradient-to-r from-emerald-500 to-green-600 text-white hover:from-emerald-600 hover:to-green-700 shadow-lg shadow-emerald-500/25'
                        ]"
                    >
                        <span v-if="processing" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                        <span v-else>✓ Confirm Redemption</span>
                    </button>
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-6 text-center">
                <Link :href="getBackUrl" class="text-gray-400 text-sm hover:text-white transition-colors">
                    ← Back to {{ backLabel }}
                </Link>
            </div>

            <!-- Security Notice -->
            <div class="mt-4 text-center">
                <p class="text-gray-600 text-xs">
                    🔒 This redemption is logged and attributed to {{ employee.name }}
                </p>
            </div>
        </div>
    </div>
</template>

