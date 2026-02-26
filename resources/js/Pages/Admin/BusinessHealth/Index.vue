<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    statusCounts: Object,
    followUpsDue: Array,
    businesses: Array,
    filters: Object,
});

const activeTab = ref('attention');
const search = ref(props.filters?.search ?? '');
const filter = ref(props.filters?.filter ?? 'all');
const showLogModal = ref(false);
const selectedFollowUp = ref(null);

const logForm = useForm({
    follow_up_id: null,
    business_id: null,
    notes: '',
    next_action: '',
    next_action_date: '',
    create_custom: false,
});

const applyFilters = () => {
    router.get('/admin/business-health', {
        search: search.value || undefined,
        filter: filter.value !== 'all' ? filter.value : undefined,
    }, { preserveState: true });
};

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});
watch(filter, applyFilters);

const statusColor = (status) => ({
    healthy: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    at_risk: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    inactive: 'bg-red-500/20 text-red-400 border-red-500/30',
    unknown: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
}[status] || 'bg-gray-500/20 text-gray-400 border-gray-500/30');

const statusDot = (status) => ({
    healthy: 'bg-emerald-400',
    at_risk: 'bg-amber-400',
    inactive: 'bg-red-400',
}[status] || 'bg-gray-400');

const openLog = (fu) => {
    selectedFollowUp.value = fu;
    logForm.follow_up_id = fu.id;
    logForm.business_id = fu.business_id;
    logForm.notes = '';
    logForm.next_action = '';
    logForm.next_action_date = '';
    showLogModal.value = true;
};

const openLogForBusiness = (biz) => {
    selectedFollowUp.value = { business_name: biz.name, type_label: 'Note / Call Log' };
    logForm.follow_up_id = null;
    logForm.business_id = biz.id;
    logForm.notes = '';
    logForm.next_action = '';
    logForm.next_action_date = '';
    logForm.create_custom = true;
    showLogModal.value = true;
};

const submitLog = () => {
    logForm.post('/admin/business-health/follow-ups', {
        onSuccess: () => {
            showLogModal.value = false;
            logForm.reset();
        },
    });
};

const snooze = (fuId, days) => {
    router.post(`/admin/business-health/follow-ups/${fuId}/snooze`, { days });
};
</script>

