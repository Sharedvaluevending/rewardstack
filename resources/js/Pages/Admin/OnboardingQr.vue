<script setup>
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    error: String,
    qrCode: Object,
    qrImage: String,
    stats: Object,
    recentScans: Array,
    businessCardQr: Object,
    businessCardQrImage: String,
    businessCardStats: Object,
    businessCardRecentScans: Array,
});

const downloadQr = () => {
    if (!props.qrImage) return;
    const a = document.createElement('a');
    a.href = props.qrImage;
    a.download = 'portal-join-qr.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

const downloadBusinessCardQr = () => {
    if (!props.businessCardQrImage) return;
    const a = document.createElement('a');
    a.href = props.businessCardQrImage;
    a.download = 'business-card-qr.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};
</script>

<template>
    <Head title="Admin QR Codes" />

    <div class="max-w-6xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-start justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Admin QR Codes</h1>
                <p class="text-gray-400 mt-1">System QR codes (admin-only)</p>
            </div>
        </div>

        <div v-if="error" class="glass-card p-4 mb-6 text-amber-300 border border-amber-500/30">
            {{ error }}
        </div>

        <template v-else>
            <!-- ── Join Flyer QR ─────────────────────────────────── -->
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-white">Join Flyer QR</h2>
                <p class="text-gray-400 text-sm mt-1">Portal Join flyer &mdash; drives sign-ups from printed flyers</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="glass-card p-6 lg:col-span-1">
                    <div class="text-sm text-gray-400 mb-3">QR Code</div>
                    <div class="bg-white rounded-2xl p-4 flex items-center justify-center">
                        <img v-if="qrImage" :src="qrImage" alt="Portal Join QR" class="w-56 h-56 object-contain" />
                        <div v-else class="text-gray-500 text-sm">QR image unavailable</div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            class="btn-primary w-full"
                            @click="downloadQr"
                            :disabled="!qrImage"
                        >
                            Download QR
                        </button>
                    </div>
                    <div class="mt-4 text-xs text-gray-400 space-y-2">
                        <div><span class="text-gray-500">Scan URL:</span> <span class="text-white">{{ qrCode?.scan_url }}</span></div>
                        <div><span class="text-gray-500">Destination:</span> <span class="text-white">{{ qrCode?.destination_url }}</span></div>
                    </div>
                </div>

                <div class="glass-card p-6 lg:col-span-2">
                    <div class="text-sm text-gray-400 mb-4">Performance</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-emerald-400">{{ stats?.total_scans ?? 0 }}</div>
                            <div class="text-gray-400 text-xs mt-1">Total Scans</div>
                        </div>
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-purple-400">{{ stats?.unique_scans ?? 0 }}</div>
                            <div class="text-gray-400 text-xs mt-1">Unique Scans</div>
                        </div>
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-yellow-400">{{ stats?.conversions ?? 0 }}</div>
                            <div class="text-gray-400 text-xs mt-1">Conversions</div>
                        </div>
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-blue-400">{{ stats?.conversion_rate ?? 0 }}%</div>
                            <div class="text-gray-400 text-xs mt-1">Conversion Rate</div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 mt-4">
                        Last scanned: <span class="text-gray-300">{{ qrCode?.last_scanned_at || '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6 mb-12">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-white">Recent Scans</h2>
                    <div class="text-xs text-gray-400">Last 12</div>
                </div>
                <div v-if="recentScans?.length" class="space-y-3">
                    <div
                        v-for="scan in recentScans"
                        :key="scan.id"
                        class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-white/5 border border-white/10"
                    >
                        <div class="text-sm text-gray-300">
                            {{ scan.scanned_at }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ scan.device_type || 'device' }} &bull; {{ scan.browser || 'browser' }} &bull; {{ scan.os || 'os' }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ [scan.city, scan.region, scan.country].filter(Boolean).join(', ') || 'Location unknown' }}
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-500 text-sm">No scans yet.</div>
            </div>

            <!-- ── Business Card QR ─────────────────────────────── -->
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-white">Business Card QR</h2>
                <p class="text-gray-400 text-sm mt-1">Printed on the back of business cards &mdash; directs to revenueqr.com</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="glass-card p-6 lg:col-span-1">
                    <div class="text-sm text-gray-400 mb-3">QR Code</div>
                    <div class="bg-white rounded-2xl p-4 flex items-center justify-center">
                        <img v-if="businessCardQrImage" :src="businessCardQrImage" alt="Business Card QR" class="w-56 h-56 object-contain" />
                        <div v-else class="text-gray-500 text-sm">QR image unavailable</div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            class="btn-primary w-full"
                            @click="downloadBusinessCardQr"
                            :disabled="!businessCardQrImage"
                        >
                            Download QR
                        </button>
                    </div>
                    <div class="mt-4 text-xs text-gray-400 space-y-2">
                        <div><span class="text-gray-500">Scan URL:</span> <span class="text-white">{{ businessCardQr?.scan_url }}</span></div>
                        <div><span class="text-gray-500">Destination:</span> <span class="text-white">{{ businessCardQr?.destination_url }}</span></div>
                    </div>
                </div>

                <div class="glass-card p-6 lg:col-span-2">
                    <div class="text-sm text-gray-400 mb-4">Performance</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-emerald-400">{{ businessCardStats?.total_scans ?? 0 }}</div>
                            <div class="text-gray-400 text-xs mt-1">Total Scans</div>
                        </div>
                        <div class="bg-white/5 rounded-xl p-4 text-center border border-white/10">
                            <div class="text-2xl font-bold text-purple-400">{{ businessCardStats?.unique_scans ?? 0 }}</div>
                            <div class="text-gray-400 text-xs mt-1">Unique Scans</div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 mt-4">
                        Last scanned: <span class="text-gray-300">{{ businessCardQr?.last_scanned_at || '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-white">Recent Scans</h2>
                    <div class="text-xs text-gray-400">Last 12</div>
                </div>
                <div v-if="businessCardRecentScans?.length" class="space-y-3">
                    <div
                        v-for="scan in businessCardRecentScans"
                        :key="scan.id"
                        class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-white/5 border border-white/10"
                    >
                        <div class="text-sm text-gray-300">
                            {{ scan.scanned_at }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ scan.device_type || 'device' }} &bull; {{ scan.browser || 'browser' }} &bull; {{ scan.os || 'os' }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ [scan.city, scan.region, scan.country].filter(Boolean).join(', ') || 'Location unknown' }}
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-500 text-sm">No scans yet.</div>
            </div>
        </template>
    </div>
</template>
