<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    message: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const getBackUrl = computed(() => {
    if (!user.value) return '/';
    
    // Return appropriate dashboard based on user role
    switch (user.value.role) {
        case 'business':
            return '/business/dashboard';
        case 'employee':
            return '/employee/redeem';
        case 'admin':
            return '/admin/dashboard';
        case 'customer':
        case 'user':
            return '/portal/dashboard';
        default:
            return '/';
    }
});

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        // If no history, redirect to appropriate dashboard
        window.location.href = getBackUrl.value;
    }
};
</script>

<template>
    <Head title="QR Code Error" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-red-900/20 to-gray-900 flex items-center justify-center p-4">
        <div class="text-center max-w-md">
            <!-- Error Icon -->
            <div class="mb-6">
                <div class="w-24 h-24 mx-auto bg-red-500/10 backdrop-blur-sm rounded-2xl p-4 border border-red-500/30">
                    <svg class="w-full h-full text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
            </div>

            <!-- Error Message -->
            <h1 class="text-2xl font-bold text-white mb-3">Oops!</h1>
            <p class="text-gray-400 mb-8">{{ message || 'This QR code is not working' }}</p>

            <!-- Helpful Info -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4 mb-8 text-left">
                <h3 class="text-white font-medium text-sm mb-3">This could mean:</h3>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="text-red-400">•</span>
                        <span>The QR code has expired</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-400">•</span>
                        <span>The promotion has ended</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-400">•</span>
                        <span>The QR code was deactivated</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-red-400">•</span>
                        <span>You scanned a damaged or incorrect code</span>
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <button @click="goBack"
                    class="w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                    ← Go Back
                </button>
                <Link :href="getBackUrl"
                    class="block w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold rounded-xl hover:opacity-90 transition-all">
                    {{ user ? 'Go to Dashboard' : 'Visit Revenue QR' }}
                </Link>
            </div>

            <!-- Support -->
            <p class="mt-8 text-gray-500 text-xs">
                Need help? Ask the business that gave you this QR code.
            </p>
        </div>
    </div>
</template>
