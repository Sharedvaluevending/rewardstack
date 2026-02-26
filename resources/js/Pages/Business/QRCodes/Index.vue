<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    qrCodes: {
        type: Object,
        default: () => ({ data: [], links: [], last_page: 1 })
    },
    leaderboardPrizesOnly: {
        type: Boolean,
        default: false,
    },
});

const deleteModal = ref({ show: false, qrCode: null, warnings: [], loading: false });

const deleteQRCode = async (qrCode) => {
    deleteModal.value = { show: false, qrCode, warnings: [], loading: true };
    try {
        const { data } = await axios.get(route('business.qr-codes.check-delete', qrCode.id));
        if (data.warnings && data.warnings.length > 0) {
            deleteModal.value = { show: true, qrCode, warnings: data.warnings, loading: false };
            return;
        }
    } catch (e) {
        // If check fails, fall through to simple confirm
    }
    deleteModal.value.loading = false;
    if (confirm(`Are you sure you want to delete "${qrCode.name}"?`)) {
        router.delete(route('business.qr-codes.destroy', qrCode.id));
    }
};

const confirmDelete = () => {
    const qr = deleteModal.value.qrCode;
    deleteModal.value = { show: false, qrCode: null, warnings: [], loading: false };
    if (qr) {
        router.delete(route('business.qr-codes.destroy', qr.id));
    }
};

