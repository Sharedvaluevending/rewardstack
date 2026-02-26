<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    orders: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const formatCurrency = (num) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num || 0);
const formatDate = (date) => new Date(date).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const getStatusColor = (status) => {
    const colors = {
        pending: 'bg-yellow-500/20 text-yellow-400',
        processing: 'bg-blue-500/20 text-blue-400',
        shipped: 'bg-purple-500/20 text-purple-400',
        delivered: 'bg-green-500/20 text-green-400',
        cancelled: 'bg-red-500/20 text-red-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};

const getPaymentColor = (status) => {
    const colors = {
        paid: 'text-green-400',
        pending: 'text-yellow-400',
        failed: 'text-red-400',
        refunded: 'text-gray-400',
    };
    return colors[status] || 'text-gray-400';
};

const applyFilters = () => {
    router.get('/admin/merch/orders', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    router.get('/admin/merch/orders');
};
</script>

<template>
    <Head title="Merch Orders" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/merch" class="hover:text-white">Merch</Link>
                    <span>/</span>
                    <span class="text-white">Orders</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Merch Orders</h1>
                <p class="text-gray-400 mt-1">{{ orders?.total || 0 }} total orders</p>
            </div>
            <Link href="/admin/merch" class="btn-secondary">
                ← Back to Products
            </Link>
        </div>

        <!-- Filters -->
        <div class="glass-card p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search order number or business..."
                    class="input-glass flex-1 min-w-[200px]"
                />
                <select v-model="statusFilter" @change="applyFilters" class="input-glass w-40">
                    <option value="" class="bg-gray-800 text-white">All Status</option>
                    <option value="pending" class="bg-gray-800 text-white">Pending</option>
                    <option value="processing" class="bg-gray-800 text-white">Processing</option>
                    <option value="shipped" class="bg-gray-800 text-white">Shipped</option>
                    <option value="delivered" class="bg-gray-800 text-white">Delivered</option>
                    <option value="cancelled" class="bg-gray-800 text-white">Cancelled</option>
                </select>
                <button @click="applyFilters" class="btn-primary">Search</button>
                <button v-if="search || statusFilter" @click="clearFilters" class="btn-secondary">Clear</button>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="glass-card overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Order</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Business</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Items</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Total</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Status</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Payment</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Date</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="order in orders.data" :key="order.id" class="hover:bg-white/5">
                        <td class="px-6 py-4">
                            <span class="text-white font-mono">{{ order.order_number }}</span>
                            <p v-if="order.printful_order_id" class="text-gray-500 text-xs mt-1">
                                PF: {{ order.printful_order_id }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <p class="text-white">{{ order.business?.name || 'N/A' }}</p>
                                <p class="text-gray-500 text-sm">{{ order.shipping_name }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex -space-x-2">
                                <div 
                                    v-for="(item, idx) in order.items?.slice(0, 3)" 
                                    :key="item.id"
                                    class="w-8 h-8 rounded bg-white/10 border-2 border-gray-800 flex items-center justify-center text-xs"
                                    :title="item.product_name"
                                >
                                    {{ item.quantity }}
                                </div>
                                <div 
                                    v-if="order.items?.length > 3"
                                    class="w-8 h-8 rounded bg-white/20 border-2 border-gray-800 flex items-center justify-center text-xs text-white"
                                >
                                    +{{ order.items.length - 3 }}
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">
                                {{ order.items?.reduce((sum, i) => sum + i.quantity, 0) }} items
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-green-400 font-medium">{{ formatCurrency(order.total) }}</span>
                            <p class="text-gray-500 text-xs mt-1">
                                Ship: {{ formatCurrency(order.shipping_cost) }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="getStatusColor(order.status)" class="px-2 py-1 rounded text-xs font-medium capitalize">
                                {{ order.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="getPaymentColor(order.payment_status)" class="text-sm capitalize">
                                {{ order.payment_status || 'pending' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-400 text-sm">
                            {{ formatDate(order.created_at) }}
                        </td>
                        <td class="px-6 py-4">
                            <Link :href="`/admin/merch/orders/${order.id}`" class="text-primary-400 hover:text-primary-300 text-sm">
                                View →
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!orders.data?.length" class="p-12 text-center">
                <p class="text-6xl mb-4">📦</p>
                <p class="text-gray-500">No orders found</p>
            </div>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1" class="px-6 py-4 border-t border-white/10 flex justify-between items-center">
                <p class="text-gray-500 text-sm">
                    Showing {{ orders.from }} to {{ orders.to }} of {{ orders.total }} orders
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in orders.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 rounded text-sm',
                            link.active ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-400 hover:bg-white/20',
                            !link.url && 'opacity-50 pointer-events-none'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
