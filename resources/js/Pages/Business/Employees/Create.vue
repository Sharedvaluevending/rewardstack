<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const form = useForm({
    email: '',
    role: 'employee',
});

const submit = () => {
    form.post('/business/employees');
};
</script>

<template>
    <Head title="Invite Employee" />

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <Link href="/business/employees" class="text-gray-400 hover:text-white flex items-center mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Team
            </Link>
            <h1 class="text-3xl font-bold text-white">Invite Employee</h1>
            <p class="text-gray-400 mt-1">Send an invite to add a team member</p>
        </div>

        <form @submit.prevent="submit" class="glass-card p-6 space-y-6">
            <!-- Info Box -->
            <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
                <h3 class="text-blue-400 font-semibold mb-2">How it works</h3>
                <p class="text-gray-400 text-sm">
                    Enter your employee's email and we'll create an invitation link. 
                    Share the link with them — they'll create their own password and be ready to go!
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Employee Email</label>
                <input 
                    v-model="form.email" 
                    type="email" 
                    class="input-glass"
                    placeholder="employee@example.com"
                    required
                    autofocus
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Role</label>
                <div class="grid grid-cols-2 gap-4">
                    <label 
                        class="flex items-center p-4 rounded-xl cursor-pointer transition-all"
                        :class="form.role === 'employee' ? 'bg-blue-500/20 border-2 border-blue-500' : 'bg-white/5 border-2 border-transparent hover:border-white/20'"
                    >
                        <input 
                            type="radio" 
                            v-model="form.role" 
                            value="employee" 
                            class="sr-only"
                        />
                        <div>
                            <div class="text-white font-medium">Staff</div>
                            <div class="text-gray-400 text-sm">Can scan & redeem promotions</div>
                        </div>
                    </label>
                    <label 
                        class="flex items-center p-4 rounded-xl cursor-pointer transition-all"
                        :class="form.role === 'manager' ? 'bg-purple-500/20 border-2 border-purple-500' : 'bg-white/5 border-2 border-transparent hover:border-white/20'"
                    >
                        <input 
                            type="radio" 
                            v-model="form.role" 
                            value="manager" 
                            class="sr-only"
                        />
                        <div>
                            <div class="text-white font-medium">Manager</div>
                            <div class="text-gray-400 text-sm">Full access + analytics</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <Link href="/business/employees" class="px-6 py-3 rounded-xl font-semibold text-white border border-white/20 hover:bg-white/10">
                    Cancel
                </Link>
                <button type="submit" :disabled="form.processing" class="btn-primary">
                    {{ form.processing ? 'Sending...' : '📧 Send Invitation' }}
                </button>
            </div>
        </form>
    </div>
</template>
