<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
  business: { type: Object, default: null },
});

const open = ref('quickstart');
const toggle = (key) => {
  open.value = open.value === key ? null : key;
};

const sections = computed(() => ([
  {
    key: 'quickstart',
    title: 'Quick Start (2 paths)',
    subtitle: 'Pick Instant Win (simple) or Leaderboard Prize (weekly/monthly winners).',
    items: [
      'First, enable the games you want: go to QRcade \u2192 Manage Games and toggle ON the games you want to offer.',
      'Decide what you want customers to experience:',
      'A) Instant Win: Scan \u2192 Play \u2192 Win a reward immediately after the game.',
      'B) Leaderboard Prize: Scan \u2192 Play to compete \u2192 Top players win the prize when the period ends.',
      'Both paths start with creating a Promotion (the prize), then creating a QR code. Follow Path A or Path B below.',
    ],
  },
  {
    key: 'instant-win',
    title: 'Path A: Instant Win (Scan \u2192 Play \u2192 Win immediately)',
    subtitle: 'Best when you want every visit to have a chance to win right now.',
    items: [
      'Step 1: Create a Promotion \u2014 go to Promotions \u2192 Create and set up your prize (e.g. 10% off, free item).',
      'Step 2: Create a QR Code \u2014 go to QR Codes \u2192 Create \u2192 select type \u201CQRcade Gaming\u201D.',
      'Step 3: Select your game(s) from the \u201CSelect Games\u201D grid.',
      'Step 4: Set \u201CWin Reward (Optional)\u201D to the promotion you created in Step 1.',
      'Step 5 (optional): Fine-tune win conditions and limits \u2014 go to QRcade \u2192 Rewards, find your QR code, and configure the win mode (Score Threshold, Speed Run, Random Chance, or Always Win) and set Daily/Total Prize Limits.',
      'Tip: You can also enable Tiered rewards (Gold/Silver/Bronze) in QRcade \u2192 Rewards for different prize levels based on score.',
      'Done! Customers scan \u2192 play \u2192 win the reward immediately after the game.',
    ],
  },
  {
    key: 'leaderboard',
    title: 'Path B: Leaderboard Prize (weekly/monthly tournaments)',
    subtitle: 'Best when you want competition + big prizes without inflating redemption rate.',
    items: [
      'Step 1: Create a Promotion \u2014 go to Promotions \u2192 Create and set up the prize (e.g. free meal, gift card).',
      'Step 2: Create a Promotion QR Code \u2014 go to QR Codes \u2192 Create \u2192 select type \u201CPromotion\u201D (not QRcade). Attach the promotion from Step 1.',
      'Step 3: Create a Leaderboard \u2014 go to QRcade \u2192 Leaderboards \u2192 Create.',
      '\u2014 Set the Name, Type (\u201CThis Location Only\u201D or \u201CPer Game\u201D), and Reset Frequency (Daily, Weekly, Monthly, or Never).',
      '\u2014 Under \u201CReward Prize (Promotion QR Code Only)\u201D, select the Promotion QR code from Step 2.',
      'Step 4: Create a QRcade Leaderboard QR Code \u2014 go to QR Codes \u2192 Create \u2192 select type \u201CQRcade Leaderboard\u201D.',
      '\u2014 Under \u201CLink to Leaderboard\u201D, select the leaderboard you created in Step 3.',
      '\u2014 The game auto-selects if the leaderboard is game-specific, otherwise pick one game.',
      '\u2014 The instant win reward is disabled (prizes come from the leaderboard).',
      'Done! Players scan \u2192 play \u2192 scores go to the leaderboard \u2192 winners receive the prize when the period resets.',
    ],
  },
  {
    key: 'tiered',
    title: 'Tiered Rewards (Gold / Silver / Bronze)',
    subtitle: 'Award different prizes based on how well the player scores.',
    items: [
      'Tiered rewards let you set three prize levels \u2014 Gold, Silver, and Bronze \u2014 each with its own score threshold and promotion.',
      'To set up: go to QRcade \u2192 Rewards \u2192 click Configure on your QR code game.',
      'Enable \u201CAdvanced: Tiered\u201D under Reward Style.',
      'Set the minimum score for each tier (e.g. Gold: 1000, Silver: 500, Bronze: 100).',
      'Select a different Promotion for each tier (e.g. Gold: 20% off, Silver: 10% off, Bronze: 5% off).',
      'Players who score above a tier threshold win that tier\u2019s prize. Players below all thresholds do not win.',
      'Note: Tiered rewards are only available on \u201CQRcade Gaming\u201D QR codes (not leaderboard QR codes).',
    ],
  },
  {
    key: 'win-modes',
    title: 'Win Modes (how players win)',
    subtitle: 'Configure how players earn prizes \u2014 skill, speed, chance, or participation.',
    items: [
      'Score Threshold \u2014 player must reach a minimum score to win. Good for skill-based challenges.',
      'Speed Run \u2014 player must complete the game under a time limit. Only available for Memory Match and Word Search.',
      'Random Chance \u2014 set a probability (0\u2013100%). Every play has that chance to win, regardless of score.',
      'Always Win \u2014 participation prize. Every player who finishes the game wins.',
      'Leaderboard \u2014 only used on QRcade Leaderboard QR codes. Prizes are based on leaderboard rank, not individual plays.',
      'To change a win mode: go to QRcade \u2192 Rewards, click Configure on the QR code game, and select a mode.',
      'Tip: combine win modes with Daily Prize Limit and Total Prize Limit to control how many prizes go out.',
    ],
  },
  {
    key: 'scheduling',
    title: 'Scheduling (days/times)',
    subtitle: 'Control when games are available (e.g. only weekends or only during lunch).',
    items: [
      'Go to QRcade \u2192 Schedule Games.',
      'Click Edit Schedule on the game you want to restrict.',
      'Check the days of the week the game should be active.',
      'Set a start time and end time for each active day.',
      'If no schedule is set, the game is always available.',
      'Tip: use scheduling for \u201CHappy Hour game challenges\u201D \u2014 make the game available only during your promo window (e.g. 4\u20136 PM on weekdays).',
    ],
  },
  {
    key: 'limits',
    title: 'Limits (keeping rewards fair)',
    subtitle: 'Rewards are controlled by promotion rules and prize limits.',
    items: [
      'Promotion rules control per-customer limits, valid days/hours, expiry, etc. \u2014 set these on the Promotion itself.',
      'Prize limits control how many times a game can award prizes \u2014 set these in QRcade \u2192 Rewards:',
      '\u2014 Daily Prize Limit: max prizes the game can give out in one day.',
      '\u2014 Total Prize Limit: max prizes the game can give out ever.',
      'Just for Fun mode: if a player hits their reward limit or no prize is configured, they can still play. Plays are still recorded, but no XP, badges, leaderboard entries, or prizes are awarded.',
      'You can also set per-game play limits (max plays per day/week, cooldown) in QRcade \u2192 Manage Games \u2192 game settings.',
    ],
  },
  {
    key: 'best-practices',
    title: 'Best Practices (recommended setups)',
    subtitle: 'Make it simple for staff + customers.',
    items: [
      'For leaderboards: always use a dedicated Promotion QR code (no games attached) as the prize. This keeps your QRcade QR codes separate from prize redemption.',
      'If you want different rules for leaderboard prizes vs. regular promos, create a separate promotion so they don\u2019t conflict.',
      'If a promotion is restricted to certain days/hours, winners must still redeem within those rules \u2014 even if they won via a leaderboard.',
      'Use tiered rewards to give every player something (Bronze = small prize) while rewarding high scorers (Gold = big prize).',
      'Start with \u201CAlways Win\u201D or \u201CRandom Chance\u201D at a low percentage if you\u2019re unsure how many prizes you want to give out.',
      'Test once as a customer and once as staff to confirm the full flow: scanning, playing, winning, and redeeming.',
    ],
  },
  {
    key: 'troubleshooting',
    title: 'Troubleshooting (what to check when something \u201Cfeels bugged\u201D)',
    subtitle: 'Most issues are a rule doing its job.',
    items: [
      'If staff sees \u201CCannot Redeem\u201D: read the reason \u2014 it\u2019s usually a time/day window, limit reached, expired promo, or inactive offer.',
      'If a player \u201Ccan play but doesn\u2019t get prizes\u201D: they may be in Just for Fun mode because they hit a reward limit or no promotion is attached.',
      'If you don\u2019t see a QR code in the leaderboard \u201CReward Prize\u201D dropdown: it must be a Promotion-type QR code (not QRcade) with an active promotion attached.',
      'If Speed Run mode is unavailable: it only works with Memory Match and Word Search games.',
      'If leaderboard scores aren\u2019t showing: make sure the QR code is type \u201CQRcade Leaderboard\u201D (not \u201CQRcade Gaming\u201D) and is linked to the correct leaderboard.',
    ],
  },
]));
</script>

