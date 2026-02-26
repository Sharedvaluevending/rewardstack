<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    segments: Array,
    promotions: Array,
    defaultFromName: String,
    defaultFromEmail: String,
    aiPrefill: Object,
});

const page = usePage();

const form = useForm({
    name: '',
    subject: '',
    preheader: '',
    segment_id: null,
    promotion_id: null,
    content_html: `<div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;background:#0b1220;padding:24px;">
  <h2 style="margin:0 0 10px 0;">New offer</h2>
  <p style="margin:0;color:rgba(255,255,255,0.75);">Write your message here.</p>
</div>`,
    content_text: '',
});

// Apply AI prefill if provided via props
if (props.aiPrefill) {
    if (props.aiPrefill.title) form.name = props.aiPrefill.title;
    if (props.aiPrefill.subject) form.subject = props.aiPrefill.subject;
    if (props.aiPrefill.body) form.content_text = props.aiPrefill.body;
    if (props.aiPrefill.promo_id) form.promotion_id = props.aiPrefill.promo_id;
}

const submit = () => {
    form.post('/business/crm/campaigns');
};
</script>

<template>
    <Head title="Create Campaign" />

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">New Campaign</h1>
                <p class="text-gray-400 text-sm mt-1">Emails only go to customers who explicitly subscribed.</p>
            </div>
            <Link href="/business/crm/campaigns" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                Back
            </Link>
        </div>

        <div v-if="page.props?.flash?.error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.flash.error }}
        </div>

        <div class="glass-card p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-gray-400">Campaign name</label>
                    <input
                        v-model="form.name"
                        class="mt-1 w-full input"
                        placeholder="January VIP Offer"
                    />
                    <div v-if="form.errors.name" class="text-xs text-red-300 mt-1">{{ form.errors.name }}</div>
                </div>
                <div>
                    <label class="text-xs text-gray-400">Segment (optional)</label>
                    <select v-model="form.segment_id" class="mt-1 w-full input">
                        <option :value="null">All subscribers</option>
                        <option v-for="s in (segments || [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div v-if="form.errors.segment_id" class="text-xs text-red-300 mt-1">{{ form.errors.segment_id }}</div>
                </div>
                <div>
                    <label class="text-xs text-gray-400">Promotion (optional)</label>
                    <select v-model="form.promotion_id" class="mt-1 w-full input">
                        <option :value="null">No attached promotion</option>
                        <option v-for="p in (promotions || [])" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.promotion_id" class="text-xs text-red-300 mt-1">{{ form.errors.promotion_id }}</div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-gray-400">Subject</label>
                    <input
                        v-model="form.subject"
                        class="mt-1 w-full input"
                        placeholder="Your next deal is here…"
                    />
                    <div v-if="form.errors.subject" class="text-xs text-red-300 mt-1">{{ form.errors.subject }}</div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-gray-400">Preheader (optional)</label>
                    <input v-model="form.preheader" class="mt-1 w-full input" placeholder="Quick line that appears in inbox previews" />
                    <div v-if="form.errors.preheader" class="text-xs text-red-300 mt-1">{{ form.errors.preheader }}</div>
                </div>
            </div>

            <div class="mt-6">
                <label class="text-xs text-gray-400">HTML content</label>
                <textarea v-model="form.content_html" rows="14" class="mt-1 w-full input font-mono text-xs"></textarea>
                <div v-if="form.errors.content_html" class="text-xs text-red-300 mt-1">{{ form.errors.content_html }}</div>
                <div class="text-xs text-gray-500 mt-2">
                    Footer + unsubscribe link are automatically appended.
                </div>
            </div>

            <div class="mt-6">
                <label class="text-xs text-gray-400">Plain text (optional)</label>
                <textarea v-model="form.content_text" rows="6" class="mt-1 w-full input text-sm"></textarea>
                <div v-if="form.errors.content_text" class="text-xs text-red-300 mt-1">{{ form.errors.content_text }}</div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <Link href="/business/crm/campaigns" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                    Cancel
                </Link>
                <button type="button" class="btn-primary" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving…' : 'Create Campaign' }}
                </button>
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
select.input {
    @apply text-white bg-white/5;
}
select.input option {
    @apply text-slate-900 bg-white;
}
</style>

