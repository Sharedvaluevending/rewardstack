<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

// Referral Calculator
const referralCount = ref(25);
const commissionPerRef = 7.90; // 10% of $79

const monthlyEarnings = computed(() => {
    return (referralCount.value * commissionPerRef).toFixed(2);
});

const yearlyEarnings = computed(() => {
    return (referralCount.value * commissionPerRef * 12).toLocaleString('en-US', { maximumFractionDigits: 0 });
});

const milestoneMessage = computed(() => {
    const count = referralCount.value;
    if (count >= 100) {
        return { emoji: '👑', text: 'LEGEND STATUS! You\'re earning a real side income!', color: 'text-yellow-400' };
    } else if (count >= 50) {
        return { emoji: '🥇', text: 'Gold tier! That\'s rent money every month!', color: 'text-emerald-400' };
    } else if (count >= 25) {
        return { emoji: '🥈', text: 'Silver tier! Car payment covered!', color: 'text-gray-300' };
    } else if (count >= 10) {
        return { emoji: '🥉', text: 'Bronze tier! Free coffee for life!', color: 'text-amber-600' };
    } else if (count >= 5) {
        return { emoji: '🚀', text: 'Great start! Keep going!', color: 'text-blue-400' };
    } else {
        return { emoji: '💡', text: 'Every business counts! Start with one.', color: 'text-gray-400' };
    }
});

const features = [
    {
        title: 'Advanced QR Generator',
        description: 'Create stunning QR codes with gradients, custom shapes, logos, glow effects, and shadows.',
        icon: '🎨',
        gradient: 'from-blue-500 to-cyan-500',
    },
    {
        title: 'QRcade Gaming',
        description: 'Turn scans into fun! Customers play games like Memory Match, Snake & more to win discounts.',
        icon: '🎮',
        gradient: 'from-purple-500 to-pink-500',
    },
    {
        title: 'Powerful Promotions',
        description: 'Build any type of discount: percentage off, BOGO, punch cards, tiered rewards, and more.',
        icon: '🎫',
        gradient: 'from-orange-500 to-red-500',
    },
    {
        title: 'Real-time Analytics',
        description: 'Track every scan and redemption with beautiful charts and AI-powered insights.',
        icon: '📊',
        gradient: 'from-green-500 to-emerald-500',
    },
    {
        title: 'Print Studio',
        description: 'Design and print QR code sheets, stickers, and professional marketing materials.',
        icon: '🖨️',
        gradient: 'from-indigo-500 to-purple-500',
    },
    {
        title: 'Merch Store',
        description: 'Order custom merchandise with your QR codes printed and shipped directly to you.',
        icon: '👕',
        gradient: 'from-pink-500 to-rose-500',
    },
    {
        title: 'Merch Referral Rewards',
        description: 'Turn merch into ambassador referrals with repeat rewards tied to unique redemptions.',
        icon: '✨',
        gradient: 'from-emerald-500 to-teal-500',
    },
];

const qrTypes = [
    { name: 'Static', description: 'Fixed URL, simple and fast', icon: '🔗' },
    { name: 'Dynamic', description: 'Editable destination, full tracking', icon: '📊' },
    { name: 'Promotion', description: 'Attached discount or deal', icon: '🎫' },
    { name: 'Merch Referral', description: 'Ambassador rewards on merch scans', icon: '👕' },
    { name: 'Level Exclusive Promotion', description: "Uses the user’s app-wide XP/level (across all businesses), not XP specific to your store.", icon: '⭐' },
    { name: 'QRcade Gaming', description: 'Play games, win instant prizes', icon: '🎮' },
    { name: 'QRcade Leaderboard', description: 'Leaderboard challenge (prizes awarded by period)', icon: '🏆' },
    { name: 'Partner Deal Chain', description: 'Partner with other businesses (open or chained unlock)', icon: '🤝' },
    { name: 'Stackable', description: 'Multiple promotions in one', icon: '📚' },
];

