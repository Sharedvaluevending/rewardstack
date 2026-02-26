<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    customers: Object, // paginator
});
</script>

<template>
    <Head title="CRM Customers" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Customers</h1>
                <p class="text-gray-400 text-sm mt-1">Subscribed customers and engagement signals.</p>
            </div>
        </div>

        <div class="glass-card overflow-hidden">
            <div class="p-5 border-b border-white/10">
                <div class="text-white font-semibold">Subscribed Audience</div>
                <div class="text-xs text-gray-400 mt-1">Only explicit opt-in subscribers are listed here.</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5">
                        <tr class="text-left text-gray-300">
                            <th class="px-5 py-3 font-semibold">Customer</th>
                            <th class="px-5 py-3 font-semibold">Subscribed</th>
                            <th class="px-5 py-3 font-semibold">Last Seen</th>
                            <th class="px-5 py-3 font-semibold">Scans</th>
                            <th class="px-5 py-3 font-semibold">Saves</th>
                            <th class="px-5 py-3 font-semibold">Redemptions</th>
                            <th class="px-5 py-3 font-semibold">Savings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in (customers?.data || [])" :key="idx" class="border-t border-white/5">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-white/10 border border-white/10 overflow-hidden flex items-center justify-center">
                                        <img v-if="row.user?.avatar_url" :src="row.user.avatar_url" alt="" class="w-full h-full object-cover" />
                                        <span v-else class="text-gray-200 font-bold text-sm">{{ (row.user?.name || 'U').charAt(0) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-white font-semibold truncate">{{ row.user?.name }}</div>
                                        <div class="text-xs text-gray-400 truncate">{{ row.user?.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-300">{{ row.subscribed_at || '—' }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ row.engagement?.last_seen_at || '—' }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ row.engagement?.scans ?? 0 }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ row.engagement?.saves ?? 0 }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ row.engagement?.redemptions ?? 0 }}</td>
                            <td class="px-5 py-3 text-emerald-300 font-semibold">${{ row.engagement?.lifetime_savings ?? '0.00' }}</td>
                        </tr>

                        <tr v-if="!(customers?.data || []).length">
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                No subscribed customers yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="customers?.last_page > 1" class="p-5 border-t border-white/10 flex justify-center">
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="customers.current_page > 1"
                        :href="customers.prev_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors text-sm"
                        preserve-scroll
                    >
                        Previous
                    </Link>
                    <span class="px-4 py-2 text-gray-400 text-sm">
                        Page {{ customers.current_page }} of {{ customers.last_page }}
                    </span>
                    <Link
                        v-if="customers.current_page < customers.last_page"
                        :href="customers.next_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors text-sm"
                        preserve-scroll
                    >
                        Next
                    </Link>
                </nav>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

