<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    referralCode: { type: String, default: null },
    referrerName: { type: String, default: null },
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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    business_name: '',
    business_type: '',
    referral_code: props.referralCode || '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Start Free Trial" />

    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="glass-card p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-white">Start Your Free Trial</h1>
                    <p class="text-gray-400 mt-2">14 days free, no credit card required</p>
                </div>

                <!-- Referral Banner -->
                <div v-if="referrerName" class="mb-6 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-center">
                    <p class="text-sm text-emerald-300">Referred by <strong>{{ referrerName }}</strong></p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                            Your Name
                        </label>
                        <input
                            id="name"
                            type="text"
                            v-model="form.name"
                            class="input-glass"
                            placeholder="John Smith"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                            Email Address
                        </label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            class="input-glass"
                            placeholder="you@example.com"
                            required
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Business Name -->
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-300 mb-2">
                            Business Name
                        </label>
                        <input
                            id="business_name"
                            type="text"
                            v-model="form.business_name"
                            class="input-glass"
                            placeholder="Acme Coffee Shop"
                            required
                        />
                        <p v-if="form.errors.business_name" class="mt-2 text-sm text-red-400">
                            {{ form.errors.business_name }}
                        </p>
                    </div>

                    <!-- Business Type -->
                    <div>
                        <label for="business_type" class="block text-sm font-medium text-gray-300 mb-2">
                            Business Type
                        </label>
                        <select
                            id="business_type"
                            v-model="form.business_type"
                            class="input-glass"
                        >
                            <option value="" class="bg-gray-800 text-white">Select a type...</option>
                            <option v-for="type in businessTypes" :key="type" :value="type" class="bg-gray-800 text-white">
                                {{ type }}
                            </option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            class="input-glass"
                            placeholder="••••••••"
                            required
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">
                            Confirm Password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            v-model="form.password_confirmation"
                            class="input-glass"
                            placeholder="••••••••"
                            required
                        />
                    </div>

                    <!-- Terms -->
                    <p class="text-xs text-gray-500">
                        By creating an account, you agree to our
                        <a href="#" class="text-primary-400 hover:underline">Terms of Service</a>
                        and
                        <a href="#" class="text-primary-400 hover:underline">Privacy Policy</a>.
                    </p>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full btn-accent disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Creating account...</span>
                        <span v-else>Start Free Trial</span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-gray-900 text-gray-500">or</span>
                    </div>
                </div>

                <!-- Other Options -->
                <div class="space-y-3">
                    <Link href="/login" class="block w-full py-3 text-center bg-white/5 text-white font-medium rounded-xl hover:bg-white/10 transition-colors border border-white/10">
                        Already have an account? Sign in
                    </Link>
                    <Link href="/portal/join" class="block w-full py-3 text-center bg-emerald-500/10 text-emerald-400 font-medium rounded-xl hover:bg-emerald-500/20 transition-colors border border-emerald-500/20">
                        👤 Join as a Customer Instead
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

