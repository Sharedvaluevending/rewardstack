<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    items: Array,
    summary: Object,
    flare: Object,
});

const flareEnabled = ref(false);
const flareToast = ref(null);
const lastScanId = ref(null);
let pollTimer;
let audioContext;
const activeTag = ref(null);
const showTagModal = ref(false);

const ensureAudio = async () => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    if (!audioContext) {
        audioContext = new AudioContext();
    }
    if (audioContext.state === 'suspended') {
        await audioContext.resume();
    }
};

const playDing = () => {
    if (!audioContext) return;
    const ctx = audioContext;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = 880;
    gain.gain.value = 0.2;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
    osc.stop(ctx.currentTime + 0.25);
};

const triggerFlare = () => {
    if (navigator.vibrate) {
        navigator.vibrate([60, 40, 60]);
    }
    playDing();
    flareToast.value = `Influence Confirmed! +${props.flare?.scan_xp || 50} XP`;
    setTimeout(() => {
        flareToast.value = null;
    }, 2000);
};

const poll = async () => {
    try {
        const response = await fetch('/portal/merch/ping', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        if (data.scan_id) {
            if (lastScanId.value !== null && data.scan_id !== lastScanId.value && flareEnabled.value) {
                triggerFlare();
            }
            lastScanId.value = data.scan_id;
        }
    } catch (e) {
        // Ignore polling failures for now.
    }
};

const toggleFlare = async () => {
    flareEnabled.value = !flareEnabled.value;
    if (flareEnabled.value) {
        await ensureAudio();
    }
};

const openTagModal = (item) => {
    activeTag.value = item;
    showTagModal.value = true;
};

const closeTagModal = () => {
    showTagModal.value = false;
};

onMounted(() => {
    poll();
    pollTimer = setInterval(poll, 5000);
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <Head title="My Merch" />

    <PortalLayout>
        <div class="space-y-4">
            <div class="glass-card p-4">
                <h1 class="text-xl font-semibold text-white">Ambassador Merch</h1>
                <p class="text-sm text-gray-400">
                    Track scans and redemptions tied to your merch.
                </p>
            </div>

            <div class="glass-card p-4 flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-white">The Flare</div>
                    <div class="text-xs text-gray-400">
                        Get a live haptic + sound when someone scans your merch.
                    </div>
                </div>
                <button
                    class="px-3 py-2 rounded-xl text-sm font-semibold border"
                    :class="flareEnabled ? 'bg-emerald-500/20 text-emerald-200 border-emerald-400/40' : 'bg-white/10 text-white border-white/10'"
                    @click="toggleFlare"
                >
                    {{ flareEnabled ? 'Live' : 'Enable' }}
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <div class="glass-card p-4">
                    <div class="text-xs text-gray-400">Merch</div>
                    <div class="text-2xl font-semibold text-white">{{ summary.total_merch }}</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-xs text-gray-400">Unique Scans</div>
                    <div class="text-2xl font-semibold text-white">{{ summary.unique_scans }}</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-xs text-gray-400">Total Scans</div>
                    <div class="text-2xl font-semibold text-white">{{ summary.total_scans }}</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-xs text-gray-400">Redemptions</div>
                    <div class="text-2xl font-semibold text-white">{{ summary.redemptions }}</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-xs text-gray-400">Rewards Earned</div>
                    <div class="text-2xl font-semibold text-white">{{ summary.awards }}</div>
                </div>
            </div>

            <div class="glass-card p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-white">My Merch</h2>
                    <Link href="/portal/scans" class="text-xs text-purple-300 hover:text-purple-200">
                        View scans
                    </Link>
                </div>

                <div v-if="!items.length" class="text-sm text-gray-400">
                    You have not claimed any merch yet.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="p-3 rounded-xl bg-white/5 border border-white/10"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-white border border-white/20 flex items-center justify-center overflow-hidden">
                                <img v-if="item.business?.logo_url" :src="item.business.logo_url" alt="" class="w-full h-full object-contain" />
                                <span v-else class="text-white text-lg">👕</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-white truncate">
                                    {{ item.business?.name || 'Business' }}
                                </div>
                                <div class="text-xs text-gray-400 truncate">
                                    {{ item.qr_code?.name || 'QR Code' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="text-xs text-gray-500 font-mono">
                                    {{ item.code }}
                                </div>
                                <button
                                    type="button"
                                    class="px-2 py-1 rounded-lg bg-white/10 text-white text-[10px] hover:bg-white/20 transition-all"
                                    @click="openTagModal(item)"
                                >
                                    My merch QR
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                            <div class="bg-black/20 rounded-lg p-2">
                                <div class="text-xs text-gray-400">Unique</div>
                                <div class="text-sm font-semibold text-white">{{ item.stats.unique_scans }}</div>
                            </div>
                            <div class="bg-black/20 rounded-lg p-2">
                                <div class="text-xs text-gray-400">Scans</div>
                                <div class="text-sm font-semibold text-white">{{ item.stats.total_scans }}</div>
                            </div>
                            <div class="bg-black/20 rounded-lg p-2">
                                <div class="text-xs text-gray-400">Redeems</div>
                                <div class="text-sm font-semibold text-white">{{ item.stats.redemptions }}</div>
                            </div>
                        </div>

                        <div v-if="item.reward" class="mt-3 p-3 rounded-xl bg-white/5 border border-white/10">
                            <div class="text-xs text-gray-400">Ambassador Reward</div>
                            <div class="text-sm text-white font-semibold">
                                {{ item.reward.description || (item.reward.type === 'free_item' ? 'Free item' : 'Reward') }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                Every {{ item.reward.redemptions_required }} unique redemptions
                            </div>
                            <div class="text-xs text-primary-300 mt-2">
                                Progress:
                                {{ item.reward.redemptions_required > 0 ? (item.stats.unique_redemptions % item.reward.redemptions_required) : 0 }}
                                / {{ item.reward.redemptions_required }}
                                • Rewards earned: {{ item.awards_count }}
                            </div>
                        </div>

                        <div v-if="item.awards?.length" class="mt-3 space-y-2">
                            <div class="text-xs text-gray-400">
                                Recent rewards:
                                <span v-for="(award, index) in item.awards" :key="award.awarded_at" class="text-gray-300">
                                    {{ index ? ',' : '' }} {{ new Date(award.awarded_at).toLocaleDateString() }}
                                </span>
                            </div>

                            <div v-if="item.awards[0]?.token_qr" class="p-3 rounded-xl bg-black/20 border border-white/10">
                                <div class="text-xs text-gray-400 mb-2">Redeem Reward</div>
                                <div class="flex items-center gap-3">
                                    <img :src="item.awards[0].token_qr" alt="Reward QR" class="w-16 h-16 rounded-lg bg-white p-1" />
                                    <div class="text-xs text-gray-300">
                                        Show this QR to staff to redeem.
                                        <div class="mt-1 text-gray-500 font-mono">{{ item.awards[0].token_code }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="flareToast"
            class="fixed inset-x-0 top-16 mx-auto w-[90%] max-w-sm z-50"
        >
            <div class="glass-card p-3 text-center text-sm font-semibold text-white">
                {{ flareToast }}
            </div>
        </div>

        <div
            v-if="showTagModal && activeTag"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            @click.self="closeTagModal"
        >
            <div class="glass-card p-6 max-w-sm w-full border border-white/10">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-sm font-semibold text-white">My merch QR</div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-white text-sm"
                        @click="closeTagModal"
                    >
                        Close
                    </button>
                </div>

                <div class="flex flex-col items-center gap-3">
                    <div class="w-48 h-48 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center">
                        <img
                            v-if="activeTag.qr_image_url"
                            :src="activeTag.qr_image_url"
                            alt="Merch QR"
                            class="w-44 h-44 rounded-xl bg-white p-2"
                        />
                        <div v-else class="text-xs text-gray-400 text-center px-4">
                            QR image not available yet.
                        </div>
                    </div>

                    <div class="w-full space-y-1 text-center">
                        <div class="text-xs text-gray-400">
                            Merch Tag: <span class="text-gray-200 font-mono">{{ activeTag.code }}</span>
                        </div>
                        <div v-if="activeTag.qr_code?.code" class="text-xs text-gray-400">
                            Gateway QR: <span class="text-gray-200 font-mono">{{ activeTag.qr_code.code }}</span>
                        </div>
                    </div>

                    <div v-if="activeTag.awards?.length && activeTag.awards[0]?.token_code" class="w-full p-3 rounded-xl bg-black/20 border border-white/10">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="activeTag.awards[0].token_qr"
                                :src="activeTag.awards[0].token_qr"
                                alt="Reward QR"
                                class="w-14 h-14 rounded-lg bg-white p-1 flex-shrink-0"
                            />
                            <div class="min-w-0">
                                <div class="text-xs text-gray-400">Reward Code</div>
                                <div class="text-sm text-white font-mono font-semibold">{{ activeTag.awards[0].token_code }}</div>
                                <div class="text-[10px] text-gray-500 mt-0.5">Show QR or code to staff to redeem</div>
                            </div>
                        </div>
                    </div>

                    <a
                        v-if="activeTag.qr_image_url"
                        :href="activeTag.qr_image_url"
                        download
                        class="px-4 py-2 rounded-lg bg-white/10 text-white text-xs hover:bg-white/20 transition-all"
                    >
                        Download QR
                    </a>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
