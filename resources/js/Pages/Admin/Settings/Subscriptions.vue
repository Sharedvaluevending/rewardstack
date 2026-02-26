<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

defineProps({
    plans: Array,
});
</script>

<template>
    <Head title="Subscription Plans" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Subscription Plans</h1>
                <p class="text-gray-400 mt-1">View Stripe plan mappings & availability (read-only)</p>
            </div>
            <Link href="/admin/settings" class="btn-primary">
                Back to Settings
            </Link>
        </div>

        <div class="glass-card overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Plan</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Price</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Stripe</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="plan in plans" :key="plan.id" class="hover:bg-white/5">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <p class="text-white font-medium">{{ plan.name }}</p>
                                <span v-if="plan.is_featured" class="px-2 py-0.5 rounded text-xs font-medium bg-primary-500/20 text-primary-300">Featured</span>
                            </div>
                            <p class="text-gray-500 text-sm">{{ plan.slug }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-200">
                            <div class="text-sm">
                                <div>Monthly: <span class="text-gray-100">${{ plan.monthly_price ?? '—' }}</span></div>
                                <div>Yearly: <span class="text-gray-100">${{ plan.yearly_price ?? '—' }}</span></div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-400 space-y-1">
                                <div>Monthly: <span class="text-gray-300">{{ plan.stripe_monthly_price_id || '—' }}</span></div>
                                <div>Yearly: <span class="text-gray-300">{{ plan.stripe_yearly_price_id || '—' }}</span></div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="plan.is_active ? 'bg-green-500/20 text-green-300' : 'bg-gray-500/20 text-gray-300'"
                                class="px-2 py-1 rounded text-xs font-medium"
                            >
                                {{ plan.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!plans?.length" class="p-12 text-center">
                <p class="text-gray-500">No plans found</p>
            </div>
        </div>
    </div>
</template>
