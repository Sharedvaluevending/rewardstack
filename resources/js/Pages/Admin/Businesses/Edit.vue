<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    business: Object,
});

const businessTypes = [
    'Restaurant',
    'Retail Store',
    'Salon/Spa',
    'Gym/Fitness',
    'Coffee Shop',
    'Bar/Nightclub',
    'Food Truck',
    'Bakery',
    'Auto Service',
    'Other',
];

const subscriptionTiers = [
    { value: 'starter', label: 'Starter' },
    { value: 'growth', label: 'Growth' },
    { value: 'pro', label: 'Pro' },
    { value: 'enterprise', label: 'Enterprise' },
];

const subscriptionStatuses = [
    { value: '', label: 'None' },
    { value: 'active', label: 'Active' },
    { value: 'trialing', label: 'Trialing' },
    { value: 'past_due', label: 'Past Due' },
    { value: 'canceled', label: 'Canceled' },
    { value: 'incomplete', label: 'Incomplete' },
];

const formatDateForInput = (value) => {
    if (!value) return '';
    const d = new Date(value);
    return d.toISOString().slice(0, 10);
};

const form = useForm({
    name: props.business?.name ?? '',
    slug: props.business?.slug ?? '',
    type: props.business?.type ?? '',
    description: props.business?.description ?? '',
    phone: props.business?.phone ?? '',
    email: props.business?.email ?? '',
    website: props.business?.website ?? '',
    address_line1: props.business?.address_line1 ?? '',
    address_line2: props.business?.address_line2 ?? '',
    city: props.business?.city ?? '',
    state: props.business?.state ?? '',
    postal_code: props.business?.postal_code ?? '',
    country: props.business?.country ?? 'US',
    is_active: props.business?.is_active ?? true,
    is_testing_account: props.business?.is_testing_account ?? false,
    subscription_tier: props.business?.subscription_tier ?? 'starter',
    subscription_status: props.business?.subscription_status ?? '',
    trial_ends_at: formatDateForInput(props.business?.trial_ends_at),
});

const submit = () => {
    form.put(route('admin.businesses.update', props.business.id));
};
</script>

<template>
    <Head :title="`Edit ${business?.name || 'Business'} - Admin`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/businesses" class="hover:text-white">Businesses</Link>
                    <span>/</span>
                    <Link :href="`/admin/businesses/${business?.id}`" class="hover:text-white">{{ business?.name }}</Link>
                    <span>/</span>
                    <span class="text-white">Edit</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Edit Business</h1>
                <p class="text-gray-400 mt-1">{{ business?.name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="`/admin/businesses/${business?.id}`" class="btn-secondary">← Back to Business</Link>
            </div>
        </div>

        <div class="max-w-2xl">
            <div class="glass-card p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <h2 class="text-lg font-semibold text-white mb-4">Basic Info</h2>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Business Name</label>
                        <input
                            id="name"
                            type="text"
                            v-model="form.name"
                            class="input-glass w-full"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-300 mb-2">Slug (URL)</label>
                        <input
                            id="slug"
                            type="text"
                            v-model="form.slug"
                            class="input-glass w-full"
                            placeholder="acme-coffee-shop"
                            required
                        />
                        <p v-if="form.errors.slug" class="mt-2 text-sm text-red-400">{{ form.errors.slug }}</p>
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-300 mb-2">Business Type</label>
                        <select id="type" v-model="form.type" class="input-glass w-full">
                            <option value="" class="bg-gray-800 text-white">Select...</option>
                            <option v-for="t in businessTypes" :key="t" :value="t" class="bg-gray-800 text-white">{{ t }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="input-glass w-full"
                            rows="3"
                        />
                    </div>

                    <h2 class="text-lg font-semibold text-white mt-8 mb-4">Contact</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
                            <input id="phone" type="text" v-model="form.phone" class="input-glass w-full" />
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                            <input id="email" type="email" v-model="form.email" class="input-glass w-full" />
                            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-300 mb-2">Website</label>
                        <input id="website" type="url" v-model="form.website" class="input-glass w-full" placeholder="https://" />
                        <p v-if="form.errors.website" class="mt-2 text-sm text-red-400">{{ form.errors.website }}</p>
                    </div>

                    <h2 class="text-lg font-semibold text-white mt-8 mb-4">Address</h2>

                    <div>
                        <label for="address_line1" class="block text-sm font-medium text-gray-300 mb-2">Address Line 1</label>
                        <input id="address_line1" type="text" v-model="form.address_line1" class="input-glass w-full" />
                    </div>
                    <div>
                        <label for="address_line2" class="block text-sm font-medium text-gray-300 mb-2">Address Line 2</label>
                        <input id="address_line2" type="text" v-model="form.address_line2" class="input-glass w-full" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-300 mb-2">City</label>
                            <input id="city" type="text" v-model="form.city" class="input-glass w-full" />
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-300 mb-2">State</label>
                            <input id="state" type="text" v-model="form.state" class="input-glass w-full" />
                        </div>
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-300 mb-2">Postal Code</label>
                            <input id="postal_code" type="text" v-model="form.postal_code" class="input-glass w-full" />
                        </div>
                    </div>
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                        <input id="country" type="text" v-model="form.country" class="input-glass w-full" maxlength="2" placeholder="US" />
                    </div>

                    <h2 class="text-lg font-semibold text-white mt-8 mb-4">Subscription & Status</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="subscription_tier" class="block text-sm font-medium text-gray-300 mb-2">Plan</label>
                            <select id="subscription_tier" v-model="form.subscription_tier" class="input-glass w-full" required>
                                <option v-for="t in subscriptionTiers" :key="t.value" :value="t.value" class="bg-gray-800 text-white">
                                    {{ t.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="subscription_status" class="block text-sm font-medium text-gray-300 mb-2">Subscription Status</label>
                            <select id="subscription_status" v-model="form.subscription_status" class="input-glass w-full">
                                <option v-for="s in subscriptionStatuses" :key="s.value" :value="s.value" class="bg-gray-800 text-white">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="trial_ends_at" class="block text-sm font-medium text-gray-300 mb-2">Trial Ends At</label>
                        <input
                            id="trial_ends_at"
                            type="date"
                            v-model="form.trial_ends_at"
                            class="input-glass w-full"
                        />
                        <p v-if="form.errors.trial_ends_at" class="mt-2 text-sm text-red-400">{{ form.errors.trial_ends_at }}</p>
                    </div>

                    <div class="flex items-center gap-6 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-white/20 bg-white/5 text-primary-500 focus:ring-primary-500" />
                            <span class="text-gray-300">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_testing_account" class="rounded border-white/20 bg-white/5 text-primary-500 focus:ring-primary-500" />
                            <span class="text-gray-300">Beta Testing Account</span>
                        </label>
                    </div>

                    <div class="pt-6 flex gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                        <Link :href="`/admin/businesses/${business?.id}`" class="btn-secondary">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
.btn-primary {
    @apply px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors;
}
.input-glass {
    @apply bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50;
}
</style>
