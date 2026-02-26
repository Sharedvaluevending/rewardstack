<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    segments: Array,
});

const page = usePage();

const form = useForm({
    name: '',
    definition: {
        filters: {
            last_seen_days: null,
            min_scans: null,
            min_redemptions: null,
            min_saved: null,
            min_level: null,
        },
    },
});

const editingId = ref(null);
const editForm = useForm({
    name: '',
    definition: {
        filters: {
            last_seen_days: null,
            min_scans: null,
            min_redemptions: null,
            min_saved: null,
            min_level: null,
        },
    },
});

const deletingId = ref(null);

const submit = () => {
    form.post('/business/crm/segments', { preserveScroll: true, onSuccess: () => form.reset() });
};

const startEdit = (segment) => {
    editingId.value = segment.id;
    editForm.name = segment.name;
    editForm.definition = {
        filters: {
            last_seen_days: segment.definition?.filters?.last_seen_days ?? null,
            min_scans: segment.definition?.filters?.min_scans ?? null,
            min_redemptions: segment.definition?.filters?.min_redemptions ?? null,
            min_saved: segment.definition?.filters?.min_saved ?? null,
            min_level: segment.definition?.filters?.min_level ?? null,
        },
    };
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const saveEdit = (id) => {
    editForm.put(`/business/crm/segments/${id}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; editForm.reset(); },
    });
};

const deleteSegment = (id) => {
    if (!id) return;
    if (!confirm('Delete this segment?')) return;
    deletingId.value = id;
    router.delete(`/business/crm/segments/${id}`, { preserveScroll: true, onFinish: () => (deletingId.value = null) });
};
</script>

<template>
    <Head title="CRM Segments" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Segments</h1>
                <p class="text-gray-400 text-sm mt-1">Create target groups based on engagement.</p>
            </div>
        </div>

        <div v-if="page.props?.flash?.success" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="glass-card p-5 lg:col-span-1">
                <h2 class="text-white font-semibold mb-3">New Segment</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400">Name</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full input" placeholder="VIP Customers" />
                        <div v-if="form.errors.name" class="text-xs text-red-300 mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-400">Seen within (days)</label>
                            <input v-model.number="form.definition.filters.last_seen_days" type="number" min="1" class="mt-1 w-full input" placeholder="30" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Min level</label>
                            <input v-model.number="form.definition.filters.min_level" type="number" min="1" class="mt-1 w-full input" placeholder="10" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Min scans</label>
                            <input v-model.number="form.definition.filters.min_scans" type="number" min="0" class="mt-1 w-full input" placeholder="5" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Min redemptions</label>
                            <input v-model.number="form.definition.filters.min_redemptions" type="number" min="0" class="mt-1 w-full input" placeholder="1" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Min saves</label>
                            <input v-model.number="form.definition.filters.min_saved" type="number" min="0" class="mt-1 w-full input" placeholder="1" />
                        </div>
                    </div>

                    <button type="button" class="btn-primary w-full" :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Creating…' : 'Create Segment' }}
                    </button>
                </div>
            </div>

            <div class="glass-card overflow-hidden lg:col-span-2">
                <div class="p-5 border-b border-white/10">
                    <div class="text-white font-semibold">Your Segments</div>
                    <div class="text-xs text-gray-400 mt-1">Used to target campaigns.</div>
                </div>
                <div class="p-5">
                    <div v-if="(segments || []).length" class="space-y-3">
                        <div v-for="s in segments" :key="s.id" class="p-4 rounded-xl bg-white/5 border border-white/10">
                            <template v-if="editingId === s.id">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-xs text-gray-400">Name</label>
                                        <input v-model="editForm.name" type="text" class="mt-1 w-full input" />
                                        <div v-if="editForm.errors.name" class="text-xs text-red-300 mt-1">{{ editForm.errors.name }}</div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-xs text-gray-400">Seen within (days)</label>
                                            <input v-model.number="editForm.definition.filters.last_seen_days" type="number" min="1" class="mt-1 w-full input" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-400">Min level</label>
                                            <input v-model.number="editForm.definition.filters.min_level" type="number" min="1" class="mt-1 w-full input" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-400">Min scans</label>
                                            <input v-model.number="editForm.definition.filters.min_scans" type="number" min="0" class="mt-1 w-full input" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-400">Min redemptions</label>
                                            <input v-model.number="editForm.definition.filters.min_redemptions" type="number" min="0" class="mt-1 w-full input" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-400">Min saves</label>
                                            <input v-model.number="editForm.definition.filters.min_saved" type="number" min="0" class="mt-1 w-full input" />
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="btn-primary text-xs px-3 py-1" :disabled="editForm.processing" @click="saveEdit(s.id)">
                                            {{ editForm.processing ? 'Saving…' : 'Save' }}
                                        </button>
                                        <button type="button" class="px-3 py-1 rounded-lg bg-white/10 text-gray-200 text-xs border border-white/20 hover:bg-white/20" @click="cancelEdit">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-white font-semibold">{{ s.name }}</div>
                                    <span class="text-xs px-2 py-1 rounded-full bg-white/10 border border-white/10 text-gray-200">
                                        {{ s.is_active ? 'active' : 'inactive' }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    <span v-if="s.definition?.filters?.last_seen_days">Seen ≤ {{ s.definition.filters.last_seen_days }}d</span>
                                    <span v-if="s.definition?.filters?.min_level"> • Level ≥ {{ s.definition.filters.min_level }}</span>
                                    <span v-if="s.definition?.filters?.min_scans"> • Scans ≥ {{ s.definition.filters.min_scans }}</span>
                                    <span v-if="s.definition?.filters?.min_redemptions"> • Redeems ≥ {{ s.definition.filters.min_redemptions }}</span>
                                    <span v-if="s.definition?.filters?.min_saved"> • Saves ≥ {{ s.definition.filters.min_saved }}</span>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        class="px-3 py-1 rounded-lg bg-white/10 text-gray-100 text-xs font-semibold border border-white/20 hover:bg-white/20 transition-colors"
                                        @click="startEdit(s)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="px-3 py-1 rounded-lg bg-white/10 text-gray-100 text-xs font-semibold border border-white/20 hover:bg-white/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="deletingId === s.id"
                                        @click="deleteSegment(s.id)"
                                    >
                                        {{ deletingId === s.id ? 'Deleting…' : 'Delete' }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div v-else class="text-gray-400 text-sm">No segments yet.</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
.input {
    @apply bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40;
}
</style>

