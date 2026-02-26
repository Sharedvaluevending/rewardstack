<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    showNavbar: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const mobileMenuOpen = ref(false);

// Notifications
const notifications = computed(() => page.props.auth?.notifications || []);
const unreadCount = computed(() => page.props.auth?.unreadNotificationsCount || 0);
const showNotifications = ref(false);

const markAsRead = (id) => {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
    showNotifications.value = false;
};

const markAllAsRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
};

const navigation = computed(() => {
    if (!user.value) {
        return [
            { name: 'Home', href: '/', icon: 'home' },
            { name: 'Features', href: '/features', icon: 'star' },
            { name: 'Pricing', href: '/pricing', icon: 'tag' },
        ];
    }
    
    // Role-based navigation
    if (user.value.role === 'admin') {
        return [
            { name: 'Dashboard', href: '/admin/dashboard', icon: 'chart' },
            {
                name: 'Businesses',
                children: [
                    { name: 'All Businesses', href: '/admin/businesses' },
                    { name: 'Add Business', href: '/admin/businesses/create' },
                ],
                icon: 'building',
            },
            {
                name: 'Users',
                children: [
                    { name: 'All Users', href: '/admin/users' },
                    { name: 'Add User', href: '/admin/users/create' },
                ],
                icon: 'users',
            },
            { name: 'Health', href: '/admin/business-health', icon: 'heart' },
            { name: 'QRcade', href: '/admin/qrcade', icon: 'games' },
            { name: 'Merch', href: '/admin/merch', icon: 'shopping' },
            { name: 'Referrals', href: '/admin/referrals', icon: 'cash' },
            { name: 'Analytics', href: '/admin/analytics', icon: 'analytics' },
            { name: 'Admin QR Codes', href: '/admin/onboarding-qr', icon: 'qr' },
            { name: 'CRM', href: '/admin/crm', icon: 'mail' },
            { name: 'Settings', href: '/admin/settings', icon: 'cog' },
        ];
    }
    
    if (user.value.role === 'business') {
        return [
            { name: 'Dashboard', href: '/business/dashboard' },
            { name: 'Promotions', href: '/business/promotions' },
            { name: 'QR Codes', href: '/business/qr-codes' },
            { name: 'QRcade', href: '/business/qrcade' },
            {
                name: 'Network',
                children: [
                    { name: 'Partnerships', href: '/business/partnerships' },
                    { name: 'Deal Pools', href: '/business/stackable-pools' },
                ]
            },
            {
                name: 'Analytics',
                children: [
                    { name: 'Overview', href: '/business/analytics' },
                    { name: 'Finance', href: '/business/analytics/finance' },
                    { name: 'Partnerships', href: '/business/analytics/partnerships' },
                    { name: 'Ambassador Merch', href: '/business/analytics/merch' },
                ]
            },
            {
                name: 'CRM',
                children: [
                    { name: 'Dashboard', href: '/business/crm' },
                    { name: 'Customers', href: '/business/crm/customers' },
                    { name: 'AI Recommendations', href: '/business/crm/recommendations' },
                    { name: 'Campaigns', href: '/business/crm/campaigns' },
                    { name: 'Segments', href: '/business/crm/segments' },
                    { name: 'Automations', href: '/business/crm/automations' },
                    { name: 'Settings', href: '/business/crm/settings' },
                ]
            },
            {
                name: 'Print & Shop',
                children: [
                    { name: 'Print Studio', href: '/business/print-studio' },
                    { name: 'Sticker Kits', href: '/business/print-kits' },
                    { name: 'Merch Store', href: '/business/merch' },
                ]
            },
            {
                name: 'Manage',
                children: [
                    { name: 'Employees', href: '/business/employees' },
                    { name: 'Billing', href: '/business/billing' },
                    { name: 'Settings', href: '/business/settings' },
                    { name: 'FAQ', href: '/business/help/faq' },
                ]
            },
        ];
    }
    
    if (user.value.role === 'employee') {
        return [
            { name: 'Scan & Redeem', href: '/employee/redeem', icon: 'scan' },
            { name: 'My Activity', href: '/employee/activity', icon: 'clock' },
        ];
    }
    
    // User/Customer role - redirect to portal
    if (user.value.role === 'user' || user.value.role === 'customer') {
        return [
            { name: 'Dashboard', href: '/portal', icon: 'home' },
            { name: 'My Games', href: '/portal/games', icon: 'games' },
            { name: 'Rewards', href: '/portal/rewards', icon: 'gift' },
            { name: 'Leaderboards', href: '/portal/leaderboards', icon: 'trophy' },
            { name: 'Referrals', href: '/portal/referrals', icon: 'cash' },
        ];
    }
    
    return [
        { name: 'Home', href: '/', icon: 'home' },
        { name: 'Features', href: '/features', icon: 'star' },
        { name: 'Pricing', href: '/pricing', icon: 'tag' },
    ];
});

