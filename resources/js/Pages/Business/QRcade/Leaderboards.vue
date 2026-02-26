<script setup>
import { ref, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    leaderboards: Array,
    games: Array,
    qrCodes: Array,
    leaderboardTypes: Object,
    resetFrequencies: Object,
});

const showCreateModal = ref(false);
const deletingId = ref(null);

watch(showCreateModal, (isOpen) => {
    if (!isOpen) return;
    // Ensure the prize list is fresh (handles cached Inertia state).
    router.reload({ only: ['qrCodes'], preserveScroll: true, preserveState: true });
});

const createForm = useForm({
    name: '',
    description: '',
    type: 'location',
    game_id: null,
    reset_frequency: 'weekly',
    is_active: true,
    qr_code_id: null,
    prize_config: null,
});

const submitCreate = () => {
    createForm.post('/business/qrcade/leaderboards', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const deleteLeaderboard = (leaderboardId) => {
    if (confirm('Are you sure you want to delete this leaderboard? This action cannot be undone.')) {
        router.delete(`/business/qrcade/leaderboards/${leaderboardId}`, {
            preserveScroll: true,
            onStart: () => {
                deletingId.value = leaderboardId;
            },
            onFinish: () => {
                deletingId.value = null;
            },
        });
    }
};

const getTypeLabel = (type) => {
    return props.leaderboardTypes[type] || type;
};

const getResetLabel = (freq) => {
    return props.resetFrequencies[freq] || freq;
};

const selectedPromotion = () => {
    if (!createForm.qr_code_id) return null;
    const qr = (props.qrCodes || []).find(q => String(q.id) === String(createForm.qr_code_id));
    return qr?.promotion || null;
};
</script>

<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <Link href="/business/qrcade" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to QRcade
                    </Link>
                    <h1 class="text-3xl font-bold text-white">Leaderboards</h1>
                    <p class="text-gray-400 mt-1">Manage rankings and competitions</p>
                </div>
                <button @click="showCreateModal = true"
                    class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors">
                    + Create Leaderboard
                </button>
            </div>

            <!-- Leaderboards Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="lb in leaderboards" :key="lb.id" class="glass-card p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-white font-semibold text-lg">{{ lb.name }}</h3>
                            <p class="text-gray-400 text-sm mt-1">{{ lb.description }}</p>
                        </div>
                        <span class="px-2 py-1 rounded text-xs font-medium"
                            :class="lb.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-500/20 text-gray-400'">
                            {{ lb.is_active ? 'Active' : 'Paused' }}
                        </span>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Type</span>
                            <span class="text-white">{{ getTypeLabel(lb.type) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Resets</span>
                            <span class="text-white">{{ getResetLabel(lb.reset_frequency) }}</span>
                        </div>
                        <div v-if="lb.game" class="flex justify-between">
                            <span class="text-gray-400">Game</span>
                            <span class="text-white">{{ lb.game.name }}</span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-400">Linked QR Codes</span>
                            <span v-if="lb.qr_code_games && lb.qr_code_games.length" class="text-right">
                                <span v-for="qcg in lb.qr_code_games" :key="qcg.id" class="block text-xs text-primary-400">
                                    {{ qcg.qr_code?.name || 'QR #' + qcg.qr_code_id }}
                                </span>
                            </span>
                            <span v-else class="text-gray-500 text-xs">None yet</span>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4 pt-4 border-t border-white/10">
                        <Link :href="`/business/qrcade/leaderboards/${lb.id}`"
                            class="flex-1 px-3 py-2 bg-white/10 text-white text-center rounded-lg hover:bg-white/20 text-sm">
                            View Rankings
                        </Link>
                        <Link :href="`/business/qrcade/leaderboards/${lb.id}/edit`"
                            class="px-3 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 text-sm"
                            title="Edit">
                            ⚙️
                        </Link>
                        <button 
                            @click="deleteLeaderboard(lb.id)"
                            :disabled="deletingId === lb.id"
                            class="px-3 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 text-sm transition-colors disabled:opacity-50"
                            title="Delete">
                            🗑️
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!leaderboards.length" class="md:col-span-2 lg:col-span-3 glass-card p-12 text-center">
                    <div class="text-4xl mb-4">🏆</div>
                    <h3 class="text-white font-semibold text-lg mb-2">No leaderboards yet</h3>
                    <p class="text-gray-400 mb-4">Create your first leaderboard to track player rankings</p>
                    <button @click="showCreateModal = true"
                        class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                        Create Leaderboard
                    </button>
                </div>
            </div>

            <!-- Leaderboard Ideas -->
            <div class="glass-card p-6 mt-8">
                <h2 class="text-xl font-semibold text-white mb-4">💡 Leaderboard Ideas</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="text-2xl mb-2">📅</div>
                        <h3 class="text-white font-medium">Weekly Champion</h3>
                        <p class="text-gray-400 text-sm">Reset every Monday, give top 3 prizes</p>
                    </div>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="text-2xl mb-2">🎮</div>
                        <h3 class="text-white font-medium">Game Master</h3>
                        <p class="text-gray-400 text-sm">Per-game leaderboard for bragging rights</p>
                    </div>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="text-2xl mb-2">👨‍👩‍👧‍👦</div>
                        <h3 class="text-white font-medium">Family Challenge</h3>
                        <p class="text-gray-400 text-sm">Team mode for family visits</p>
                    </div>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="text-2xl mb-2">🏅</div>
                        <h3 class="text-white font-medium">Beat the Manager</h3>
                        <p class="text-gray-400 text-sm">Challenge mode with special prize</p>
                    </div>
                </div>
            </div>

            <!-- Create Modal -->
            <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
                <div class="glass-card p-6 w-full max-w-2xl mt-6 max-h-[80vh] overflow-y-auto">
                    <h2 class="text-xl font-semibold text-white mb-4">Create Leaderboard</h2>
                    
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                            <input type="text" v-model="createForm.name" required
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Weekly Champions">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                            <textarea v-model="createForm.description" rows="3"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Optional description"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Type</label>
                            <select v-model="createForm.type"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option v-for="(label, type) in leaderboardTypes" :key="type" :value="type" class="bg-gray-800 text-white">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="createForm.type === 'game_specific'">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Game <span class="text-red-400">*</span></label>
                            <p class="text-xs text-gray-500 mb-2">
                                This controls which scores count for this leaderboard.
                                The QR code game determines what players actually play.
                            </p>
                            <select v-model="createForm.game_id" required
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option :value="null" class="bg-gray-800 text-white">Select a game...</option>
                                <option v-for="game in games" :key="game.id" :value="game.id" class="bg-gray-800 text-white">
                                    {{ game.name }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">This leaderboard will only track scores from the selected game.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Reset Frequency</label>
                            <select v-model="createForm.reset_frequency"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option v-for="(label, freq) in resetFrequencies" :key="freq" :value="freq" class="bg-gray-800 text-white">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="createForm.reset_frequency === 'never'" class="mt-2 text-xs text-amber-400 flex items-start gap-1">
                                <span>⚠️</span>
                                <span>"Never" means this is an all-time leaderboard for bragging rights only. Prizes are awarded at the end of each period, so an all-time board will not automatically award prizes. Use Daily, Weekly, or Monthly if you want automatic prize awarding.</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" v-model="createForm.is_active" id="create_is_active"
                                class="w-4 h-4 rounded bg-white/10 border-white/20 text-primary-500">
                            <label for="create_is_active" class="text-sm text-gray-300">Leaderboard is active</label>
                        </div>

                        <!-- Prize QR (Promotion QR codes only) -->
                        <div class="pt-2 border-t border-white/10">
                            <h3 class="text-white font-semibold mb-2">Reward Prize</h3>
                            <p class="text-gray-400 text-sm mb-3">
                                Select a Promotion QR code. Winners will receive the promotion attached to that QR code when the period ends.
                            </p>

                            <label class="block text-sm font-medium text-gray-300 mb-1">Reward Prize (Promotion QR Code Only)</label>
                            <select v-model="createForm.qr_code_id"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                <option :value="null" class="bg-gray-800 text-white">No reward prize</option>
                                <option v-for="qr in (qrCodes || [])" :key="qr.id" :value="qr.id" class="bg-gray-800 text-white">
                                    {{ qr.name }} ({{ qr.code }}){{ qr.promotion ? ` - ${qr.promotion.name}` : '' }}
                                </option>
                            </select>
                            <p class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                                <span>💡</span>
                                <span><strong>Tip:</strong> Use a dedicated Promotion QR code (no games attached) as the prize QR.</span>
                            </p>

                            <div v-if="createForm.qr_code_id && selectedPromotion()" class="mt-4 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                                <h4 class="text-blue-300 font-semibold text-sm mb-2">Selected Promotion Rules</h4>
                                <div class="text-xs text-gray-300 space-y-1">
                                    <div v-if="selectedPromotion()?.rules?.max_redemptions_per_user">
                                        <strong>Per-user limit:</strong> {{ selectedPromotion().rules.max_redemptions_per_user }} per period
                                    </div>
                                    <div v-if="selectedPromotion()?.ends_at">
                                        <strong>Expires:</strong> {{ new Date(selectedPromotion().ends_at).toLocaleDateString() }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showCreateModal = false"
                                class="flex-1 px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20">
                                Cancel
                            </button>
                            <button type="submit" :disabled="createForm.processing"
                                class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>

