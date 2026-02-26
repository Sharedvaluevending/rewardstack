<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    steps: {
        type: Array,
        default: () => [],
    },
    upsellSteps: {
        type: Array,
        default: () => [],
    },
    progress: {
        type: Object,
        default: () => ({ completed: 0, total: 0, percentage: 0 }),
    },
    showOnboarding: {
        type: Boolean,
        default: false,
    },
});

const isCollapsed = ref(false);
const isDismissed = ref(!props.showOnboarding);
const showUpsell = ref(false);

const completedCount = computed(() => {
    return props.steps.filter(step => step.is_completed).length;
});

const allComplete = computed(() => {
    return props.steps.length > 0 && completedCount.value === props.steps.length;
});

const completeStep = async (stepId) => {
    try {
        const response = await fetch('/business/onboarding/complete-step', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ step_id: stepId }),
        });

        if (response.ok) {
            // Reload page to update progress
            router.reload({ only: ['onboardingSteps', 'onboardingUpsellSteps', 'onboardingProgress'] });
        }
    } catch (error) {
        console.error('Failed to complete step:', error);
    }
};

const dismiss = async () => {
    try {
        const response = await fetch('/business/onboarding/dismiss', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        if (response.ok) {
            isDismissed.value = true;
        }
    } catch (error) {
        console.error('Failed to dismiss:', error);
    }
};

const reopen = async () => {
    try {
        const response = await fetch('/business/onboarding/reopen', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        if (response.ok) {
            isDismissed.value = false;
            router.reload({ only: ['showOnboarding'] });
        }
    } catch (error) {
        console.error('Failed to reopen:', error);
    }
};

const goToUpgrade = () => {
    router.visit('/business/billing');
};
</script>

<template>
    <!-- Onboarding Checklist Widget -->
    <div v-if="showOnboarding && !isDismissed" class="glass-card p-6 mb-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-semibold text-white">Getting Started</h3>
                <span class="px-2 py-1 bg-primary-500/20 text-primary-400 text-xs rounded-full">
                    {{ completedCount }}/{{ progress.total }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="isCollapsed = !isCollapsed"
                    class="p-1 rounded-lg hover:bg-white/10 transition-colors"
                >
                    <svg 
                        class="w-5 h-5 text-gray-400 transition-transform"
                        :class="{ 'rotate-180': isCollapsed }"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <button
                    @click="dismiss"
                    class="p-1 rounded-lg hover:bg-white/10 transition-colors"
                    title="Dismiss"
                >
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-gray-400">Progress</span>
                <span class="text-white font-medium">{{ progress.percentage }}%</span>
            </div>
            <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
                <div 
                    class="h-full bg-gradient-to-r from-primary-500 to-accent-500 rounded-full transition-all duration-500"
                    :style="{ width: `${progress.percentage}%` }"
                ></div>
            </div>
        </div>

        <!-- All Complete Celebration -->
        <div v-if="allComplete && !isCollapsed" class="text-center py-4 mb-2">
            <div class="text-3xl mb-2">&#127881;</div>
            <p class="text-emerald-400 font-semibold">You're all set!</p>
            <p class="text-gray-400 text-sm mt-1">You've completed all the getting started steps for your plan.</p>
        </div>

        <!-- Steps List -->
        <div v-if="!isCollapsed" class="space-y-3">
            <div
                v-for="step in steps"
                :key="step.id"
                class="p-3 rounded-lg border transition-colors"
                :class="[
                    step.is_completed 
                        ? 'bg-emerald-500/10 border-emerald-500/30' 
                        : 'bg-white/5 border-white/10 hover:bg-white/10'
                ]"
            >
                <div class="flex items-start gap-3">
                    <!-- Checkbox -->
                    <div class="flex-shrink-0 mt-0.5">
                        <div 
                            v-if="step.is_completed"
                            class="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center"
                        >
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div 
                            v-else
                            class="w-5 h-5 rounded-full border-2 border-gray-500"
                        ></div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <h4 
                                    class="text-sm font-medium mb-1"
                                    :class="step.is_completed ? 'text-emerald-400' : 'text-white'"
                                >
                                    {{ step.title }}
                                </h4>
                                <p class="text-xs text-gray-400 mb-2">{{ step.description }}</p>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-2">
                            <Link
                                v-if="!step.is_completed"
                                :href="step.route"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-500/20 hover:bg-primary-500/30 text-primary-400 text-xs font-medium rounded-lg transition-colors"
                            >
                                Go
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </Link>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-500/20 text-emerald-400 text-xs font-medium rounded-lg"
                            >
                                &#10003; Completed
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="steps.length === 0" class="text-center py-4">
                <p class="text-gray-500 text-sm">No onboarding steps available</p>
            </div>
        </div>

        <!-- Collapsed State -->
        <div v-if="isCollapsed" class="text-center py-2">
            <p class="text-gray-400 text-sm">
                {{ completedCount }} of {{ progress.total }} steps completed
            </p>
        </div>

        <!-- Upsell Section (separate from main checklist) -->
        <div v-if="!isCollapsed && upsellSteps.length > 0" class="mt-5 pt-4 border-t border-white/10">
            <button 
                @click="showUpsell = !showUpsell"
                class="w-full flex items-center justify-between text-sm text-gray-400 hover:text-gray-300 transition-colors"
            >
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Unlock {{ upsellSteps.length }} more feature{{ upsellSteps.length > 1 ? 's' : '' }} with an upgrade</span>
                </span>
                <svg 
                    class="w-4 h-4 transition-transform"
                    :class="{ 'rotate-180': showUpsell }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-if="showUpsell" class="mt-3 space-y-2">
                <div
                    v-for="upsell in upsellSteps"
                    :key="upsell.id"
                    class="p-3 rounded-lg bg-gray-700/30 border border-gray-600/30"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-5 h-5 rounded-full bg-gray-600 flex items-center justify-center">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-gray-400">{{ upsell.title }}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">{{ upsell.description }}</p>
                            <span class="inline-block mt-1.5 px-2 py-0.5 bg-accent-500/20 text-accent-400 text-xs rounded-full">
                                {{ upsell.upsell_tier }} plan
                            </span>
                        </div>
                    </div>
                </div>

                <button
                    @click="goToUpgrade"
                    class="w-full mt-2 px-4 py-2 bg-gradient-to-r from-accent-500/20 to-primary-500/20 hover:from-accent-500/30 hover:to-primary-500/30 text-accent-400 text-sm font-medium rounded-lg transition-colors flex items-center justify-center gap-2 border border-accent-500/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    View Plans & Upgrade
                </button>
            </div>
        </div>
    </div>

    <!-- Reopen Button (if dismissed) -->
    <div v-else-if="isDismissed" class="glass-card p-4 mb-6">
        <button
            @click="reopen"
            class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-lg transition-colors text-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reopen Getting Started Checklist
        </button>
    </div>
</template>
