<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Maintenance Mode" />
    
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-blue-900/30 to-gray-900 flex items-center justify-center px-4">
        <div class="text-center">
            <!-- Maintenance Icon -->
            <div class="mb-8 relative">
                <div class="w-32 h-32 mx-auto bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-blue-500/30 shadow-2xl">
                    <svg class="w-full h-full text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <!-- Spinning animation -->
                <div class="absolute inset-0 w-32 h-32 mx-auto">
                    <div class="w-full h-full border-4 border-transparent border-t-blue-500/50 rounded-full animate-spin"></div>
                </div>
            </div>

            <!-- Error Code -->
            <h1 class="text-8xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-400 mb-4">
                503
            </h1>
            
            <!-- Message -->
            <h2 class="text-2xl font-semibold text-white mb-4">
                Under Maintenance
            </h2>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                We're making Revenue QR even better! We'll be back online shortly. 
                Thanks for your patience.
            </p>

            <!-- Progress indicator -->
            <div class="max-w-xs mx-auto mb-8">
                <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full animate-pulse" style="width: 60%"></div>
                </div>
                <p class="text-gray-500 text-sm mt-2">Estimated: A few minutes</p>
            </div>

            <!-- Status indicator -->
            <div class="inline-flex items-center px-4 py-2 bg-white/5 rounded-full border border-white/10">
                <span class="w-2 h-2 bg-blue-400 rounded-full mr-2 animate-pulse"></span>
                <span class="text-gray-400 text-sm">Maintenance in progress</span>
            </div>

            <!-- Support Link -->
            <p class="mt-8 text-gray-500 text-sm">
                Questions? 
                <a href="mailto:support@revenueqr.com" class="text-blue-400 hover:text-blue-300 underline">
                    Contact Support
                </a>
            </p>
        </div>
    </div>
</template>
