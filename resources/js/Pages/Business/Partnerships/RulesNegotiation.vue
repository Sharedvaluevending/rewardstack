<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    crossPromo: Object,
    partner: Object,
    promotion1: Object,
    promotion2: Object,
    conflicts: Array,
    merged_rules: Object,
});

const form = useForm({
    agreed_rules: props.merged_rules || {},
});

const submitAgreement = () => {
    form.post(`/business/partnerships/cross-promo/${props.crossPromo.id}/rules/agree`, {
        onSuccess: () => {
            // Redirect back to partnerships
        },
    });
};
</script>

<template>
    <Head :title="`Rules Negotiation: ${crossPromo.name}`" />

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/partnerships" class="text-gray-400 hover:text-white mb-2 inline-block">
                ← Back to Partnerships
            </Link>
            <h1 class="text-3xl font-bold text-white">Rules Negotiation</h1>
            <p class="text-gray-400 mt-1">{{ crossPromo.name }} — Partner: {{ partner.name }}</p>
        </div>

        <!-- Conflicts Warning -->
        <div v-if="conflicts.length > 0" class="glass-card p-6 mb-6 border-amber-500/30 bg-amber-500/10">
            <h3 class="text-lg font-semibold text-amber-400 mb-3">⚠️ Rules Conflicts Detected</h3>
            <div class="space-y-2">
                <div v-for="(conflict, idx) in conflicts" :key="idx" class="text-sm text-gray-300">
                    <strong>{{ conflict.type }}:</strong> {{ conflict.message }}
                    <span v-if="conflict.promo1 && conflict.promo2">
                        ({{ conflict.promo1 }} vs {{ conflict.promo2 }})
                    </span>
                </div>
            </div>
        </div>

        <!-- Promotion Rules Comparison -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Promotion 1 Rules -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">{{ promotion1?.name }}</h3>
                <div class="space-y-3 text-sm">
                    <div v-if="promotion1?.rules_formatted?.valid_days">
                        <strong class="text-gray-300">Valid Days:</strong>
                        <span class="text-white ml-2">{{ promotion1.rules_formatted.valid_days.join(', ') }}</span>
                    </div>
                    <div v-if="promotion1?.rules_formatted?.valid_hours">
                        <strong class="text-gray-300">Valid Hours:</strong>
                        <span class="text-white ml-2">{{ promotion1.rules_formatted.valid_hours }}</span>
                    </div>
                    <div v-if="promotion1?.rules_formatted?.max_redemptions_per_user">
                        <strong class="text-gray-300">Max Per User:</strong>
                        <span class="text-white ml-2">{{ promotion1.rules_formatted.max_redemptions_per_user }}</span>
                    </div>
                </div>
            </div>

            <!-- Promotion 2 Rules -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">{{ promotion2?.name }}</h3>
                <div class="space-y-3 text-sm">
                    <div v-if="promotion2?.rules_formatted?.valid_days">
                        <strong class="text-gray-300">Valid Days:</strong>
                        <span class="text-white ml-2">{{ promotion2.rules_formatted.valid_days.join(', ') }}</span>
                    </div>
                    <div v-if="promotion2?.rules_formatted?.valid_hours">
                        <strong class="text-gray-300">Valid Hours:</strong>
                        <span class="text-white ml-2">{{ promotion2.rules_formatted.valid_hours }}</span>
                    </div>
                    <div v-if="promotion2?.rules_formatted?.max_redemptions_per_user">
                        <strong class="text-gray-300">Max Per User:</strong>
                        <span class="text-white ml-2">{{ promotion2.rules_formatted.max_redemptions_per_user }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Merged Rules Proposal -->
        <div class="glass-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Proposed Merged Rules</h3>
            <form @submit.prevent="submitAgreement" class="space-y-4">
                <!-- Valid Days -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Valid Days</label>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="day in ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']" 
                            :key="day"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 cursor-pointer"
                            :class="{ 'bg-primary-500/20 border border-primary-500/30': form.agreed_rules.valid_days?.includes(day) }">
                            <input type="checkbox" 
                                :value="day" 
                                v-model="form.agreed_rules.valid_days"
                                class="text-primary-500 focus:ring-primary-500" />
                            <span class="text-white text-sm capitalize">{{ day }}</span>
                        </label>
                    </div>
                </div>

                <!-- Valid Hours -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Start Time</label>
                        <input type="time" 
                            v-model="form.agreed_rules.valid_hours.start"
                            class="input-glass w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">End Time</label>
                        <input type="time" 
                            v-model="form.agreed_rules.valid_hours.end"
                            class="input-glass w-full" />
                    </div>
                </div>

                <!-- Usage Limits -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Max Per User</label>
                        <input type="number" 
                            v-model.number="form.agreed_rules.max_redemptions_per_user"
                            min="0"
                            class="input-glass w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Max Per Day</label>
                        <input type="number" 
                            v-model.number="form.agreed_rules.max_per_day"
                            min="0"
                            class="input-glass w-full" />
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <Link href="/business/partnerships" class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20 text-center">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600 disabled:opacity-50">
                        Agree on Rules
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
