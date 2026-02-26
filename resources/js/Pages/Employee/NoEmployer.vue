<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    message: String,
});
</script>

<template>
    <Head title="Not Authorized" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-red-900/20 to-gray-900 flex items-center justify-center p-4">
        <div class="text-center max-w-md">
            <!-- Icon -->
            <div class="mb-6">
                <div class="w-24 h-24 mx-auto bg-red-500/10 backdrop-blur-sm rounded-2xl p-4 border border-red-500/30">
                    <svg class="w-full h-full text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>

            <!-- Message -->
            <h1 class="text-2xl font-bold text-white mb-3">Access Denied</h1>
            <p class="text-gray-400 mb-8">{{ message || 'You are not registered as an employee.' }}</p>

            <!-- What to do -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4 mb-8 text-left">
                <h3 class="text-white font-medium text-sm mb-3">What to do:</h3>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="text-purple-400">1.</span>
                        <span>Ask your manager to add you as an employee</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-purple-400">2.</span>
                        <span>Make sure you're logged in with the correct account</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-purple-400">3.</span>
                        <span>Ensure your account has redemption permissions</span>
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <Link href="/"
                    class="block w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold rounded-xl hover:opacity-90 transition-all">
                    Go Home
                </Link>
                <Link href="/login"
                    class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                    Try Different Account
                </Link>
            </div>
        </div>
    </div>
</template>
