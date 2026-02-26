<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    qrCode: Object,
    pool: Object,
    entries: Array,
    showPlatformBranding: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <Head :title="pool.name" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <!-- Header -->
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-purple-600/20 to-transparent"></div>
            
            <div class="relative px-4 py-8 text-center">
                <!-- Pool Icon -->
                <div class="w-20 h-20 mx-auto bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-2xl shadow-purple-500/30 mb-4">
                    <span class="text-4xl">📍</span>
                </div>

                <!-- Pool Info -->
                <h1 class="text-2xl font-bold text-white mb-2">Revenue QR</h1>
                <p class="text-gray-400 text-sm mb-2">Deals near you from local businesses</p>
                <p v-if="pool.city" class="text-purple-400 text-sm flex items-center justify-center gap-1">
                    <span>📍</span> {{ pool.city }}
                </p>
            </div>
        </div>

        <!-- Revenue QR Info -->
        <div class="px-4 mb-6">
            <div class="bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-amber-500/30 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">⚡</span>
                    <div>
                        <h3 class="text-amber-400 font-semibold">Revenue QR</h3>
                        <p class="text-gray-400 text-sm">Tap a deal to save it to your wallet</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Businesses List -->
        <div class="px-4 pb-8">
            <h2 class="text-white font-semibold mb-4 flex items-center gap-2">
                <span>Deals Near You</span>
                <span class="text-xs bg-purple-500/30 text-purple-300 px-2 py-1 rounded-full">{{ entries.length }}</span>
            </h2>

            <div class="space-y-3">
                <div v-for="(entry, index) in entries" :key="index">
                    <Link v-if="entry.promotion?.qr_code"
                        :href="`/promo/${entry.promotion.qr_code}?source=stackable&no_xp=1`"
                        class="block bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all">
                        <div class="flex items-center gap-4">
                        <!-- Business Logo -->
                        <div class="flex-shrink-0">
                            <div v-if="entry.business.logo_url" 
                                class="w-14 h-14 rounded-xl bg-white p-1">
                                <img :src="entry.business.logo_url" :alt="entry.business.name" 
                                    class="w-full h-full object-contain" />
                            </div>
                            <div v-else 
                                class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                                <span class="text-xl font-bold text-white">{{ entry.business.name.charAt(0) }}</span>
                            </div>
                        </div>

                        <!-- Business Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-white font-semibold truncate">{{ entry.business.name }}</h3>
                                <span v-if="entry.is_featured" class="text-xs bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded-full">
                                    ⭐ Featured
                                </span>
                            </div>
                            <p v-if="entry.business.city" class="text-gray-500 text-sm">{{ entry.business.city }}</p>
                            
                            <!-- Promotion -->
                            <div class="mt-2 inline-flex items-center gap-1 bg-green-500/10 text-green-400 px-3 py-1 rounded-full text-sm">
                                <span>🎁</span>
                                <span>{{ entry.promotion.display_value }}</span>
                            </div>
                        </div>

                        <!-- Arrow -->
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                        </div>
                    </Link>
                    <div v-else class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4 opacity-70">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div v-if="entry.business.logo_url"
                                    class="w-14 h-14 rounded-xl bg-white p-1">
                                    <img :src="entry.business.logo_url" :alt="entry.business.name"
                                        class="w-full h-full object-contain" />
                                </div>
                                <div v-else
                                    class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                                    <span class="text-xl font-bold text-white">{{ entry.business.name.charAt(0) }}</span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-white font-semibold truncate">{{ entry.business.name }}</h3>
                                </div>
                                <p class="text-gray-500 text-sm">Deal unavailable</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="entries.length === 0" class="text-center py-12">
                <div class="text-6xl mb-4">🏪</div>
                <h3 class="text-white font-semibold mb-2">No businesses yet</h3>
                <p class="text-gray-400 text-sm">Businesses are being added to this pool soon!</p>
            </div>
        </div>

        <!-- How It Works -->
        <div class="px-4 pb-8">
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4">
                <h3 class="text-white font-semibold mb-3">How Revenue QR Works</h3>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 text-xs flex items-center justify-center font-bold">1</span>
                        <p class="text-gray-400 text-sm">Browse nearby deals from local businesses</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 text-xs flex items-center justify-center font-bold">2</span>
                        <p class="text-gray-400 text-sm">Tap a deal to save it to your wallet</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 text-xs flex items-center justify-center font-bold">3</span>
                        <p class="text-gray-400 text-sm">Redeem in-store like any other promo</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div v-if="showPlatformBranding" class="px-4 pb-6 text-center">
            <p class="text-gray-500 text-xs">
                Powered by <span class="text-purple-400">Revenue QR</span>
            </p>
        </div>
    </div>
</template>
