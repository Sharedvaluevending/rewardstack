<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    referrerName: String,
    referralCode: String,
});
</script>

<template>
    <Head title="Join Revenue QR" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900">
        <!-- Header -->
        <div class="p-4">
            <Link href="/" class="text-2xl font-bold text-white">
                Revenue QR
            </Link>
        </div>

        <!-- Hero Section -->
        <div class="max-w-4xl mx-auto px-4 py-12 text-center">
            <!-- Referral Badge -->
            <div class="inline-flex items-center gap-2 bg-emerald-500/20 text-emerald-400 px-4 py-2 rounded-full text-sm font-medium mb-6">
                <span>🎁</span>
                <span>Referred by {{ referrerName }}</span>
            </div>

            <h1 class="text-4xl md:text-5xl font-bold text-white mb-6">
                Bring Customers Back<br />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400">Without the Tech Headaches</span>
            </h1>

            <p class="text-xl text-gray-400 mb-8 max-w-2xl mx-auto">
                QR codes that track, games that engage, and promotions that actually work. 
                No tablets. No hardware. No training.
            </p>

            <!-- CTA -->
            <Link :href="`/register?ref=${referralCode}`"
                class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold text-lg rounded-xl hover:from-purple-700 hover:to-pink-700 transition-all shadow-lg shadow-purple-500/25">
                Start Free Trial
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </Link>

            <p class="text-gray-500 text-sm mt-4">No credit card required · Set up in 5 minutes</p>
        </div>

        <!-- Features Grid -->
        <div class="max-w-5xl mx-auto px-4 py-12">
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                    <div class="text-4xl mb-4">📱</div>
                    <h3 class="text-xl font-bold text-white mb-2">Scan & Track</h3>
                    <p class="text-gray-400">
                        Customers scan your QR code. You see exactly when they visit, how often, and what promotions they use.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                    <div class="text-4xl mb-4">🎮</div>
                    <h3 class="text-xl font-bold text-white mb-2">Games That Engage</h3>
                    <p class="text-gray-400">
                        Kids play, win prizes, and drag their parents back. 8 built-in games turn bored customers into repeat visitors.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                    <div class="text-4xl mb-4">👕</div>
                    <h3 class="text-xl font-bold text-white mb-2">Merch That Markets</h3>
                    <p class="text-gray-400">
                        Your logo + QR code on mugs, shirts, hats. Customers take them home. Your brand is everywhere.
                    </p>
                </div>
            </div>
        </div>

        <!-- Social Proof / How It Works -->
        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="bg-gradient-to-r from-purple-600/20 to-pink-600/20 rounded-2xl p-8 border border-purple-500/20">
                <h2 class="text-2xl font-bold text-white text-center mb-8">How It Works</h2>
                
                <div class="grid md:grid-cols-4 gap-6 text-center">
                    <div>
                        <div class="w-12 h-12 rounded-full bg-purple-500/20 text-purple-400 text-xl font-bold flex items-center justify-center mx-auto mb-3">1</div>
                        <p class="text-white font-medium">Sign Up</p>
                        <p class="text-gray-400 text-sm">5 minutes, no credit card</p>
                    </div>
                    <div>
                        <div class="w-12 h-12 rounded-full bg-purple-500/20 text-purple-400 text-xl font-bold flex items-center justify-center mx-auto mb-3">2</div>
                        <p class="text-white font-medium">Create QR Codes</p>
                        <p class="text-gray-400 text-sm">Link to promotions & games</p>
                    </div>
                    <div>
                        <div class="w-12 h-12 rounded-full bg-purple-500/20 text-purple-400 text-xl font-bold flex items-center justify-center mx-auto mb-3">3</div>
                        <p class="text-white font-medium">Put by Register</p>
                        <p class="text-gray-400 text-sm">Or on merch, flyers, anywhere</p>
                    </div>
                    <div>
                        <div class="w-12 h-12 rounded-full bg-purple-500/20 text-purple-400 text-xl font-bold flex items-center justify-center mx-auto mb-3">4</div>
                        <p class="text-white font-medium">Watch 'Em Return</p>
                        <p class="text-gray-400 text-sm">Track every scan & redemption</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Preview -->
        <div class="max-w-4xl mx-auto px-4 py-12 text-center">
            <h2 class="text-2xl font-bold text-white mb-8">Simple Pricing</h2>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">Starter</div>
                    <div class="text-3xl font-bold text-white mb-1">$29<span class="text-lg text-gray-400">/mo</span></div>
                    <div class="text-gray-400 text-sm">10 QR codes, 3 games</div>
                </div>
                <div class="bg-gradient-to-b from-purple-600/20 to-pink-600/20 rounded-2xl p-6 border border-purple-500/30 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-purple-500 text-white text-xs font-bold rounded-full">POPULAR</div>
                    <div class="text-gray-400 text-sm mb-2">Growth</div>
                    <div class="text-3xl font-bold text-white mb-1">$79<span class="text-lg text-gray-400">/mo</span></div>
                    <div class="text-gray-400 text-sm">50 QR codes, all games</div>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">Pro</div>
                    <div class="text-3xl font-bold text-white mb-1">$149<span class="text-lg text-gray-400">/mo</span></div>
                    <div class="text-gray-400 text-sm">Unlimited everything</div>
                </div>
            </div>
        </div>

        <!-- Final CTA -->
        <div class="max-w-4xl mx-auto px-4 py-12 text-center">
            <div class="bg-gradient-to-r from-emerald-600/20 to-green-600/20 rounded-2xl p-8 border border-emerald-500/20">
                <h2 class="text-2xl font-bold text-white mb-4">Ready to Bring Customers Back?</h2>
                <p class="text-gray-400 mb-6">Join hundreds of local businesses using Revenue QR</p>
                
                <Link :href="`/register?ref=${referralCode}`"
                    class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold text-lg rounded-xl hover:from-emerald-600 hover:to-green-700 transition-all">
                    Get Started Free
                </Link>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-white/10 py-8">
            <div class="max-w-4xl mx-auto px-4 text-center text-gray-500 text-sm">
                <p>© {{ new Date().getFullYear() }} Revenue QR. All rights reserved.</p>
                <div class="mt-2 space-x-4">
                    <Link href="/privacy" class="hover:text-white">Privacy</Link>
                    <Link href="/terms" class="hover:text-white">Terms</Link>
                </div>
            </div>
        </div>
    </div>
</template>
