<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const features = [
    {
        title: 'Advanced QR Generator',
        description: 'Create stunning QR codes with gradients, custom shapes, logos, text overlays, and special effects like glow and shadows.',
        icon: '🎨',
        details: [
            'Gradient backgrounds and module colors',
            'Custom shapes: dots, rounded, diamond, heart',
            'Add your logo with automatic sizing',
            'Text labels top and bottom',
            'Borders and glow effects',
            'Export as PNG, SVG, or print-ready PDF'
        ]
    },
    {
        title: 'Powerful Promotions',
        description: 'Create any type of promotion or discount with our flexible rule engine.',
        icon: '🎁',
        details: [
            'Percentage off discounts',
            'Fixed amount discounts',
            'Buy one get one (BOGO)',
            'Punch cards and loyalty programs',
            'Tiered rewards',
            'Time-limited flash sales'
        ]
    },
    {
        title: 'Real-time Analytics',
        description: 'Track every scan and redemption with beautiful charts and AI-powered insights.',
        icon: '📊',
        details: [
            'Real-time scan tracking',
            'Geographic heatmaps',
            'Device and browser analytics',
            'Conversion funnel tracking',
            'AI-powered recommendations',
            'Export reports to CSV/PDF'
        ]
    },
    {
        title: 'Print Studio',
        description: 'Design and print professional QR code materials for your business.',
        icon: '🖨️',
        details: [
            'Multiple QR codes per sheet',
            'Custom layouts and spacing',
            'Include text and branding',
            'Print-ready PDF export',
            'Sticker and label templates',
            'Business card integration'
        ]
    },
    {
        title: 'Merch Integration',
        description: 'Order custom merchandise with your QR codes printed and shipped directly.',
        icon: '👕',
        details: [
            'T-shirts, mugs, stickers',
            'Automatic QR code placement',
            'Bulk order discounts',
            'Direct shipping to customers',
            'Track orders in dashboard',
            'Powered by Printful'
        ]
    },
    {
        title: 'QRcade Games',
        description: 'Gamify your promotions with location-locked mini-games customers love.',
        icon: '🎮',
        details: [
            'Memory match, word search, and more',
            'Win discounts and prizes',
            'Leaderboards and badges',
            'Location verification',
            'Seasonal game packs',
            'Custom branded games'
        ]
    },
];
</script>

<template>
    <Head title="Features" />

    <!-- Hero -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">
                <span class="gradient-text">Powerful Features</span>
            </h1>
            <p class="text-xl text-gray-400 max-w-3xl mx-auto">
                Everything you need to create, manage, and track QR code promotions that drive real results.
            </p>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-16">
                <div 
                    v-for="(feature, index) in features" 
                    :key="feature.title"
                    :class="['glass-card p-8 md:p-12', index % 2 === 1 ? 'md:flex-row-reverse' : '']"
                >
                    <div class="grid md:grid-cols-2 gap-8 items-center">
                        <div :class="index % 2 === 1 ? 'md:order-2' : ''">
                            <div class="text-6xl mb-4">{{ feature.icon }}</div>
                            <h2 class="text-3xl font-bold text-white mb-4">{{ feature.title }}</h2>
                            <p class="text-lg text-gray-400 mb-6">{{ feature.description }}</p>
                            <ul class="space-y-3">
                                <li v-for="detail in feature.details" :key="detail" class="flex items-start">
                                    <svg class="w-5 h-5 text-primary-400 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-300">{{ detail }}</span>
                                </li>
                            </ul>
                        </div>
                        <div :class="['flex justify-center', index % 2 === 1 ? 'md:order-1' : '']">
                            <div class="w-64 h-64 rounded-3xl bg-gradient-to-br from-primary-500/20 to-accent-500/20 flex items-center justify-center">
                                <span class="text-8xl">{{ feature.icon }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-white mb-6">Ready to Get Started?</h2>
            <p class="text-xl text-gray-400 mb-8">
                Join thousands of businesses using Revenue QR to grow their customer base.
            </p>
            <Link href="/register" class="btn-primary text-lg px-10 py-4">
                Start Your Free Trial
            </Link>
        </div>
    </section>
</template>

