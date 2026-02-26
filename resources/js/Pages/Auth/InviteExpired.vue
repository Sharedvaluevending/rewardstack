<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});
</script>

<template>
    <Head title="Invitation Expired" />

    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md text-center">
            <div class="glass-card p-8">
                <!-- Icon -->
                <div class="w-20 h-20 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-white mb-2">Invitation Expired</h1>
                <p class="text-gray-400 mb-6">
                    This invitation link has expired or has already been used. 
                    Please contact your employer to send a new invitation.
                </p>

                <div class="space-y-3">
                    <Link href="/login" class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-colors">
                        Go to Login
                    </Link>
                    <Link href="/" class="block w-full py-3 text-gray-400 hover:text-white transition-colors">
                        Back to Home
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