<template>
  <Head title="QRcade How To" />

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="glass-card p-6 md:p-8">
      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
          <Link href="/business/qrcade" class="text-gray-400 hover:text-white text-sm inline-block mb-2">
            ← Back to QRcade
          </Link>
          <h1 class="text-2xl md:text-3xl font-bold text-white">QRcade How-To</h1>
          <p class="text-gray-400 mt-2">
            Step-by-step guides for simple Instant Win games and advanced weekly/monthly leaderboard prizes.
          </p>
          <div class="text-xs text-gray-500 mt-2" v-if="props.business?.name">
            Business: <span class="text-gray-300">{{ props.business.name }}</span>
          </div>
        </div>

        <div class="flex gap-2">
          <Link href="/business/qrcade/games" class="btn-secondary text-sm">Manage Games</Link>
          <Link href="/business/qrcade/leaderboards" class="btn-secondary text-sm">Leaderboards</Link>
          <Link href="/business/qrcade/rewards" class="btn-primary text-sm">Rewards</Link>
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
              @click="open = s.key"
            >
              {{ s.title }}
            </a>
          </div>
          <div class="mt-4 text-xs text-gray-500">
            Tip: Start with <span class="text-gray-300">Quick Start</span>, then follow Path A or Path B.
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
              <p v-if="s.subtitle" class="text-gray-400 text-sm mt-1">{{ s.subtitle }}</p>
            </div>
            <div class="mt-1">
              <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                <svg
                  class="w-4 h-4 text-gray-300 transition-transform"
                  :class="{ 'rotate-180': open === s.key }"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </button>

          <div v-show="open === s.key" class="mt-4">
            <ul class="space-y-2">
              <li v-for="(line, idx) in s.items" :key="idx" class="flex gap-3">
                <div class="mt-2 w-2 h-2 rounded-full bg-primary-400/80 flex-shrink-0"></div>
                <div class="text-gray-300 text-sm leading-relaxed">{{ line }}</div>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.glass-card {
  @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
.btn-primary {
  @apply px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-500 text-white rounded-lg font-medium hover:opacity-90 transition-opacity;
}
.btn-secondary {
  @apply px-4 py-2 bg-white/10 text-white rounded-lg font-medium hover:bg-white/20 transition-colors;
}
</style>
