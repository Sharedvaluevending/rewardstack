<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: Object,
    size: Object,
});

const openAveryUrl = computed(() => `/business/print-kits/${props.order.id}/avery`);
const qrDownloadUrl = (item) => {
    const qrId = item?.qr_code_id || item?.qrCode?.id;
    return qrId ? `/business/qr-codes/${qrId}/download/png` : null;
};
</script>

<template>
    <MainLayout>
        <Head title="Sticker Kit Checkout" />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <Link href="/business/print-kits" class="text-gray-400 hover:text-white text-sm mb-4 inline-block">
                ← Back to Sticker Kits
            </Link>

            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-white">Sticker Kit Setup</h1>
                        <p class="text-gray-400 text-sm">Order #{{ order.order_number }}</p>
                        <p v-if="size?.name" class="text-gray-500 text-xs mt-1">{{ size.name }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-white font-semibold text-xl">{{ order.items?.reduce((sum, it) => sum + (Number(it.quantity) || 0), 0) || 0 }} labels</div>
                        <div class="text-gray-400 text-xs">Avery account required to place final print/order</div>
                        <div class="text-gray-500 text-xs mt-1">Status: {{ order.status }}</div>
                    </div>
                </div>

                <div class="mb-6 p-4 bg-white/5 rounded-xl border border-white/10">
                    <div class="text-white font-semibold">Next steps</div>
                    <div class="mt-3 grid sm:grid-cols-4 gap-2">
                        <div class="step step-active">
                            <div class="step-dot">1</div>
                            <div>
                                <div class="step-title">Download QRs</div>
                                <div class="step-sub">PNG files</div>
                            </div>
                        </div>
                        <div class="step step-active">
                            <div class="step-dot">2</div>
                            <div>
                                <div class="step-title">Open Avery</div>
                                <div class="step-sub">Sign in first</div>
                            </div>
                        </div>
                        <div class="step step-active">
                            <div class="step-dot">3</div>
                            <div>
                                <div class="step-title">Image Tool</div>
                                <div class="step-sub">Add + place</div>
                            </div>
                        </div>
                        <div class="step step-active">
                            <div class="step-dot">4</div>
                            <div>
                                <div class="step-title">Print / Order</div>
                                <div class="step-sub">Inside Avery</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 space-y-1">
                        <div>Use your own Avery account. Carts and shipping options are managed by Avery.</div>
                        <div>Inside Avery: choose <span class="text-gray-300 font-medium">Image</span>, upload your downloaded QR, place on label, then continue to Print/Order.</div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-white font-medium mb-2">Items</h2>
                        <div class="space-y-3">
                            <div v-for="item in order.items" :key="item.id" class="p-3 bg-white/5 rounded-lg">
                                <div class="text-white font-medium">{{ item.product_name }}</div>
                                <div class="text-gray-400 text-sm">{{ item.variant }}</div>
                                <div class="text-gray-300 text-sm mt-1">Qty: {{ item.quantity }} • {{ item.total_price }}</div>
                                <a
                                    v-if="qrDownloadUrl(item)"
                                    :href="qrDownloadUrl(item)"
                                    class="text-xs text-primary-300 hover:underline inline-block mt-2"
                                >
                                    Download this QR image (.png)
                                </a>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="mt-6">
                            <a
                                class="btn-primary w-full text-center block"
                                :href="openAveryUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open Avery Print →
                            </a>
                        </div>

                        <div class="mt-4 p-4 bg-white/5 rounded-xl border border-white/10">
                            <div class="text-white font-semibold text-sm">What happens when Avery opens?</div>
                            <ul class="mt-2 space-y-2 text-xs text-gray-300">
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 text-gray-400">•</span>
                                    <span>Your selected sticker template loads for <span class="text-white font-medium">{{ size?.name || 'your chosen size' }}</span>.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 text-gray-400">•</span>
                                    <span>Upload QR files from your computer if Avery does not auto-place them.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 text-gray-400">•</span>
                                    <span>Choose <span class="text-white font-medium">Print</span> or <span class="text-white font-medium">Order</span> inside Avery to finish checkout.</span>
                                </li>
                            </ul>
                            <div class="mt-3 text-xs text-gray-500">
                                Avery shipping costs and account checkout happen inside Avery.
                                You can also reopen Avery anytime from <Link href="/business/print-kits/orders" class="text-primary-300 hover:underline">Orders</Link>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card { @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl; }
.btn-primary { @apply px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-500 text-white rounded-lg font-medium hover:opacity-90 transition-opacity; }
.step { @apply flex items-center gap-2 p-2 rounded-lg border border-white/10; }
.step-dot { @apply w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs text-gray-200 font-semibold flex-shrink-0; }
.step-title { @apply text-xs text-white font-semibold leading-none; }
.step-sub { @apply text-[11px] text-gray-400 leading-none mt-0.5; }
.step-muted { @apply bg-white/5 opacity-80; }
.step-active { @apply bg-primary-500/10 border-primary-500/30; }
.step-done { @apply bg-emerald-500/10 border-emerald-500/30; }
</style>