<template>
    <Head title="Business Health" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Business Health</h1>
            <p class="text-gray-400 mt-1">Track business engagement and manage follow-ups</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <div class="text-3xl font-bold text-white">{{ statusCounts.total }}</div>
                <div class="text-sm text-gray-400 mt-1">Total Tracked</div>
            </div>
            <div class="glass-card p-6 border-l-4 border-emerald-500">
                <div class="text-3xl font-bold text-emerald-400">{{ statusCounts.healthy }}</div>
                <div class="text-sm text-gray-400 mt-1">Healthy</div>
            </div>
            <div class="glass-card p-6 border-l-4 border-amber-500">
                <div class="text-3xl font-bold text-amber-400">{{ statusCounts.at_risk }}</div>
                <div class="text-sm text-gray-400 mt-1">At Risk</div>
            </div>
            <div class="glass-card p-6 border-l-4 border-red-500">
                <div class="text-3xl font-bold text-red-400">{{ statusCounts.inactive }}</div>
                <div class="text-sm text-gray-400 mt-1">Inactive</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6">
            <button
                @click="activeTab = 'attention'"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                    activeTab === 'attention' ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white hover:bg-white/5',
                ]"
            >
                Needs Attention
                <span v-if="followUpsDue.length" class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-red-500/30 text-red-300">
                    {{ followUpsDue.length }}
                </span>
            </button>
            <button
                @click="activeTab = 'all'"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                    activeTab === 'all' ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white hover:bg-white/5',
                ]"
            >
                All Businesses
            </button>
        </div>

        <!-- Needs Attention Tab -->
        <div v-if="activeTab === 'attention'">
            <div v-if="followUpsDue.length === 0" class="glass-card p-12 text-center">
                <div class="text-4xl mb-3">&#10003;</div>
                <div class="text-xl font-semibold text-white">All caught up!</div>
                <div class="text-gray-400 mt-1">No follow-ups due right now.</div>
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="fu in followUpsDue"
                    :key="fu.id"
                    class="glass-card p-5 flex flex-col sm:flex-row sm:items-center gap-4"
                >
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <Link :href="`/admin/business-health/${fu.business_id}`" class="text-lg font-semibold text-white hover:text-primary-400 transition-colors truncate">
                                {{ fu.business_name }}
                            </Link>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium border', statusColor(fu.health_status)]">
                                {{ fu.health_status }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-400">
                            <span class="font-medium text-gray-300">{{ fu.type_label }}</span>
                            <span class="mx-1">&middot;</span>
                            <span>Signed up {{ fu.business_created }}</span>
                            <span v-if="fu.days_overdue > 0" class="ml-2 text-red-400 font-medium">
                                {{ fu.days_overdue }} day{{ fu.days_overdue !== 1 ? 's' : '' }} overdue
                            </span>
                        </div>
                        <div v-if="fu.owner_email" class="text-sm text-gray-500 mt-1">
                            {{ fu.owner_email }}
                            <span v-if="fu.owner_phone" class="ml-2">{{ fu.owner_phone }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            v-if="fu.owner_email"
                            :href="`mailto:${fu.owner_email}`"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition-colors"
                        >Email</a>
                        <button
                            @click="openLog(fu)"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-colors"
                        >Complete</button>
                        <div class="relative group">
                            <button class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                                Snooze
                            </button>
                            <div class="hidden group-hover:flex absolute right-0 top-full mt-1 gap-1 z-10 bg-gray-800 rounded-lg p-1 border border-white/10 shadow-xl">
                                <button @click="snooze(fu.id, 1)" class="px-2 py-1 text-xs rounded hover:bg-white/10 text-gray-300 whitespace-nowrap">1d</button>
                                <button @click="snooze(fu.id, 3)" class="px-2 py-1 text-xs rounded hover:bg-white/10 text-gray-300 whitespace-nowrap">3d</button>
                                <button @click="snooze(fu.id, 7)" class="px-2 py-1 text-xs rounded hover:bg-white/10 text-gray-300 whitespace-nowrap">7d</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Businesses Tab -->
        <div v-if="activeTab === 'all'">
            <!-- Filters -->
            <div class="glass-card p-4 mb-6 flex flex-col sm:flex-row gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name..."
                    class="input-glass flex-1"
                />
                <select v-model="filter" class="input-glass sm:w-48">
                    <option value="all">All statuses</option>
                    <option value="healthy">Healthy</option>
                    <option value="at_risk">At Risk</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="glass-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-white/5 text-xs text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">Business</th>
                                <th class="px-4 py-3">Health</th>
                                <th class="px-4 py-3 hidden md:table-cell">Scans</th>
                                <th class="px-4 py-3 hidden md:table-cell">Promos</th>
                                <th class="px-4 py-3 hidden lg:table-cell">Last Login</th>
                                <th class="px-4 py-3 hidden lg:table-cell">Age</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr
                                v-for="biz in businesses"
                                :key="biz.id"
                                class="hover:bg-white/5 transition-colors"
                            >
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/business-health/${biz.id}`" class="text-white font-medium hover:text-primary-400 transition-colors">
                                        {{ biz.name }}
                                    </Link>
                                    <div class="text-xs text-gray-500">{{ biz.owner_email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span :class="['w-2 h-2 rounded-full', statusDot(biz.health_status)]"></span>
                                        <span class="text-sm text-gray-300">{{ biz.health_score }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300 hidden md:table-cell">{{ biz.scans_count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-300 hidden md:table-cell">{{ biz.promotions_count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-400 hidden lg:table-cell">{{ biz.last_login ?? 'Never' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-400 hidden lg:table-cell">{{ biz.days_since_signup }}d</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="openLogForBusiness(biz)"
                                            class="px-2 py-1 rounded text-xs bg-white/10 text-gray-300 hover:bg-white/20 transition-colors"
                                        >Log</button>
                                        <Link
                                            :href="`/admin/business-health/${biz.id}`"
                                            class="px-2 py-1 rounded text-xs bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 transition-colors"
                                        >View</Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="businesses.length === 0" class="p-12 text-center text-gray-400">
                    No businesses found.
                </div>
            </div>
        </div>
    </div>

    <!-- Log / Complete Modal -->
    <Teleport to="body">
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/70" @click="showLogModal = false"></div>
            <div class="relative w-full max-w-lg glass-card p-6 border border-white/10">
                <h2 class="text-lg font-bold text-white mb-1">
                    {{ selectedFollowUp?.type_label ?? 'Log Follow-up' }}
                </h2>
                <p class="text-sm text-gray-400 mb-5">{{ selectedFollowUp?.business_name }}</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Notes</label>
                        <textarea
                            v-model="logForm.notes"
                            rows="3"
                            class="input-glass"
                            placeholder="What happened? Any insights?"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Next Action</label>
                        <input
                            v-model="logForm.next_action"
                            type="text"
                            class="input-glass"
                            placeholder="e.g. Follow up on promo setup"
                        />
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Next Action Date</label>
                        <input
                            v-model="logForm.next_action_date"
                            type="date"
                            class="input-glass"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        @click="showLogModal = false"
                        class="px-4 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 transition-colors"
                    >Cancel</button>
                    <button
                        @click="submitLog"
                        :disabled="logForm.processing"
                        class="btn-primary"
                    >
                        {{ logForm.processing ? 'Saving...' : 'Save & Complete' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
