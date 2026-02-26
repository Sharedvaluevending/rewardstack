<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    business: Object,
    stats: Object,
    healthHistory: Array,
    followUps: Array,
    recentScans: Array,
});

const showLogModal = ref(false);

const logForm = useForm({
    follow_up_id: null,
    business_id: props.business.id,
    notes: '',
    next_action: '',
    next_action_date: '',
    create_custom: true,
});

const submitLog = () => {
    logForm.post('/admin/business-health/follow-ups', {
        onSuccess: () => {
            showLogModal.value = false;
            logForm.reset();
            logForm.business_id = props.business.id;
            logForm.create_custom = true;
        },
    });
};

const snooze = (fuId, days) => {
    router.post(`/admin/business-health/follow-ups/${fuId}/snooze`, { days });
};

const completeFollowUp = (fu) => {
    logForm.follow_up_id = fu.id;
    logForm.business_id = props.business.id;
    logForm.notes = '';
    logForm.next_action = '';
    logForm.next_action_date = '';
    logForm.create_custom = false;
    showLogModal.value = true;
};

const statusColor = (status) => ({
    healthy: 'text-emerald-400',
    at_risk: 'text-amber-400',
    inactive: 'text-red-400',
}[status] || 'text-gray-400');

const statusBg = (status) => ({
    healthy: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    at_risk: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    inactive: 'bg-red-500/20 text-red-400 border-red-500/30',
}[status] || 'bg-gray-500/20 text-gray-400 border-gray-500/30');

const followUpStatusStyle = (status) => ({
    pending: 'bg-amber-500/20 text-amber-400',
    completed: 'bg-emerald-500/20 text-emerald-400',
    snoozed: 'bg-blue-500/20 text-blue-400',
}[status] || 'bg-gray-500/20 text-gray-400');

// Simple sparkline-style chart using CSS
const chartMax = computed(() => Math.max(100, ...props.healthHistory.map(h => h.score)));
const chartBars = computed(() =>
    props.healthHistory.map(h => ({
        ...h,
        height: Math.max(4, (h.score / chartMax.value) * 100),
    }))
);
</script>

