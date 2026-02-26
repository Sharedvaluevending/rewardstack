<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    items: Object, // paginator
    summary: Object,
    period: {
        type: String,
        default: '30',
    },
    filters: Object,
});

const selectedPeriod = ref(props.period || '30');

const statusOptions = [
    { id: 'all', label: 'All' },
    { id: 'claimed', label: 'Claimed' },
    { id: 'unclaimed', label: 'Unclaimed' },
];

const selectedStatus = ref(props.filters?.status || 'all');
const searchQuery = ref(props.filters?.q || '');
const perPage = ref(props.filters?.per_page || 25);

const changePeriod = (period) => {
    selectedPeriod.value = period;
    applyFilters();
};

const applyFilters = () => {
    const params = {
        period: selectedPeriod.value,
    };
    if (selectedStatus.value && selectedStatus.value !== 'all') params.status = selectedStatus.value;
    if (searchQuery.value) params.q = searchQuery.value;
    if (perPage.value) params.per_page = perPage.value;

    router.get('/business/analytics/merch', params, { preserveState: true });
};

const clearFilters = () => {
    selectedStatus.value = 'all';
    searchQuery.value = '';
    perPage.value = 25;
    applyFilters();
};
</script>

<template>
    <Head title="Ambassador Merch Analytics" />

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Ambassador Merch</h1>
                <p class="text-sm text-gray-400">Track scans and redemptions tied to wearable merch.</p>
            </div>

            <div class="flex gap-2">
                <button
                    v-for="option in ['7', '30', '90']"
                    :key="option"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
                    :class="selectedPeriod === option ? 'bg-purple-500/20 text-purple-200 border-purple-500/40' : 'bg-white/5 text-gray-300 border-white/10'"
                    @click="changePeriod(option)"
                >
                    {{ option }}d
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="glass-card p-4">
                <div class="text-xs text-gray-400">Tags</div>
                <div class="text-2xl font-semibold text-white">{{ summary.total_tags }}</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-xs text-gray-400">Claimed</div>
                <div class="text-2xl font-semibold text-white">{{ summary.claimed_tags }}</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-xs text-gray-400">Unique Scans</div>
                <div class="text-2xl font-semibold text-white">{{ summary.unique_scans }}</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-xs text-gray-400">Redemptions</div>
                <div class="text-2xl font-semibold text-white">{{ summary.redemptions }}</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-xs text-gray-400">Rewards Issued</div>
                <div class="text-2xl font-semibold text-white">{{ summary.awards }}</div>
            </div>
        </div>

        <div class="glass-card p-4">
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="status in statusOptions"
                    :key="status.id"
                    @click="selectedStatus = status.id; applyFilters()"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                        selectedStatus === status.id
                            ? 'bg-purple-500/20 text-purple-200 border border-purple-500/40'
                            : 'bg-white/5 text-gray-300 border border-white/10 hover:bg-white/10'
                    ]"
                >
                    {{ status.label }}
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search ambassador, QR name, or tag code"
                    class="input-glass"
                />
                <select v-model="perPage" class="input-glass">
                    <option :value="25" class="bg-gray-800 text-white">25 / page</option>
                    <option :value="50" class="bg-gray-800 text-white">50 / page</option>
                    <option :value="100" class="bg-gray-800 text-white">100 / page</option>
                </select>
                <div class="flex items-center gap-3">
                    <button @click="applyFilters" class="btn-primary">Apply</button>
                    <button @click="clearFilters" class="text-sm text-gray-400 hover:text-white">Clear</button>
                </div>
            </div>
        </div>

        <div class="glass-card p-4">
            <div class="text-sm text-gray-300 mb-3">Merch Tags</div>

            <div v-if="!items.data?.length" class="text-sm text-gray-400">
                No merch tags found for this period.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="item in items.data"
                    :key="item.id"
                    class="p-3 rounded-xl bg-white/5 border border-white/10"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-white">
                                {{ item.owner?.name || 'Unclaimed' }}
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ item.qr_code?.name || 'QR Code' }} · {{ item.code }}
                            </div>
                        </div>
                        <div class="text-xs text-gray-400">
                            Last scan: {{ item.stats.last_scan_at ? new Date(item.stats.last_scan_at).toLocaleString() : '—' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                        <div class="bg-black/20 rounded-lg p-2">
                            <div class="text-xs text-gray-400">Unique</div>
                            <div class="text-sm font-semibold text-white">{{ item.stats.unique_scans }}</div>
                        </div>
                        <div class="bg-black/20 rounded-lg p-2">
                            <div class="text-xs text-gray-400">Scans</div>
                            <div class="text-sm font-semibold text-white">{{ item.stats.total_scans }}</div>
                        </div>
                        <div class="bg-black/20 rounded-lg p-2">
                            <div class="text-xs text-gray-400">Redeems</div>
                            <div class="text-sm font-semibold text-white">{{ item.stats.redemptions }}</div>
                        </div>
                    </div>

                    <div v-if="item.reward" class="mt-3 p-3 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Reward</div>
                        <div class="text-sm text-white font-semibold">
                            {{ item.reward.description || (item.reward.type === 'free_item' ? 'Free item' : 'Reward') }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            Every {{ item.reward.redemptions_required }} unique redemptions
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Rewards issued: {{ item.stats.awards }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="items.links && items.links.length > 3" class="flex justify-center gap-2">
            <button
                v-for="link in items.links"
                :key="link.label"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
                class="px-3 py-1 rounded-lg text-sm transition-colors"
                :class="[
                    link.active ? 'bg-purple-500/20 text-purple-200' : 'bg-white/5 text-gray-300 hover:bg-white/10',
                    !link.url ? 'opacity-50 cursor-not-allowed' : ''
                ]"
                v-html="link.label"
            ></button>
        </div>
    </div>
</template>
