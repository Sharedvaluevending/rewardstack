<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    productsByCategory: Object,
});

const categoryLabel = (key) => {
    const map = {
        't-shirt': 'T-Shirts',
        hoodie: 'Hoodies',
        mug: 'Mugs',
        sticker: 'Stickers',
        poster: 'Posters',
        bag: 'Bags',
        hat: 'Hats',
        phone_case: 'Phone Cases',
        other: 'Other',
    };
    return map[key] || key;
};

const formatCurrency = (amount) => {
    const n = Number(amount || 0);
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
};
</script>

<template>
    <Head title="Merch Products" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Product Catalog</h1>
                <p class="text-gray-400 mt-1">Browse available products by category</p>
            </div>
            <div class="mt-4 md:mt-0">
                <Link href="/business/merch" class="btn-primary">
                    Back to Store
                </Link>
            </div>
        </div>

        <div v-if="productsByCategory && Object.keys(productsByCategory).length" class="space-y-6">
            <div
                v-for="(products, category) in productsByCategory"
                :key="category"
                class="glass-card overflow-hidden"
            >
                <div class="px-6 py-4 border-b border-white/10">
                    <h2 class="text-lg font-semibold text-white">{{ categoryLabel(category) }}</h2>
                    <p class="text-gray-400 text-sm">{{ products?.length || 0 }} item(s)</p>
                </div>

                <div class="divide-y divide-white/10">
                    <div
                        v-for="p in products"
                        :key="p.id"
                        class="px-6 py-4 flex items-center justify-between hover:bg-white/5 transition-colors"
                    >
                        <div class="min-w-0">
                            <p class="text-white font-medium truncate">{{ p.name }}</p>
                            <p class="text-gray-400 text-sm truncate">
                                {{ p.slug }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-white font-semibold">{{ formatCurrency(p.base_price) }}</p>
                                <p class="text-gray-400 text-xs">
                                    Printful: {{ p.printful_product_id || '—' }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'px-3 py-1 rounded-full text-xs font-medium border capitalize',
                                    p.is_active
                                        ? 'text-green-400 bg-green-400/20 border-green-400/30'
                                        : 'text-gray-400 bg-gray-400/10 border-gray-400/20',
                                ]"
                            >
                                {{ p.is_active ? 'active' : 'inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass-card p-8 text-center">
            <p class="text-gray-400">No products available.</p>
        </div>
    </div>
</template>

