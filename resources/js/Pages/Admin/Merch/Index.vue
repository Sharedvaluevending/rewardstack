<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    products: Array,
    stats: Object,
    recentOrders: Array,
    storeBusiness: Object,
    storeBusinesses: Array,
    storeEnabled: Boolean,
});

const formatCurrency = (num) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num || 0);
const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0);

const syncing = ref(false);
const editingProduct = ref(null);
const showAddModal = ref(false);
const showSyncDropdown = ref(false);
const selectedStoreBusinessId = ref(props.storeBusiness?.id || null);

const categories = [
    { value: 't-shirt', label: 'T-Shirts', icon: '👕' },
    { value: 'hoodie', label: 'Hoodies', icon: '🧥' },
    { value: 'mug', label: 'Mugs', icon: '☕' },
    { value: 'poster', label: 'Posters', icon: '🖼️' },
    { value: 'sticker', label: 'Stickers', icon: '🏷️' },
    { value: 'bag', label: 'Bags', icon: '👜' },
    { value: 'hat', label: 'Hats', icon: '🧢' },
    { value: 'phone_case', label: 'Phone Cases', icon: '📱' },
    { value: 'home', label: 'Home', icon: '🏠' },
    { value: 'other', label: 'Other', icon: '📦' },
];

const getCategoryIcon = (cat) => categories.find(c => c.value === cat)?.icon || '📦';
const getCategoryLabel = (cat) => categories.find(c => c.value === cat)?.label || cat;

const productsByCategory = computed(() => {
    const grouped = {};
    props.products?.forEach(product => {
        const cat = product.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(product);
    });
    return grouped;
});

const syncProducts = () => {
    showSyncDropdown.value = false;
    syncing.value = true;
    router.post('/admin/merch/sync', {}, {
        onFinish: () => syncing.value = false,
    });
};

const syncSpecificProducts = () => {
    showSyncDropdown.value = false;
    const productIds = prompt('Enter Printful catalog product IDs (comma-separated):', '');
    if (productIds && productIds.trim()) {
        syncing.value = true;
        router.post('/admin/merch/sync', { product_ids: productIds.trim() }, {
            onFinish: () => syncing.value = false,
        });
    }
};

const syncStoreProducts = () => {
    showSyncDropdown.value = false;
    const storeProductIds = prompt('Enter your Printful store product IDs (comma-separated).\n\nFind these IDs in your Printful dashboard under Products.', '');
    if (storeProductIds && storeProductIds.trim()) {
        syncing.value = true;
        router.post('/admin/merch/sync', { store_product_ids: storeProductIds.trim() }, {
            onFinish: () => syncing.value = false,
        });
    }
};

const syncAllProducts = () => {
    showSyncDropdown.value = false;
    if (confirm('This will sync ALL catalog products from Printful. This may take a while. Continue?')) {
        syncing.value = true;
        router.post('/admin/merch/sync', { sync_all: true }, {
            onFinish: () => syncing.value = false,
        });
    }
};

const toggleProduct = (product) => {
    router.post(`/admin/merch/products/${product.id}/toggle`);
};

const editForm = useForm({
    name: '',
    description: '',
    base_price: 0,
    sort_order: 0,
    print_config: null,
});

const showPrintConfig = ref(false);
const printConfigProduct = ref(null);

const printPlacements = [
    { value: 'front', label: 'Front Center' },
    { value: 'back', label: 'Back Center' },
    { value: 'sleeve_left', label: 'Left Sleeve' },
    { value: 'sleeve_right', label: 'Right Sleeve' },
    { value: 'default', label: 'Default/Wrap' },
];

const printSizes = [
    { value: 'tiny', label: 'Tiny (2")', width: 150, height: 150 },
    { value: 'small', label: 'Small (3")', width: 300, height: 300 },
    { value: 'medium', label: 'Medium (5")', width: 600, height: 600 },
    { value: 'large', label: 'Large (8")', width: 800, height: 800 },
    { value: 'xlarge', label: 'X-Large (12")', width: 1200, height: 1200 },
];