const cancelDelete = () => {
    deleteModal.value = { show: false, qrCode: null, warnings: [], loading: false };
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getQRImageUrl = (qrCode) => {
    // Check multiple possible paths for the image
    if (qrCode.design?.generated_path) {
        return `/storage/${qrCode.design.generated_path}`;
    }
    if (qrCode.image_url) {
        return qrCode.image_url;
    }
    // Try to generate URL from QR code ID if we have an accessor
    return null;
};

const handleImageError = (event) => {
    // Hide broken image and show placeholder
    event.target.style.display = 'none';
};

const getDisplayType = (qrCode) => {
    // Preserve explicit QRcade leaderboard type (don't collapse it to generic QRcade)
    if (qrCode.type === 'qrcade_leaderboard') {
        return 'qrcade_leaderboard';
    }
    // If QR code has games attached, show as QRcade
    if (qrCode.qr_code_games && qrCode.qr_code_games.length > 0) {
        return 'qrcade';
    }
    return qrCode.type;
};

const getDisplayTypeLabel = (qrCode) => {
    const displayType = getDisplayType(qrCode);
    const labels = {
        qrcade: 'QRcade',
        qrcade_leaderboard: 'QRcade Leaderboard',
        promotion: 'Promotion',
        merch_referral: 'Merch Referral',
        level_exclusive: 'Level Exclusive',
        stackable: 'Stackable',
        cross_promo: 'Partner Deal Chain',
        dynamic: 'Dynamic',
        static: 'Static',
    };
    return labels[displayType] || String(displayType || 'Unknown');
};

const getTypeColor = (qrCode) => {
    const displayType = getDisplayType(qrCode);

    if (displayType === 'qrcade') {
        return 'bg-green-500/20 text-green-400';
    }
    if (displayType === 'qrcade_leaderboard') {
        return 'bg-amber-500/20 text-amber-400';
    }
    if (displayType === 'level_exclusive') {
        return 'bg-cyan-500/20 text-cyan-300';
    }
    if (qrCode.type === 'promotion') {
        return 'bg-purple-500/20 text-purple-400';
    }
    if (qrCode.type === 'merch_referral') {
        return 'bg-emerald-500/20 text-emerald-300';
    }
    if (qrCode.type === 'dynamic') {
        return 'bg-blue-500/20 text-blue-400';
    }
    return 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <Head :title="leaderboardPrizesOnly ? 'Leaderboard Prize QR Codes' : 'QR Codes'" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <Link v-if="leaderboardPrizesOnly" href="/business/qr-codes" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                    ← Back to All QR Codes
                </Link>
                <h1 class="text-3xl font-bold text-white">{{ leaderboardPrizesOnly ? 'Leaderboard Prize QR Codes' : 'QR Codes' }}</h1>
                <p class="text-gray-400 mt-1">{{ leaderboardPrizesOnly ? 'These are the internal QR codes used as leaderboard prizes' : 'Manage your QR codes and track their performance' }}</p>
            </div>
            <div class="flex gap-3 mt-4 md:mt-0">
                <Link 
                    v-if="!leaderboardPrizesOnly"
                    href="/business/qr-codes?leaderboard_prizes=1" 
                    class="px-4 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 border border-white/10 transition-colors text-sm font-medium flex items-center gap-2"
                >
                    🏆 Leaderboard Prizes
                </Link>
                <Link href="/business/qr-codes/create" class="btn-primary">
                    + New QR Code
                </Link>
            </div>
        </div>

        <!-- QR Codes Grid -->
        <div v-if="qrCodes && qrCodes.data && qrCodes.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div
                v-for="qrCode in qrCodes.data"
                :key="qrCode?.id"
                class="glass-card overflow-hidden card-hover group"
            >
                <!-- QR Preview -->
                <div class="aspect-square bg-white p-4 flex items-center justify-center relative">
                    <img 
                        v-if="getQRImageUrl(qrCode)" 
                        :src="getQRImageUrl(qrCode)" 
                        :alt="qrCode.name"
                        class="w-full h-full object-contain"
                        @error="handleImageError"
                    />
                    <div v-else class="w-32 h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-800" viewBox="0 0 100 100">
                            <rect x="10" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                            <rect x="65" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                            <rect x="10" y="65" width="25" height="25" rx="3" fill="currentColor"/>
                            <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                        </svg>
                    </div>
                    
                    <!-- Overlay on hover -->
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                        <Link
                            :href="route('business.qr-codes.show', qrCode.id)"
                            class="p-2 rounded-lg bg-white/20 hover:bg-white/30 transition-colors"
                            title="View"
                        >
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </Link>
                        <Link
                            :href="route('business.qr-codes.edit', qrCode.id)"
                            class="p-2 rounded-lg bg-white/20 hover:bg-white/30 transition-colors"
                            title="Edit"
                        >
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </Link>
                        <!-- Hide download for merch_referral - must order merch to get QR -->
                        <a
                            v-if="qrCode.type !== 'merch_referral'"
                            :href="route('business.qr-codes.download', [qrCode.id, 'png'])"
                            class="p-2 rounded-lg bg-white/20 hover:bg-white/30 transition-colors"
                            title="Download"
                        >
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        <button
                            @click="deleteQRCode(qrCode)"
                            class="p-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 transition-colors"
                            title="Delete"
                        >
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-white font-medium truncate">{{ qrCode.name }}</h3>
                            <p class="text-gray-400 text-sm mt-1">
                                <span :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                    getTypeColor(qrCode)
                                ]">
                                    {{ getDisplayTypeLabel(qrCode) }}
                                </span>
                            </p>
                        </div>
                        <span :class="[
                            'w-2 h-2 rounded-full mt-2',
                            qrCode.is_active ? 'bg-green-500' : 'bg-red-500'
                        ]"></span>
                    </div>

                    <!-- Stats -->
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-white/10">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-white">{{ qrCode.total_scans || 0 }}</p>
                            <p class="text-gray-500 text-xs">Scans</p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-400">{{ qrCode.placement_location || 'No location' }}</p>
                            <p class="text-gray-500 text-xs">Placement</p>
                        </div>
                    </div>

                    <!-- Linked Promotion -->
                    <div v-if="qrCode.promotion" class="mt-3 p-2 rounded-lg bg-purple-500/10 border border-purple-500/20">
                        <p class="text-purple-400 text-sm truncate">
                            🎁 {{ qrCode.promotion.name }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="glass-card p-12 text-center">
            <div class="w-20 h-20 rounded-full bg-white/10 mx-auto flex items-center justify-center mb-6">
                <span v-if="leaderboardPrizesOnly" class="text-4xl">🏆</span>
                <svg v-else class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">{{ leaderboardPrizesOnly ? 'No Leaderboard Prize QR Codes' : 'No QR Codes Yet' }}</h3>
            <p class="text-gray-400 mb-6">{{ leaderboardPrizesOnly ? 'Create a Promotion QR code and select "Leaderboard Prize (internal)" to use it as a leaderboard reward.' : 'Create your first QR code to start tracking scans and engaging customers.' }}</p>
            <Link href="/business/qr-codes/create" class="btn-primary">
                {{ leaderboardPrizesOnly ? '+ Create QR Code' : 'Create Your First QR Code' }}
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="hasQRCodes && qrCodes.last_page > 1" class="mt-8 flex justify-center">
            <nav class="flex items-center space-x-2">
                <Link
                    v-for="link in qrCodes.links"
                    :key="link.label"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg transition-colors',
                        link.active
                            ? 'bg-primary-500 text-white'
                            : link.url
                                ? 'bg-white/10 text-gray-300 hover:bg-white/20'
                                : 'bg-white/5 text-gray-500 cursor-not-allowed'
                    ]"
                    v-html="link.label"
                />
            </nav>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
        <div v-if="deleteModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="cancelDelete"></div>
            <div class="relative w-full max-w-md bg-slate-900 border border-white/20 rounded-2xl shadow-2xl p-6">
                <h3 class="text-lg font-bold text-white mb-2">Delete "{{ deleteModal.qrCode?.name }}"?</h3>
                <p class="text-gray-400 text-sm mb-4">This QR code has linked data that will be affected:</p>
                <ul class="space-y-2 mb-6">
                    <li v-for="(w, i) in deleteModal.warnings" :key="i" class="flex gap-2 text-sm">
                        <span class="text-amber-400 flex-shrink-0">&#9888;</span>
                        <span class="text-gray-300">{{ w }}</span>
                    </li>
                </ul>
                <div class="flex gap-3 justify-end">
                    <button @click="cancelDelete" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                        Cancel
                    </button>
                    <button @click="confirmDelete" class="px-4 py-2 rounded-lg bg-red-500/80 text-white hover:bg-red-500 text-sm font-semibold">
                        Delete Anyway
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

