<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    order: Object,
    printfulStatus: Object,
});

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
        pending: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
        processing: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        shipped: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
        delivered: 'bg-green-500/20 text-green-400 border-green-500/30',
        cancelled: 'bg-red-500/20 text-red-400 border-red-500/30',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400 border-gray-500/30';
};

const editForm = useForm({
    status: props.order.status,
    tracking_number: props.order.tracking_number || '',
    tracking_url: props.order.tracking_url || '',
});

const updateOrder = () => {
    editForm.put(`/admin/merch/orders/${props.order.id}`);
};

const refundOrder = () => {
    if (confirm('Are you sure you want to refund this order? This cannot be undone.')) {
        router.post(`/admin/merch/orders/${props.order.id}/refund`);
    }
};
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <div class="max-w-5xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex justify-between items-start mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/merch" class="hover:text-white">Merch</Link>
                    <span>/</span>
                    <Link href="/admin/merch/orders" class="hover:text-white">Orders</Link>
                    <span>/</span>
                    <span class="text-white">{{ order.order_number }}</span>
                </div>
                <h1 class="text-3xl font-bold text-white">{{ order.order_number }}</h1>
                <p class="text-gray-400 mt-1">Placed {{ formatDate(order.created_at) }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span :class="[getStatusColor(order.status), 'px-4 py-2 rounded-full text-sm font-medium capitalize border']">
                    {{ order.status }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="glass-card overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/10">
                        <h2 class="text-lg font-semibold text-white">Order Items</h2>
                    </div>
                    <div class="divide-y divide-white/10">
                        <div v-for="item in order.items" :key="item.id" class="p-6 flex gap-4">
                            <!-- Product Image / QR Preview -->
                            <div class="w-24 h-24 rounded-lg bg-white/10 flex-shrink-0 overflow-hidden relative">
                                <img 
                                    v-if="item.preview_url" 
                                    :src="item.preview_url" 
                                    :alt="item.product_name"
                                    class="w-full h-full object-contain"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-500">
                                    No preview
                                </div>
                            </div>
                            
                            <!-- Item Details -->
                            <div class="flex-1">
                                <h3 class="text-white font-medium">{{ item.product_name }}</h3>
                                <p v-if="item.variant" class="text-gray-400 text-sm">{{ item.variant }}</p>
                                <div class="flex items-center gap-4 mt-2 text-sm">
                                    <span class="text-gray-500">Qty: {{ item.quantity }}</span>
                                    <span class="text-gray-500">{{ formatCurrency(item.unit_price) }} each</span>
                                </div>
                                <p v-if="item.qr_code" class="text-gray-500 text-xs mt-2">
                                    QR: {{ item.qr_code.name }} ({{ item.qr_code.code }})
                                </p>
                            </div>
                            
                            <!-- Item Total -->
                            <div class="text-right">
                                <p class="text-white font-medium">{{ formatCurrency(item.total_price) }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Totals -->
                    <div class="px-6 py-4 bg-white/5 space-y-2">
                        <div class="flex justify-between text-gray-400">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(order.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>Shipping</span>
                            <span>{{ formatCurrency(order.shipping_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>Tax</span>
                            <span>{{ formatCurrency(order.tax) }}</span>
                        </div>
                        <div class="flex justify-between text-white font-semibold text-lg pt-2 border-t border-white/10">
                            <span>Total</span>
                            <span>{{ formatCurrency(order.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Printful Status -->
                <div v-if="order.printful_order_id || printfulStatus" class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">🖨️ Printful Status</h2>
                    <div v-if="printfulStatus" class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Printful Order ID</span>
                            <span class="text-white font-mono">{{ order.printful_order_id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Status</span>
                            <span class="text-white capitalize">{{ printfulStatus.status }}</span>
                        </div>
                        <div v-if="printfulStatus.shipping" class="pt-3 border-t border-white/10">
                            <p class="text-gray-400 text-sm mb-2">Shipping Info</p>
                            <p class="text-white">{{ printfulStatus.shipping.carrier }} - {{ printfulStatus.shipping.service }}</p>
                            <a v-if="printfulStatus.shipping.tracking_url" 
                               :href="printfulStatus.shipping.tracking_url" 
                               target="_blank"
                               class="text-primary-400 hover:underline text-sm">
                                Track Package →
                            </a>
                        </div>
                    </div>
                    <div v-else-if="order.printful_order_id" class="text-gray-500">
                        <p>Order submitted to Printful</p>
                        <p class="text-xs mt-1">ID: {{ order.printful_order_id }}</p>
                    </div>
                    <div v-else class="text-yellow-400">
                        Order not yet submitted to Printful
                    </div>
                </div>

                <!-- Update Status Form -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Update Order</h2>
                    <form @submit.prevent="updateOrder" class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Status</label>
                            <select v-model="editForm.status" class="input-glass w-full">
                                <option value="pending" class="bg-gray-800 text-white">Pending</option>
                                <option value="processing" class="bg-gray-800 text-white">Processing</option>
                                <option value="shipped" class="bg-gray-800 text-white">Shipped</option>
                                <option value="delivered" class="bg-gray-800 text-white">Delivered</option>
                                <option value="cancelled" class="bg-gray-800 text-white">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Tracking Number</label>
                            <input v-model="editForm.tracking_number" type="text" class="input-glass w-full" placeholder="1Z999AA10123456784" />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Tracking URL</label>
                            <input v-model="editForm.tracking_url" type="url" class="input-glass w-full" placeholder="https://..." />
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="editForm.processing" class="btn-primary">
                                {{ editForm.processing ? 'Saving...' : 'Update Order' }}
                            </button>
                            <button 
                                v-if="order.payment_status === 'paid' && order.status !== 'cancelled'"
                                type="button" 
                                @click="refundOrder"
                                class="btn-secondary text-red-400 hover:text-red-300"
                            >
                                Refund Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Business Info -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Business</h3>
                    <div v-if="order.business">
                        <p class="text-white font-medium">{{ order.business.name }}</p>
                        <p class="text-gray-400 text-sm">{{ order.business.email }}</p>
                        <Link :href="`/admin/businesses/${order.business.id}`" class="text-primary-400 text-sm hover:underline mt-2 inline-block">
                            View Business →
                        </Link>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">📦 Shipping Address</h3>
                    <div class="text-gray-300 space-y-1">
                        <p class="font-medium">{{ order.shipping_name }}</p>
                        <p>{{ order.shipping_address_1 }}</p>
                        <p v-if="order.shipping_address_2">{{ order.shipping_address_2 }}</p>
                        <p>{{ order.shipping_city }}, {{ order.shipping_state }} {{ order.shipping_zip }}</p>
                        <p>{{ order.shipping_country }}</p>
                        <p v-if="order.shipping_phone" class="text-gray-500 text-sm mt-2">
                            📱 {{ order.shipping_phone }}
                        </p>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">💳 Payment</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Status</span>
                            <span :class="order.payment_status === 'paid' ? 'text-green-400' : 'text-yellow-400'" class="capitalize">
                                {{ order.payment_status || 'pending' }}
                            </span>
                        </div>
                        <div v-if="order.paid_at" class="flex justify-between">
                            <span class="text-gray-400">Paid At</span>
                            <span class="text-gray-300 text-sm">{{ formatDate(order.paid_at) }}</span>
                        </div>
                        <div v-if="order.refunded_at" class="flex justify-between">
                            <span class="text-gray-400">Refunded At</span>
                            <span class="text-gray-300 text-sm">{{ formatDate(order.refunded_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">📋 Timeline</h3>
                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-green-400"></div>
                            <div>
                                <p class="text-white text-sm">Order Created</p>
                                <p class="text-gray-500 text-xs">{{ formatDate(order.created_at) }}</p>
                            </div>
                        </div>
                        <div v-if="order.paid_at" class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-green-400"></div>
                            <div>
                                <p class="text-white text-sm">Payment Received</p>
                                <p class="text-gray-500 text-xs">{{ formatDate(order.paid_at) }}</p>
                            </div>
                        </div>
                        <div v-if="order.printful_order_id" class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-blue-400"></div>
                            <div>
                                <p class="text-white text-sm">Sent to Printful</p>
                                <p class="text-gray-500 text-xs">{{ order.printful_order_id }}</p>
                            </div>
                        </div>
                        <div v-if="order.status === 'shipped'" class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-purple-400"></div>
                            <div>
                                <p class="text-white text-sm">Shipped</p>
                                <p v-if="order.tracking_number" class="text-gray-500 text-xs">{{ order.tracking_number }}</p>
                            </div>
                        </div>
                        <div v-if="order.status === 'delivered'" class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-green-400"></div>
                            <div>
                                <p class="text-white text-sm">Delivered</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
