<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    orders: Object,
});
</script>

<template>
    <MainLayout>
        <Head title="Sticker Kit Orders" />

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-white">Sticker Kit Orders</h1>
                    <p class="text-gray-400 mt-1">Create your kit here, then finish print/order inside your Avery account</p>
                </div>
                <Link href="/business/print-kits" class="btn-primary">New Order</Link>
            </div>

            <div class="mb-6 glass-card p-4">
                <div class="text-white font-semibold">How to use an order</div>
                <div class="text-gray-300 text-sm mt-1">
                    Open any order in Avery to print or place an order. Make sure you are signed into your Avery account. You can reopen Avery anytime.
                </div>
            </div>

            <div class="glass-card overflow-hidden">
                <div v-if="orders?.data?.length" class="divide-y divide-white/10">
                    <div v-for="o in orders.data" :key="o.id" class="p-5 flex items-center justify-between">
                        <div>
                            <div class="text-white font-semibold">#{{ o.order_number }}</div>
                            <div class="text-xs text-gray-400">
                                {{ o.status }} • {{ o.created_at }}
                            </div>
                            <div v-if="o.items?.length" class="mt-2 text-xs text-gray-500">
                                {{ o.items.length }} QR code(s) • {{ o.items.reduce((sum, it) => sum + (Number(it.quantity) || 0), 0) }} label(s)
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Link :href="`/business/print-kits/checkout/${o.id}`" class="btn-secondary">View</Link>
                            <a
                                :href="`/business/print-kits/${o.id}/avery`"
                                class="btn-secondary"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open Avery
                            </a>
                        </div>
                    </div>
                </div>
                <div v-else class="p-8 text-center text-gray-400">
                    No sticker kit orders yet.
                </div>
            </div>

            <div v-if="orders?.links?.length" class="flex justify-center gap-2 mt-6">
                <Link
                    v-for="link in orders.links"
                    :key="link.label"
                    :href="link.url || ''"
                    v-html="link.label"
                    :class="[
                        'px-3 py-2 rounded-lg text-sm',
                        link.active ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-300',
                        !link.url ? 'opacity-50 pointer-events-none' : 'hover:bg-white/20'
                    ]"
                />
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card { @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl; }
.btn-primary { @apply px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-500 text-white rounded-lg font-medium hover:opacity-90 transition-opacity; }
.btn-secondary { @apply px-4 py-2 bg-white/10 text-white rounded-lg font-medium hover:bg-white/20 transition-colors; }
</style>


