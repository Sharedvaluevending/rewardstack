<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    businesses: Array,
    userLocation: Object,
    radius: Number,
});

const locating = ref(false);
const locationError = ref(null);

const shareLocation = () => {
    if (!navigator.geolocation) {
        locationError.value = 'Geolocation is not supported by your browser.';
        return;
    }

    locating.value = true;
    locationError.value = null;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            router.get('/portal/games/nearby', {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                radius: props.radius || 1000,
            }, {
                preserveState: false,
            });
        },
        (error) => {
            locating.value = false;
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Location access was denied. Please enable location in your browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    locationError.value = 'Location request timed out. Please try again.';
                    break;
                default:
                    locationError.value = 'An unknown error occurred.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
};
</script>

<template>
    <Head title="Nearby Games" />
    <PortalLayout>
        <div class="mb-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-white">📍 Nearby Games</h1>
                    <p v-if="userLocation" class="text-gray-400 text-sm mt-1">Showing businesses within {{ radius || 1000 }}m that have games enabled</p>
                    <p v-else class="text-gray-400 text-sm mt-1">Share your location to find games near you</p>
                </div>
                <div class="flex gap-2">
                    <Link href="/portal/games" class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                        🎮 Overview
                    </Link>
                    <Link href="/portal/games/history" class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                        📜 History
                    </Link>
                </div>
            </div>
        </div>

        <!-- Location prompt when no coordinates provided -->
        <div v-if="!userLocation" class="text-center py-12">
            <div class="text-5xl mb-4">📍</div>
            <h3 class="text-white font-semibold text-lg mb-2">Find Games Near You</h3>
            <p class="text-gray-400 text-sm mb-6">Share your location to discover businesses with games nearby.</p>
            <button
                @click="shareLocation"
                :disabled="locating"
                class="px-6 py-3 rounded-xl bg-gradient-to-r from-purple-500 to-pink-500 text-white font-semibold hover:from-purple-600 hover:to-pink-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span v-if="locating">Locating...</span>
                <span v-else>Share My Location</span>
            </button>
            <div v-if="locationError" class="mt-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm max-w-md mx-auto">
                {{ locationError }}
            </div>
        </div>

        <!-- Results -->
        <div v-else-if="businesses?.length" class="space-y-3">
            <div class="flex justify-end mb-2">
                <button
                    @click="shareLocation"
                    :disabled="locating"
                    class="px-3 py-1.5 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 text-sm transition-colors"
                >
                    🔄 Refresh Location
                </button>
            </div>
            <div v-for="b in businesses" :key="b.id" class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-xl">
                        🏪
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-white font-medium truncate">{{ b.name }}</div>
                        <div class="text-gray-400 text-sm truncate">
                            {{ b.city ? `${b.city}${b.state ? ', ' + b.state : ''}` : '—' }}
                        </div>
                        <div class="text-gray-500 text-xs mt-1">
                            {{ b.formatted_distance || '' }} • {{ b.games_count || 0 }} games
                        </div>
                    </div>
                    <Link :href="`/b/${b.slug}`" class="px-3 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 transition-colors text-sm">
                        View
                    </Link>
                </div>
            </div>
        </div>

        <!-- No results after sharing location -->
        <div v-else class="text-center py-12">
            <div class="text-5xl mb-4">📍</div>
            <h3 class="text-white font-semibold text-lg mb-2">No nearby games found</h3>
            <p class="text-gray-400 text-sm mb-4">Try increasing the radius or moving closer to participating businesses.</p>
            <button
                @click="shareLocation"
                :disabled="locating"
                class="px-4 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 text-sm transition-colors"
            >
                🔄 Try Again
            </button>
        </div>
    </PortalLayout>
</template>

