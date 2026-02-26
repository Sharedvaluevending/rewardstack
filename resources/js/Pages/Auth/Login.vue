<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    redirectTo: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
    redirect_to: props.redirectTo || null,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const submitEmployee = () => {
    form.redirect_to = '/employee/redeem';
    submit();
};

// --- PWA install CTA (Android prompt + iOS instructions) ---
const deferredPrompt = ref(null);
const showInstallHelp = ref(false);
const isStandalone = ref(false);

const isIOS = computed(() => {
    if (typeof window === 'undefined') return false;
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
});

const updateStandalone = () => {
    if (typeof window === 'undefined') return;
    // iOS uses navigator.standalone; others support display-mode media query.
    isStandalone.value =
        window.matchMedia?.('(display-mode: standalone)')?.matches === true ||
        window.navigator.standalone === true;
};

let mql;
let onBeforeInstallPrompt;
let onAppInstalled;

onMounted(() => {
    updateStandalone();

    mql = window.matchMedia?.('(display-mode: standalone)') || null;
    mql?.addEventListener?.('change', updateStandalone);

    onBeforeInstallPrompt = (e) => {
        // Store the event so we can trigger it from our button.
        // Note: we do NOT call preventDefault() to avoid Chrome's "Banner not shown" warning.
        deferredPrompt.value = e;
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);

    onAppInstalled = () => {
        deferredPrompt.value = null;
        updateStandalone();
    };
    window.addEventListener('appinstalled', onAppInstalled);
});

onBeforeUnmount(() => {
    mql?.removeEventListener?.('change', updateStandalone);
    if (onBeforeInstallPrompt) window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    if (onAppInstalled) window.removeEventListener('appinstalled', onAppInstalled);
});

const showInstallButton = computed(() => {
    // Show if not installed AND either we can prompt (Android) or we need to guide (iOS).
    return !isStandalone.value && (deferredPrompt.value !== null || isIOS.value);
});

const installLabel = computed(() => 'Add Revenue QR to Home Screen');

const handleInstall = async () => {
    if (deferredPrompt.value) {
        deferredPrompt.value.prompt();
        try {
            await deferredPrompt.value.userChoice;
        } finally {
            deferredPrompt.value = null;
        }
        return;
    }

    // iOS (or browsers without prompt API): show instructions.
    showInstallHelp.value = true;
};
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-[80vh] flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="glass-card p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <img src="/brand/logoRQ.png" alt="Revenue QR" class="w-full h-full object-contain" />
                    </div>
                    <h1 class="text-2xl font-bold text-white">Welcome Back</h1>
                    <p class="text-gray-400 mt-2">Sign in to your account</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-6">
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
                            autofocus
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

                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="w-4 h-4 rounded border-white/20 bg-white/10 text-primary-500 focus:ring-primary-500/50"
                            />
                            <span class="ml-2 text-sm text-gray-400">Remember me</span>
                        </label>
                        <Link href="/forgot-password" class="text-sm text-primary-400 hover:text-primary-300">
                            Forgot password?
                        </Link>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Signing in...</span>
                        <span v-else>Sign In</span>
                    </button>

                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="submitEmployee"
                        class="w-full py-3 rounded-xl bg-white/10 hover:bg-white/15 transition-colors border border-white/10 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Sign In as Employee
                    </button>

                    <!-- PWA Install CTA -->
                    <button
                        v-if="showInstallButton"
                        type="button"
                        @click="handleInstall"
                        class="w-full py-3 rounded-xl bg-white/10 hover:bg-white/15 transition-colors border border-white/10 text-white font-semibold"
                    >
                        {{ installLabel }}
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-gray-900 text-gray-500">New here?</span>
                    </div>
                </div>

                <!-- Register Options -->
                <div class="space-y-3">
                    <Link href="/register" class="block w-full py-3 text-center bg-gradient-to-r from-primary-500 to-accent-500 text-white font-semibold rounded-xl hover:from-primary-600 hover:to-accent-600 transition-all">
                        🏪 Register Your Business
                    </Link>
                    <Link href="/portal/join" class="block w-full py-3 text-center bg-emerald-500/10 text-emerald-400 font-medium rounded-xl hover:bg-emerald-500/20 transition-colors border border-emerald-500/20">
                        👤 Join as a Customer
                    </Link>
                </div>

                <p class="mt-4 text-center text-gray-500 text-xs">
                    Customers can track rewards, play games & earn referral income
                </p>
            </div>
        </div>
    </div>

    <!-- iOS install instructions modal -->
    <div v-if="showInstallHelp" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <button
            type="button"
            class="absolute inset-0 bg-black/70"
            @click="showInstallHelp = false"
            aria-label="Close"
        ></button>
        <div class="relative w-full max-w-md glass-card p-6 border border-white/10">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white">Add to Home Screen</h2>
                    <p class="text-sm text-gray-400 mt-1">Install the customer portal for a faster, app-like experience.</p>
                </div>
                <button
                    type="button"
                    class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 transition-colors flex items-center justify-center"
                    @click="showInstallHelp = false"
                    aria-label="Close"
                >
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <ol class="space-y-3 text-sm text-gray-200">
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">1</span>
                    <span>Open Safari’s <span class="font-semibold">Share</span> menu.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">2</span>
                    <span>Tap <span class="font-semibold">Add to Home Screen</span>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">3</span>
                    <span>Tap <span class="font-semibold">Add</span>.</span>
                </li>
            </ol>

            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors border border-white/10 text-white font-semibold"
                    @click="showInstallHelp = false"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>
</template>

