<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    promotion: Object,
    recentRedemptions: Array,
});

const formatDiscount = (promo) => {
    if (promo.discount_type === 'percentage') {
        return `${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'fixed_amount' || promo.discount_type === 'fixed') {
        return `$${promo.discount_value} off`;
    } else if (promo.discount_type === 'bogo') {
        return 'Buy One Get One';
    } else if (promo.discount_type === 'buy_x_get_y') {
        return `Buy ${promo.buy_quantity} Get ${promo.get_quantity}`;
    } else if (promo.discount_type === 'punch_card') {
        return `Punch Card (${promo.punches_required} punches)`;
    } else if (promo.discount_type === 'happy_hour') {
        return `Happy Hour ${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'first_time') {
        return `First Visit ${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'tiered') {
        return 'Tiered Discount';
    } else if (promo.discount_type === 'free_item') {
        return 'Free Item';
    }
    return promo.discount_type?.replace(/_/g, ' ') || 'Special Offer';
};

const getDiscountTypeIcon = (type) => {
    const icons = {
        percentage: '🏷️',
        fixed_amount: '💵',
        fixed: '💵',
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

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const deletePromotion = () => {
    if (confirm(`Are you sure you want to delete "${props.promotion.name}"?\n\nThis action cannot be undone.`)) {
        router.delete(route('business.promotions.destroy', props.promotion.id));
    }
};

const toggleActive = () => {
    router.post(route('business.promotions.toggle', props.promotion.id));
};
</script>

<template>
    <Head :title="`${promotion.name} - Promotion`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/promotions" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                ← Back to Promotions
            </Link>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">{{ getDiscountTypeIcon(promotion.discount_type) }}</span>
                        <h1 class="text-3xl font-bold text-white">{{ promotion.name }}</h1>
                        <span :class="[
                            'px-3 py-1 rounded-full text-sm font-medium',
                            promotion.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'
                        ]">
                            {{ promotion.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-gray-400 mt-1">{{ promotion.description || 'No description provided' }}</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <button
                        @click="toggleActive"
                        :class="[
                            'px-4 py-2 rounded-lg transition-colors',
                            promotion.is_active 
                                ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30' 
                                : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                        ]"
                    >
                        {{ promotion.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <Link
                        :href="route('business.promotions.edit', promotion.id)"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                    >
                        Edit
                    </Link>
                    <button
                        @click="deletePromotion"
                        class="px-4 py-2 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/30 transition-colors"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Discount Preview -->
                <div class="glass-card p-8">
                    <h2 class="text-xl font-semibold text-white mb-4">Promotion Details</h2>
                    <div class="bg-gradient-to-r from-primary-500/20 to-accent-500/20 rounded-lg p-6 mb-6">
                        <p class="text-gray-400 text-sm mb-2">Discount</p>
                        <p class="text-4xl font-bold gradient-text">{{ formatDiscount(promotion) }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/5 rounded-lg p-4">
                            <p class="text-gray-400 text-sm mb-1">Redemptions</p>
                            <p class="text-2xl font-bold text-white">{{ promotion.redemptions_count || 0 }}</p>
                        </div>
                        <div class="bg-white/5 rounded-lg p-4">
                            <p class="text-gray-400 text-sm mb-1">QR Codes</p>
                            <p class="text-2xl font-bold text-white">{{ promotion.qr_codes_count || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Rules & Restrictions -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Rules & Restrictions</h3>
                    <div class="space-y-3 text-sm">
                        <div v-if="promotion.rules?.max_redemptions_total" class="flex justify-between">
                            <span class="text-gray-400">Max Total Redemptions:</span>
                            <span class="text-white">{{ promotion.rules.max_redemptions_total }}</span>
                        </div>
                        <div v-if="promotion.rules?.max_redemptions_per_user" class="flex justify-between">
                            <span class="text-gray-400">Max Per User:</span>
                            <span class="text-white">{{ promotion.rules.max_redemptions_per_user }}</span>
                        </div>
                        <div v-if="promotion.rules?.valid_days?.length" class="flex justify-between">
                            <span class="text-gray-400">Valid Days:</span>
                            <span class="text-white">{{ promotion.rules.valid_days.join(', ') }}</span>
                        </div>
                        <div v-if="promotion.rules?.valid_hours" class="flex justify-between">
                            <span class="text-gray-400">Valid Hours:</span>
                            <span class="text-white">
                                {{ promotion.rules.valid_hours.start }} - {{ promotion.rules.valid_hours.end }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Stackable:</span>
                            <span class="text-white">{{ promotion.is_stackable ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Dates -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Schedule</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Starts:</span>
                            <span class="text-white">{{ formatDate(promotion.starts_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ends:</span>
                            <span class="text-white">{{ formatDate(promotion.ends_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Created:</span>
                            <span class="text-white">{{ formatDate(promotion.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div v-if="promotion.terms" class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Terms & Conditions</h3>
                    <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ promotion.terms }}</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- QR Codes -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">QR Codes</h3>
                    <div v-if="promotion.qr_codes?.length" class="space-y-2">
                        <Link
                            v-for="qr in promotion.qr_codes"
                            :key="qr.id"
                            :href="route('business.qr-codes.show', qr.id)"
                            class="block p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors"
                        >
                            <p class="text-white font-medium text-sm">{{ qr.name }}</p>
                            <p class="text-gray-500 text-xs">{{ qr.code }}</p>
                        </Link>
                    </div>
                    <p v-else class="text-gray-500 text-sm text-center py-4">
                        No QR codes linked yet
                    </p>
                    <Link
                        :href="route('business.qr-codes.create', { promotion_id: promotion.id })"
                        class="mt-4 block w-full text-center px-4 py-2 rounded-lg bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 transition-colors text-sm"
                    >
                        Create QR Code →
                    </Link>
                </div>

                <!-- Recent Redemptions -->
                <div v-if="recentRedemptions?.length" class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Recent Redemptions</h3>
                    <div class="space-y-3">
                        <div
                            v-for="redemption in recentRedemptions"
                            :key="redemption.id"
                            class="p-3 rounded-lg bg-white/5"
                        >
                            <p class="text-white text-sm font-medium">
                                {{ redemption.employee?.user?.name || 'Employee' }}
                            </p>
                            <p class="text-gray-500 text-xs mt-1">
                                {{ formatDate(redemption.redeemed_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
