<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    qrCode: Object,
    preview: String,
});

const deleteQRCode = () => {
    if (confirm(`Are you sure you want to delete "${props.qrCode.name}"?`)) {
        router.delete(route('business.qr-codes.destroy', props.qrCode.id));
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getTypeLabel = (type) => {
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
    return labels[String(type || '')] || String(type || 'Unknown');
};

const getQRImageUrl = () => {
    if (props.qrCode.design?.generated_path) {
        return `/storage/${props.qrCode.design.generated_path}`;
    }
    if (props.qrCode.image_url) {
        return props.qrCode.image_url;
    }
    return props.preview || null;
};
</script>

<template>
    <Head :title="`${qrCode.name} - QR Code`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/qr-codes" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                ← Back to QR Codes
            </Link>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">{{ qrCode.name }}</h1>
                    <p class="text-gray-400 mt-1">View and manage your QR code</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <Link
                        :href="route('business.qr-codes.edit', qrCode.id)"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                    >
                        Edit
                    </Link>
                    <!-- Hide download for merch_referral - must order merch to get QR -->
                    <a
                        v-if="qrCode.type !== 'merch_referral'"
                        :href="route('business.qr-codes.download', [qrCode.id, 'png'])"
                        class="px-4 py-2 rounded-lg bg-primary-500 text-white hover:bg-primary-600 transition-colors"
                    >
                        Download
                    </a>
                    <button
                        @click="deleteQRCode"
                        class="px-4 py-2 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/30 transition-colors"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- QR Code Preview -->
            <div class="lg:col-span-2">
                <div class="glass-card p-8">
                    <h2 class="text-xl font-semibold text-white mb-6">QR Code Preview</h2>
                    <div class="bg-white p-8 rounded-xl flex items-center justify-center">
                        <img 
                            v-if="getQRImageUrl()" 
                            :src="getQRImageUrl()" 
                            :alt="qrCode.name"
                            class="max-w-full max-h-96 object-contain"
                        />
                        <div v-else class="text-gray-400 text-center p-8">
                            <svg class="w-24 h-24 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            <p>Loading QR code...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="space-y-6">
                <!-- Info Card -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Details</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-400 text-sm">Type</p>
                            <p class="text-white font-medium">{{ getTypeLabel(qrCode.type) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Status</p>
                            <span :class="[
                                'inline-flex items-center px-2 py-1 rounded text-xs font-medium',
                                qrCode.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                            ]">
                                {{ qrCode.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div v-if="qrCode.placement_location">
                            <p class="text-gray-400 text-sm">Placement</p>
                            <p class="text-white font-medium">{{ qrCode.placement_location }}</p>
                        </div>
                        <div v-if="qrCode.created_at">
                            <p class="text-gray-400 text-sm">Created</p>
                            <p class="text-white font-medium">{{ formatDate(qrCode.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Total Scans</span>
                            <span class="text-2xl font-bold text-white">{{ qrCode.total_scans || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Unique Scans</span>
                            <span class="text-2xl font-bold text-white">{{ qrCode.unique_scans || 0 }}</span>
                        </div>
                        <div v-if="qrCode.last_scanned_at" class="flex items-center justify-between">
                            <span class="text-gray-400">Last Scanned</span>
                            <span class="text-white">{{ formatDate(qrCode.last_scanned_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Linked Promotion -->
                <div v-if="qrCode.promotion" class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Linked Promotion</h3>
                    <div class="p-4 rounded-lg bg-purple-500/10 border border-purple-500/20">
                        <p class="text-purple-400 font-medium">{{ qrCode.promotion.name }}</p>
                        <p class="text-gray-400 text-sm mt-1">{{ qrCode.promotion.discount_type }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>
