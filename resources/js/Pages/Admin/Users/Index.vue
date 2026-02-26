<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const role = ref(props.filters?.role ?? '');

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value || undefined,
        role: role.value || undefined,
    }, {
        preserveState: true,
    });
};

let searchDebounce;
watch(search, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
});

watch(role, () => {
    applyFilters();
});

const toggleStatus = (user) => {
    router.post(`/admin/users/${user.id}/toggle`);
};
</script>

<template>
    <Head title="Manage Users" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Users</h1>
                <p class="text-gray-400 mt-1">Manage platform users</p>
            </div>
            <Link href="/admin/users/create" class="btn-primary">
                + Add User
            </Link>
        </div>

        <!-- Filters -->
        <div class="glass-card p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search users..."
                    class="input-glass flex-1"
                />
                <select v-model="role" class="input-glass w-40">
                    <option value="" class="bg-gray-800 text-white">All Roles</option>
                    <option value="admin" class="bg-gray-800 text-white">Admin</option>
                    <option value="business" class="bg-gray-800 text-white">Business</option>
                    <option value="employee" class="bg-gray-800 text-white">Employee</option>
                    <option value="customer" class="bg-gray-800 text-white">Customer</option>
                    <option value="user" class="bg-gray-800 text-white">User</option>
                </select>
            </div>
        </div>

        <!-- Users Table -->
        <div class="glass-card overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">User</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Role</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Business</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Games Played</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Joined</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-white/5">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold mr-3">
                                    {{ user.name?.charAt(0) }}
                                </div>
                                <div>
                                    <p class="text-white font-medium">{{ user.name }}</p>
                                    <p class="text-gray-500 text-sm">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="{
                                'bg-red-500/20 text-red-400': user.role === 'admin',
                                'bg-blue-500/20 text-blue-400': user.role === 'business',
                                'bg-purple-500/20 text-purple-400': user.role === 'employee',
                                'bg-emerald-500/20 text-emerald-400': user.role === 'customer',
                                'bg-gray-500/20 text-gray-400': user.role === 'user',
                            }" class="px-2 py-1 rounded text-xs font-medium capitalize">
                                {{ user.role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-300">{{ user.business?.name || '-' }}</td>
                        <td class="px-6 py-4 text-gray-300">{{ user.total_games_played || 0 }}</td>
                        <td class="px-6 py-4 text-gray-500 text-sm">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                        <td class="px-6 py-4">
                            <div class="flex space-x-2">
                                <Link :href="`/admin/users/${user.id}`" class="text-primary-400 hover:text-primary-300 text-sm">View</Link>
                                <Link :href="`/admin/users/${user.id}/edit`" class="text-gray-400 hover:text-white text-sm">Edit</Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div v-if="!users.data?.length" class="p-12 text-center">
                <p class="text-gray-500">No users found</p>
            </div>
        </div>
    </div>
</template>

