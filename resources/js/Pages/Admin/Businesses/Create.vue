<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    business_name: '',
    business_type: '',
});

const submit = () => {
    form.post(route('admin.businesses.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Add Business - Admin" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/businesses" class="hover:text-white">Businesses</Link>
                    <span>/</span>
                    <span class="text-white">Add Business</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Add Business</h1>
                <p class="text-gray-400 mt-1">Create a new business and owner account</p>
            </div>
            <Link href="/admin/businesses" class="btn-secondary">← Back to Businesses</Link>
        </div>

        <div class="max-w-xl">
            <div class="glass-card p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <h2 class="text-lg font-semibold text-white mb-4">Owner Account</h2>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Owner Name</label>
                        <input
                            id="name"
                            type="text"
                            v-model="form.name"
                            class="input-glass w-full"
                            placeholder="John Smith"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Owner Email</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            class="input-glass w-full"
                            placeholder="owner@example.com"
                            required
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            class="input-glass w-full"
                            placeholder="••••••••"
                            required
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            v-model="form.password_confirmation"
                            class="input-glass w-full"
                            placeholder="••••••••"
                            required
                        />
                    </div>

                    <h2 class="text-lg font-semibold text-white mt-8 mb-4">Business Details</h2>

                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-300 mb-2">Business Name</label>
                        <input
                            id="business_name"
                            type="text"
                            v-model="form.business_name"
                            class="input-glass w-full"
                            placeholder="Acme Coffee Shop"
                            required
                        />
                        <p v-if="form.errors.business_name" class="mt-2 text-sm text-red-400">{{ form.errors.business_name }}</p>
                    </div>

                    <div>
                        <label for="business_type" class="block text-sm font-medium text-gray-300 mb-2">Business Type</label>
                        <select
                            id="business_type"
                            v-model="form.business_type"
                            class="input-glass w-full"
                        >
                            <option value="" class="bg-gray-800 text-white">Select a type...</option>
                            <option v-for="type in businessTypes" :key="type" :value="type" class="bg-gray-800 text-white">
                                {{ type }}
                            </option>
                        </select>
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Business</span>
                        </button>
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
