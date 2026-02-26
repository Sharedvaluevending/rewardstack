<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    message: String,
    qrBusiness: String,
    yourBusiness: String,
});
</script>

<template>
    <Head title="Wrong Business" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-amber-900/20 to-gray-900 flex items-center justify-center p-4">
        <div class="text-center max-w-md">
            <!-- Warning Icon -->
            <div class="mb-6">
                <div class="w-24 h-24 mx-auto bg-amber-500/10 backdrop-blur-sm rounded-2xl p-4 border border-amber-500/30">
                    <svg class="w-full h-full text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>

            <!-- Message -->
            <h1 class="text-2xl font-bold text-white mb-3">Wrong Business</h1>
            <p class="text-gray-400 mb-6">{{ message }}</p>

            <!-- Business Comparison -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4 mb-8">
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                        <p class="text-gray-400 text-xs mb-1">QR Code belongs to</p>
                        <p class="text-red-400 font-semibold">{{ qrBusiness }}</p>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                        <p class="text-gray-400 text-xs mb-1">You work at</p>
                        <p class="text-green-400 font-semibold">{{ yourBusiness }}</p>
                    </div>
                </div>
            </div>

            <!-- Explanation -->
            <p class="text-gray-500 text-sm mb-8">
                You can only redeem QR codes that belong to your employer. 
                This QR code is from a different business.
            </p>

            <!-- Actions -->
            <div class="space-y-3">
                <Link href="/employee/redeem"
                    class="block w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold rounded-xl hover:opacity-90 transition-all">
                    Go to Redemption Terminal
                </Link>
                <button @click="() => window.history.back()"
                    class="w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                    ← Go Back
                </button>
            </div>
        </div>
    </div>
</template>
