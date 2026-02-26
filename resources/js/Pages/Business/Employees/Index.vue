<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    pendingInvites: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const copiedUrl = ref(null);

const copyInviteUrl = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        copiedUrl.value = url;
        setTimeout(() => copiedUrl.value = null, 2000);
    } catch (err) {
        alert('Copy this URL: ' + url);
    }
};

const resendInvite = (invite) => {
    useForm({}).post(`/business/employees/invite/${invite.id}/resend`);
};

const cancelInvite = (invite) => {
    if (confirm('Cancel this invitation?')) {
        useForm({}).delete(`/business/employees/invite/${invite.id}`);
    }
};

const removeEmployee = (employee) => {
    if (confirm(`Remove ${employee.name} from your team?`)) {
        useForm({}).delete(`/business/employees/${employee.id}`);
    }
};
</script>

<template>
    <Head title="Employees" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Team Members</h1>
                <p class="text-gray-400 mt-1">Manage who can scan and redeem promotions</p>
            </div>
            <Link href="/business/employees/create" class="btn-primary">
                + Invite Employee
            </Link>
        </div>

        <!-- Flash Success with Invite URL -->
        <div v-if="page.props.flash?.invite_url" class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-emerald-400 font-semibold">Invitation Created!</h3>
                    <p class="text-gray-400 text-sm mt-1">Share this link with your employee:</p>
                    <code class="block mt-2 text-xs text-emerald-300 bg-black/30 p-2 rounded break-all">
                        {{ page.props.flash.invite_url }}
                    </code>
                </div>
                <button @click="copyInviteUrl(page.props.flash.invite_url)"
                    class="ml-4 px-3 py-1 bg-emerald-500 text-white text-sm rounded hover:bg-emerald-600">
                    {{ copiedUrl === page.props.flash.invite_url ? '✓ Copied!' : 'Copy' }}
                </button>
            </div>
        </div>

        <!-- Pending Invites -->
        <div v-if="pendingInvites.length" class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                <span class="text-amber-400">📧</span> Pending Invitations
            </h2>
            <div class="glass-card overflow-hidden">
                <table class="w-full">
                    <thead class="bg-amber-500/10">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-amber-300">Email</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-amber-300">Role</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-amber-300">Expires</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-amber-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <tr v-for="invite in pendingInvites" :key="invite.id" class="hover:bg-white/5">
                            <td class="px-6 py-4 text-white">{{ invite.email }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium bg-amber-500/20 text-amber-400 capitalize">
                                    {{ invite.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-sm">
                                {{ new Date(invite.expires_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button @click="copyInviteUrl(invite.url)"
                                        class="text-blue-400 hover:text-blue-300 text-sm">
                                        {{ copiedUrl === invite.url ? '✓ Copied' : 'Copy Link' }}
                                    </button>
                                    <span class="text-gray-600">·</span>
                                    <button @click="resendInvite(invite)"
                                        class="text-emerald-400 hover:text-emerald-300 text-sm">
                                        Resend
                                    </button>
                                    <span class="text-gray-600">·</span>
                                    <button @click="cancelInvite(invite)"
                                        class="text-red-400 hover:text-red-300 text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Employees -->
        <div>
            <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                <span class="text-emerald-400">👥</span> Active Team Members
            </h2>
            <div class="glass-card overflow-hidden">
                <div v-if="employees.length === 0" class="p-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-primary-500/20 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">No employees yet</h3>
                    <p class="text-gray-400 mb-6">Invite employees to let them scan and redeem promotions</p>
                    <Link href="/business/employees/create" class="btn-primary">
                        Invite Your First Employee
                    </Link>
                </div>

                <table v-else class="w-full">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Name</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Email</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Joined</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <tr v-for="employee in employees" :key="employee.id" class="hover:bg-white/5">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                        <span class="text-white font-bold">{{ employee.name?.charAt(0) || '?' }}</span>
                                    </div>
                                    <span class="text-white">{{ employee.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-400">{{ employee.email }}</td>
                            <td class="px-6 py-4 text-gray-400 text-sm">
                                {{ new Date(employee.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4">
                                <button @click="removeEmployee(employee)"
                                    class="text-red-400 hover:text-red-300 text-sm">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Help Text -->
        <div class="mt-8 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
            <h3 class="text-blue-400 font-semibold mb-2">How Employee Invites Work</h3>
            <ol class="text-gray-400 text-sm space-y-1 list-decimal list-inside">
                <li>Click "Invite Employee" and enter their email</li>
                <li>Share the invite link with them (or we'll email it)</li>
                <li>They click the link and create their password</li>
                <li>They can now log in and redeem your promotions!</li>
            </ol>
        </div>
    </div>
</template>