const startEdit = (product) => {
    editingProduct.value = product;
    editForm.name = product.name;
    editForm.description = product.description;
    editForm.base_price = product.base_price;
    editForm.sort_order = product.sort_order || 0;
};

const saveEdit = () => {
    editForm.put(`/admin/merch/products/${editingProduct.value.id}`, {
        onSuccess: () => editingProduct.value = null,
    });
};

const cancelEdit = () => {
    editingProduct.value = null;
    editForm.reset();
};

const openPrintConfig = (product) => {
    printConfigProduct.value = product;
    editForm.print_config = product.print_config || getDefaultPrintConfig(product.category);
    showPrintConfig.value = true;
};

const getDefaultPrintConfig = (category) => {
    const configs = {
        't-shirt': {
            logo: { placement: 'front', size: 'medium' },
            qr_code: { placement: 'sleeve_left', size: 'small' },
        },
        'hoodie': {
            logo: { placement: 'front', size: 'medium' },
            qr_code: { placement: 'sleeve_left', size: 'small' },
        },
        'mug': {
            logo: { placement: 'default', size: 'medium' },
            qr_code: { placement: 'default', size: 'small' },
        },
    };
    return configs[category] || configs['t-shirt'];
};

const savePrintConfig = () => {
    router.put(`/admin/merch/products/${printConfigProduct.value.id}`, {
        print_config: editForm.print_config,
    }, {
        onSuccess: () => {
            showPrintConfig.value = false;
            printConfigProduct.value = null;
        },
    });
};

const addForm = useForm({
    name: '',
    description: '',
    category: 't-shirt',
    base_price: 25,
    printful_product_id: '',
});

