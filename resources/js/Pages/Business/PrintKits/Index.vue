<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    qrCodes: Array,
    canAccess: Boolean,
    sizes: Array,
});
</script>

<template>
    <MainLayout>
        <Head title="Sticker Kits" />

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white">Sticker Kits</h1>
                <p class="text-gray-400 mt-1">Download your QR code images, then use Avery to print or order sticker sheets</p>
            </div>

            <!-- Avery account notice -->
            <div class="mb-6 glass-card p-4 border border-blue-500/30 bg-blue-500/5">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="text-blue-300 font-semibold">You need an Avery account</div>
                        <div class="text-gray-300 text-sm mt-1">
                            Sticker Kits use <span class="text-white font-medium">Avery Design &amp; Print Online</span> to print/order your stickers.
                            You'll need a free Avery account to complete your order on their site.
                        </div>
                        <a
                            href="https://www.avery.ca/en/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-block mt-3 px-4 py-2 bg-blue-500/20 text-blue-300 border border-blue-500/30 rounded-lg hover:bg-blue-500/30 transition-colors text-sm font-medium"
                        >
                            Go to Avery.ca
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upgrade notice -->
            <div v-if="!canAccess" class="mb-6 glass-card p-4 border border-amber-500/30 bg-amber-500/10">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="flex-1">
                        <div class="text-amber-300 font-medium">Sticker Kits Available on Growth &amp; Above</div>
                        <div class="text-gray-300 text-sm mt-1">
                            Sticker kits are only available on Growth, Pro, and Enterprise plans. Upgrade your plan to access this feature.
                        </div>
                        <Link href="/business/billing" class="inline-block mt-3 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors text-sm font-medium">
                            Upgrade Plan
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6" :class="{ 'opacity-60 pointer-events-none': !canAccess }">

                <!-- Step 1: Download QR codes -->
                <div class="glass-card p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="step-number">1</div>
                        <h2 class="text-xl font-semibold text-white">Download your QR codes</h2>
                    </div>
                    <p class="text-gray-400 text-sm mb-4">Download QR code images to your computer. You'll upload these into Avery in a later step.</p>

                    <div class="glass-table overflow-hidden border border-white/10 rounded-lg">
                        <div class="divide-y divide-white/10">
                            <div v-for="q in qrCodes" :key="q.id" class="p-3 flex items-center gap-3">
                                <img
                                    v-if="q.image_url"
                                    :src="q.image_url"
                                    :alt="q.name"
                                    class="w-12 h-12 rounded-lg border border-white/10 object-contain bg-white/5"
                                />
                                <div v-else class="w-12 h-12 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-white text-sm font-medium truncate">{{ q.name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ q.code }}</div>
                                </div>
                                <a
                                    :href="`/business/qr-codes/${q.id}/download/png`"
                                    class="px-3 py-1.5 bg-white/10 text-white text-xs rounded-lg hover:bg-white/20 transition-colors font-medium flex-shrink-0"
                                >
                                    Download .png
                                </a>
                            </div>
                            <div v-if="!qrCodes?.length" class="p-6 text-center text-gray-400 text-sm">
                                No QR codes available. Create QR codes first, then come back here.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Choose template + open Avery -->
                <div class="glass-card p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="step-number">2</div>
                        <h2 class="text-xl font-semibold text-white">Open Avery template</h2>
                    </div>
                    <p class="text-gray-400 text-sm mb-4">Pick a sticker size. This opens Avery Design &amp; Print where you can upload your QR image and order stickers.</p>

                    <div class="space-y-3">
                        <a
                            v-for="s in sizes"
                            :key="s.key"
                            :href="`/business/print-kits/launch/${s.key}`"
                            target="_blank"
                            class="block p-4 bg-white/5 rounded-lg border border-white/10 hover:bg-white/10 hover:border-white/20 transition-all group"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-white font-medium group-hover:text-primary-300 transition-colors">{{ s.name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">Template {{ s.template_number }} &middot; {{ s.labels_per_sheet }} per sheet</div>
                                </div>
                                <div class="flex items-center gap-2 text-gray-500 group-hover:text-primary-300 transition-colors">
                                    <span class="text-xs font-medium">Open in Avery</span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Step 3: Instructions -->
                <div class="lg:col-span-2 glass-card p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="step-number">3</div>
                        <h2 class="text-xl font-semibold text-white">How to set up your stickers in Avery</h2>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="instruction-card">
                            <div class="instruction-num">A</div>
                            <div class="instruction-title">Upload your QR image</div>
                            <div class="instruction-text">In the Avery editor, click <span class="text-white font-medium">Image</span> on the left toolbar, then <span class="text-white font-medium">Add Image</span>. Upload the QR code file you downloaded in Step 1.</div>
                        </div>
                        <div class="instruction-card">
                            <div class="instruction-num">B</div>
                            <div class="instruction-title">Place on the label</div>
                            <div class="instruction-text">Drag/resize the QR code to fill the label area. It will automatically apply to all labels on the sheet.</div>
                        </div>
                        <div class="instruction-card">
                            <div class="instruction-num">C</div>
                            <div class="instruction-title">Preview &amp; Print</div>
                            <div class="instruction-text">Click <span class="text-white font-medium">Preview &amp; Print</span> in the bottom right. Review your labels before ordering.</div>
                        </div>
                        <div class="instruction-card">
                            <div class="instruction-num">D</div>
                            <div class="instruction-title">Order or download PDF</div>
                            <div class="instruction-text">Choose <span class="text-white font-medium">Let Us Print</span> to have Avery ship stickers to you, or <span class="text-white font-medium">Download PDF</span> to print at home.</div>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-white/5 rounded-lg border border-white/10 text-xs text-gray-400">
                        <span class="text-gray-300 font-medium">Tip:</span> If you want different QR codes on the same sheet, repeat this process per QR code.
                        Save your project in Avery so you can reorder anytime without re-uploading.
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card { @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl; }
.glass-table { @apply bg-white/5 backdrop-blur-sm; }
.step-number { @apply w-8 h-8 rounded-full bg-gradient-to-r from-primary-500 to-accent-500 flex items-center justify-center text-white text-sm font-bold flex-shrink-0; }
.instruction-card { @apply p-4 bg-white/5 rounded-lg border border-white/10; }
.instruction-num { @apply w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs text-gray-200 font-semibold mb-2; }
.instruction-title { @apply text-white text-sm font-semibold mb-1; }
.instruction-text { @apply text-gray-400 text-xs leading-relaxed; }
</style>
