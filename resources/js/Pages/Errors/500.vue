<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Server Error" />
    
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-red-900/30 to-gray-900 flex items-center justify-center px-4">
        <div class="text-center">
            <!-- Broken QR Code Icon -->
            <div class="mb-8 relative">
                <div class="w-32 h-32 mx-auto bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-red-500/30 shadow-2xl">
                    <svg class="w-full h-full text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <!-- Glitch effect -->
                <div class="absolute inset-0 w-32 h-32 mx-auto bg-red-500/10 rounded-2xl animate-pulse"></div>
            </div>

            <!-- Error Code -->
            <h1 class="text-8xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-red-400 to-orange-400 mb-4">
                500
            </h1>
            
            <!-- Message -->
            <h2 class="text-2xl font-semibold text-white mb-4">
                Something Went Wrong
            </h2>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                Our servers hit a snag. Don't worry, our team has been notified and we're working on it.
                Please try again in a moment.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button 
                    @click="() => window.location.reload()"
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-red-600 to-orange-600 text-white font-semibold rounded-lg hover:from-red-700 hover:to-orange-700 transition-all duration-200 shadow-lg hover:shadow-red-500/25"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Try Again
                </button>
                <a 
                    href="/"
                    class="inline-flex items-center px-6 py-3 bg-white/10 backdrop-blur-sm text-white font-semibold rounded-lg hover:bg-white/20 transition-all duration-200 border border-white/20"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Go Home
                </a>
            </div>

            <!-- Status indicator -->
            <div class="mt-12 inline-flex items-center px-4 py-2 bg-white/5 rounded-full border border-white/10">
                <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2 animate-pulse"></span>
                <span class="text-gray-400 text-sm">Our team is investigating</span>
            </div>

            <!-- Support Link -->
            <p class="mt-6 text-gray-500 text-sm">
                If this persists, please 
                <a href="mailto:support@revenueqr.com" class="text-red-400 hover:text-red-300 underline">
                    contact support
                </a>
            </p>
        </div>
    </div>
</template>
