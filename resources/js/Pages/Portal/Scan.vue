<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { BrowserQRCodeReader } from '@zxing/browser';

defineOptions({
    layout: PortalLayout,
});

const videoEl = ref(null);
const error = ref(null);
const isScanning = ref(false);
const manualCode = ref('');
const diagnostics = ref({
    secureContext: null,
    hasMediaDevices: null,
    hasGetUserMedia: null,
    userAgent: null,
});
const permissionState = ref(null); // 'granted' | 'prompt' | 'denied' | null
const showHelp = ref(false);

let qrReader = null;
let controls = null;

const extractCode = (raw) => {
    if (!raw) return null;
    const s = String(raw).trim();

    // If the detector returns a URL, extract /s/{code} or /promo/{code} or last 8 chars token.
    const m1 = s.match(/\/s\/([A-Za-z0-9]{8})/);
    if (m1) return m1[1];
    const m2 = s.match(/\/promo\/([A-Za-z0-9]{8})/);
    if (m2) return m2[1];
    const m3 = s.match(/\b([A-Za-z0-9]{8})\b/);
    if (m3) return m3[1];

    return null;
};

const goToCode = (code) => {
    const cleaned = (code || '').trim();
    if (!cleaned) return;
    window.location.href = `/s/${cleaned}`;
};

const stopCamera = () => {
    try {
        controls?.stop?.();
    } catch (e) {}
    controls = null;
    try {
        qrReader?.reset?.();
    } catch (e) {}
    isScanning.value = false;
};

const refreshPermissionState = async () => {
    try {
        if (!navigator.permissions?.query) {
            permissionState.value = null;
            return;
        }
        // Chromium supports 'camera' here; other browsers may throw.
        const status = await navigator.permissions.query({ name: 'camera' });
        permissionState.value = status?.state || null;
        status?.addEventListener?.('change', () => {
            permissionState.value = status.state;
        });
    } catch (e) {
        permissionState.value = null;
    }
};

const openInChrome = () => {
    // In an installed PWA, this typically opens the same URL in Chrome with an address bar,
    // making it easier to adjust site permissions if needed.
    window.open(window.location.href, '_blank', 'noopener,noreferrer');
};

const startCamera = async () => {
    error.value = null;

    diagnostics.value = {
        secureContext: !!window.isSecureContext,
        hasMediaDevices: !!navigator.mediaDevices,
        hasGetUserMedia: !!navigator.mediaDevices?.getUserMedia,
        userAgent: navigator.userAgent || null,
    };

    if (!videoEl.value) {
        error.value = 'Scanner not ready yet. Please try again.';
        return;
    }

    if (!window.isSecureContext) {
        error.value = 'Camera requires HTTPS. Please open the secure site URL and try again.';
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'Camera API not available in this browser. Please update your browser or use manual code entry.';
        return;
    }

    await refreshPermissionState();

    try {
        qrReader = qrReader || new BrowserQRCodeReader();
        isScanning.value = true;

        // Prefer back camera and use constraints (more reliable across Android/iOS than decodeFromVideoDevice).
        controls = await qrReader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            videoEl.value,
            (result, err, c) => {
                if (result?.getText) {
                    const found = extractCode(result.getText());
                    if (found) {
                        try { c?.stop?.(); } catch (e) {}
                        stopCamera();
                        goToCode(found);
                    }
                }
                // ignore decode errors/noise
            }
        );
    } catch (e) {
        const name = e?.name || 'Error';
        const msg = e?.message || String(e);
        if (name === 'NotAllowedError') {
            error.value =
                'Camera permission is blocked.\n\n' +
                'If you installed this as an app (no address bar): Android Settings → Apps → Revenue QR → Permissions → Camera → Allow.\n\n' +
                'Or tap “Open in Chrome” below and allow Camera for this site.';
            showHelp.value = true;
        } else {
            error.value = `Camera error: ${name}. ${msg}`;
        }
        stopCamera();
    }
};

