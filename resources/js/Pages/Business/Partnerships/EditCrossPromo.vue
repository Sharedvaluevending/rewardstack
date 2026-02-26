<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    crossPromo: Object,
    partner: Object,
    my_promotion: Object,
    partner_promotion: Object,
    displayModes: Object,
    chainModes: Object,
});

const form = useForm({
    name: props.crossPromo?.name ?? '',
    display_mode: props.crossPromo?.display_mode ?? 'split',
    chain_mode: props.crossPromo?.chain_mode ?? 'open',
    starts_at: props.crossPromo?.starts_at ? props.crossPromo.starts_at.slice(0, 10) : null,
    expires_at: props.crossPromo?.expires_at ? props.crossPromo.expires_at.slice(0, 10) : null,
    usage_limit: props.crossPromo?.usage_limit ?? null,
});

const canResume = computed(() => {
    if (!props.crossPromo?.expires_at) return true;
    return new Date(props.crossPromo.expires_at) > new Date();
});

const submit = () => {
    form.put(`/business/partnerships/cross-promo/${props.crossPromo.id}`, {
        preserveScroll: true,
    });
};

const pause = () => router.post(`/business/partnerships/cross-promo/${props.crossPromo.id}/pause`);
const resume = () => router.post(`/business/partnerships/cross-promo/${props.crossPromo.id}/resume`);
</script>

<template>
    <Head title="Edit Partner Deal Chain" />

    <div class="max-w-5xl mx-auto px-4 py-8">
        <Link href="/business/partnerships" class="text-gray-400 hover:text-white mb-4 inline-block">
            ← Back to Partnerships
        </Link>

        <div class="glass-card p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-white">Edit Partner Deal Chain</h1>
                <p class="text-gray-400 mt-1">
                    Partner: <span class="text-white/90">{{ partner?.name }}</span>
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Rules are locked to the requester’s promotion (expiry, redemption limits, schedule).
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                    <div class="text-emerald-400 text-sm font-medium mb-1">Your Promotion</div>
                    <div class="text-white font-semibold">{{ my_promotion?.name || 'Not set' }}</div>
                </div>
                <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                    <div class="text-blue-400 text-sm font-medium mb-1">Partner Promotion</div>
                    <div class="text-white font-semibold">{{ partner_promotion?.name || 'Not set' }}</div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Deal name</label>
                    <input v-model="form.name" type="text" class="input-glass w-full" required />
                    <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Display mode</label>
                        <select v-model="form.display_mode" class="input-glass w-full" required>
                            <option v-for="(label, value) in (displayModes || {})" :key="value" :value="value" class="bg-gray-800 text-white">
                                {{ label }}
                            </option>
                        </select>
                        <p v-if="form.errors.display_mode" class="mt-2 text-sm text-red-400">{{ form.errors.display_mode }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Chain mode</label>
                        <select v-model="form.chain_mode" class="input-glass w-full">
                            <option v-for="(label, value) in (chainModes || {})" :key="value" :value="value" class="bg-gray-800 text-white">
                                {{ label }}
                            </option>
                        </select>
                        <p v-if="form.errors.chain_mode" class="mt-2 text-sm text-red-400">{{ form.errors.chain_mode }}</p>
                    </div>
                </div>

                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Start date</label>
                        <input v-model="form.starts_at" type="date" class="input-glass w-full" />
                        <p v-if="form.errors.starts_at" class="mt-2 text-sm text-red-400">{{ form.errors.starts_at }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Expires at</label>
                        <input v-model="form.expires_at" type="date" class="input-glass w-full" />
                        <p v-if="form.errors.expires_at" class="mt-2 text-sm text-red-400">{{ form.errors.expires_at }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Usage limit</label>
                        <input v-model.number="form.usage_limit" type="number" min="1" class="input-glass w-full" placeholder="Unlimited" />
                        <p v-if="form.errors.usage_limit" class="mt-2 text-sm text-red-400">{{ form.errors.usage_limit }}</p>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <Link href="/business/partnerships" class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20 text-center">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600 disabled:opacity-50">
                        Save changes
                    </button>
                </div>
            </form>
        </div>

        <div class="glass-card p-6 mt-6">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="crossPromo?.is_active"
                    type="button"
                    @click="pause"
                    class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20"
                >
                    Pause
                </button>
                <button
                    v-else
                    type="button"
                    @click="resume"
                    :disabled="!canResume"
                    class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 disabled:opacity-50"
                >
                    Resume
                </button>

                <Link
                    :href="`/business/partnerships/cross-promo/${crossPromo.id}/rules`"
                    class="px-4 py-2 bg-amber-500/15 text-amber-200 rounded-lg hover:bg-amber-500/25"
                >
                    Rules
                </Link>
                <Link
                    :href="`/business/partnerships/cross-promo/${crossPromo.id}/analytics`"
                    class="px-4 py-2 bg-blue-500/15 text-blue-200 rounded-lg hover:bg-blue-500/25"
                >
                    Analytics
                </Link>
            </div>
            <p v-if="crossPromo?.expires_at && !canResume" class="text-xs text-red-300 mt-3">
                This partner deal is expired and can’t be resumed.
            </p>
        </div>
    </div>
</template>

