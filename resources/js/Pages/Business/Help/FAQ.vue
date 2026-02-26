<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    business: { type: Object, default: null },
    lastUpdated: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const open = ref(null);
const toggle = (key) => {
    open.value = open.value === key ? null : key;
};

const sections = computed(() => ([
    {
        key: 'quickstart',
        title: 'Quick Start (in 10 minutes)',
        items: [
            'Create at least 1 Promotion (ex: % off, BOGO, Buy X Get Y, X for $Y, Punch Card, Happy Hour).',
            'Create 1 QR Code and attach that promotion.',
            'Print/Download the QR code and place it where customers will scan.',
            'Have staff use Staff Redemption (Scan & Redeem) to redeem customer codes (no POS required).',
            'Watch your Dashboard + Analytics update after scans and redemptions.',
        ],
    },
    {
        key: 'navigation',
        title: 'Business Navigation (what each page does)',
        items: [
            'Dashboard: high-level performance, onboarding checklist, and quick stats.',
            'Promotions: create/edit offers, set rules (limits, days/times, expiry, stacking). Use Templates or Ideas for inspiration.',
            'QR Codes: generate printable QRs. Types include: Promotion, QRcade (game), QRcade Leaderboard, Level Exclusive, Partner Deal Chain, Stackable, Merch Referral (Ambassador), and more.',
            'QRcade: attach games to QR codes, manage game schedules, configure rewards, run leaderboards, and view game analytics.',
            'Network: Partnerships (find + connect with other businesses), Cross-Promotions (deal chains), and Stackable Deal Pools.',
            'CRM: Customer list, Segments (group customers by behavior), Email Campaigns, Automations (win-back, re-engagement), and AI Recommendations.',
            'Analytics: overview + finance breakdown, scan/redemption charts, partnership analytics, merch analytics, and exportable reports.',
            'AI Insights: AI-generated suggestions for improving promotions, timing, and customer engagement.',
            'Print & Shop: Print Studio (generate print-ready layouts), Sticker Kits (order printed QR stickers), Merch Store (branded items).',
            'Manage: Employees (staff access + invites), Billing (subscription), Settings (logo, business info).',
        ],
    },
    {
        key: 'qr-codes',
        title: 'QR Codes (types, creation, scanning)',
        items: [
            'A "scan" is only counted when someone physically scans the business QR code. Viewing/redeeming a customer code is NOT a scan.',
            'If a scan attempt is blocked because the customer reached the per-customer limit, we do NOT record that scan (keeps analytics clean).',
            'QR Code Types:',
            'Promotion: links to a single promotion. Customer scans, gets the offer, staff redeems their code.',
            'QRcade (Game): links to a game with optional instant-win reward. Customer scans, plays, wins immediately.',
            'QRcade Leaderboard: links to a game AND a specific leaderboard. Customer scans, plays, score goes to that leaderboard. Prizes are awarded when the leaderboard period ends.',
            'Level Exclusive: only accessible to customers who have reached a specific level (earned through XP from playing games).',
            'Partner Deal Chain (Cross-Promo): links to a cross-promotion with a partner business. Customer unlocks both deals.',
            'Stackable (Revenue QR): participates in a shared deal pool. Customer scans multiple business QRs to unlock stacked deals.',
            'Merch Referral (Ambassador): a QR code placed on branded merchandise (stickers, shirts, etc.). When new customers scan it, the merch owner earns ambassador rewards.',
        ],
    },
    {
        key: 'staff-redemption',
        title: 'Staff Redemption (how redemption works without POS)',
        items: [
            'Customers show a short customer code / QR in their portal.',
            'Staff enters/scans that customer code on the Staff Redemption page.',
            'The system validates redemption rules (time/day limits, expiry, per-customer limit, etc.).',
            'If valid, redemption is recorded with financial breakdown (purchase amount, original amount, customer saved).',
            'If invalid, staff sees a clear "Cannot Redeem" reason (not a server error).',
        ],
    },
    {
        key: 'limits',
        title: 'Limits & rules (Max per customer, portal stacking, days/times, expiry)',
        items: [
            'Max per customer: controlled by promotion rule "Max Per Customer" (max_redemptions_per_user).',
            'If Max per customer is blank/0, it is treated as unlimited.',
            'Valid Days: promotions can be restricted to specific days of the week.',
            'Happy Hour / Valid Hours: promotions can only be redeemed within the configured time window (supports overnight windows).',
            'Expiry: promotions can have start/end dates; once expired, they show as unavailable and cannot be redeemed.',
            'Portal stacking ("Allow multiple active scans"): when enabled, customers can hold multiple active scan tokens for that promo until their max-per-customer limit is reached.',
        ],
    },
    {
        key: 'promotion-types',
        title: 'Promotion types (how the discount math is tracked)',
        items: [
            'Percent/Amount Off: staff enters purchase amount; system computes savings.',
            'BOGO: tracks paid + free value correctly (original = paid + free; savings = free item value).',
            'Buy X Get Y: supports individual item prices (buy and get) for accurate original + savings.',
            'X for $Y: supports itemized prices / original amount so staff redemption can auto-fill + compute breakdown.',
            'Punch Cards: regular punches record "Money Collected (Sale Amount)" and show savings as $0; final free punch records free item value as savings.',
        ],
    },
    {
        key: 'qrcade',
        title: 'QRcade (Step-by-step: Instant Win vs Leaderboards)',
        items: [
            'Step 1: Enable games \u2014 Business > QRcade > Manage Games. Only enabled games show up when creating QRcade QR codes.',
            'Step 2: Choose your mode: (A) Instant Win (one-off) or (B) Leaderboard Challenge (daily/weekly/monthly winners).',
            '',
            'INSTANT WIN (one-off) setup:',
            '1) Create QR Code > Type: QRcade Gaming.',
            '2) Select the game(s) players will play.',
            '3) Set "Win Reward" to the promotion you want players to win immediately.',
            'Result: Customer scans > plays > wins the promotion immediately. No leaderboard involved.',
            '',
            'LEADERBOARD CHALLENGE setup:',
            '1) Go to QRcade > Leaderboards > Create Leaderboard.',
            '2) Choose the game, reset frequency (daily/weekly/monthly), and optionally attach a prize promotion.',
            '3) Create QR Code > Type: QRcade Leaderboard.',
            '4) In the "Link to Leaderboard" dropdown, select the leaderboard you just created. The game is auto-selected.',
            'Result: Customer scans > plays > score goes to that specific leaderboard. Winners get prizes when the period ends.',
            '',
            'KEY POINTS:',
            'Each leaderboard QR code is explicitly linked to ONE leaderboard. This means you can have the same game on multiple leaderboards (e.g., daily + weekly) without conflict \u2014 each QR feeds its own leaderboard.',
            'Leaderboard types: "This Location" (all games count) or "Per Game" (only the selected game counts).',
            'Reset frequencies: Daily, Weekly, Monthly, or Never (all-time).',
            'Prizes are set on the leaderboard itself (not on the QR code). Top players receive the prize when the period ends.',
            'The Leaderboards page shows which QR codes are linked to each leaderboard, so you always know what feeds where.',
            'Just for Fun mode: If a player hits the reward limit, they can still play but no XP/leaderboard/prize tracking is recorded.',
        ],
    },
    {
        key: 'merch-referral',
        title: 'Merch Referral / Ambassador Program',
        items: [
            'Merch Referral QR codes turn your branded merchandise into a customer acquisition channel.',
            'How it works:',
            '1) Create a Promotion that acts as the "gateway" (the offer new customers get when they scan the merch QR).',
            '2) Create QR Code > Type: Merch Referral. Select the gateway promotion, set the ambassador reward type (percent off, amount off, or free item), and the number of redemptions required before the reward unlocks.',
            '3) Print the QR code on merchandise (stickers, shirts, hats, bags, etc.) or generate Merch Tags for individual items.',
            'When a new customer scans the QR from someone\'s merch, they get the gateway promotion. The merch owner (ambassador) earns progress toward their reward.',
            'Ambassador rewards unlock after the configured number of referral redemptions (e.g., "Refer 5 friends, get a free coffee").',
            'Merch Tags: unique QR codes tied to individual merch items. Each tag tracks scans separately, so you know which items drive the most traffic.',
            'View merch performance in Analytics > Merch Analytics.',
            'Requires Growth plan or higher.',
        ],
    },
    {
        key: 'cross-promos',
        title: 'Cross-Promotions / Partner Deals',
        items: [
            'Cross-Promotions let two businesses create a "deal chain" \u2014 customers get offers from both businesses.',
            'How to set up:',
            '1) Go to Network > Partnerships and search for a partner business.',
            '2) Send a partnership request. Once accepted, create a Cross-Promotion.',
            '3) Each business selects their promotion for the deal chain.',
            '4) Both businesses agree on rules (revenue share, display mode).',
            '5) Create QR Code > Type: Partner Deal Chain and select the cross-promotion.',
            'When a customer scans, they see both offers and can unlock the partner deal.',
            'Track cross-promo performance in the Partner Analytics page.',
        ],
    },
    {
        key: 'stackable',
        title: 'Stackable Deals (Revenue QR / Deal Pools)',
        items: [
            'Stackable QR codes participate in a shared deal pool that spans multiple businesses.',
            'Customers scan QR codes from different businesses to stack deals and unlock combined savings.',
            'Setup: Create QR Code > Type: Stackable. The QR automatically joins the Revenue QR deal pool.',
            'Manage your pool participation in Network > Deal Pools.',
            'Requires Growth plan or higher.',
        ],
    },
    {
        key: 'level-exclusive',
        title: 'Level Exclusive QR Codes',
        items: [
            'Level Exclusive QR codes are only accessible to customers who have reached a specific player level.',
            'Players earn XP by playing QRcade games. As they level up, they unlock access to higher-tier QR codes.',
            'Setup: Create QR Code > Type: Level Exclusive. Set the minimum level required and attach a promotion.',
            'If a customer scans a Level Exclusive QR but has not reached the required level, they see a "level required" message.',
            'Great for VIP offers, loyalty rewards, or gamified progression.',
        ],
    },
    {
        key: 'crm',
        title: 'CRM (Customer Management)',
        items: [
            'CRM gives you tools to manage customer relationships beyond the point of scan.',
            'Customers: view all customers who have interacted with your business, their scan/redemption history, and engagement metrics.',
            'Segments: group customers by behavior (e.g., "lapsed 30+ days", "top spenders", "new this week") for targeted campaigns.',
            'Campaigns: create and send email campaigns to customer segments with personalized offers.',
            'Automations: set up automated actions like win-back emails for lapsed customers or re-engagement nudges.',
            'Recommendations: AI-generated suggestions for reaching specific customer groups.',
        ],
    },
    {
        key: 'ai-insights',
        title: 'AI Insights',
        items: [
            'AI Insights analyzes your business data and provides actionable recommendations.',
            'Basic Insights: quick tips based on your recent scan/redemption patterns, best-performing promotions, and timing.',
            'Advanced Insights: deeper analysis including customer behavior trends, promotion optimization, and competitive positioning.',
            'Generate new insights on-demand from the AI Insights page.',
        ],
    },
    {
        key: 'analytics',
        title: 'Analytics (what the charts mean)',
        items: [
            'Scans Over Time: bar chart of real QR scans (not portal views/redemptions).',
            'Redemptions Over Time: bar chart of completed redemptions.',
            'Finance: Revenue vs Cost Over Time and per-promo ROI/efficiency.',
            'Hourly Activity: distribution of activity by hour (useful for staffing + promo timing).',
            'Partnership Analytics: track cross-promo and partner deal performance.',
            'Merch Analytics: track ambassador/merch referral scans, conversions, and rewards unlocked.',
            'Reports: exportable summaries for a date range.',
        ],
    },
    {
        key: 'employees',
        title: 'Employees (staff access)',
        items: [
            'Add employees from the Employees page.',
            'Employees can use Staff Redemption and view their activity.',
            'Business owners can revoke employees and resend invites.',
        ],
    },
    {
        key: 'billing',
        title: 'Billing (trial, subscription, and access)',
        items: [
            'Billing stays accessible even if trial/subscription expires (so you can resubscribe).',
            'Most business pages require an active subscription.',
            'Some features (Stackable, Merch Referral, Advanced AI) require Growth plan or higher.',
        ],
    },
    {
        key: 'troubleshooting',
        title: 'Troubleshooting (common issues)',
        items: [
            '"Cannot Redeem": read the reason (time/day window, limit reached, expired, inactive offer).',
            'If a customer hits their limit: they see a clear message, and the attempt is not counted in analytics.',
            'If a promo was deleted/inactivated: user history may show "Offer No Longer Available."',
            'Leaderboard scores not showing: verify the QR code is linked to the correct leaderboard (check QRcade > Leaderboards for linked QR codes).',
            'Merch Referral not working: ensure the gateway promotion is active and the merch tag/QR is valid.',
            'If something feels off: confirm promotion rules (max per customer, valid hours/days, expiry) and retry with a fresh scan.',
        ],
    },
]));
</script>