const createProduct = () => {
    addForm.post('/admin/merch/products', {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const deleteProduct = (product) => {
    if (confirm(`Delete "${product.name}"? This cannot be undone.`)) {
        router.delete(`/admin/merch/products/${product.id}`);
    }
};

const placeholderImages = {
    't-shirt': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><path d="M60 50 L80 30 L120 30 L140 50 L160 60 L150 80 L140 75 L140 160 L60 160 L60 75 L50 80 L40 60 Z" fill="#4B5563"/><rect x="85" y="80" width="30" height="30" rx="3" fill="#6B7280"/></svg>`),
    'hoodie': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><path d="M50 60 L70 40 L90 50 L100 45 L110 50 L130 40 L150 60 L160 70 L150 90 L140 85 L145 160 L55 160 L60 85 L50 90 L40 70 Z" fill="#4B5563"/><ellipse cx="100" cy="55" rx="15" ry="10" fill="#374151"/><rect x="85" y="85" width="30" height="30" rx="3" fill="#6B7280"/></svg>`),
    'mug': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><rect x="50" y="60" width="80" height="100" rx="5" fill="#4B5563"/><path d="M130 75 Q160 75 160 110 Q160 145 130 145" fill="none" stroke="#4B5563" stroke-width="10"/><rect x="65" y="90" width="50" height="40" rx="3" fill="#6B7280"/></svg>`),
    'poster': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><rect x="40" y="30" width="120" height="140" rx="3" fill="#4B5563"/><rect x="70" y="70" width="60" height="60" rx="3" fill="#6B7280"/></svg>`),
    'sticker': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><circle cx="100" cy="100" r="60" fill="#4B5563"/><rect x="80" y="80" width="40" height="40" rx="3" fill="#6B7280"/></svg>`),
    'bag': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><rect x="50" y="70" width="100" height="100" rx="5" fill="#4B5563"/><path d="M70 70 L70 50 Q100 30 130 50 L130 70" fill="none" stroke="#4B5563" stroke-width="8"/><rect x="75" y="100" width="50" height="40" rx="3" fill="#6B7280"/></svg>`),
    'hat': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><ellipse cx="100" cy="130" rx="70" ry="20" fill="#4B5563"/><path d="M50 130 Q50 70 100 60 Q150 70 150 130" fill="#4B5563"/><rect x="80" y="85" width="40" height="30" rx="3" fill="#6B7280"/></svg>`),
    'phone_case': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><rect x="60" y="30" width="80" height="140" rx="15" fill="#4B5563"/><rect x="75" y="70" width="50" height="50" rx="3" fill="#6B7280"/></svg>`),
    'default': 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect fill="#374151" width="200" height="200"/><rect x="50" y="50" width="100" height="100" rx="10" fill="#4B5563"/><rect x="75" y="75" width="50" height="50" rx="5" fill="#6B7280"/></svg>`),
};

const getProductImage = (product) => {
    if (product.images?.length > 0) {
        const firstValid = product.images.find((img) => typeof img === 'string' && img.trim());
        if (firstValid) {
            return firstValid;
        }
    }
    if (product.preview_template_url) {
        return product.preview_template_url;
    }
    return placeholderImages[product.category] || placeholderImages['default'];
};

const applyStoreBusiness = () => {
    if (!selectedStoreBusinessId.value) return;
    router.get('/admin/merch', { business_id: selectedStoreBusinessId.value }, {
        preserveState: true,
        replace: true,
    });
};

// Click outside handler to close dropdown
const handleClickOutside = (event) => {
    const dropdown = document.querySelector('.sync-dropdown-container');
    const button = document.querySelector('.sync-dropdown-button');

    if (dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
        showSyncDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const getStatusColor = (status) => {
    const colors = {
        pending: 'bg-yellow-500/20 text-yellow-400',
        processing: 'bg-blue-500/20 text-blue-400',
        shipped: 'bg-purple-500/20 text-purple-400',
        delivered: 'bg-green-500/20 text-green-400',
        cancelled: 'bg-red-500/20 text-red-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <Head title="Merch Management" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Merch Store</h1>
                <p class="text-gray-400 mt-1">Manage products and orders</p>
            </div>
            <div class="flex gap-3">
                <div class="glass-card px-4 py-2">
                    <label class="block text-xs text-gray-500 mb-1">Store view</label>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedStoreBusinessId"
                            @change="applyStoreBusiness"
                            class="input-glass text-sm py-2"
                        >
                            <option v-for="b in (storeBusinesses || [])" :key="b.id" :value="b.id" class="bg-gray-800 text-white">
                                {{ b.name }}
                            </option>
                        </select>
                        <span
                            :class="storeEnabled ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
                            class="px-2 py-1 rounded text-xs"
                        >
                            {{ storeEnabled ? 'Store Enabled' : 'Store Disabled' }}
                        </span>
                    </div>
                </div>
                <div class="relative sync-dropdown-container">
                <button
                        @click="showSyncDropdown = !showSyncDropdown"
                    :disabled="syncing"
                        class="sync-dropdown-button btn-secondary flex items-center gap-2"
                >
                    <svg v-if="syncing" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span v-else>🔄</span>
                        {{ syncing ? 'Syncing...' : 'Sync Store' }}
                        <svg v-if="!syncing" class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Dropdown for sync options -->
                    <div v-if="showSyncDropdown && !syncing" class="absolute top-full mt-2 right-0 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-10 min-w-48">
                        <button
                            @click="syncSpecificProducts"
                            class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white rounded-t-lg"
                        >
                            🎯 Sync Catalog IDs
                        </button>
                        <button
                            @click="syncStoreProducts"
                            class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white"
                        >
                            🏪 Sync Specific Store IDs
                        </button>
                        <button
                            @click="syncAllProducts"
                            class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white rounded-b-lg"
                        >
                            🌍 Sync All Catalog
                </button>
                    </div>
                </div>

                <button @click="showAddModal = true" class="btn-primary">
                    + Add Product
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Total Products</p>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats?.total_products) }}</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Active</p>
                <p class="text-3xl font-bold text-green-400">{{ formatNumber(stats?.active_products) }}</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Total Orders</p>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats?.total_orders) }}</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Pending</p>
                <p class="text-3xl font-bold text-yellow-400">{{ formatNumber(stats?.pending_orders) }}</p>
            </div>
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm">Revenue</p>
                <p class="text-3xl font-bold text-green-400">{{ formatCurrency(stats?.revenue) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Products List -->
            <div class="lg:col-span-2 space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Products</h2>
                    <Link href="/admin/merch/orders" class="text-primary-400 hover:text-primary-300 text-sm">
                        View All Orders →
                    </Link>
                </div>

                <!-- Products by Category -->
                <div v-for="(catProducts, category) in productsByCategory" :key="category" class="glass-card overflow-hidden">
                    <div class="px-6 py-4 bg-white/5 border-b border-white/10">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <span>{{ getCategoryIcon(category) }}</span>
                            {{ getCategoryLabel(category) }}
                            <span class="text-sm text-gray-500">({{ catProducts.length }})</span>
                        </h3>
                    </div>
                    
                    <div class="divide-y divide-white/10">
                        <div 
                            v-for="product in catProducts" 
                            :key="product.id"
                            class="p-4 hover:bg-white/5 transition-all"
                        >
                            <div class="flex items-center gap-4">
                                <!-- Product Image -->
                                <div class="w-16 h-16 rounded-lg bg-white/10 overflow-hidden flex-shrink-0">
                                    <img 
                                        :src="getProductImage(product)" 
                                        :alt="product.name"
                                        class="w-full h-full object-cover"
                                        @error="$event.target.src = placeholderImages['default']"
                                    />
                                </div>

                                <!-- Product Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-white font-medium truncate">{{ product.name }}</h4>
                                        <span 
                                            :class="product.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
                                            class="px-2 py-0.5 rounded text-xs"
                                        >
                                            {{ product.is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    <span
                                        :class="product.store_visible ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-500/20 text-gray-400'"
                                        class="px-2 py-0.5 rounded text-xs"
                                        :title="product.store_reason"
                                    >
                                        {{ product.store_visible ? 'Store Live' : 'Store Hidden' }}
                                    </span>
                                    </div>
                                    <p class="text-gray-500 text-sm truncate">{{ product.description }}</p>
                                    <div class="flex items-center gap-4 mt-1 text-sm">
                                        <span class="text-green-400 font-medium">{{ formatCurrency(product.base_price) }}</span>
                                        <span v-if="product.cost_price" class="text-gray-500" title="Printful cost">
                                            (cost: {{ formatCurrency(product.cost_price) }})
                                        </span>
                                        <span class="text-gray-500">{{ product.variants?.length || 0 }} variants</span>
                                        <span class="text-gray-500">{{ product.order_items_count || 0 }} orders</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="openPrintConfig(product)"
                                        class="p-2 text-gray-400 hover:text-primary-400 transition-colors"
                                        title="Print Placement"
                                    >
                                        🎯
                                    </button>
                                    <button 
                                        @click="startEdit(product)"
                                        class="p-2 text-gray-400 hover:text-white transition-colors"
                                        title="Edit"
                                    >
                                        ✏️
                                    </button>
                                    <button 
                                        @click="toggleProduct(product)"
                                        class="p-2 transition-colors"
                                        :class="product.is_active ? 'text-green-400 hover:text-red-400' : 'text-gray-400 hover:text-green-400'"
                                        :title="product.is_active ? 'Disable' : 'Enable'"
                                    >
                                        {{ product.is_active ? '✅' : '⭕' }}
                                    </button>
                                    <button 
                                        @click="deleteProduct(product)"
                                        class="p-2 text-gray-400 hover:text-red-400 transition-colors"
                                        title="Delete"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!products?.length" class="glass-card p-12 text-center">
                    <p class="text-6xl mb-4">📦</p>
                    <h3 class="text-xl font-semibold text-white mb-2">No Products Yet</h3>
                    <p class="text-gray-400 mb-6">Sync from Printful or add products manually</p>
                    <button @click="syncProducts" class="btn-primary">
                        🔄 Sync from Printful
                    </button>
                </div>
            </div>

            <!-- Recent Orders Sidebar -->
            <div class="space-y-6">
                <h2 class="text-xl font-semibold text-white">Recent Orders</h2>
                
                <div class="glass-card divide-y divide-white/10">
                    <Link 
                        v-for="order in recentOrders" 
                        :key="order.id"
                        :href="`/admin/merch/orders/${order.id}`"
                        class="block p-4 hover:bg-white/5 transition-all"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-white font-medium">{{ order.order_number }}</span>
                            <span :class="getStatusColor(order.status)" class="px-2 py-0.5 rounded text-xs capitalize">
                                {{ order.status }}
                            </span>
                        </div>
                        <p class="text-gray-400 text-sm">{{ order.business?.name }}</p>
                        <div class="flex justify-between items-center mt-2 text-sm">
                            <span class="text-gray-500">{{ order.items?.length || 0 }} items</span>
                            <span class="text-green-400">{{ formatCurrency(order.total) }}</span>
                        </div>
                    </Link>
                    
                    <div v-if="!recentOrders?.length" class="p-8 text-center">
                        <p class="text-gray-500">No orders yet</p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="glass-card p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Quick Links</h3>
                    <Link href="/admin/merch/orders" class="block p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all">
                        <span class="text-white">📋 All Orders</span>
                    </Link>
                    <a href="https://www.printful.com/dashboard" target="_blank" class="block p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all">
                        <span class="text-white">🖨️ Printful Dashboard</span>
                        <span class="text-gray-500 text-sm ml-2">↗</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div v-if="editingProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
        <div class="glass-card max-w-lg w-full p-6">
            <h3 class="text-xl font-semibold text-white mb-6">Edit Product</h3>
            
            <form @submit.prevent="saveEdit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                    <input v-model="editForm.name" type="text" class="input-glass w-full" />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <textarea v-model="editForm.description" rows="3" class="input-glass w-full"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Base Price ($)</label>
                        <input v-model="editForm.base_price" type="number" step="0.01" class="input-glass w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Sort Order</label>
                        <input v-model="editForm.sort_order" type="number" class="input-glass w-full" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="cancelEdit" class="btn-secondary">Cancel</button>
                    <button type="submit" :disabled="editForm.processing" class="btn-primary">
                        {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
        <div class="glass-card max-w-lg w-full p-6">
            <h3 class="text-xl font-semibold text-white mb-6">Add New Product</h3>
            
            <form @submit.prevent="createProduct" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                    <input v-model="addForm.name" type="text" class="input-glass w-full" required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Category</label>
                    <select v-model="addForm.category" class="input-glass w-full">
                        <option v-for="cat in categories" :key="cat.value" :value="cat.value" class="bg-gray-800 text-white">
                            {{ cat.icon }} {{ cat.label }}
                        </option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <textarea v-model="addForm.description" rows="3" class="input-glass w-full" required></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Base Price ($)</label>
                        <input v-model="addForm.base_price" type="number" step="0.01" class="input-glass w-full" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Printful Product ID</label>
                        <input v-model="addForm.printful_product_id" type="text" class="input-glass w-full" placeholder="Optional" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showAddModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" :disabled="addForm.processing" class="btn-primary">
                        {{ addForm.processing ? 'Creating...' : 'Create Product' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print Config Modal -->
    <div v-if="showPrintConfig && printConfigProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
        <div class="glass-card max-w-2xl w-full p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-semibold text-white">Print Placement Settings</h3>
                    <p class="text-gray-400 text-sm">{{ printConfigProduct.name }}</p>
                </div>
                <button @click="showPrintConfig = false" class="p-2 text-gray-400 hover:text-white">✕</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Preview -->
                <div class="space-y-4">
                    <h4 class="text-white font-medium">Preview</h4>
                    <div class="aspect-square bg-gradient-to-br from-gray-700 to-gray-800 rounded-xl relative overflow-hidden">
                        <img :src="getProductImage(printConfigProduct)" class="w-full h-full object-cover opacity-50" />
                        
                        <!-- Logo Preview -->
                        <div 
                            class="absolute bg-primary-500/80 rounded flex items-center justify-center text-white text-xs font-bold"
                            :class="{
                                'top-[25%] left-1/2 -translate-x-1/2 w-20 h-20': editForm.print_config?.logo?.placement === 'front',
                                'top-[25%] left-1/2 -translate-x-1/2 w-20 h-20': editForm.print_config?.logo?.placement === 'back',
                                'top-[30%] right-4 w-10 h-10': editForm.print_config?.logo?.placement === 'sleeve_right',
                                'top-[30%] left-4 w-10 h-10': editForm.print_config?.logo?.placement === 'sleeve_left',
                            }"
                        >
                            LOGO
                        </div>
                        
                        <!-- QR Preview -->
                        <div 
                            class="absolute bg-white rounded flex items-center justify-center"
                            :class="{
                                'bottom-[25%] left-1/2 -translate-x-1/2 w-12 h-12': editForm.print_config?.qr_code?.placement === 'front',
                                'bottom-[25%] left-1/2 -translate-x-1/2 w-12 h-12': editForm.print_config?.qr_code?.placement === 'back',
                                'top-[50%] right-4 w-8 h-8': editForm.print_config?.qr_code?.placement === 'sleeve_right',
                                'top-[50%] left-4 w-8 h-8': editForm.print_config?.qr_code?.placement === 'sleeve_left',
                            }"
                        >
                            <svg class="w-full h-full p-1 text-gray-800" viewBox="0 0 100 100">
                                <rect x="15" y="15" width="25" height="25" fill="currentColor"/>
                                <rect x="60" y="15" width="25" height="25" fill="currentColor"/>
                                <rect x="15" y="60" width="25" height="25" fill="currentColor"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-500 text-xs text-center">
                        🎯 Logo = colored | QR Code = white box
                    </p>
                </div>

                <!-- Settings -->
                <div class="space-y-6">
                    <!-- Logo Settings -->
                    <div class="space-y-3">
                        <h4 class="text-white font-medium flex items-center gap-2">
                            <span class="w-3 h-3 rounded bg-primary-500"></span>
                            Business Logo
                        </h4>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Placement</label>
                            <select v-model="editForm.print_config.logo.placement" class="input-glass w-full">
                                <option v-for="p in printPlacements" :key="p.value" :value="p.value" class="bg-gray-800">
                                    {{ p.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Size</label>
                            <select v-model="editForm.print_config.logo.size" class="input-glass w-full">
                                <option v-for="s in printSizes" :key="s.value" :value="s.value" class="bg-gray-800 text-white">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- QR Code Settings -->
                    <div class="space-y-3">
                        <h4 class="text-white font-medium flex items-center gap-2">
                            <span class="w-3 h-3 rounded bg-white"></span>
                            QR Code
                        </h4>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Placement</label>
                            <select v-model="editForm.print_config.qr_code.placement" class="input-glass w-full">
                                <option v-for="p in printPlacements" :key="p.value" :value="p.value" class="bg-gray-800 text-white">
                                    {{ p.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Size</label>
                            <select v-model="editForm.print_config.qr_code.size" class="input-glass w-full">
                                <option v-for="s in printSizes" :key="s.value" :value="s.value" class="bg-gray-800 text-white">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Preset Buttons -->
                    <div class="pt-4 border-t border-white/10">
                        <p class="text-sm text-gray-400 mb-2">Quick Presets</p>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                type="button"
                                @click="editForm.print_config = { logo: { placement: 'front', size: 'medium' }, qr_code: { placement: 'sleeve_left', size: 'small' } }"
                                class="px-3 py-1 text-xs rounded bg-white/10 text-gray-300 hover:bg-white/20"
                            >
                                👕 Shirt Classic
                            </button>
                            <button 
                                type="button"
                                @click="editForm.print_config = { logo: { placement: 'default', size: 'medium' }, qr_code: { placement: 'default', size: 'small' } }"
                                class="px-3 py-1 text-xs rounded bg-white/10 text-gray-300 hover:bg-white/20"
                            >
                                ☕ Mug Wrap
                            </button>
                            <button 
                                type="button"
                                @click="editForm.print_config = { logo: { placement: 'front', size: 'large' }, qr_code: { placement: 'front', size: 'small' } }"
                                class="px-3 py-1 text-xs rounded bg-white/10 text-gray-300 hover:bg-white/20"
                            >
                                🖼️ Poster
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-white/10">
                <button type="button" @click="showPrintConfig = false" class="btn-secondary">Cancel</button>
                <button @click="savePrintConfig" class="btn-primary">Save Placement</button>
            </div>
        </div>
    </div>
</template>