const qrFeatures = [
    'Gradient backgrounds & modules',
    'Custom shapes: dots, rounded, diamond, heart, star',
    'Logo upload with auto-sizing',
    'Text labels with outline effects',
    'Glow & drop shadow effects',
    'Separate finder (corner) styling',
    'Export as PNG, SVG, or PDF',
    'Live preview while designing',
];

const games = [
    { name: 'Memory Match', icon: '🧠', desc: 'Flip tiles to match pairs' },
    { name: 'Snake', icon: '🐍', desc: 'Classic arcade game' },
    { name: 'Word Search', icon: '🔤', desc: 'Find hidden words' },
    { name: 'Tap Counter', icon: '👆', desc: 'Tap fast, build combos' },
    { name: 'Brick Breaker', icon: '🧱', desc: 'Break all the bricks' },
    { name: 'QR Dash', icon: '⬛', desc: 'Dash through QR obstacles' },
    { name: 'Cupcake Catcher', icon: '🧁', desc: 'Catch falling treats' },
    { name: 'Slice Saver', icon: '🍕', desc: 'Save the pizza!' },
];

const discountTypes = [
    '% Off', 'Fixed Amount', 'BOGO', 'Buy X Get Y',
    'Punch Card', 'Tiered Rewards', 'Bundle Deals', 'Happy Hour',
    'First-Time Customer', 'Loyalty Milestone', 'Flash Sale', 'Seasonal'
];
</script>

