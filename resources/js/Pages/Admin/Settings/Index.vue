<script setup>
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

defineProps({
    system: Object,
});

const page = usePage();
const status = page.props.flash?.status;

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    passwordForm.put('/account/password', {
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Head title="Admin Settings" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Settings</h1>
                <p class="text-gray-400 mt-1">Platform configuration & system info</p>
            </div>
            <Link href="/admin/settings/subscriptions" class="btn-primary">
                Subscription Plans
            </Link>
        </div>

        <div v-if="status" class="glass-card p-4 mb-6 border border-emerald-500/30">
            <p class="text-emerald-200 text-sm">{{ status }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">System</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Environment</span>
                        <span class="text-gray-200">{{ system?.app_env }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">App URL</span>
                        <span class="text-gray-200">{{ system?.app_url }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Version</span>
                        <span class="text-gray-200">{{ system?.app_version || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Cache</span>
                        <span class="text-gray-200">{{ system?.cache }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Queue</span>
                        <span class="text-gray-200">{{ system?.queue }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Session</span>
                        <span class="text-gray-200">{{ system?.session }}</span>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-2">Next enterprise toggles</h2>
                <p class="text-gray-400 text-sm mb-4">These are the last production hardening steps we typically set.</p>

                <div class="space-y-3">
                    <div class="p-3 rounded-lg bg-white/5">
                        <p class="text-white font-medium">Sentry DSN</p>
                        <p class="text-gray-400 text-sm">Set <code class="text-gray-300">SENTRY_LARAVEL_DSN</code> to enable error monitoring.</p>
                    </div>
                    <div class="p-3 rounded-lg bg-white/5">
                        <p class="text-white font-medium">APP_VERSION</p>
                        <p class="text-gray-400 text-sm">Set <code class="text-gray-300">APP_VERSION</code> (commit SHA or release id) to show in <code class="text-gray-300">/health</code>.</p>
                    </div>
                    <div class="p-3 rounded-lg bg-white/5">
                        <p class="text-white font-medium">Config/route caches on deploy</p>
                        <p class="text-gray-400 text-sm">Enable <code class="text-gray-300">php artisan config:cache</code> + <code class="text-gray-300">route:cache</code> in your deploy script.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-6 mt-6">
            <h2 class="text-lg font-semibold text-white mb-4">🔒 Change Password</h2>
            <div class="grid gap-4 max-w-lg">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Current Password</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">New Password</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Confirm New Password</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">Minimum 8 characters.</p>
                    <button
                        @click="updatePassword"
                        :disabled="passwordForm.processing"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white text-sm hover:bg-white/20 disabled:opacity-50"
                    >
                        Update Password
                    </button>
                </div>
                <div v-if="passwordForm.errors?.current_password" class="text-xs text-red-400">
                    {{ passwordForm.errors.current_password }}
                </div>
                <div v-if="passwordForm.errors?.password" class="text-xs text-red-400">
                    {{ passwordForm.errors.password }}
                </div>
            </div>
        </div>
    </div>
</template>
