<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    invite: Object,
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(`/employee/accept/${props.invite.token}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Accept Employee Invitation" />

    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="glass-card p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <!-- Business Logo -->
                    <div v-if="invite.business_logo" class="w-20 h-20 rounded-2xl bg-white p-2 mx-auto mb-4">
                        <img :src="invite.business_logo" :alt="invite.business_name" class="w-full h-full object-contain" />
                    </div>
                    <div v-else class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center mx-auto mb-4">
                        <span class="text-3xl font-bold text-white">{{ invite.business_name.charAt(0) }}</span>
                    </div>

                    <h1 class="text-2xl font-bold text-white">You're Invited!</h1>
                    <p class="text-gray-400 mt-2">
                        <span class="text-white font-medium">{{ invite.business_name }}</span> has invited you to join as an employee
                    </p>
                </div>

                <!-- Info Box -->
                <div class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
                    <div class="text-sm space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Email:</span>
                            <span class="text-white">{{ invite.email }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Role:</span>
                            <span class="text-blue-400 font-medium capitalize">{{ invite.role }}</span>
                        </div>
                    </div>
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

                    <!-- Email (Readonly) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Email Address
                        </label>
                        <input
                            type="email"
                            :value="invite.email"
                            class="input-glass opacity-60"
                            readonly
                        />
                        <p class="text-xs text-gray-500 mt-1">This email was specified in your invitation</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Create Password
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

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-indigo-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Setting up your account...</span>
                        <span v-else>Accept Invitation & Join</span>
                    </button>
                </form>

                <p class="mt-6 text-center text-gray-500 text-sm">
                    After joining, you'll be able to scan and redeem customer promotions for {{ invite.business_name }}.
                </p>
            </div>
        </div>
    </div>
</template>
