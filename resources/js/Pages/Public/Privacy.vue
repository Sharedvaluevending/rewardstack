<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});
</script>

<template>
    <Head title="Privacy Policy" />

    <div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
        <div class="glass-card p-8 md:p-12">
            <h1 class="text-4xl font-bold text-white mb-2">Privacy Policy</h1>
            <p class="text-gray-400 mb-8">Last updated: December 8, 2024</p>

            <div class="prose prose-invert max-w-none space-y-8">
                <!-- Introduction -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">1. Introduction</h2>
                    <p class="text-gray-300 leading-relaxed">
                        Revenue QR ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our platform and services.
                    </p>
                </section>

                <!-- Information We Collect -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">2. Information We Collect</h2>
                    
                    <h3 class="text-lg font-medium text-white mt-4 mb-2">Personal Information</h3>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>Name and email address when you create an account</li>
                        <li>Business information (name, address, phone) for business accounts</li>
                        <li>Payment information processed securely through Stripe</li>
                        <li>Shipping addresses for merchandise orders</li>
                    </ul>

                    <h3 class="text-lg font-medium text-white mt-4 mb-2">Usage Information</h3>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>QR code scan data (location, device type, timestamp)</li>
                        <li>Game play statistics and scores</li>
                        <li>Promotion redemption history</li>
                        <li>Analytics and usage patterns</li>
                    </ul>

                    <h3 class="text-lg font-medium text-white mt-4 mb-2">Automatically Collected Information</h3>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>IP address and browser type</li>
                        <li>Device information</li>
                        <li>Cookies and similar tracking technologies</li>
                    </ul>
                </section>

                <!-- How We Use Information -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">3. How We Use Your Information</h2>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>To provide and maintain our services</li>
                        <li>To process transactions and send related information</li>
                        <li>To send promotional communications (with your consent)</li>
                        <li>To analyze usage and improve our platform</li>
                        <li>To detect and prevent fraud</li>
                        <li>To comply with legal obligations</li>
                    </ul>
                </section>

                <!-- Information Sharing -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">4. Information Sharing</h2>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        We do not sell your personal information. We may share information with:
                    </p>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li><strong>Service Providers:</strong> Stripe (payments), Printful (merchandise), and hosting providers</li>
                        <li><strong>Business Partners:</strong> When you scan a business's QR code, they receive scan analytics</li>
                        <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                    </ul>
                </section>

                <!-- Data Security -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">5. Data Security</h2>
                    <p class="text-gray-300 leading-relaxed">
                        We implement industry-standard security measures including encryption, secure connections (HTTPS), and regular security audits. However, no method of transmission over the Internet is 100% secure.
                    </p>
                </section>

                <!-- Your Rights -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">6. Your Rights</h2>
                    <p class="text-gray-300 leading-relaxed mb-4">You have the right to:</p>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>Access your personal data</li>
                        <li>Correct inaccurate data</li>
                        <li>Request deletion of your data</li>
                        <li>Export your data</li>
                        <li>Opt-out of marketing communications</li>
                    </ul>
                    <p class="text-gray-300 leading-relaxed mt-4">
                        To exercise these rights, contact us at <a href="mailto:support@revenueqr.com" class="text-primary-400 hover:underline">support@revenueqr.com</a>
                    </p>
                </section>

                <!-- Cookies -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">7. Cookies</h2>
                    <p class="text-gray-300 leading-relaxed">
                        We use cookies to maintain your session, remember preferences, and analyze usage. You can control cookies through your browser settings, but disabling them may affect functionality.
                    </p>
                </section>

                <!-- Children's Privacy -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">8. Children's Privacy</h2>
                    <p class="text-gray-300 leading-relaxed">
                        Our services are not intended for children under 13. We do not knowingly collect information from children under 13. If you believe we have collected such information, please contact us.
                    </p>
                </section>

                <!-- Changes -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">9. Changes to This Policy</h2>
                    <p class="text-gray-300 leading-relaxed">
                        We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date.
                    </p>
                </section>

                <!-- Contact -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">10. Contact Us</h2>
                    <p class="text-gray-300 leading-relaxed">
                        If you have questions about this Privacy Policy, please contact us at:
                    </p>
                    <div class="mt-4 p-4 bg-white/5 rounded-lg">
                        <p class="text-white font-medium">Revenue QR</p>
                        <p class="text-gray-400">Email: <a href="mailto:support@revenueqr.com" class="text-primary-400 hover:underline">support@revenueqr.com</a></p>
                    </div>
                </section>
            </div>

            <div class="mt-8 pt-8 border-t border-white/10">
                <Link href="/" class="text-primary-400 hover:text-primary-300">
                    ← Back to Home
                </Link>
            </div>
        </div>
    </div>
</template>
