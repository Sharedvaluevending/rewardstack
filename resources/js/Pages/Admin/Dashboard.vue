<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

defineProps({
    stats: Object,
    recentBusinesses: Array,
    recentScans: Array,
});

const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0);
const formatCurrency = (num) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num || 0);
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Admin Dashboard</h1>
                <p class="text-gray-400 mt-1">Platform overview and management</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Total Businesses</p>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats?.total_businesses) }}</p>
                <p class="text-green-400 text-sm mt-1">{{ stats?.active_businesses }} active</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Total Users</p>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats?.total_users) }}</p>
                <p class="text-gray-500 text-sm mt-1">{{ stats?.new_users_today }} today</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Total Scans</p>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats?.total_scans) }}</p>
                <p class="text-gray-500 text-sm mt-1">{{ stats?.scans_today }} today</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Monthly Revenue</p>
                <p class="text-3xl font-bold text-green-400">{{ formatCurrency(stats?.monthly_revenue) }}</p>
                <p class="text-gray-500 text-sm mt-1">MRR</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <Link href="/admin/businesses" class="glass-card p-6 hover:bg-white/10 transition-all">
                <div class="text-3xl mb-2">🏢</div>
                <h3 class="text-white font-medium">Businesses</h3>
                <p class="text-gray-400 text-sm">Manage all businesses</p>
            </Link>
            <Link href="/admin/users" class="glass-card p-6 hover:bg-white/10 transition-all">
                <div class="text-3xl mb-2">👥</div>
                <h3 class="text-white font-medium">Users</h3>
                <p class="text-gray-400 text-sm">Manage platform users</p>
            </Link>
            <Link href="/admin/qrcade" class="glass-card p-6 hover:bg-white/10 transition-all">
                <div class="text-3xl mb-2">🎮</div>
                <h3 class="text-white font-medium">QRcade</h3>
                <p class="text-gray-400 text-sm">Manage games & packs</p>
            </Link>
            <Link href="/admin/merch" class="glass-card p-6 hover:bg-white/10 transition-all">
                <div class="text-3xl mb-2">👕</div>
                <h3 class="text-white font-medium">Merch Store</h3>
                <p class="text-gray-400 text-sm">Products & orders</p>
            </Link>
            <Link href="/admin/referrals" class="glass-card p-6 hover:bg-white/10 transition-all border-2 border-emerald-500/30">
                <div class="text-3xl mb-2">💰</div>
                <h3 class="text-white font-medium">Referral Army</h3>
                <p class="text-gray-400 text-sm">Manage payouts</p>
            </Link>
            <Link href="/admin/analytics" class="glass-card p-6 hover:bg-white/10 transition-all">
                <div class="text-3xl mb-2">📊</div>
                <h3 class="text-white font-medium">Analytics</h3>
                <p class="text-gray-400 text-sm">Platform analytics</p>
            </Link>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Businesses -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Businesses</h3>
                <div class="space-y-3">
                    <div v-for="business in recentBusinesses" :key="business.id" class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                        <div>
                            <p class="text-white font-medium">{{ business.name }}</p>
                            <p class="text-gray-500 text-sm">{{ business.owner?.email }}</p>
                        </div>
                        <span :class="business.is_active ? 'text-green-400' : 'text-gray-500'" class="text-sm">
                            {{ business.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p v-if="!recentBusinesses?.length" class="text-gray-500 text-center py-4">No businesses yet</p>
                </div>
            </div>

            <!-- Platform Health -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Platform Health</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">API Status</span>
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-sm">Operational</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Database</span>
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-sm">Healthy</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Queue Workers</span>
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-sm">Running</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Storage</span>
                        <span class="text-gray-300">{{ stats?.storage_used || '0' }} GB / 100 GB</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