// Dropdown state management
const openDropdown = ref(null);
const toggleDropdown = (name) => {
    if (openDropdown.value === name) {
        openDropdown.value = null;
    } else {
        openDropdown.value = name;
    }
};

// Close dropdown on click outside
if (typeof window !== 'undefined') {
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-dropdown')) {
            openDropdown.value = null;
        }
    });
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
        <!-- Navigation -->
        <nav v-if="showNavbar" class="glass-dark sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex-shrink-0">
                        <Link href="/" class="flex items-center space-x-2">
                            <div class="w-10 h-10 flex items-center justify-center">
                                <img src="/brand/logoRQ.png" alt="Revenue QR" class="w-10 h-10 object-contain" />
                            </div>
                            <span class="text-xl font-bold gradient-text">Revenue QR</span>
                        </Link>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center space-x-1">
                        <template v-for="item in navigation" :key="item.name">
                            <!-- Direct Link -->
                            <Link
                                v-if="!item.children"
                                :href="item.href"
                                class="px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-all duration-200 text-sm font-medium"
                                :class="{ 'bg-white/10 text-white': page.url === item.href }"
                            >
                                {{ item.name }}
                            </Link>

                            <!-- Dropdown -->
                            <div v-else class="relative nav-dropdown">
                                <button
                                    @click.stop="toggleDropdown(item.name)"
                                    class="flex items-center px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-all duration-200 text-sm font-medium"
                                    :class="{ 'bg-white/10 text-white': openDropdown === item.name }"
                                >
                                    <span>{{ item.name }}</span>
                                    <svg class="w-4 h-4 ml-1 transition-transform duration-200" :class="{ 'rotate-180': openDropdown === item.name }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <transition
                                    enter-active-class="transition duration-100 ease-out"
                                    enter-from-class="transform scale-95 opacity-0"
                                    enter-to-class="transform scale-100 opacity-100"
                                    leave-active-class="transition duration-75 ease-in"
                                    leave-from-class="transform scale-100 opacity-100"
                                    leave-to-class="transform scale-95 opacity-0"
                                >
                                    <div
                                        v-if="openDropdown === item.name"
                                        class="absolute left-0 mt-2 w-48 rounded-xl bg-gray-900 border border-white/10 shadow-2xl py-2 z-[60]"
                                    >
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.name"
                                            :href="child.href"
                                            class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5 transition-colors"
                                            @click="openDropdown = null"
                                        >
                                            {{ child.name }}
                                        </Link>
                                    </div>
                                </transition>
                            </div>
                        </template>
                    </div>

                    <!-- Auth Buttons -->
                    <div class="hidden md:flex items-center space-x-4">
                        <template v-if="!user">
                            <Link href="/login" class="text-gray-300 hover:text-white transition-colors">
                                Sign In
                            </Link>
                            <Link href="/register" class="btn-primary text-sm">
                                Get Started
                            </Link>
                        </template>
                        <template v-else>
                            <div class="flex items-center space-x-4">
                                <!-- Notifications -->
                                <div class="relative">
                                    <button @click="showNotifications = !showNotifications" class="relative p-1 rounded-full text-gray-400 hover:text-white focus:outline-none">
                                        <span class="sr-only">View notifications</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                        </svg>
                                        <span v-if="unreadCount > 0" class="absolute top-0 right-0 block h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-gray-900"></span>
                                    </button>

                                    <!-- Dropdown -->
                                    <div v-if="showNotifications" class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-xl bg-gray-800 py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none border border-white/10">
                                        <div class="px-4 py-2 border-b border-white/10 flex justify-between items-center">
                                            <h3 class="text-sm font-semibold text-white">Notifications</h3>
                                            <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs text-primary-400 hover:text-primary-300">Mark all read</button>
                                        </div>
                                        <div v-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400 text-sm">
                                            No new notifications
                                        </div>
                                        <div v-else class="max-h-96 overflow-y-auto">
                                            <Link
                                                v-for="notification in notifications"
                                                :key="notification.id"
                                                :href="notification.data.action_url || '#'"
                                                class="block px-4 py-3 hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                                @click="markAsRead(notification.id)"
                                            >
                                                <div class="flex items-start">
                                                    <div class="flex-shrink-0 pt-0.5 mr-3">
                                                        <span class="text-lg">{{ notification.data.icon || '📢' }}</span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm text-gray-300 break-words">{{ notification.data.message }}</p>
                                                        <p class="mt-1 text-xs text-gray-500">{{ new Date(notification.created_at).toLocaleDateString() }}</p>
                                                    </div>
                                                    <div v-if="!notification.read_at" class="ml-2 flex-shrink-0">
                                                        <span class="block h-2 w-2 rounded-full bg-primary-500"></span>
                                                    </div>
                                                </div>
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <span class="text-gray-400">{{ user.name }}</span>
                                    <Link href="/logout" method="post" as="button" class="text-gray-300 hover:text-white transition-colors">
                                        Logout
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div v-if="mobileMenuOpen" class="md:hidden border-t border-white/10">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <template v-for="item in navigation" :key="item.name">
                        <Link
                            v-if="!item.children"
                            :href="item.href"
                            class="block px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10"
                            @click="mobileMenuOpen = false"
                        >
                            {{ item.name }}
                        </Link>
                        
                        <div v-else class="space-y-1">
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ item.name }}
                            </div>
                            <Link
                                v-for="child in item.children"
                                :key="child.name"
                                :href="child.href"
                                class="block px-6 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 text-sm"
                                @click="mobileMenuOpen = false"
                            >
                                {{ child.name }}
                            </Link>
                        </div>
                    </template>

                    <div v-if="user" class="mt-2 pt-2 border-t border-white/10">
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="block w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10"
                        >
                            Log Out
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/10 mt-auto">
            <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="text-gray-400 text-sm">
                        &copy; {{ new Date().getFullYear() }} Revenue QR. All rights reserved.
                    </div>
                    <div class="flex space-x-6">
                        <Link
                            v-if="user?.role === 'business'"
                            href="/business/help/faq"
                            class="text-gray-400 hover:text-white transition-colors text-sm"
                        >
                            FAQ
                        </Link>
                        <Link
                            v-else
                            href="/pricing#faqs"
                            class="text-gray-400 hover:text-white transition-colors text-sm"
                        >
                            FAQ
                        </Link>
                        <Link href="/privacy" class="text-gray-400 hover:text-white transition-colors text-sm">Privacy</Link>
                        <Link href="/terms" class="text-gray-400 hover:text-white transition-colors text-sm">Terms</Link>
                        <a href="mailto:support@revenueqr.com" class="text-gray-400 hover:text-white transition-colors text-sm">Support</a>
                    </div>
                </div>

                <!-- Integrations (text-only; no logos / no partnership claims) -->
                <div class="mt-6 flex justify-center">
                    <div class="text-xs text-gray-500 bg-white/5 border border-white/10 rounded-full px-4 py-2 backdrop-blur-sm">
                        <span class="text-gray-400 font-semibold">Integrations:</span>
                        Stripe (payments) · Printful (merch fulfillment) · Avery Design &amp; Print Online (label printing)
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