const submitManual = () => {
    const found = extractCode(manualCode.value);
    if (!found) {
        error.value = 'Please enter a valid 8-character code.';
        return;
    }
    goToCode(found);
};

onMounted(() => {
    diagnostics.value = {
        secureContext: !!window.isSecureContext,
        hasMediaDevices: !!navigator.mediaDevices,
        hasGetUserMedia: !!navigator.mediaDevices?.getUserMedia,
        userAgent: navigator.userAgent || null,
    };
    refreshPermissionState();
});

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Head title="Scan" />

    <div class="max-w-lg mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-white">Scan</h1>
            <Link href="/portal" class="text-sm text-gray-400 hover:text-white transition-colors">Done</Link>
        </div>

        <div class="glass-card overflow-hidden mb-4">
            <div class="p-3 border-b border-white/10 flex items-center justify-between">
                <div class="text-sm text-gray-300">Camera scanner</div>
                <button
                    v-if="isScanning"
                    @click="stopCamera"
                    class="text-xs px-3 py-1 rounded-full bg-white/10 text-gray-300 hover:bg-white/20"
                >
                    Stop
                </button>
                <button
                    v-else
                    @click="startCamera"
                    class="text-xs px-3 py-1 rounded-full bg-white/10 text-gray-300 hover:bg-white/20"
                >
                    Start
                </button>
            </div>

            <div class="p-3">
                <div class="w-full bg-black/40 rounded-xl overflow-hidden border border-white/10" style="aspect-ratio: 1 / 1;">
                    <video ref="videoEl" class="w-full h-full object-cover" playsinline muted autoplay></video>
                </div>
                <p v-if="error" class="text-sm text-amber-400 mt-3">{{ error }}</p>
                <p class="text-xs text-gray-500 mt-3">
                    Scanning inside the portal keeps you logged in, so scanned promos and rewards save automatically.
                </p>
                <div v-if="permissionState" class="mt-2 text-xs text-gray-500">
                    Camera permission: <span class="text-gray-300">{{ permissionState }}</span>
                </div>

                <div v-if="showHelp" class="mt-3 p-3 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm text-white font-semibold">Need help enabling camera?</div>
                        <button
                            type="button"
                            class="text-xs px-3 py-1 rounded-full bg-white/10 text-gray-300 hover:bg-white/20"
                            @click="showHelp = !showHelp"
                        >
                            {{ showHelp ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <div class="mt-2 text-xs text-gray-400 space-y-2">
                        <div>
                            <div class="text-gray-300 font-medium mb-1">If you installed the app (no address bar):</div>
                            <div>Android Settings → Apps → <span class="text-gray-200">Revenue QR</span> → Permissions → Camera → Allow</div>
                        </div>
                        <div>
                            <div class="text-gray-300 font-medium mb-1">Or open in Chrome:</div>
                            <button
                                type="button"
                                class="px-3 py-2 rounded-lg bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition-colors"
                                @click="openInChrome"
                            >
                                Open in Chrome
                            </button>
                            <div class="mt-1">Then allow Camera for this site (🔒 lock icon → Permissions → Camera → Allow).</div>
                        </div>
                    </div>
                </div>
                <details class="mt-3">
                    <summary class="text-xs text-gray-500 cursor-pointer select-none">Diagnostics</summary>
                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                        <div>secureContext: {{ diagnostics.secureContext }}</div>
                        <div>mediaDevices: {{ diagnostics.hasMediaDevices }}</div>
                        <div>getUserMedia: {{ diagnostics.hasGetUserMedia }}</div>
                    </div>
                </details>
            </div>
        </div>

        <div class="glass-card p-4">
            <div class="text-sm font-medium text-white mb-2">Manual code entry</div>
            <div class="flex gap-2">
                <input
                    v-model="manualCode"
                    type="text"
                    placeholder="Enter 8-character code"
                    class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-purple-500/50"
                />
                <button
                    @click="submitManual"
                    class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition-colors"
                >
                    Go
                </button>
            </div>

            <p v-if="error" class="mt-3 text-sm text-amber-400">{{ error }}</p>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>


