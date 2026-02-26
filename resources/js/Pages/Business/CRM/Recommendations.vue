<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    recommendations: Array,
    aiConfigured: Boolean,
    campaignsUrl: {
        type: String,
        default: '/business/crm/campaigns/create',
    },
});

const page = usePage();

const generate = () => {
    router.post('/business/crm/recommendations/generate', {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="CRM AI Recommendations" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">AI Recommendations</h1>
                <p class="text-gray-400 text-sm mt-1">Campaign ideas tailored to your business + last 30 days.</p>
            </div>
            <button type="button" class="btn-primary text-sm" @click="generate">
                Generate
            </button>
        </div>

        <div v-if="page.props?.flash?.success" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>

        <div class="glass-card p-5 mb-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-white font-semibold">AI status</div>
                    <div class="text-xs text-gray-400 mt-1">Uses DeepSeek if configured; otherwise a safe fallback set.</div>
                </div>
                <span
                    class="text-xs px-2 py-1 rounded-full border"
                    :class="aiConfigured ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : 'bg-yellow-500/10 border-yellow-500/20 text-yellow-300'"
                >
                    {{ aiConfigured ? 'DeepSeek configured' : 'Fallback mode' }}
                </span>
            </div>
        </div>

        <div v-if="(recommendations || []).length" class="space-y-4">
            <div v-for="r in recommendations" :key="r.id" class="glass-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-white font-semibold">{{ r.type }}</div>
                    <div class="text-xs text-gray-500">{{ r.created_at }}</div>
                </div>

                <div class="mt-4">
                    <div class="text-xs text-gray-400 mb-2">Campaign ideas</div>
                    <div class="space-y-3">
                        <div
                            v-for="(c, idx) in (r.payload?.recommendations?.campaigns || [])"
                            :key="idx"
                            class="p-4 rounded-xl bg-white/5 border border-white/10"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-white font-semibold">{{ c.title }}</div>
                                <span class="text-xs px-2 py-1 rounded-full bg-white/10 border border-white/10 text-gray-200">{{ c.priority }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                <span class="text-gray-500">Audience:</span> {{ c.audience }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1" v-if="c.promo_name">
                                <span class="text-gray-500">Promo:</span> {{ c.promo_name }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                <span class="text-gray-500">Subject:</span> <span class="text-gray-200">{{ c.subject_line }}</span>
                            </div>
                            <div class="text-sm text-gray-300 mt-2">{{ c.short_body }}</div>
                            <div class="text-xs text-gray-500 mt-2">
                                <span class="text-gray-500">Offer:</span> {{ c.offer_structure }}
                            </div>
                            <div class="mt-3 flex items-center gap-3">
                                <Link
                                    :href="`${campaignsUrl}?ai_title=${encodeURIComponent(c.title || '')}&ai_subject=${encodeURIComponent(c.subject_line || '')}&ai_body=${encodeURIComponent(c.short_body || '')}&ai_promo_id=${c.promo_id || ''}`"
                                    class="btn-primary text-xs px-3 py-2"
                                >
                                    Apply to new campaign
                                </Link>
                                <span v-if="c.promo_id" class="text-[11px] px-2 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-200">
                                    Uses promo
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="text-xs text-gray-400 mb-2">Automation ideas</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            v-for="(a, idx) in (r.payload?.recommendations?.automations || [])"
                            :key="idx"
                            class="p-4 rounded-xl bg-white/5 border border-white/10"
                        >
                            <div class="text-white font-semibold">{{ a.title }}</div>
                            <div class="text-xs text-gray-400 mt-1"><span class="text-gray-500">Trigger:</span> {{ a.trigger }}</div>
                            <div class="text-sm text-gray-300 mt-2">{{ a.message }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass-card p-6 text-center">
            <div class="text-4xl mb-2">🧠</div>
            <div class="text-white font-semibold">No recommendations yet</div>
            <div class="text-sm text-gray-400 mt-1">Click “Generate” to create tailored campaign ideas.</div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

