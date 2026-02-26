<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    businesses: Object,
    filters: Object,
    testingAccountCount: Number,
    cancelledCount: Number,
});

const getSubscriptionStatusColor = (status) => {
    const colors = {
        'active': 'bg-emerald-500/20 text-emerald-400',
        'trialing': 'bg-blue-500/20 text-blue-400',
        'past_due': 'bg-amber-500/20 text-amber-400',
        'canceled': 'bg-red-500/20 text-red-400',
        'incomplete': 'bg-gray-500/20 text-gray-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const plan = ref(props.filters?.plan ?? '');
const subscriptionStatus = ref(props.filters?.subscription_status ?? '');

const applyFilters = () => {
    router.get('/admin/businesses', {
        search: search.value || undefined,
        status: status.value || undefined,
        plan: plan.value || undefined,
        subscription_status: subscriptionStatus.value || undefined,
    }, {
        preserveState: true,
    });
};

let searchDebounce;
watch(search, (val) => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
});

watch([status, plan, subscriptionStatus], () => {
    applyFilters();
});

const toggleStatus = (business) => {
    router.post(`/admin/businesses/${business.id}/toggle`);
};

const toggleTestingAccount = (business) => {
    router.post(`/admin/businesses/${business.id}/toggle-testing`);
};
</script>

<template>
    <Head title="Manage Businesses" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Businesses</h1>
                <p class="text-gray-400 mt-1">
                    Manage all platform businesses
                    <span v-if="testingAccountCount > 0" class="ml-2 px-2 py-1 rounded text-xs font-medium bg-accent-500/20 text-accent-400">
                        {{ testingAccountCount }} Beta Tester{{ testingAccountCount !== 1 ? 's' : '' }}
                    </span>
                    <span v-if="cancelledCount > 0" class="ml-2 px-2 py-1 rounded text-xs font-medium bg-red-500/20 text-red-400">
                        {{ cancelledCount }} Cancelled
                    </span>
                </p>
            </div>
            <Link href="/admin/businesses/create" class="btn-primary">
                + Add Business
            </Link>
        </div>

        <!-- Filters -->
        <div class="glass-card p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search businesses..."
                    class="input-glass flex-1"
                />
                <select v-model="status" class="input-glass w-40">
                    <option value="" class="bg-gray-800 text-white">All Status</option>
                    <option value="active" class="bg-gray-800 text-white">Active</option>
                    <option value="inactive" class="bg-gray-800 text-white">Inactive</option>
                </select>
                <select v-model="plan" class="input-glass w-40">
                    <option value="" class="bg-gray-800 text-white">All Plans</option>
                    <option value="starter" class="bg-gray-800 text-white">Starter</option>
                    <option value="growth" class="bg-gray-800 text-white">Growth</option>
                    <option value="pro" class="bg-gray-800 text-white">Pro</option>
                    <option value="enterprise" class="bg-gray-800 text-white">Enterprise</option>
                </select>
                <select v-model="subscriptionStatus" class="input-glass w-48">
                    <option value="" class="bg-gray-800 text-white">All Subscriptions</option>
                    <option value="active" class="bg-gray-800 text-white">Active</option>
                    <option value="trialing" class="bg-gray-800 text-white">Trialing</option>
                    <option value="past_due" class="bg-gray-800 text-white">Past Due</option>
                    <option value="canceled" class="bg-gray-800 text-white">Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Businesses Table -->
        <div class="glass-card overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Business</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Owner</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Plan</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Subscription</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">QR Codes</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Status</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="business in businesses.data" :key="business.id" class="hover:bg-white/5">
                        <td class="px-6 py-4">
                            <div>
                                <p class="text-white font-medium">{{ business.name }}</p>
                                <p class="text-gray-500 text-sm">{{ business.slug }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-gray-300">{{ business.owner?.name }}</p>
                            <p class="text-gray-500 text-sm">{{ business.owner?.email }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1">
                                <span class="px-2 py-1 rounded text-xs font-medium bg-primary-500/20 text-primary-400">
                                    {{ business.subscription_plan || 'Free' }}
                                </span>
                                <span v-if="business.is_testing_account" class="px-2 py-1 rounded text-xs font-medium bg-accent-500/20 text-accent-400">
                                    🧪 Beta Tester
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1">
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getSubscriptionStatusColor(business.subscription_status)]">
                                    {{ business.subscription_status || 'none' }}
                                </span>
                                <span v-if="business.subscription_cancel_at_period_end" class="px-2 py-1 rounded text-xs font-medium bg-amber-500/20 text-amber-400">
                                    Cancelling
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-300">{{ business.qr_codes_count || 0 }}</td>
                        <td class="px-6 py-4">
                            <button @click="toggleStatus(business)" :class="business.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'" class="px-2 py-1 rounded text-xs font-medium">
                                {{ business.is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                <div class="flex space-x-2">
                                    <Link :href="`/admin/businesses/${business.id}`" class="text-primary-400 hover:text-primary-300 text-sm">View</Link>
                                    <Link :href="`/admin/businesses/${business.id}/edit`" class="text-gray-400 hover:text-white text-sm">Edit</Link>
                                </div>
                                <button 
                                    @click="toggleTestingAccount(business)" 
                                    :class="business.is_testing_account ? 'bg-accent-500/20 text-accent-400 hover:bg-accent-500/30' : 'bg-gray-500/20 text-gray-400 hover:bg-gray-500/30'" 
                                    class="px-2 py-1 rounded text-xs font-medium transition-colors"
                                    title="Toggle beta testing account status"
                                >
                                    {{ business.is_testing_account ? '✓ Beta Tester' : 'Mark as Beta' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div v-if="!businesses.data?.length" class="p-12 text-center">
                <p class="text-gray-500">No businesses found</p>
            </div>
        </div>
    </div>
</template>

