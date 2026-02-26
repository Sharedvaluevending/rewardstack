<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    stats: Object,
    milestones: Array,
    topReferrers: Array,
    pendingPayouts: Array,
    recentReferrals: Array,
    stripeConnect: Object,
    referralBufferDays: Number,
});

const selectedPayout = ref(null);
const showPayoutModal = ref(false);

const payoutForm = useForm({
    transaction_id: '',
    notes: '',
});

const approveForm = useForm({});
const autoPayoutForm = useForm({});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

const openPayoutModal = (payout) => {
    selectedPayout.value = payout;
    showPayoutModal.value = true;
    payoutForm.reset();
};

const markAsPaid = () => {
    payoutForm.post(`/admin/referrals/payouts/${selectedPayout.value.id}/paid`, {
        onSuccess: () => {
            showPayoutModal.value = false;
            selectedPayout.value = null;
        },
    });
};

</script>

<template>
    <Head title="Referral Army" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    💰 Referral Army HQ
                </h1>
                <p class="text-gray-400 mt-1">Manage your affiliate army and payouts</p>
            </div>
            <Link href="/admin/referrals/payouts" class="btn-secondary">
                View All Payouts
            </Link>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="glass-card p-4">
                <div class="text-3xl font-bold text-purple-400">{{ stats.total_referrers }}</div>
                <div class="text-gray-400 text-sm">Total Referrers</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-3xl font-bold text-emerald-400">{{ stats.total_referrals }}</div>
                <div class="text-gray-400 text-sm">Businesses Referred</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-3xl font-bold text-amber-400">{{ formatCurrency(stats.total_commissions_paid) }}</div>
                <div class="text-gray-400 text-sm">Total Paid Out</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-3xl font-bold text-red-400">{{ formatCurrency(stats.pending_commissions) }}</div>
                <div class="text-gray-400 text-sm">Pending Commissions</div>
            </div>
        </div>

        <!-- Pending Payouts Alert -->
        <div v-if="pendingPayouts.length" class="glass-card p-4 border-2 border-amber-500/50 bg-amber-500/10">
            <h2 class="text-lg font-semibold text-amber-400 mb-4 flex items-center gap-2">
                ⚠️ Pending Payouts ({{ pendingPayouts.length }}) - {{ formatCurrency(stats.pending_payout_amount) }}
            </h2>
            <div class="space-y-3">
                <div v-for="payout in pendingPayouts" :key="payout.id"
                    class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold">
                            {{ payout.user?.name?.charAt(0) || '?' }}
                        </div>
                        <div>
                            <div class="text-white font-medium">{{ payout.user?.name }}</div>
                            <div class="text-gray-400 text-sm">{{ payout.method }}: {{ payout.destination }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="text-emerald-400 font-bold text-lg">{{ formatCurrency(payout.amount) }}</div>
                            <div class="text-gray-500 text-xs">{{ new Date(payout.requested_at).toLocaleDateString() }}</div>
                        </div>
                        <button @click="openPayoutModal(payout)"
                            class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 text-sm font-medium">
                            Mark Paid
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Milestones -->
        <div v-if="milestones?.length" class="glass-card p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                🎯 Program Milestones
            </h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="(m, i) in milestones" :key="i"
                    class="p-4 rounded-xl"
                    :class="m.completed ? 'bg-emerald-500/20 border border-emerald-500/30' : 'bg-white/5'">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl flex-shrink-0">{{ m.completed ? '✓' : '○' }}</span>
                        <div class="min-w-0">
                            <div class="text-white font-medium text-sm">{{ m.name }}</div>
                            <div class="text-gray-400 text-xs mt-1">
                                {{ m.target >= 100 ? formatCurrency(m.current) : m.current }} / {{ m.target >= 100 ? formatCurrency(m.target) : m.target }}
                            </div>
                            <div v-if="m.action" class="text-amber-300 text-xs mt-2">{{ m.action }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Reference Cards -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Payouts & Automation -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    💳 Payouts & Automation
                </h2>
                <div class="space-y-4 text-sm text-gray-300">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                        <div>
                            <div class="text-white font-medium">Stripe Connect</div>
                            <div class="text-gray-400 text-xs">Auto-payouts to bank accounts</div>
                        </div>
                        <span :class="stripeConnect?.enabled ? 'text-emerald-400' : 'text-gray-500'">
                            {{ stripeConnect?.enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                        <div>
                            <div class="text-white font-medium">Auto payout schedule</div>
                            <div class="text-gray-400 text-xs">Config-driven schedule</div>
                        </div>
                        <span class="text-gray-300 capitalize">{{ stripeConnect?.payout_schedule || 'manual' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                        <div>
                            <div class="text-white font-medium">Auto payout minimum</div>
                            <div class="text-gray-400 text-xs">Minimum balance to send</div>
                        </div>
                        <span class="text-gray-300">{{ formatCurrency(stripeConnect?.auto_payout_minimum || 25) }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300">
                        Buffer: commissions approve after {{ referralBufferDays || 30 }} days from payment to reduce churn/cancellations.
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        @click="approveForm.post('/admin/referrals/approve-commissions')"
                        class="btn-secondary"
                    >
                        Approve {{ referralBufferDays || 30 }}‑day Commissions
                    </button>
                    <button
                        @click="autoPayoutForm.post('/admin/referrals/payouts/run')"
                        :disabled="!stripeConnect?.enabled"
                        class="btn-secondary"
                    >
                        Run Auto Payouts Now
                    </button>
                </div>
            </div>

            <!-- Tax/Legal Reference -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    📋 Tax & Legal Checklist
                </h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-xl"
                        :class="stats.total_commissions_paid < 500 ? 'bg-emerald-500/20 border border-emerald-500/30' : 'bg-white/5'">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">💵</span>
                            <div>
                                <div class="text-white font-medium">$0 - $500 Paid</div>
                                <div class="text-gray-400 text-sm">Stripe tracks payouts automatically</div>
                            </div>
                        </div>
                        <span class="text-emerald-400">✓</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl"
                        :class="stats.total_commissions_paid >= 500 && stats.total_commissions_paid < 5000 ? 'bg-amber-500/20 border border-amber-500/30' : 'bg-white/5'">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📊</span>
                            <div>
                                <div class="text-white font-medium">$500+ Paid</div>
                                <div class="text-gray-400 text-sm">Stripe Connect keeps payout history</div>
                            </div>
                        </div>
                        <span v-if="stats.total_commissions_paid >= 500" class="text-amber-400">⚠️</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📄</span>
                            <div>
                                <div class="text-white font-medium">$600+ to One Person</div>
                                <div class="text-gray-400 text-sm">Stripe Connect handles 1099 for US</div>
                            </div>
                        </div>
                        <span class="text-gray-500">📌</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl"
                        :class="stats.total_commissions_paid >= 5000 ? 'bg-red-500/20 border border-red-500/30' : 'bg-white/5'">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">⚖️</span>
                            <div>
                                <div class="text-white font-medium">$5k+/mo Total</div>
                                <div class="text-gray-400 text-sm">Review affiliate terms & compliance</div>
                            </div>
                        </div>
                        <span v-if="stats.total_commissions_paid >= 5000" class="text-red-400">⚠️</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Top Referrers -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">🏆 Top Referrers</h2>
                <div v-if="topReferrers.length" class="space-y-3">
                    <Link v-for="(referrer, index) in topReferrers" :key="referrer.id"
                        :href="`/admin/referrals/referrer/${referrer.id}`"
                        class="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                                :class="[
                                    index === 0 ? 'bg-amber-500 text-black' :
                                    index === 1 ? 'bg-gray-300 text-black' :
                                    index === 2 ? 'bg-amber-700 text-white' :
                                    'bg-white/10 text-gray-400'
                                ]">
                                {{ index + 1 }}
                            </div>
                            <div>
                                <div class="text-white font-medium">{{ referrer.name }}</div>
                                <div class="text-gray-500 text-xs">{{ referrer.email }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-purple-400 font-bold">{{ referrer.referral_count }} refs</div>
                            <div class="text-emerald-400 text-sm">{{ formatCurrency(referrer.total_earned) }}</div>
                        </div>
                    </Link>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    No referrals yet
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">📊 Recent Referrals</h2>
                <div v-if="recentReferrals.length" class="space-y-3">
                    <div v-for="referral in recentReferrals" :key="referral.id"
                        class="flex items-center justify-between p-3 rounded-xl bg-white/5">
                        <div>
                            <div class="text-white font-medium">{{ referral.business?.name }}</div>
                            <div class="text-gray-400 text-sm">by {{ referral.referrer?.name }}</div>
                            <div class="flex gap-1 mt-1">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                    :class="referral.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : referral.status === 'cancelled' ? 'bg-red-500/20 text-red-400' : 'bg-gray-500/20 text-gray-400'">
                                    {{ referral.status }}
                                </span>
                                <span v-if="referral.business?.subscription_status" class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                    :class="referral.business.subscription_status === 'active' ? 'bg-blue-500/20 text-blue-400' : referral.business.subscription_status === 'canceled' ? 'bg-red-500/20 text-red-400' : 'bg-amber-500/20 text-amber-400'">
                                    sub: {{ referral.business.subscription_status }}
                                </span>
                                <span v-if="referral.business?.subscription_cancel_at_period_end" class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/20 text-amber-400">
                                    cancelling
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-emerald-400 text-sm">{{ referral.commission_rate }}%</div>
                            <div class="text-gray-500 text-xs">{{ new Date(referral.created_at).toLocaleDateString() }}</div>
                            <div v-if="referral.business?.subscription_tier" class="text-gray-500 text-xs">{{ referral.business.subscription_tier }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    No referrals yet
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Modal -->
    <div v-if="showPayoutModal" class="fixed inset-0 bg-black/80 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-md border border-white/10">
            <h3 class="text-xl font-bold text-white mb-4">Mark Payout as Paid</h3>
            
            <div class="mb-4 p-4 rounded-xl bg-white/5">
                <div class="text-gray-400 text-sm">Paying</div>
                <div class="text-white font-medium">{{ selectedPayout?.user?.name }}</div>
                <div class="text-2xl font-bold text-emerald-400 mt-2">{{ formatCurrency(selectedPayout?.amount) }}</div>
                <div class="text-gray-400 text-sm mt-1">via {{ selectedPayout?.method }} to {{ selectedPayout?.destination }}</div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Transaction ID (optional)</label>
                <input v-model="payoutForm.transaction_id"
                    type="text"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white"
                    placeholder="PayPal/Venmo transaction ID"
                />
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Notes (optional)</label>
                <textarea v-model="payoutForm.notes"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white"
                    rows="2"
                    placeholder="Any notes about this payout"
                ></textarea>
            </div>

            <div class="flex gap-3">
                <button @click="showPayoutModal = false"
                    class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20">
                    Cancel
                </button>
                <button @click="markAsPaid"
                    :disabled="payoutForm.processing"
                    class="flex-1 py-3 bg-emerald-500 text-white font-semibold rounded-xl hover:bg-emerald-600 disabled:opacity-50">
                    {{ payoutForm.processing ? 'Processing...' : '✓ Mark as Paid' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
</style>
