<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    orders: Object, // Paginated
    statusCounts: Object,
    filters: Object,
});

const resolveCurrency = (order) => {
    const c = String(order?.shipping_country || '').toUpperCase();
    return c === 'US' ? 'USD' : 'CAD';
};

// Format currency
const formatCurrency = (amount, order = null) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: resolveCurrency(order),
    }).format(amount || 0);
};

// Format date
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Get status color
const getStatusColor = (status) => {
    const colors = {
        pending: 'text-yellow-400 bg-yellow-400/20 border-yellow-400/30',
        processing: 'text-blue-400 bg-blue-400/20 border-blue-400/30',
        shipped: 'text-purple-400 bg-purple-400/20 border-purple-400/30',
        delivered: 'text-green-400 bg-green-400/20 border-green-400/30',
        cancelled: 'text-red-400 bg-red-400/20 border-red-400/30',
    };
    return colors[status] || colors.pending;
};

// Get payment status color
const getPaymentColor = (status) => {
    const colors = {
        pending: 'text-yellow-400',
        paid: 'text-green-400',
        failed: 'text-red-400',
        refunded: 'text-gray-400',
    };
    return colors[status] || colors.pending;
};

// Expanded order details
const expandedOrder = ref(null);
const toggleOrder = (orderId) => {
    expandedOrder.value = expandedOrder.value === orderId ? null : orderId;
};

const statusOptions = [
    { id: 'all', label: 'All' },
    { id: 'pending', label: 'Pending' },
    { id: 'processing', label: 'Processing' },
    { id: 'shipped', label: 'Shipped' },
    { id: 'delivered', label: 'Delivered' },
    { id: 'cancelled', label: 'Cancelled' },
];

const activeStatus = ref(props.filters?.status || 'all');
const searchQuery = ref(props.filters?.q || '');
const dateFrom = ref(props.filters?.from || '');
const dateTo = ref(props.filters?.to || '');
const perPage = ref(props.filters?.per_page || 20);