<template>
    <Head title="Business FAQ" />

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="glass-card p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white">Business FAQ</h1>
                    <p class="text-gray-400 mt-2">
                        Everything your team needs to run Revenue QR day-to-day (setup, scanning, redemption, rules, and analytics).
                    </p>
                    <div class="text-xs text-gray-500 mt-2">
                        <span v-if="props.business?.name">Business: {{ props.business.name }} &bull; </span>
                        <span v-if="props.lastUpdated">Last updated: {{ props.lastUpdated }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Link href="/business/dashboard" class="btn-secondary text-sm">Back to Dashboard</Link>
                    <a href="mailto:support@revenueqr.com" class="btn-primary text-sm">Contact Support</a>
                </div>
            </div>
        </div>

        <div class="mt-6 grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <div class="glass-card p-5 sticky top-20">
                    <div class="text-sm font-semibold text-white mb-3">On this page</div>
                    <div class="space-y-2">
                        <a
                            v-for="s in sections"
                            :key="s.key"
                            class="block text-sm text-gray-300 hover:text-white transition-colors"
                            :href="`#${s.key}`"
                        >
                            {{ s.title }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2 space-y-4">
                <section
                    v-for="s in sections"
                    :key="s.key"
                    class="glass-card p-5 md:p-6 scroll-mt-24"
                    :id="s.key"
                >
                    <button
                        class="w-full flex items-start justify-between gap-4 text-left"
                        @click="toggle(s.key)"
                    >
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-white">{{ s.title }}</h2>
                            <p class="text-gray-400 text-sm mt-1" v-if="s.key === 'limits'">
                                This is the #1 place things "feel bugged" when testing &mdash; usually it's a rule doing its job.
                            </p>
                        </div>
                        <div class="mt-1">
                            <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-300 transition-transform" :class="{ 'rotate-180': open === s.key }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </button>

                    <div v-show="open === s.key" class="mt-4">
                        <ul class="space-y-2">
                            <li v-for="(line, idx) in s.items" :key="idx">
                                <div v-if="line === ''" class="h-2"></div>
                                <div v-else class="flex gap-3">
                                    <div class="mt-2 w-2 h-2 rounded-full bg-primary-400/80 flex-shrink-0"></div>
                                    <div class="text-gray-300 text-sm leading-relaxed">{{ line }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>

        <div class="mt-8 text-center text-xs text-gray-500">
            Logged in as: <span class="text-gray-300">{{ user?.email || 'Guest' }}</span>
        </div>
    </div>
</template>
