<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    leaderboard: Object,
    games: Array,
    promotions: Array,
    qrCodes: Array,
    leaderboardTypes: Object,
    resetFrequencies: Object,
});

const form = useForm({
    name: props.leaderboard.name,
    description: props.leaderboard.description || '',
    type: props.leaderboard.type,
    game_id: props.leaderboard.game_id,
    reset_frequency: props.leaderboard.reset_frequency,
    is_active: props.leaderboard.is_active,
    qr_code_id: props.leaderboard.qr_code_id || null,
    prize_config: props.leaderboard.prize_config || null,
});

const submit = () => {
    form.put(`/business/qrcade/leaderboards/${props.leaderboard.id}`, {
        preserveScroll: true,
    });
};

// Get selected promotion details
const selectedPromotion = computed(() => {
    if (!form.qr_code_id) return null;
    const qrCode = props.qrCodes.find(qr => qr.id === form.qr_code_id);
    return qrCode?.promotion || null;
});
</script>

<template>
    <MainLayout>
        <Head :title="`Edit ${leaderboard.name}`" />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <Link :href="`/business/qrcade/leaderboards/${leaderboard.id}`" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                    ← Back to Rankings
                </Link>
                <h1 class="text-3xl font-bold text-white">Edit Leaderboard</h1>
                <p class="text-gray-400 mt-1">Update leaderboard settings and prizes</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Name</label>
                            <input type="text" v-model="form.name" required
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Weekly Champions">
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-400">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea v-model="form.description" rows="3"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Optional description"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Type</label>
                                <select v-model="form.type"
                                    class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                    <option v-for="(label, type) in leaderboardTypes" :key="type" :value="type" class="bg-gray-800 text-white">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Reset Frequency</label>
                                <select v-model="form.reset_frequency"
                                    class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                    <option v-for="(label, freq) in resetFrequencies" :key="freq" :value="freq" class="bg-gray-800 text-white">
                                        {{ label }}
                                    </option>
                                </select>
                                <p v-if="form.reset_frequency === 'never'" class="mt-2 text-xs text-amber-400 flex items-start gap-1">
                                    <span>⚠️</span>
                                    <span>"Never" means this is an all-time leaderboard for bragging rights only. Prizes are awarded at the end of each period, so an all-time board will not automatically award prizes. Use Daily, Weekly, or Monthly if you want automatic prize awarding.</span>
                                </p>
                            </div>
                        </div>

                        <div v-if="form.type === 'game_specific'">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Game <span class="text-red-400">*</span></label>
                            <select v-model="form.game_id" required
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option :value="null" class="bg-gray-800 text-white">Select a game...</option>
                                <option v-for="game in games" :key="game.id" :value="game.id" class="bg-gray-800 text-white">
                                    {{ game.name }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">This leaderboard will only track scores from the selected game at this location. Scores are not shared with other businesses. Scores will be recorded even if "Enable Leaderboard" is off for that game.</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" v-model="form.is_active" id="is_active"
                                class="w-4 h-4 rounded bg-white/10 border-white/20 text-primary-500">
                            <label for="is_active" class="text-sm text-gray-300">Leaderboard is active</label>
                        </div>
                    </div>
                </div>

                <!-- Prizes & Rewards -->
                <div class="glass-card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Reward Prize</h2>
                    <p class="text-gray-400 text-sm mb-4">Select a Promotion QR code. Top players will win the promotion attached to that QR code.</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Reward Prize (Promotion QR Code Only)</label>
                            <select v-model="form.qr_code_id"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option :value="null" class="bg-gray-800 text-white">No reward prize</option>
                                <option v-for="qr in qrCodes" :key="qr.id" :value="qr.id" class="bg-gray-800 text-white">
                                    {{ qr.name }} ({{ qr.code }}){{ qr.promotion ? ` - ${qr.promotion.name}` : '' }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Top players will win the promotion attached to this QR code</p>
                            <p class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                                <span>💡</span>
                                <span><strong>Tip:</strong> For leaderboard prizes, use a dedicated Promotion QR code (no games attached). This prevents players from winning instantly when they scan to play.</span>
                            </p>
                            <p class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                                <span>💡</span>
                                <span><strong>Best practice:</strong> Use a separate promotion for each prize type. Reusing the same promotion across leaderboard prizes, play-to-win rewards, and tiered prizes can cause redemption limits to overlap unexpectedly.</span>
                            </p>
                        </div>

                        <!-- Promotion Rules Info -->
                        <div v-if="form.qr_code_id" class="mt-4 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                            <div class="flex items-start gap-2 mb-3">
                                <span class="text-blue-400 text-lg">ℹ️</span>
                                <div class="flex-1">
                                    <h4 class="text-blue-300 font-semibold text-sm mb-2">How Leaderboard Prizes Work</h4>
                                    <ul class="text-xs text-gray-300 space-y-1 list-disc list-inside">
                                        <li>Per-user limits apply <strong>per leaderboard period</strong> (users can win each period)</li>
                                        <li>Expiration dates and time restrictions still apply</li>
                                        <li>If you want different rules for leaderboard prizes, create a separate promotion</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Show selected promotion details -->
                            <div v-if="selectedPromotion" class="mt-3 pt-3 border-t border-blue-500/20">
                                <h5 class="text-blue-300 font-medium text-xs mb-2">Selected Promotion Rules:</h5>
                                <div class="text-xs text-gray-400 space-y-1">
                                    <div v-if="selectedPromotion.rules?.max_redemptions_per_user">
                                        <strong>Per-user limit:</strong> {{ selectedPromotion.rules.max_redemptions_per_user }} per period
                                    </div>
                                    <div v-if="selectedPromotion.ends_at">
                                        <strong>Expires:</strong> {{ new Date(selectedPromotion.ends_at).toLocaleDateString() }}
                                    </div>
                                    <div v-if="selectedPromotion.rules?.valid_days && selectedPromotion.rules.valid_days.length > 0">
                                        <strong>Valid days:</strong> {{ selectedPromotion.rules.valid_days.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ') }}
                                    </div>
                                    <div v-if="selectedPromotion.rules?.valid_hours">
                                        <strong>Valid hours:</strong> {{ selectedPromotion.rules.valid_hours.start }} - {{ selectedPromotion.rules.valid_hours.end }}
                                    </div>
                                    <div v-if="selectedPromotion.rules?.max_per_day">
                                        <strong>Daily limit:</strong> {{ selectedPromotion.rules.max_per_day }} per day
                                    </div>
                                    <div v-if="selectedPromotion.rules?.max_redemptions_total">
                                        <strong>Total limit:</strong> {{ selectedPromotion.rules.max_redemptions_total }} total
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <Link :href="`/business/qrcade/leaderboards/${leaderboard.id}`"
                        class="flex-1 px-4 py-2 bg-white/10 text-white text-center rounded-lg hover:bg-white/20">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>

