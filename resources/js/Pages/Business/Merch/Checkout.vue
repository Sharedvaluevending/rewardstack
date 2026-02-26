<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    order: Object,
    clientSecret: String, // Stripe client secret for payment
});

const resolveCurrency = () => {
    const c = String(props.order?.shipping_country || '').toUpperCase();
    return c === 'US' ? 'USD' : 'CAD';
};

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: resolveCurrency(),
    }).format(amount || 0);
};

const loading = ref(false);
const error = ref(null);

// (Stripe Checkout handles payment details; no card fields needed here.)

// Process payment (simplified - in production use Stripe Elements)
const processPayment = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await fetch(`/business/merch/checkout/${props.order.id}/pay`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                // We redirect to Stripe Checkout; no card data is collected in-app.
            }),
        });
        
        const data = await response.json();
        
        if (response.ok) {
            const url = data?.checkout_url;
            if (!url) {
                throw new Error('Missing checkout URL');
            }
            window.location.href = url;
        } else {
            error.value = data.message || 'Payment failed. Please try again.';
        }
    } catch (e) {
        console.error('Payment error:', e);
        error.value = 'An error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};

// Get category icon
const getCategoryIcon = (category) => {
    const icons = {
        't-shirt': 'M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92',
        'mug': 'M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z',
        'sticker': 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4z',
        'poster': 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14',
    };
    return icons[category] || icons['poster'];
};
</script>

<template>
    <Head title="Checkout" />

    <div class="max-w-4xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/merch" class="inline-flex items-center text-gray-400 hover:text-white mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Store
            </Link>
            <h1 class="text-3xl font-bold text-white">Checkout</h1>
            <p class="text-gray-400 mt-1">Complete your order #{{ order.order_number }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: Order Summary -->
            <div class="space-y-6">
                <!-- Order Items -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Order Items</h2>
                    <div class="space-y-4">
                        <div 
                            v-for="item in order.items" 
                            :key="item.id"
                            class="flex items-center space-x-4"
                        >
                            <!-- Product Image/Icon -->
                            <div class="w-16 h-16 rounded-lg bg-gray-700 flex-shrink-0 flex items-center justify-center">
                                <div v-if="item.preview_url" class="w-full h-full rounded-lg overflow-hidden">
                                    <img :src="item.preview_url" alt="Preview" class="w-full h-full object-contain bg-white" />
                                </div>
                                <svg v-else class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            
                            <!-- Details -->
                            <div class="flex-1 min-w-0">
                                <p class="text-white font-medium">{{ item.product_name }}</p>
                                <p class="text-gray-400 text-sm">
                                    {{ item.variant || 'Standard' }} × {{ item.quantity }}
                                </p>
                                <p v-if="item.qr_code" class="text-gray-500 text-xs mt-1">
                                    QR: {{ item.qr_code.name }}
                                </p>
                            </div>
                            
                            <!-- Price -->
                            <p class="text-white font-medium">{{ formatCurrency(item.total_price) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Shipping To</h2>
                    <div class="text-gray-300">
                        <p class="font-medium text-white">{{ order.shipping_name }}</p>
                        <p>{{ order.shipping_address_1 }}</p>
                        <p v-if="order.shipping_address_2">{{ order.shipping_address_2 }}</p>
                        <p>{{ order.shipping_city }}, {{ order.shipping_state }} {{ order.shipping_zip }}</p>
                        <p>{{ order.shipping_country }}</p>
                        <p v-if="order.shipping_phone" class="mt-2 text-gray-400">{{ order.shipping_phone }}</p>
                    </div>
                </div>

                <!-- Order Totals -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Order Total</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Subtotal</span>
                            <span class="text-white">{{ formatCurrency(order.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Shipping</span>
                            <span class="text-white">{{ formatCurrency(order.shipping_cost) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tax</span>
                            <span class="text-white">{{ formatCurrency(order.tax) }}</span>
                        </div>
                        <div class="flex justify-between text-xl font-bold pt-3 border-t border-white/10">
                            <span class="text-white">Total</span>
                            <span class="gradient-text">{{ formatCurrency(order.total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Payment Form -->
            <div>
                <div class="glass-card p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-white mb-6">Payment Details</h2>
                    
                    <!-- Error Message -->
                    <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                        <p class="text-red-400 text-sm">{{ error }}</p>
                    </div>

                    <form @submit.prevent="processPayment" class="space-y-4">
                        <button 
                            type="submit"
                            :disabled="loading"
                            class="w-full btn-primary text-lg py-4 mt-6 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="loading" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                            <span v-else>Continue to Stripe Checkout · {{ formatCurrency(order.total) }}</span>
                        </button>

                        <!-- Security Note -->
                        <div class="flex items-center justify-center space-x-2 mt-4 text-gray-500 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Secured by Stripe</span>
                        </div>
                    </form>

                    <!-- What Happens Next -->
                    <div class="mt-8 pt-6 border-t border-white/10">
                        <h3 class="text-white font-medium mb-4">What happens next?</h3>
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-primary-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <span class="text-primary-400 text-xs font-bold">1</span>
                                </div>
                                <div>
                                    <p class="text-white text-sm font-medium">Order Confirmed</p>
                                    <p class="text-gray-500 text-xs">Your QR code will be prepared for printing</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-primary-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <span class="text-primary-400 text-xs font-bold">2</span>
                                </div>
                                <div>
                                    <p class="text-white text-sm font-medium">Production</p>
                                    <p class="text-gray-500 text-xs">Items printed by Printful (2-5 business days)</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-primary-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <span class="text-primary-400 text-xs font-bold">3</span>
                                </div>
                                <div>
                                    <p class="text-white text-sm font-medium">Shipped</p>
                                    <p class="text-gray-500 text-xs">Tracking info sent via email</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