const applyFilters = () => {
    const params = {};
    if (activeStatus.value && activeStatus.value !== 'all') params.status = activeStatus.value;
    if (searchQuery.value) params.q = searchQuery.value;
    if (dateFrom.value) params.from = dateFrom.value;
    if (dateTo.value) params.to = dateTo.value;
    if (perPage.value) params.per_page = perPage.value;

    router.get('/business/merch/orders', params, {
        preserveState: false,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    activeStatus.value = 'all';
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    perPage.value = 20;
    applyFilters();
};
</script>

<template>
    <Head title="Merch Orders" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Order History</h1>
                <p class="text-gray-400 mt-1">Track your merchandise orders</p>
            </div>
            <div class="mt-4 md:mt-0">
                <Link href="/business/merch" class="btn-primary">
                    + New Order
                </Link>
            </div>
        </div>

        <!-- Summary + Filters -->
        <div class="glass-card p-4 mb-6">
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="status in statusOptions"
                    :key="status.id"
                    @click="activeStatus = status.id; applyFilters()"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                        activeStatus === status.id
                            ? 'bg-primary-500 text-white'
                            : 'bg-white/5 text-gray-300 hover:bg-white/10'
                    ]"
                >
                    {{ status.label }}
                    <span v-if="status.id !== 'all'" class="ml-2 text-xs text-white/70">
                        {{ statusCounts?.[status.id] || 0 }}
                    </span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search order #, tracking, name, QR"
                    class="input-glass"
                />
                <input v-model="dateFrom" type="date" class="input-glass" />
                <input v-model="dateTo" type="date" class="input-glass" />
                <select v-model="perPage" class="input-glass">
                    <option :value="20">20 / page</option>
                    <option :value="50">50 / page</option>
                    <option :value="100">100 / page</option>
                </select>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button @click="applyFilters" class="btn-primary">Apply</button>
                <button @click="clearFilters" class="text-sm text-gray-400 hover:text-white">Clear</button>
            </div>
        </div>

        <!-- Orders List -->
        <div v-if="orders.data?.length" class="space-y-4">
            <div
                v-for="order in orders.data"
                :key="order.id"
                class="glass-card overflow-hidden"
            >
                <!-- Order Header -->
                <div 
                    @click="toggleOrder(order.id)"
                    class="p-6 cursor-pointer hover:bg-white/5 transition-colors"
                >
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <!-- Order Info -->
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-xl bg-primary-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-white font-semibold">{{ order.order_number }}</p>
                                <p class="text-gray-400 text-sm">{{ formatDate(order.created_at) }}</p>
                            </div>
                        </div>

                        <!-- Status & Total -->
                        <div class="flex items-center space-x-6">
                            <div class="text-right">
                                <p class="text-gray-400 text-sm">{{ order.items?.length || 0 }} items</p>
                                <p class="text-white font-semibold">{{ formatCurrency(order.total, order) }}</p>
                            </div>
                            <span :class="['px-3 py-1 rounded-full text-xs font-medium capitalize border', getStatusColor(order.status)]">
                                {{ order.status }}
                            </span>
                            <svg 
                                :class="['w-5 h-5 text-gray-400 transition-transform', expandedOrder === order.id ? 'rotate-180' : '']"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Expanded Details -->
                <div v-if="expandedOrder === order.id" class="border-t border-white/10">
                    <!-- Order Items -->
                    <div class="p-6 bg-white/5">
                        <h4 class="text-white font-medium mb-4">Order Items</h4>
                        <div class="space-y-3">
                            <div 
                                v-for="item in order.items" 
                                :key="item.id"
                                class="flex items-center justify-between p-3 rounded-lg bg-white/5"
                            >
                                <div class="flex items-center space-x-4">
                                    <div class="w-14 h-14 rounded-lg bg-gray-700 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white font-medium">{{ item.product_name }}</p>
                                        <p class="text-gray-400 text-sm">{{ item.variant || 'Standard' }} × {{ item.quantity }}</p>
                                        <p v-if="item.qr_code" class="text-gray-500 text-xs">QR: {{ item.qr_code.name || item.qr_code.code }}</p>
                                    </div>
                                </div>
                                <p class="text-white font-medium">{{ formatCurrency(item.total_price, order) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                        <!-- Shipping Address -->
                        <div>
                            <h4 class="text-gray-400 text-sm font-medium mb-2">Shipping Address</h4>
                            <div class="text-white text-sm">
                                <p>{{ order.shipping_name }}</p>
                                <p>{{ order.shipping_address_1 }}</p>
                                <p v-if="order.shipping_address_2">{{ order.shipping_address_2 }}</p>
                                <p>{{ order.shipping_city }}, {{ order.shipping_state }} {{ order.shipping_zip }}</p>
                                <p>{{ order.shipping_country }}</p>
                            </div>
                        </div>

                        <!-- Payment Info -->
                        <div>
                            <h4 class="text-gray-400 text-sm font-medium mb-2">Payment</h4>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Status</span>
                                    <span :class="getPaymentColor(order.payment_status)" class="capitalize font-medium">
                                        {{ order.payment_status }}
                                    </span>
                                </div>
                                <div v-if="order.paid_at" class="flex justify-between text-sm">
                                    <span class="text-gray-400">Paid</span>
                                    <span class="text-white">{{ formatDate(order.paid_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tracking -->
                        <div>
                            <h4 class="text-gray-400 text-sm font-medium mb-2">Tracking</h4>
                            <div v-if="order.tracking_number" class="space-y-2">
                                <p class="text-white text-sm font-mono">{{ order.tracking_number }}</p>
                                <a 
                                    v-if="order.tracking_url"
                                    :href="order.tracking_url"
                                    target="_blank"
                                    class="inline-flex items-center text-primary-400 text-sm hover:underline"
                                >
                                    Track Package
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                            <p v-else class="text-gray-500 text-sm">Not yet shipped</p>
                        </div>
                    </div>

                    <!-- Order Totals -->
                    <div class="p-6 bg-white/5 border-t border-white/10">
                        <div class="max-w-xs ml-auto space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Subtotal</span>
                                <span class="text-white">{{ formatCurrency(order.subtotal, order) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Shipping</span>
                                <span class="text-white">{{ formatCurrency(order.shipping_cost, order) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Tax</span>
                                <span class="text-white">{{ formatCurrency(order.tax, order) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold pt-2 border-t border-white/10">
                                <span class="text-white">Total</span>
                                <span class="text-white">{{ formatCurrency(order.total, order) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Printful Status -->
                    <div v-if="order.printful_order_id" class="p-4 bg-purple-500/10 border-t border-purple-500/20">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-purple-300 text-sm">
                                Printful Order: {{ order.printful_order_id }}
                                <span v-if="order.printful_status" class="ml-2 capitalize">({{ order.printful_status }})</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="orders.links && orders.links.length > 3" class="mt-8 flex justify-center gap-2">
            <Link
                v-for="link in orders.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                    'px-3 py-1 rounded-lg text-sm transition-colors',
                    link.active ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10',
                    !link.url ? 'opacity-50 cursor-not-allowed' : ''
                ]"
                v-html="link.label"
            ></Link>
        </div>

        <!-- Empty State -->
        <div v-else class="glass-card p-12 text-center">
            <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <h3 class="text-xl font-semibold text-white mb-2">No orders yet</h3>
            <p class="text-gray-400 mb-6">Start ordering merchandise with your QR codes!</p>
            <Link href="/business/merch" class="btn-primary inline-flex items-center">
                Browse Products
            </Link>
        </div>
    </div>
</template>
