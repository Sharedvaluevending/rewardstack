<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});
</script>

<template>
    <Head title="Terms of Service" />

    <div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
        <div class="glass-card p-8 md:p-12">
            <h1 class="text-4xl font-bold text-white mb-2">Terms of Service</h1>
            <p class="text-gray-400 mb-8">Last updated: December 8, 2024</p>

            <div class="prose prose-invert max-w-none space-y-8">
                <!-- Agreement -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">1. Agreement to Terms</h2>
                    <p class="text-gray-300 leading-relaxed">
                        By accessing or using Revenue QR ("Service"), you agree to be bound by these Terms of Service. If you disagree with any part of these terms, you may not access the Service.
                    </p>
                </section>

                <!-- Description of Service -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">2. Description of Service</h2>
                    <p class="text-gray-300 leading-relaxed">
                        Revenue QR provides a platform for businesses to create and manage QR codes, promotions, games, and merchandise. Our services include:
                    </p>
                    <ul class="list-disc list-inside text-gray-300 space-y-2 mt-4">
                        <li>QR code generation and tracking</li>
                        <li>Promotion and discount management</li>
                        <li>Gamification features (QRcade games)</li>
                        <li>Merchandise ordering through print-on-demand</li>
                        <li>Analytics and insights</li>
                    </ul>
                </section>

                <!-- Account Terms -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">3. Account Terms</h2>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>You must be 18 years or older to use this Service</li>
                        <li>You must provide accurate and complete registration information</li>
                        <li>You are responsible for maintaining the security of your account</li>
                        <li>You are responsible for all activities that occur under your account</li>
                        <li>You must notify us immediately of any unauthorized use</li>
                    </ul>
                </section>

                <!-- Acceptable Use -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">4. Acceptable Use</h2>
                    <p class="text-gray-300 leading-relaxed mb-4">You agree NOT to:</p>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>Use the Service for any illegal purpose</li>
                        <li>Create QR codes or promotions that are fraudulent, deceptive, or misleading</li>
                        <li>Violate any laws regarding advertising or consumer protection</li>
                        <li>Transmit malware or other malicious code</li>
                        <li>Attempt to gain unauthorized access to our systems</li>
                        <li>Interfere with or disrupt the Service</li>
                        <li>Scrape or collect data without permission</li>
                        <li>Resell or redistribute the Service without authorization</li>
                    </ul>
                </section>

                <!-- Payment Terms -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">5. Payment Terms</h2>
                    
                    <h3 class="text-lg font-medium text-white mt-4 mb-2">Subscriptions</h3>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>Subscription fees are billed in advance on a monthly basis</li>
                        <li>You authorize us to charge your payment method automatically</li>
                        <li>Prices may change with 30 days notice</li>
                        <li>No refunds for partial months of service</li>
                    </ul>

                    <h3 class="text-lg font-medium text-white mt-4 mb-2">Merchandise Orders</h3>
                    <ul class="list-disc list-inside text-gray-300 space-y-2">
                        <li>All merchandise sales are final once production begins</li>
                        <li>Shipping times are estimates and not guaranteed</li>
                        <li>We are not responsible for shipping delays or lost packages</li>
                        <li>Defective items will be replaced at no cost</li>
                    </ul>
                </section>

                <!-- Intellectual Property -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">6. Intellectual Property</h2>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        <strong>Our Property:</strong> The Service, including its original content, features, and functionality, is owned by Revenue QR and protected by copyright, trademark, and other laws.
                    </p>
                    <p class="text-gray-300 leading-relaxed">
                        <strong>Your Content:</strong> You retain ownership of content you upload (logos, images). By uploading content, you grant us a license to use it to provide the Service.
                    </p>
                </section>

                <!-- Limitation of Liability -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">7. Limitation of Liability</h2>
                    <p class="text-gray-300 leading-relaxed">
                        TO THE MAXIMUM EXTENT PERMITTED BY LAW, REVENUE QR SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING LOSS OF PROFITS, DATA, OR OTHER INTANGIBLE LOSSES, RESULTING FROM YOUR USE OF THE SERVICE.
                    </p>
                    <p class="text-gray-300 leading-relaxed mt-4">
                        Our total liability shall not exceed the amount you paid us in the 12 months preceding the claim.
                    </p>
                </section>

                <!-- Disclaimer -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">8. Disclaimer</h2>
                    <p class="text-gray-300 leading-relaxed">
                        THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EXPRESS OR IMPLIED. WE DO NOT WARRANT THAT THE SERVICE WILL BE UNINTERRUPTED, SECURE, OR ERROR-FREE.
                    </p>
                </section>

                <!-- Termination -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">9. Termination</h2>
                    <p class="text-gray-300 leading-relaxed">
                        We may terminate or suspend your account immediately, without prior notice, for conduct that we believe violates these Terms or is harmful to other users, us, or third parties.
                    </p>
                    <p class="text-gray-300 leading-relaxed mt-4">
                        Upon termination, your right to use the Service will cease immediately. You may export your data before termination.
                    </p>
                </section>

                <!-- Governing Law -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">10. Governing Law</h2>
                    <p class="text-gray-300 leading-relaxed">
                        These Terms shall be governed by the laws of the United States, without regard to conflict of law provisions. Any disputes shall be resolved in the courts of [Your State/Jurisdiction].
                    </p>
                </section>

                <!-- Changes -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">11. Changes to Terms</h2>
                    <p class="text-gray-300 leading-relaxed">
                        We reserve the right to modify these Terms at any time. We will provide notice of significant changes. Your continued use of the Service after changes constitutes acceptance of the new Terms.
                    </p>
                </section>

                <!-- Contact -->
                <section>
                    <h2 class="text-2xl font-semibold text-white mb-4">12. Contact Us</h2>
                    <p class="text-gray-300 leading-relaxed">
                        If you have questions about these Terms, please contact us at:
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