<template>
    <Head title="QR Code Promotions & Gaming Platform" />

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/30 rounded-full blur-3xl animate-pulse-slow"></div>
            <div class="absolute top-60 -left-40 w-96 h-96 bg-accent-500/20 rounded-full blur-3xl animate-pulse-slow" style="animation-delay: 2s;"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 flex items-center justify-center">
                        <img src="/brand/logoRQ.png" alt="Revenue QR" class="w-20 h-20 object-contain" />
                    </div>
                </div>
                <!-- Badge -->
                <div class="inline-flex items-center px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-sm text-gray-300 mb-8">
                    👕 Wearable Discounts &nbsp;•&nbsp; 🎫 Custom Promotions (with or without games) &nbsp;•&nbsp; 🎮 Optional Play-to-Win
                </div>
                <p class="text-xs text-gray-500 -mt-4 mb-6">Powered by Revenue QR</p>

                <!-- Heading -->
                <h1 class="text-5xl md:text-7xl font-bold mb-6">
                    <span class="gradient-text">QR Codes That</span>
                    <br />
                    <span class="text-white">Engage & Convert</span>
                </h1>

                <!-- Subheading -->
                <p class="text-xl md:text-2xl text-gray-400 max-w-3xl mx-auto mb-10">
                    Create beautiful QR codes and attach custom promotions (BOGO, punch cards, % off) —
                    <span class="text-primary-400 font-semibold">with or without games</span>.
                    Let customers play to win discounts, or redeem instantly — you choose.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link href="/register" class="btn-primary text-lg px-8 py-4">
                        Start Free Trial
                    </Link>
                    <Link href="/demo" class="px-8 py-4 rounded-xl font-semibold text-white border border-white/20 hover:bg-white/10 transition-all duration-200">
                        Watch Demo
                    </Link>
                </div>
                <p class="mt-4 text-sm text-gray-500">
                    No POS (point-of-sale) integration required.
                </p>

                <!-- Trust Indicators -->
                <div class="mt-12 flex flex-wrap items-center justify-center gap-8 text-gray-500">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        No credit card required
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        14-day free trial
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Cancel anytime
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Merch Referral Section -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-8 md:p-12">
                <div class="grid md:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center px-4 py-2 rounded-full bg-emerald-500/20 text-emerald-300 text-sm font-medium mb-6">
                            👕 Merch Referral QR
                        </div>
                        <h2 class="text-3xl md:text-4xl font-bold text-white mb-5">
                            Turn Merch Into Ambassador Referrals
                        </h2>
                        <p class="text-gray-400 text-lg mb-6">
                            Print a unique QR on every shirt, tie it to the wearer, and reward them every time
                            new customers redeem your offer. Fully tracked, repeatable rewards, and built-in
                            anti-fraud protection.
                        </p>

                        <div class="space-y-3 text-gray-300">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Unique merch QR per item for clean attribution
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Repeatable rewards every N redemptions
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Reward redemption uses the same staff flow you already trust
                            </div>
                        </div>

                        <Link href="/register" class="btn-primary inline-flex items-center mt-8">
                            <span class="mr-2">👕</span> Launch Merch Referrals
                        </Link>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
                        <div class="space-y-4">
                            <div class="rounded-xl bg-black/30 p-4 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Step 1</div>
                                <div class="text-white font-semibold">Create a Merch Referral QR</div>
                                <div class="text-xs text-gray-500 mt-1">Pick the gateway promo + reward rules.</div>
                            </div>
                            <div class="rounded-xl bg-black/30 p-4 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Step 2</div>
                                <div class="text-white font-semibold">Order merch with unique tags</div>
                                <div class="text-xs text-gray-500 mt-1">Each shirt has its own referral QR.</div>
                            </div>
                            <div class="rounded-xl bg-black/30 p-4 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Step 3</div>
                                <div class="text-white font-semibold">Ambassadors earn repeat rewards</div>
                                <div class="text-xs text-gray-500 mt-1">Rewards show in their portal and redeem fast.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- QRcade Gaming Section (NEW - Prominent) -->
    <section class="py-20 bg-gradient-to-b from-purple-900/20 to-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-8 md:p-12 relative overflow-hidden">
                <!-- Background decoration -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-full blur-3xl"></div>
                
                <div class="relative grid md:grid-cols-2 gap-12 items-center">
                    <!-- Left: Content -->
                    <div>
                        <div class="inline-flex items-center px-4 py-2 rounded-full bg-purple-500/20 text-purple-400 text-sm font-medium mb-6">
                            🎮 QRcade Gaming Platform
                        </div>
                        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
                            Scan. Play. <span class="gradient-text">Win!</span>
                        </h2>
                        <p class="text-xl text-gray-400 mb-8">
                            Turn every QR scan into an engaging experience. Customers play fun mini-games 
                            and win your promotions as prizes. Higher engagement, more redemptions, happier customers.
                        </p>
                        
                        <div class="space-y-4 mb-8">
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                8 different games to choose from
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Leaderboards & tournaments
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Skill-based or random prize modes
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Detailed analytics on plays & wins
                            </div>
                        </div>

                        <Link href="/register" class="btn-primary inline-flex items-center">
                            <span class="mr-2">🎮</span> Try QRcade Free
                        </Link>
                    </div>

                    <!-- Right: Game Grid -->
                    <div>
                        <div class="grid grid-cols-4 gap-3">
                            <div 
                                v-for="game in games" 
                                :key="game.name"
                                class="aspect-square rounded-xl bg-white/5 border border-white/10 flex flex-col items-center justify-center p-2 hover:bg-white/10 hover:border-purple-500/50 transition-all cursor-pointer group"
                            >
                                <span class="text-3xl mb-1 group-hover:scale-110 transition-transform">{{ game.icon }}</span>
                                <span class="text-xs text-gray-400 text-center leading-tight">{{ game.name }}</span>
                            </div>
                        </div>
                        <p class="text-center text-gray-500 text-sm mt-4">
                            All games work on mobile — no app download required!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Advanced QR Generator Section -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-8 md:p-12">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <!-- QR Preview -->
                    <div class="flex justify-center">
                        <div class="relative">
                            <!-- Glow Effect -->
                            <div class="absolute inset-0 bg-gradient-to-r from-primary-500 to-accent-500 rounded-3xl blur-2xl opacity-30"></div>
                            
                            <!-- QR Code Image -->
                            <div class="relative bg-white rounded-3xl p-6 shadow-2xl">
                                <div class="w-48 h-48 rounded-2xl flex items-center justify-center relative overflow-hidden bg-white">
                                    <img 
                                        src="/storage/qrcodes/qr_693c34e7cc0ff.png" 
                                        alt="QR Code Example" 
                                        class="w-full h-full object-contain"
                                    />
                                </div>
                                <p class="text-center text-gray-600 mt-4 font-medium">Scan for 20% Off! 🎉</p>
                            </div>
                        </div>
                    </div>

                    <!-- Features List -->
                    <div>
                        <div class="inline-flex items-center px-4 py-2 rounded-full bg-blue-500/20 text-blue-400 text-sm font-medium mb-6">
                            🎨 Advanced QR Generator
                        </div>
                        <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Design QR Codes Your Way</h2>
                        <p class="text-gray-400 mb-8">
                            Our powerful QR builder gives you complete control over every aspect of your QR code design. 
                            Make it match your brand perfectly.
                        </p>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div 
                                v-for="feature in qrFeatures" 
                                :key="feature"
                                class="flex items-center"
                            >
                                <span class="w-6 h-6 rounded-full bg-primary-500/20 flex items-center justify-center mr-2 flex-shrink-0">
                                    <svg class="w-4 h-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <span class="text-gray-300 text-sm">{{ feature }}</span>
                            </div>
                        </div>

                        <Link href="/register" class="btn-primary inline-flex items-center mt-8">
                            <span class="mr-2">🎨</span> Create Your First QR Code
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- QR Types Section -->
    <section class="py-20 bg-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">8 Types of QR Codes</h2>
                <p class="text-xl text-gray-400">Choose the right type for your business needs</p>
            </div>

            <div class="grid md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div
                    v-for="type in qrTypes"
                    :key="type.name"
                    class="glass-card p-6 text-center card-hover"
                >
                    <div class="text-4xl mb-3">{{ type.icon }}</div>
                    <h3 class="text-lg font-semibold text-white mb-2">{{ type.name }}</h3>
                    <p class="text-sm text-gray-400">{{ type.description }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Everything You Need</h2>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto">
                    A complete platform for creating, managing, and tracking QR code promotions.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="glass-card p-6 card-hover"
                >
                    <div :class="`w-14 h-14 rounded-xl bg-gradient-to-br ${feature.gradient} flex items-center justify-center mb-4 text-2xl`">
                        {{ feature.icon }}
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">{{ feature.title }}</h3>
                    <p class="text-gray-400">{{ feature.description }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Discount Types -->
    <section class="py-20 bg-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Any Promotion Type</h2>
                <p class="text-xl text-gray-400">Create the perfect offer for your customers</p>
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <span
                    v-for="type in discountTypes"
                    :key="type"
                    class="px-4 py-2 rounded-full bg-white/10 text-gray-300 text-sm border border-white/10 hover:border-primary-500/50 hover:bg-primary-500/10 transition-all cursor-pointer"
                >
                    {{ type }}
                </span>
            </div>
        </div>
    </section>

    <!-- CRM Section (NEW) -->
    <section class="py-20 bg-gradient-to-b from-sky-900/15 to-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Built-in CRM (Now Live)</h2>
                <p class="text-xl text-gray-400">
                    Turn one-time scans into repeat customers — with explicit opt-in email marketing.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="glass-card p-6">
                    <div class="text-3xl mb-3">📬</div>
                    <h3 class="text-xl font-bold text-white mb-2">Real Subscribers (Opt-in)</h3>
                    <p class="text-gray-400 text-sm">
                        Customers can subscribe from business pages & promo pages. Unsubscribe anytime.
                    </p>
                </div>

                <div class="glass-card p-6">
                    <div class="text-3xl mb-3">🎯</div>
                    <h3 class="text-xl font-bold text-white mb-2">Segments That Make Sense</h3>
                    <p class="text-gray-400 text-sm">
                        Target customers by engagement: scans, saves, redemptions, last seen, and level.
                    </p>
                </div>

                <div class="glass-card p-6">
                    <div class="text-3xl mb-3">📈</div>
                    <h3 class="text-xl font-bold text-white mb-2">Send + Track Performance</h3>
                    <p class="text-gray-400 text-sm">
                        Campaigns with attached discounts/promotions (one-click save links), full tracking (delivered, opens, clicks, bounces, unsubscribes), and AI campaign ideas.
                    </p>
                </div>
            </div>

            <div class="glass-card p-6 mt-8">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <div class="text-white font-semibold mb-3">What’s included</div>
                        <ul class="space-y-3 text-gray-300">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Customer list</span> with scan/save/redeem engagement</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Campaign builder</span> + send to subscribers or segments</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Attach promotions</span> to campaigns with one-click “save to wallet” links</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Signed unsubscribe links</span> + suppression protection</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Automations</span>: winback, punch-card nudges, expiring-offer reminders</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div class="text-white font-semibold mb-3">Tracking you can trust</div>
                        <ul class="space-y-3 text-gray-300">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Delivered, opens, clicks (plus bounces & unsubscribes)</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">Signed SendGrid webhook</span> verification (secure)</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span><span class="text-white font-semibold">AI campaign ideas</span> tailored to your business</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-8">
                    <Link href="/register" class="btn-primary text-lg px-10 py-4">
                        Start Using CRM Free
                    </Link>
                    <Link href="/demo" class="px-10 py-4 rounded-xl font-semibold text-white border border-white/20 hover:bg-white/10 transition-all duration-200">
                        Watch Demo
                    </Link>
                </div>
                <div class="mt-3 text-center text-sm text-gray-500">
                    No POS integration required.
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Preview Section -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Simple, Transparent Pricing</h2>
                <p class="text-xl text-gray-400">Plans for businesses of all sizes</p>
            </div>

            <div class="grid md:grid-cols-4 gap-6">
                <!-- Starter -->
                <div class="glass-card p-6 text-center">
                    <h3 class="text-xl font-bold text-white mb-2">Starter</h3>
                    <div class="text-3xl font-bold text-white mb-2">$19.99<span class="text-sm text-gray-400 font-normal">/mo</span></div>
                    <p class="text-gray-400 text-sm mb-4">For small businesses</p>
                    <ul class="text-gray-300 text-sm space-y-2 mb-6">
                        <li>10 QR Codes</li>
                        <li>1,000 Scans/month</li>
                        <li>Basic QRcade Games</li>
                        <li>Built-in CRM + Email Campaigns</li>
                        <li>Automations: winback + reminders</li>
                    </ul>
                    <Link href="/register" class="block py-2 px-4 bg-white/10 rounded-lg text-white hover:bg-white/20 transition-colors">
                        Start Free Trial
                    </Link>
                </div>

                <!-- Growth (Popular) -->
                <div class="glass-card p-6 text-center ring-2 ring-primary-500 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-3 py-1 bg-primary-500 text-white text-xs font-semibold rounded-full">Popular</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Growth</h3>
                    <div class="text-3xl font-bold text-white mb-2">$39.99<span class="text-sm text-gray-400 font-normal">/mo</span></div>
                    <p class="text-gray-400 text-sm mb-4">For growing businesses</p>
                    <ul class="text-gray-300 text-sm space-y-2 mb-6">
                        <li>50 QR Codes</li>
                        <li>10,000 Scans/month</li>
                        <li>Pro Games + Leaderboards</li>
                        <li>Built-in CRM + Email Campaigns</li>
                        <li>Automations: winback + reminders</li>
                    </ul>
                    <Link href="/register" class="block py-2 px-4 bg-primary-500 rounded-lg text-white hover:bg-primary-600 transition-colors">
                        Start Free Trial
                    </Link>
                </div>

                <!-- Pro -->
                <div class="glass-card p-6 text-center">
                    <h3 class="text-xl font-bold text-white mb-2">Pro</h3>
                    <div class="text-3xl font-bold text-white mb-2">$79.99<span class="text-sm text-gray-400 font-normal">/mo</span></div>
                    <p class="text-gray-400 text-sm mb-4">Full-featured</p>
                    <ul class="text-gray-300 text-sm space-y-2 mb-6">
                        <li>200 QR Codes</li>
                        <li>50,000 Scans/month</li>
                        <li>All Games + AI Insights</li>
                        <li>Built-in CRM + Email Campaigns</li>
                        <li>Automations: winback + reminders</li>
                    </ul>
                    <Link href="/register" class="block py-2 px-4 bg-white/10 rounded-lg text-white hover:bg-white/20 transition-colors">
                        Start Free Trial
                    </Link>
                </div>

                <!-- Enterprise -->
                <div class="glass-card p-6 text-center">
                    <h3 class="text-xl font-bold text-white mb-2">Enterprise</h3>
                    <div class="text-3xl font-bold text-white mb-2">$199.99<span class="text-sm text-gray-400 font-normal">/mo</span></div>
                    <p class="text-gray-400 text-sm mb-4">White-label solution</p>
                    <ul class="text-gray-300 text-sm space-y-2 mb-6">
                        <li>Unlimited Everything</li>
                        <li>Custom Branding</li>
                        <li>Dedicated Support</li>
                        <li>Built-in CRM + Email Campaigns</li>
                        <li>Automations: winback + reminders</li>
                    </ul>
                    <Link href="/pricing" class="block py-2 px-4 bg-white/10 rounded-lg text-white hover:bg-white/20 transition-colors">
                        Learn More
                    </Link>
                </div>
            </div>

            <div class="text-center mt-8">
                <Link href="/pricing" class="text-primary-400 hover:text-primary-300 font-medium">
                    Compare all features →
                </Link>
            </div>
        </div>
    </section>

    <!-- QR Ambassador Section -->
    <section class="py-20 bg-gradient-to-b from-emerald-900/20 to-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-8 md:p-12 relative overflow-hidden border-2 border-emerald-500/30">
                <!-- Background decoration -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500/20 to-green-500/20 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-gradient-to-br from-green-500/10 to-emerald-500/10 rounded-full blur-2xl"></div>
                
                <div class="relative grid md:grid-cols-2 gap-12 items-center">
                    <!-- Left: Content -->
                    <div>
                        <div class="inline-flex items-center px-4 py-2 rounded-full bg-emerald-500/20 text-emerald-400 text-sm font-medium mb-6">
                            💰 Referral Army Program
                        </div>
                        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
                            Become a <span class="text-emerald-400">QR Ambassador</span>
                        </h2>
                        <p class="text-xl text-gray-400 mb-8">
                            Know local businesses? Earn <span class="text-emerald-400 font-semibold">recurring passive income</span> 
                            by helping them grow with Revenue QR. Get your personal QR code, share it, and earn 10% 
                            of every subscription — <span class="text-white font-medium">every single month they stay on the platform</span>.
                        </p>
                        
                        <div class="space-y-4 mb-8">
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Personal QR code linked to your account
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Print cards to leave at business counters
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                10% recurring commission every month
                            </div>
                            <div class="flex items-center text-gray-300">
                                <span class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Stripe Connect direct deposits — link routing/transit + account numbers from your check (no PayPal/Venmo)
                            </div>
                        </div>

                        <Link href="/portal/join" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold rounded-xl hover:from-emerald-600 hover:to-green-700 transition-all shadow-lg shadow-emerald-500/25">
                            <span class="mr-2">🚀</span> Join the Referral Army
                        </Link>
                    </div>

                    <!-- Right: Interactive Calculator -->
                    <div>
                        <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <span class="mr-2">🧮</span> Earnings Calculator
                            </h3>
                            
                            <!-- Slider -->
                            <div class="mb-6">
                                <div class="flex justify-between text-sm text-gray-400 mb-2">
                                    <span>Businesses you refer:</span>
                                    <span class="text-white font-bold text-lg">{{ referralCount }}</span>
                                </div>
                                <input 
                                    type="range" 
                                    v-model="referralCount" 
                                    min="1" 
                                    max="150" 
                                    class="w-full h-3 bg-white/10 rounded-full appearance-none cursor-pointer accent-emerald-500"
                                />
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>1</span>
                                    <span>50</span>
                                    <span>100</span>
                                    <span>150</span>
                                </div>
                            </div>

                            <!-- Earnings Display -->
                            <div class="text-center p-6 rounded-xl bg-gradient-to-br from-emerald-500/20 to-green-600/20 border border-emerald-500/30 mb-4">
                                <div class="text-gray-400 text-sm mb-1">Your Monthly Earnings</div>
                                <div class="text-5xl font-bold text-emerald-400">${{ monthlyEarnings }}</div>
                                <div class="text-emerald-300/70 text-sm mt-2">= ${{ yearlyEarnings }}/year passive income</div>
                            </div>

                            <!-- Milestone Badges -->
                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <div 
                                    class="text-center p-2 rounded-lg transition-all"
                                    :class="referralCount >= 10 ? 'bg-emerald-500/20 border border-emerald-500/50' : 'bg-white/5 border border-white/10 opacity-50'"
                                >
                                    <div class="text-lg">🥉</div>
                                    <div class="text-xs text-gray-400">10</div>
                                </div>
                                <div 
                                    class="text-center p-2 rounded-lg transition-all"
                                    :class="referralCount >= 25 ? 'bg-emerald-500/20 border border-emerald-500/50' : 'bg-white/5 border border-white/10 opacity-50'"
                                >
                                    <div class="text-lg">🥈</div>
                                    <div class="text-xs text-gray-400">25</div>
                                </div>
                                <div 
                                    class="text-center p-2 rounded-lg transition-all"
                                    :class="referralCount >= 50 ? 'bg-emerald-500/20 border border-emerald-500/50' : 'bg-white/5 border border-white/10 opacity-50'"
                                >
                                    <div class="text-lg">🥇</div>
                                    <div class="text-xs text-gray-400">50</div>
                                </div>
                                <div 
                                    class="text-center p-2 rounded-lg transition-all"
                                    :class="referralCount >= 100 ? 'bg-gradient-to-br from-yellow-500/30 to-amber-500/30 border border-yellow-500/50' : 'bg-white/5 border border-white/10 opacity-50'"
                                >
                                    <div class="text-lg">👑</div>
                                    <div class="text-xs text-gray-400">100</div>
                                </div>
                            </div>

                            <!-- Dynamic Message -->
                            <div class="p-3 rounded-xl bg-white/5 text-center">
                                <p class="text-sm" :class="milestoneMessage.color">
                                    {{ milestoneMessage.emoji }} {{ milestoneMessage.text }}
                                </p>
                            </div>

                            <div class="mt-4 flex items-center justify-center gap-2 text-gray-500 text-xs">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Based on avg $79/mo plan · 10% commission · As long as they stay subscribed
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-12 text-center relative overflow-hidden">
                <!-- Background gradient -->
                <div class="absolute inset-0 bg-gradient-to-r from-primary-500/20 to-accent-500/20"></div>
                
                <div class="relative">
                    <h2 class="text-4xl font-bold text-white mb-4">Ready to Get Started?</h2>
                    <p class="text-xl text-gray-400 mb-8">
                        Join thousands of businesses using Revenue QR to grow their customer base.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link href="/register" class="btn-accent text-lg px-10 py-4">
                            Start Your Free Trial
                        </Link>
                        <Link href="/features" class="text-gray-400 hover:text-white transition-colors">
                            Learn more about features →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Custom range slider styling */
input[type="range"] {
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    cursor: pointer;
}

input[type="range"]::-webkit-slider-runnable-track {
    background: linear-gradient(to right, #10b981, #059669);
    height: 8px;
    border-radius: 4px;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 24px;
    height: 24px;
    background: white;
    border: 3px solid #10b981;
    border-radius: 50%;
    margin-top: -8px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    transition: transform 0.15s ease;
}

input[type="range"]::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

input[type="range"]::-moz-range-track {
    background: linear-gradient(to right, #10b981, #059669);
    height: 8px;
    border-radius: 4px;
}

input[type="range"]::-moz-range-thumb {
    width: 24px;
    height: 24px;
    background: white;
    border: 3px solid #10b981;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    transition: transform 0.15s ease;
}

input[type="range"]::-moz-range-thumb:hover {
    transform: scale(1.1);
}
</style>
