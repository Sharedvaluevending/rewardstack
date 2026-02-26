<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Page Not Found" />
    
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 flex items-center justify-center px-4">
        <div class="text-center">
            <!-- Animated QR Code Icon -->
            <div class="mb-8 relative">
                <div class="w-32 h-32 mx-auto bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-white/20 shadow-2xl">
                    <svg class="w-full h-full text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
                <!-- Scanning line animation -->
                <div class="absolute inset-0 w-32 h-32 mx-auto overflow-hidden rounded-2xl">
                    <div class="h-1 bg-gradient-to-r from-transparent via-purple-500 to-transparent animate-pulse"></div>
                </div>
            </div>

            <!-- Error Code -->
            <h1 class="text-8xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400 mb-4">
                404
            </h1>
            
            <!-- Message -->
            <h2 class="text-2xl font-semibold text-white mb-4">
                QR Code Not Found
            </h2>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                The page you're looking for doesn't exist or the QR code may have expired. 
                Let's get you back on track.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <Link 
                    href="/" 
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-200 shadow-lg hover:shadow-purple-500/25"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Go Home
                </Link>
                <Link 
                    href="/login" 
                    class="inline-flex items-center px-6 py-3 bg-white/10 backdrop-blur-sm text-white font-semibold rounded-lg hover:bg-white/20 transition-all duration-200 border border-white/20"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Sign In
                </Link>
            </div>

            <!-- Support Link -->
            <p class="mt-12 text-gray-500 text-sm">
                Need help? 
                <a href="mailto:support@revenueqr.com" class="text-purple-400 hover:text-purple-300 underline">
                    Contact Support
                </a>
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes scan {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(120px); }
}
</style>
