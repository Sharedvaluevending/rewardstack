<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    from: String,
    redirectTo: String,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    redirect_to: props.redirectTo || null,
    from: props.from || null,
});

const submit = () => {
    form.post('/portal/join', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Join Revenue QR" />

    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="glass-card p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mx-auto mb-4">
                        <img src="/brand/logoRQ.png" alt="Revenue QR" class="w-12 h-12 object-contain" />
                    </div>
                    <h1 class="text-2xl font-bold text-white">Join Revenue QR</h1>
                    <p class="text-gray-400 mt-2">Track your rewards, play games & earn referral income</p>
                </div>

                <!-- Benefits -->
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <div class="text-sm space-y-2">
                        <div class="flex items-center text-gray-300">
                            <span class="text-emerald-400 mr-2">✓</span>
                            Track all your scanned promotions
                        </div>
                        <div class="flex items-center text-gray-300">
                            <span class="text-emerald-400 mr-2">✓</span>
                            Play games & compete on leaderboards
                        </div>
                        <div class="flex items-center text-gray-300">
                            <span class="text-emerald-400 mr-2">✓</span>
                            Earn money with referral program
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    <input type="hidden" v-model="form.from" />
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
                        <Link href="/terms" class="text-primary-400 hover:underline">Terms of Service</Link>
                        and
                        <Link href="/privacy" class="text-primary-400 hover:underline">Privacy Policy</Link>.
                    </p>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-green-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Creating account...</span>
                        <span v-else>Create Free Account</span>
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
                    <Link href="/register" class="block w-full py-3 text-center bg-purple-500/10 text-purple-400 font-medium rounded-xl hover:bg-purple-500/20 transition-colors border border-purple-500/20">
                        🏪 Register a Business Instead
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