<template>
    <Head :title="`Health: ${business.name}`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-start justify-between mb-8">
            <div>
                <Link href="/admin/business-health" class="text-sm text-gray-400 hover:text-white transition-colors mb-2 inline-block">
                    &larr; Back to Health Dashboard
                </Link>
                <h1 class="text-3xl font-bold text-white">{{ business.name }}</h1>
                <div class="flex items-center gap-3 mt-2 text-sm text-gray-400">
                    <span>{{ business.owner_name }}</span>
                    <span>&middot;</span>
                    <span>{{ business.owner_email }}</span>
                    <span>&middot;</span>
                    <span>{{ business.days_since_signup }} days old</span>
                    <span>&middot;</span>
                    <span class="capitalize">{{ business.subscription_tier }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <a
                    v-if="business.owner_email"
                    :href="`mailto:${business.owner_email}`"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition-colors"
                >Email Owner</a>
                <button
                    @click="showLogModal = true; logForm.follow_up_id = null; logForm.create_custom = true;"
                    class="btn-primary text-sm"
                >Add Note / Log Call</button>
            </div>
        </div>

        <!-- Health Score + Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
            <div class="glass-card p-5 col-span-2 md:col-span-1">
                <div :class="['text-4xl font-bold', statusColor(stats.health_status)]">{{ stats.health_score }}</div>
                <div class="text-sm text-gray-400 mt-1">Health Score</div>
                <span :class="['inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-medium border', statusBg(stats.health_status)]">
                    {{ stats.health_status }}
                </span>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white">{{ stats.total_scans }}</div>
                <div class="text-xs text-gray-400 mt-1">Total Scans</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white">{{ stats.scans_this_week }}</div>
                <div class="text-xs text-gray-400 mt-1">Scans This Week</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white">{{ stats.active_promotions }}</div>
                <div class="text-xs text-gray-400 mt-1">Active Promos</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white">{{ stats.qr_codes }}</div>
                <div class="text-xs text-gray-400 mt-1">QR Codes</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white">{{ stats.customers }}</div>
                <div class="text-xs text-gray-400 mt-1">Customers</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-2xl font-bold text-white text-sm">{{ stats.last_login ?? 'Never' }}</div>
                <div class="text-xs text-gray-400 mt-1">Last Login</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Health Chart + Recent Activity -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Health Score Chart (last 30 days) -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Health Score (Last 30 Days)</h2>
                    <div v-if="chartBars.length === 0" class="text-gray-400 text-sm py-8 text-center">
                        No data yet. Health scores are calculated daily at 6 AM.
                    </div>
                    <div v-else class="flex items-end gap-1 h-32">
                        <div
                            v-for="(bar, i) in chartBars"
                            :key="i"
                            class="flex-1 rounded-t transition-all group relative cursor-default"
                            :class="bar.status === 'healthy' ? 'bg-emerald-500/60' : bar.status === 'at_risk' ? 'bg-amber-500/60' : 'bg-red-500/60'"
                            :style="{ height: bar.height + '%' }"
                        >
                            <div class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 rounded bg-gray-900 text-xs text-white whitespace-nowrap border border-white/10 z-10">
                                {{ bar.date }}: {{ bar.score }}
                            </div>
                        </div>
                    </div>
                    <div v-if="chartBars.length" class="flex justify-between mt-2 text-xs text-gray-500">
                        <span>{{ chartBars[0]?.date }}</span>
                        <span>{{ chartBars[chartBars.length - 1]?.date }}</span>
                    </div>
                </div>

                <!-- Recent Scans -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Recent Activity</h2>
                    <div v-if="recentScans.length === 0" class="text-gray-400 text-sm">No scans recorded yet.</div>
                    <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="scan in recentScans"
                            :key="scan.id"
                            class="flex items-center justify-between py-2 border-b border-white/5 last:border-0"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary-400"></span>
                                <span class="text-sm text-gray-300">Scan</span>
                                <span v-if="scan.scan_type" class="px-1.5 py-0.5 rounded text-xs bg-white/10 text-gray-400">{{ scan.scan_type }}</span>
                            </div>
                            <span class="text-xs text-gray-500">{{ scan.scanned_at }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Follow-up Timeline -->
            <div class="space-y-6">
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-white">Follow-ups</h2>
                        <button
                            @click="showLogModal = true; logForm.follow_up_id = null; logForm.create_custom = true;"
                            class="text-xs px-2 py-1 rounded bg-white/10 text-gray-300 hover:bg-white/20 transition-colors"
                        >+ Add</button>
                    </div>

                    <div v-if="followUps.length === 0" class="text-gray-400 text-sm">No follow-ups yet.</div>

                    <div v-else class="space-y-0">
                        <div
                            v-for="(fu, i) in followUps"
                            :key="fu.id"
                            class="relative pl-6 pb-5 last:pb-0"
                        >
                            <!-- Timeline line -->
                            <div v-if="i < followUps.length - 1" class="absolute left-[7px] top-3 bottom-0 w-px bg-white/10"></div>
                            <!-- Timeline dot -->
                            <div :class="[
                                'absolute left-0 top-1.5 w-[15px] h-[15px] rounded-full border-2',
                                fu.status === 'completed' ? 'bg-emerald-500 border-emerald-400' : fu.status === 'snoozed' ? 'bg-blue-500 border-blue-400' : 'bg-gray-700 border-gray-500',
                            ]"></div>

                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-medium text-white">{{ fu.type_label }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-xs', followUpStatusStyle(fu.status)]">{{ fu.status }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Due: {{ fu.due_date }}
                                    <span v-if="fu.completed_at"> &middot; Done: {{ fu.completed_at }}</span>
                                </div>
                                <div v-if="fu.notes" class="text-sm text-gray-400 mt-1 bg-white/5 rounded p-2">{{ fu.notes }}</div>
                                <div v-if="fu.next_action" class="text-xs text-primary-400 mt-1">
                                    Next: {{ fu.next_action }}
                                    <span v-if="fu.next_action_date"> ({{ fu.next_action_date }})</span>
                                </div>

                                <!-- Actions for pending -->
                                <div v-if="fu.status === 'pending'" class="flex items-center gap-2 mt-2">
                                    <button
                                        @click="completeFollowUp(fu)"
                                        class="px-2 py-1 rounded text-xs bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 transition-colors"
                                    >Complete</button>
                                    <button
                                        @click="snooze(fu.id, 3)"
                                        class="px-2 py-1 rounded text-xs bg-white/10 text-gray-300 hover:bg-white/20 transition-colors"
                                    >Snooze 3d</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-3">Quick Links</h2>
                    <div class="space-y-2">
                        <Link :href="`/admin/businesses/${business.id}`" class="block text-sm text-primary-400 hover:text-primary-300 transition-colors">
                            View in Admin Panel &rarr;
                        </Link>
                        <a v-if="business.phone" :href="`tel:${business.phone}`" class="block text-sm text-primary-400 hover:text-primary-300 transition-colors">
                            Call {{ business.phone }} &rarr;
                        </a>
                        <a v-if="business.owner_email" :href="`mailto:${business.owner_email}`" class="block text-sm text-primary-400 hover:text-primary-300 transition-colors">
                            Email {{ business.owner_email }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log / Note Modal -->
    <Teleport to="body">
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/70" @click="showLogModal = false"></div>
            <div class="relative w-full max-w-lg glass-card p-6 border border-white/10">
                <h2 class="text-lg font-bold text-white mb-4">
                    {{ logForm.follow_up_id ? 'Complete Follow-up' : 'Add Note / Log Call' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Notes</label>
                        <textarea
                            v-model="logForm.notes"
                            rows="3"
                            class="input-glass"
                            placeholder="What happened? Key takeaways?"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Schedule Next Action</label>
                        <input
                            v-model="logForm.next_action"
                            type="text"
                            class="input-glass"
                            placeholder="e.g. Check if they set up first promo"
                        />
                    </div>
                    <div v-if="logForm.next_action">
                        <label class="block text-sm text-gray-300 mb-1">When</label>
                        <input v-model="logForm.next_action_date" type="date" class="input-glass" />
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
                    >{{ logForm.processing ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
