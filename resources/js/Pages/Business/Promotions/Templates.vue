<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
});

const getDiscountTypeIcon = (type) => {
    const icons = {
        percentage: '🏷️',
        fixed_amount: '💵',
        bogo: '🎁',
        buy_x_get_y: '🛍️',
        punch_card: '⭐',
        happy_hour: '🕐',
        first_time: '👋',
        tiered: '📈',
        free_item: '🎉',
    };
    return icons[type] || '🏷️';
};

const formatDiscount = (template) => {
    if (template.discount_type === 'percentage') {
        return `${template.discount_value}% off`;
    } else if (template.discount_type === 'fixed_amount') {
        return `$${template.discount_value} off`;
    } else if (template.discount_type === 'bogo') {
        return 'Buy One Get One';
    } else if (template.discount_type === 'buy_x_get_y') {
        return `Buy ${template.buy_quantity} Get ${template.get_quantity}`;
    } else if (template.discount_type === 'punch_card') {
        return `${template.punches_required} punches`;
    } else if (template.discount_type === 'happy_hour') {
        return `${template.discount_value}% off`;
    } else if (template.discount_type === 'first_time') {
        return `${template.discount_value}% off`;
    } else if (template.discount_type === 'tiered') {
        return 'Tiered Discount';
    }
    return 'Special Offer';
};

const useTemplate = (template) => {
    // Navigate to create page with template data
    const params = new URLSearchParams();
    params.set('template', JSON.stringify(template));
    router.get(`/business/promotions/create?${params.toString()}`);
};
</script>

<template>
    <Head title="Promotion Templates" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Promotion Templates</h1>
                <p class="text-gray-400 mt-1">Start with a proven template and customize it for your business</p>
            </div>
            <Link href="/business/promotions" class="btn-glass">
                ← Back to Promotions
            </Link>
        </div>

        <!-- Templates Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="(template, index) in templates"
                :key="index"
                class="glass-card p-6 hover:bg-white/5 transition-all group cursor-pointer"
                @click="useTemplate(template)"
            >
                <div class="flex items-start justify-between mb-4">
                    <span class="text-3xl">{{ getDiscountTypeIcon(template.discount_type) }}</span>
                    <span class="px-2 py-1 rounded text-xs font-medium bg-primary-500/20 text-primary-400">
                        {{ template.discount_type?.replace(/_/g, ' ') }}
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-white group-hover:text-primary-400 transition-colors mb-2">
                    {{ template.name }}
                </h3>

                <p class="text-gray-400 text-sm mb-4">
                    {{ template.description }}
                </p>

                <div class="bg-gradient-to-r from-primary-500/20 to-accent-500/20 rounded-lg p-3 mb-4">
                    <span class="text-lg font-bold gradient-text">{{ formatDiscount(template) }}</span>
                </div>

                <button class="w-full py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-primary-500 hover:text-white transition-all text-sm font-medium">
                    Use This Template
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!templates?.length" class="glass-card p-12 text-center">
            <div class="w-20 h-20 rounded-full bg-white/10 mx-auto flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">No Templates Available</h3>
            <p class="text-gray-400 mb-6">Templates are coming soon. Create a custom promotion instead.</p>
            <Link href="/business/promotions/create" class="btn-primary">
                Create Custom Promotion
            </Link>
        </div>
    </div>
</template>
