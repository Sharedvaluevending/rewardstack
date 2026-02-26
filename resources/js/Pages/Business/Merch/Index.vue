<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    products: Array,
    qrCodes: Array,
    recentOrders: Array,
    shippingRates: Array,
    businessLogo: String, // Business logo URL
    defaultShipping: Object,
    merchCurrency: String,
});

// Cart state
const cart = ref([]);
const selectedQRCode = ref(null);
const selectedVariant = ref({});
const selectedColor = ref({});
const selectedColorVariantId = ref({});
const selectedView = ref({});
const quantity = ref({});
const showCart = ref(false);
const showCheckout = ref(false);
const loading = ref(false);
const showMockupPreview = ref(false);
const previewProduct = ref(null);
const mockupLoading = ref(false);
const previewImage = ref(null);
const previewConfig = ref(null);
// CSRF helpers (fix 419s on fetch POST)
const getCookie = (name) => {
    const match = document.cookie
        ?.split('; ')
        .find((row) => row.startsWith(`${name}=`));
    if (!match) return null;
    // Cookie values can contain '=' (base64 padding). Don't split on '='.
    return match.substring(name.length + 1);
};

const getCsrfHeaders = () => {
    const headers = {};

    // Laravel provides both a meta csrf token and an XSRF-TOKEN cookie.
    const meta = document.querySelector('meta[name="csrf-token"]')?.content;
    if (meta) headers['X-CSRF-TOKEN'] = meta;

    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);

    headers['X-Requested-With'] = 'XMLHttpRequest';
    return headers;
};

// Debug logging disabled for performance
// fetch('http://127.0.0.1:7242/ingest/5aabbf36-9c6b-4389-b706-53fa9b49814b',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'Merch/Index.vue:34',message:'Component initialized',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'initial-load',hypothesisId:'debug_positioning_system'})}).catch(()=>{});
// #endregion

// Live editing state - store positions per view
const logoPosition = ref({});
const qrPosition = ref({});
const isEditing = ref(false);
const isDragging = ref(null); // 'logo' or 'qr'
const dragStart = ref({ x: 0, y: 0 });

const availableViewsForProduct = (product) => {
    if (!product?.category) return ['front'];
    if (product.category === 'mug') return ['wrap'];
    if (product.category === 't-shirt' || product.category === 'hoodie') {
        return ['front', 'back', 'sleeve_left', 'sleeve_right'];
    }
    return ['front'];
};

const getDefaultViewForProduct = (product) => {
    const views = availableViewsForProduct(product);
    return views[0] || 'front';
};

const normalizeColorName = (name) => {
    return String(name || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
};

const updateSelectedColorVariant = (product) => {
    if (!product?.id) return;
    const pid = product.id;
    const variantName = selectedVariant.value?.[pid] || product.variants?.[0]?.name;
    const colors = getAvailableColors(product, variantName) || [];
    const selectedName = selectedColor.value?.[pid];

    console.log('🎨 updateSelectedColorVariant:', {
        pid,
        variantName,
        selectedName,
        colors_count: colors.length,
        colors_sample: colors.slice(0, 2),
    });

    // Colors may be objects ({name, variant_id}) or strings.
    const matchObj = colors.find((c) => typeof c === 'object' && c && c.name === selectedName);
    if (matchObj?.variant_id) {
        selectedColorVariantId.value[pid] = matchObj.variant_id;
        console.log('✅ Found variant_id from color match:', matchObj.variant_id);
        return;
    }

    const variant = product.variants?.find((v) => v.name === variantName) || product.variants?.[0];
    const fallbackId = variant?.variant_ids?.[0] ?? null;
    selectedColorVariantId.value[pid] = fallbackId || null;
    console.log('⚠️ Using fallback variant_id:', fallbackId);
};

// Product placeholder images by category
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

// Select QR code with debugging
const selectQRCode = (qr) => {
    selectedQRCode.value = qr;
    if (showMockupPreview.value && previewProduct.value) {
        fetchPreview();
    }
};

// Get product image with fallback
const getProductImage = (product) => {
    // Prefer a real image URL (png/webp first to avoid lifestyle jpgs)
    if (product.images?.length > 0) {
        const preferredPng = product.images.find(
            (u) => typeof u === 'string' && /\.(png|webp)(\?.*)?$/i.test(u)
        );
        if (preferredPng) return preferredPng;

        const preferredAny = product.images.find(
            (u) => typeof u === 'string' && /\.(png|jpe?g|webp)(\?.*)?$/i.test(u)
        );
        return preferredAny || product.images[0];
    }

    // Template fallback
    if (product.preview_template_url) return product.preview_template_url;

    return placeholderImages[product.category] || placeholderImages['default'];
};

// Open mockup preview modal
const openMockupPreview = async (product) => {
    previewProduct.value = product;
    mockupLoading.value = true;
    showMockupPreview.value = true;

    try {
        // Check if user is authenticated
        const page = usePage();
        const user = page.props.auth?.user;

        if (!user) {
            console.error('User not authenticated');
            alert('Your session has expired. Please refresh the page and try again.');
            return;
        }

        ensureDesignDefaults(product);
        await fetchPreview();
    } catch (error) {
        console.error('Failed to generate preview:', error);
        // Fallback to product default image instead of white template
        previewImage.value = getProductImage(product);
        previewConfig.value = product.preview_config;
    } finally {
        mockupLoading.value = false;
    }
};

// Get current view
const getCurrentView = () => {
    if (!previewProduct.value) return 'front';
    return selectedView.value?.[previewProduct.value.id] || getDefaultViewForProduct(previewProduct.value);
};

// Computed properties for current view positions
const currentViewLogoPos = computed(() => {
    const view = getCurrentView();
    if (!logoPosition.value[view]) {
        logoPosition.value[view] = { x: null, y: null, width: 550, height: 550 };
    }
    return logoPosition.value[view];
});

const currentViewQrPos = computed(() => {
    const view = getCurrentView();
    if (!qrPosition.value[view]) {
        qrPosition.value[view] = { x: null, y: null, width: 400, height: 400 };
    }
    return qrPosition.value[view];
});

// Switch view and refresh preview via API
const switchView = async (view, product) => {
    selectedView.value[product.id] = view;
    
    // Auto-adjust QR size: if switching to back view and QR position is back, use XL
    const pid = product.id;
    if (view === 'back' && qrPlacement.value[pid] === 'back' && qrSizePreset.value[pid] !== 'xl') {
        qrSizePreset.value[pid] = 'xl';
    }
    
    await fetchPreview();
};

// Get product slug for mockup paths
const getProductSlug = (product) => {
    const slugs = {
        71: 'unisex-staple-t-shirt-bella-canvas-3001',
        959: 'unisex-ringer-t-shirt-next-level-3604', // Next Level 3604
        380: 'cotton-heritage-m2580-premium-pullover-hoodie',
        146: 'unisex-heavy-blend-hoodie-gildan-18500'
    };
    return slugs[product.printful_product_id] || `product-${product.id}`;
};

// Preset-based design controls (no drag/scale)
const logoPlacement = ref({});
const logoSizePreset = ref({});
const logoBgWhite = ref({});
const qrPlacement = ref({});
const qrSizePreset = ref({});

const ensureDesignDefaults = (product) => {
    if (!product?.id) return;

    const pid = product.id;
    if (!logoPlacement.value[pid]) {
        logoPlacement.value[pid] = product.category === 'mug' ? 'back_center' : 'front_center';
    }
    if (!logoSizePreset.value[pid]) {
        logoSizePreset.value[pid] = 'medium';
    }
    if (!qrPlacement.value[pid]) {
        qrPlacement.value[pid] = (product.category === 't-shirt' || product.category === 'hoodie') ? 'sleeve_left' : 'front';
    }
    if (!qrSizePreset.value[pid]) {
        // Default to XL for back view, medium for sleeves
        const qrPos = qrPlacement.value[pid] || (product.category === 't-shirt' || product.category === 'hoodie') ? 'sleeve_left' : 'front';
        qrSizePreset.value[pid] = qrPos === 'back' ? 'xl' : 'medium';
    }
};

const fetchPreview = async () => {
    if (!previewProduct.value) return;

    ensureDesignDefaults(previewProduct.value);
    const product = previewProduct.value;
    const view = getCurrentView();

    mockupLoading.value = true;
    try {
        updateSelectedColorVariant(product);
        const variantId = selectedColorVariantId.value?.[product.id] || null;
        const color = selectedColor.value?.[product.id] || null;
        const qrCodeId = selectedQRCode.value?.id || null;
        
        console.log('🔍 Preview request:', {
            product_id: product.id,
            printful_product_id: product.printful_product_id,
            view,
            variant_id: variantId,
            color,
            qr_code_id: qrCodeId,
            selectedColor: selectedColor.value?.[product.id],
            variants: product.variants,
        });
        
        const response = await fetch('/business/merch/preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...getCsrfHeaders(),
            },
            body: JSON.stringify({
                product_id: product.id,
                // Allow base mockup previews even before a QR is selected
                qr_code_id: qrCodeId,
                variant_id: variantId,
                view,
                color,
                logo_position: logoPlacement.value[product.id],
                logo_size: logoSizePreset.value[product.id],
                logo_bg_white: logoBgWhite.value[product.id] || false,
                qr_position: qrPlacement.value[product.id],
                qr_size: qrSizePreset.value[product.id],
            }),
        });

        // If CSRF/session expired (common after leaving tab open overnight),
        // hard refresh once to rehydrate cookies + CSRF meta token.
        if (response.status === 419) {
            const key = 'merch_preview_last_419_reload_at';
            const last = Number(sessionStorage.getItem(key) || '0');
            const now = Date.now();
            // Prevent infinite reload loops: only once per 60s
            if (!last || now - last > 60_000) {
                sessionStorage.setItem(key, String(now));
                window.location.reload();
                return;
            }
            throw new Error('Session expired (419). Please refresh the page.');
        }

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            // Non-JSON (e.g., 419 HTML) - force fallback
            throw new Error('Preview API returned non-JSON response');
        }

        if (!response.ok) {
            throw new Error(data?.error || data?.message || 'Failed to generate preview');
        }

        previewImage.value = data.preview_url || getProductImage(product);
        previewConfig.value = data.config || product.preview_config;
    } catch (e) {
        console.error('Preview API failed:', e);
        previewImage.value = getProductImage(product);
        previewConfig.value = product.preview_config;
    } finally {
        mockupLoading.value = false;
    }
};

// Resolve view config (copied from backend logic)
const resolveViewConfig = (config, view) => {
    if (!config?.views || !config.views[view]) {
        return config;
    }
    return { ...config, ...config.views[view] };
};

// Shipping form (prefill from business settings if available)
const shipping = ref({
    name: props.defaultShipping?.name || '',
    address_1: props.defaultShipping?.address_1 || '',
    address_2: props.defaultShipping?.address_2 || '',
    city: props.defaultShipping?.city || '',
    state: props.defaultShipping?.state || '',
    zip: props.defaultShipping?.zip || '',
    country: props.defaultShipping?.country || 'US',
    phone: props.defaultShipping?.phone || '',
});

// Shipping/tax quote (from Printful) before placing order
const quote = ref(null);
const quoteLoading = ref(false);
const quoteError = ref(null);
let quoteTimer = null;

const canQuote = computed(() => {
    return (
        cart.value.length > 0 &&
        shipping.value?.address_1 &&
        shipping.value?.city &&
        shipping.value?.state &&
        shipping.value?.zip &&
        shipping.value?.country
    );
});

const buildQuotePayload = () => {
    const items = cart.value.map((item) => ({
        product_id: item.product.id,
        variant: item.variant,
        quantity: item.quantity,
    }));

    return {
        items,
        shipping: {
            address_1: shipping.value.address_1,
            city: shipping.value.city,
            state: shipping.value.state,
            zip: shipping.value.zip,
            country: shipping.value.country,
        },
    };
};

const fetchQuote = async () => {
    if (!canQuote.value) return;
    quoteLoading.value = true;
    quoteError.value = null;

    try {
        const response = await fetch('/business/merch/quote', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...getCsrfHeaders(),
            },
            body: JSON.stringify(buildQuotePayload()),
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data?.message || 'Failed to calculate shipping');
        }
        quote.value = data;
    } catch (e) {
        quote.value = null;
        quoteError.value = e?.message || 'Failed to calculate shipping';
    } finally {
        quoteLoading.value = false;
    }
};

const scheduleQuote = () => {
    if (quoteTimer) clearTimeout(quoteTimer);
    quoteTimer = setTimeout(fetchQuote, 400);
};

// When opening checkout with a prefilled address, quote immediately.
watch(showCheckout, (open) => {
    if (open) {
        scheduleQuote();
    }
});

// Category filter
const selectedCategory = ref('all');
const categories = computed(() => {
    // Filter out mugs from categories
    const cats = [...new Set(props.products.map(p => p.category).filter(cat => cat !== 'mug'))];
    return ['all', ...cats.sort()];
});

// Get cached colors for a product (reads from actual cached directories)
const getCachedColors = (product) => {
    if (!product.printful_product_id) return [];

    const knownSlugs = {
        71: 'unisex-staple-t-shirt-bella-canvas-3001',
        380: 'cotton-heritage-m2580-premium-pullover-hoodie',
        146: 'unisex-heavy-blend-hoodie-gildan-18500',
        959: 'unisex-ringer-t-shirt-next-level-3604' // Next Level 3604
    };

    const slug = knownSlugs[product.printful_product_id];
    if (!slug) return [];

    // Return all cached colors - since almost all colors are cached, just return them all
    // In a real implementation, you'd fetch this from an API endpoint, but for now
    // we'll assume all colors in the variants are cached
    return [
        'white', 'black', 'navy', 'red', 'grey', 'green', 'charcoal', 'royal',
        'military-green', 'orange', 'aqua', 'army', 'ash', 'asphalt', 'athletic-heather',
        'autumn', 'baby-blue', 'berry', 'black-heather', 'brown', 'burnt-orange',
        'cardinal', 'charity-pink', 'dark-grey', 'dark-grey-heather', 'forest',
        'gold', 'heather-aqua', 'heather-autumn', 'heather-brown', 'heather-carolina-blue',
        'heather-clay', 'heather-columbia-blue', 'heather-deep-teal', 'heather-dust',
        'heather-emerald', 'heather-forest', 'heather-grass-green', 'heather-ice-blue',
        'heather-kelly', 'heather-mauve', 'heather-midnight-navy', 'heather-mint',
        'heather-natural', 'heather-navy', 'heather-olive', 'heather-orange',
        'heather-orchid', 'heather-prism-dusty-blue', 'heather-prism-ice-blue',
        'heather-prism-lilac', 'heather-prism-mint', 'heather-prism-peach',
        'heather-raspberry', 'heather-red', 'heather-slate', 'heather-team-purple',
        'heather-true-royal', 'heather-yellow-gold', 'kelly', 'leaf', 'light-blue',
        'lilac', 'maroon', 'mauve', 'mint', 'mustard', 'natural', 'ocean-blue',
        'olive', 'oxblood-black', 'pebble', 'pink', 'sage', 'silver', 'soft-cream',
        'soft-pink', 'solid-white-blend', 'steel-blue', 'tan', 'teal', 'team-purple',
        'toast', 'true-royal', 'turquoise', 'vintage-black', 'vintage-white', 'yellow'
    ];
};

// Allowed colors only: black, white, blue, yellow, green, red
const ALLOWED_COLORS = ['black', 'white', 'blue', 'yellow', 'green', 'red'];

// Map color names to allowed colors (normalize variations)
const normalizeColorToAllowed = (colorName) => {
    if (!colorName) return null;
    const normalized = colorName.toLowerCase().trim();
    
    // Handle compound color names (e.g., "Heather Gray / Black", "Natural / Forest Green")
    // Extract the main color from compound names
    if (normalized.includes('/')) {
        const parts = normalized.split('/').map(p => p.trim());
        // Check each part for color matches
        for (const part of parts) {
            if (ALLOWED_COLORS.includes(part)) {
                return part;
            }
            // Check if part contains a color name
            for (const color of ALLOWED_COLORS) {
                if (part.includes(color)) {
                    return color;
                }
            }
        }
        // If no direct match, check color map for each part
        for (const part of parts) {
            const mapped = getColorMapping(part);
            if (mapped) return mapped;
        }
    }
    
    // Direct matches
    if (ALLOWED_COLORS.includes(normalized)) {
        return normalized;
    }
    
    // Map variations to allowed colors
    const colorMap = {
        // Blue variations
        'navy': 'blue',
        'royal': 'blue',
        'royal-blue': 'blue',
        'light-blue': 'blue',
        'baby-blue': 'blue',
        'ocean-blue': 'blue',
        'steel-blue': 'blue',
        'heather-navy': 'blue',
        'heather-midnight-navy': 'blue',
        'heather-carolina-blue': 'blue',
        'heather-columbia-blue': 'blue',
        'heather-ice-blue': 'blue',
        'heather-prism-ice-blue': 'blue',
        'heather-prism-dusty-blue': 'blue',
        'heather-true-royal': 'blue',
        'aqua': 'blue',
        'heather-aqua': 'blue',
        'teal': 'blue',
        'heather-deep-teal': 'blue',
        'turquoise': 'blue',
        
        // Yellow variations
        'gold': 'yellow',
        'heather-yellow-gold': 'yellow',
        'mustard': 'yellow',
        
        // Green variations
        'military-green': 'green',
        'heather-emerald': 'green',
        'heather-forest': 'green',
        'heather-grass-green': 'green',
        'heather-kelly': 'green',
        'kelly': 'green',
        'forest': 'green',
        'leaf': 'green',
        'sage': 'green',
        'olive': 'green',
        'heather-olive': 'green',
        'heather-mint': 'green',
        'mint': 'green',
        'heather-prism-mint': 'green',
        
        // Red variations
        'cardinal': 'red',
        'heather-red': 'red',
        'heather-raspberry': 'red',
        'berry': 'red',
        'maroon': 'red',
        
        // Black variations
        'charcoal': 'black',
        'dark-grey': 'black',
        'dark-grey-heather': 'black',
        'black-heather': 'black',
        'vintage-black': 'black',
        'oxblood-black': 'black',
        
        // White variations
        'vintage-white': 'white',
        'soft-cream': 'white',
        'natural': 'white',
        'heather-natural': 'white',
        'solid-white-blend': 'white',
        
        // Gray variations (map to black)
        'gray': 'black',
        'grey': 'black',
        'heather-gray': 'black',
        'heather-grey': 'black',
    };
    
    return colorMap[normalized] || null;
};

// Helper function to get color mapping
const getColorMapping = (colorName) => {
    if (!colorName) return null;
    const normalized = colorName.toLowerCase().trim();
    
    // Direct matches
    if (ALLOWED_COLORS.includes(normalized)) {
        return normalized;
    }
    
    // Map variations to allowed colors
    const colorMap = {
        // Blue variations
        'navy': 'blue',
        'royal': 'blue',
        'royal-blue': 'blue',
        'light-blue': 'blue',
        'baby-blue': 'blue',
        'ocean-blue': 'blue',
        'steel-blue': 'blue',
        'heather-navy': 'blue',
        'heather-midnight-navy': 'blue',
        'heather-carolina-blue': 'blue',
        'heather-columbia-blue': 'blue',
        'heather-ice-blue': 'blue',
        'heather-prism-ice-blue': 'blue',
        'heather-prism-dusty-blue': 'blue',
        'heather-true-royal': 'blue',
        'aqua': 'blue',
        'heather-aqua': 'blue',
        'teal': 'blue',
        'heather-deep-teal': 'blue',
        'turquoise': 'blue',
        'midnight-navy': 'blue',
        
        // Yellow variations
        'gold': 'yellow',
        'heather-yellow-gold': 'yellow',
        'mustard': 'yellow',
        
        // Green variations
        'military-green': 'green',
        'heather-emerald': 'green',
        'heather-forest': 'green',
        'heather-grass-green': 'green',
        'heather-kelly': 'green',
        'kelly': 'green',
        'forest': 'green',
        'forest-green': 'green',
        'leaf': 'green',
        'sage': 'green',
        'olive': 'green',
        'heather-olive': 'green',
        'heather-mint': 'green',
        'mint': 'green',
        'heather-prism-mint': 'green',
        
        // Red variations
        'cardinal': 'red',
        'heather-red': 'red',
        'heather-raspberry': 'red',
        'berry': 'red',
        'maroon': 'red',
        
        // Black variations
        'charcoal': 'black',
        'dark-grey': 'black',
        'dark-grey-heather': 'black',
        'black-heather': 'black',
        'vintage-black': 'black',
        'oxblood-black': 'black',
        'gray': 'black',
        'grey': 'black',
        'heather-gray': 'black',
        'heather-grey': 'black',
        
        // White variations
        'vintage-white': 'white',
        'soft-cream': 'white',
        'natural': 'white',
        'heather-natural': 'white',
        'solid-white-blend': 'white',
    };
    
    return colorMap[normalized] || null;
};

// Filter available colors to only show allowed ones (black, white, blue, yellow, green, red)
// Keep original Printful color names for backend, but filter to only show colors that map to allowed colors
const getAvailableColors = (product, variantName) => {
    const variant = product.variants?.find(v => v.name === variantName) || product.variants?.[0];
    if (!variant?.colors) return [];

    // Filter colors to only those that map to allowed colors
    // Keep original color name for backend communication
    const allowed = variant.colors.filter(c => {
        const colorName = typeof c === 'object' ? c.name : c;
        const mapped = normalizeColorToAllowed(colorName);
        return mapped !== null;
    });

    // Group by mapped color and take first of each group (to avoid showing multiple "blue" options)
    const grouped = {};
    allowed.forEach(c => {
        const colorName = typeof c === 'object' ? c.name : c;
        const mapped = normalizeColorToAllowed(colorName);
        if (!grouped[mapped]) {
            grouped[mapped] = c;
        }
    });

    return Object.values(grouped);
};

// Format category name for display
const formatCategory = (category) => {
    if (category === 't-shirt') return 'T-Shirts';
    if (category === 'hoodie') return 'Hoodies';
    if (category === 'hat') return 'Hats';
    if (category === 'mug') return 'Mugs';
    if (category === 'bag') return 'Bags';
    if (category === 'poster') return 'Posters';
    if (category === 'sticker') return 'Stickers';
    return category.charAt(0).toUpperCase() + category.slice(1);
};

const filteredProducts = computed(() => {
    // Always filter out mugs from the catalog
    const productsWithoutMugs = props.products.filter(p => p.category !== 'mug');
    if (selectedCategory.value === 'all') return productsWithoutMugs;
    return productsWithoutMugs.filter(p => p.category === selectedCategory.value);
});

const resolveCurrency = (currencyOrCountry) => {
    const v = String(currencyOrCountry || '').toUpperCase().trim();
    if (v === 'USD' || v === 'CAD') return v;
    if (v === 'US') return 'USD';
    if (v === 'CA') return 'CAD';
    return (props.merchCurrency || 'CAD').toUpperCase();
};

// Format currency
const formatCurrency = (amount, currencyHint = null) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: resolveCurrency(currencyHint || quote.value?.currency || shipping.value?.country),
    }).format(amount || 0);
};

// Get category icon
const getCategoryIcon = (category) => {
    const icons = {
        't-shirt': 'M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.073 4.073 0 01.8 7.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 010 16.407a11.616 11.616 0 006.29 1.84',
        'mug': 'M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z',
        'sticker': 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
        'poster': 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'business_card': 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        'hoodie': 'M12 4c-1.5 0-3 .5-3 2v2H7a2 2 0 00-2 2v8a2 2 0 002 2h10a2 2 0 002-2v-8a2 2 0 00-2-2h-2V6c0-1.5-1.5-2-3-2z',
    };
    return icons[category] || icons['poster'];
};

// Add to cart
const addToCart = (product) => {
    if (!selectedQRCode.value) {
        alert('Please select a QR code first');
        return;
    }

    // Ensure we have default placements/sizes even if user didn't open preview modal.
    ensureDesignDefaults(product);

    const variant = selectedVariant.value[product.id] || (product.variants?.[0]?.name || 'Default');
    const qty = quantity.value[product.id] || 1;
    const logo_position = logoPlacement.value?.[product.id] || 'front_center';
    const logo_size = logoSizePreset.value?.[product.id] || 'medium';
    const logo_bg_white = logoBgWhite.value?.[product.id] || false;
    const qr_position = qrPlacement.value?.[product.id] || ((product.category === 't-shirt' || product.category === 'hoodie') ? 'sleeve_left' : 'front');
    const qr_size = qrSizePreset.value?.[product.id] || 'medium';

    const existingIndex = cart.value.findIndex(
        item => item.product.id === product.id && 
                item.qr_code.id === selectedQRCode.value.id &&
                item.variant === variant &&
                (item.logo_position || 'front_center') === logo_position &&
                (item.qr_position || 'sleeve_left') === qr_position &&
                (item.logo_size || 'medium') === logo_size &&
                (item.qr_size || 'medium') === qr_size &&
                (item.logo_bg_white || false) === logo_bg_white
    );

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity += qty;
    } else {
        cart.value.push({
            product,
            qr_code: selectedQRCode.value,
            variant,
            quantity: qty,
            logo_position,
            logo_size,
            logo_bg_white,
            qr_position,
            qr_size,
        });
    }

    // Reset quantity
    quantity.value[product.id] = 1;
};

// Remove from cart
const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// Update cart quantity
const updateCartQuantity = (index, newQty) => {
    if (newQty < 1) {
        removeFromCart(index);
    } else {
        cart.value[index].quantity = newQty;
    }
};

// Cart totals
const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        return sum + (item.product.base_price * item.quantity);
    }, 0);
});

const cartItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

// Before checkout we don't know shipping/tax (needs address).
const cartTotal = computed(() => cartSubtotal.value);

// Submit order
const submitOrder = async () => {
    if (cart.value.length === 0) return;
    
    loading.value = true;
    
    try {
        // Refresh quote before placing order so totals are current.
        if (canQuote.value) {
            await fetchQuote();
        }

        const items = cart.value.map(item => ({
            product_id: item.product.id,
            qr_code_id: item.qr_code.id,
            variant: item.variant,
            quantity: item.quantity,
            // Persist placement choices so Printful output matches preview selections.
            logo_position: item.logo_position || 'front_center',
            logo_size: item.logo_size || 'medium',
            logo_bg_white: item.logo_bg_white || false,
            qr_position: item.qr_position || 'sleeve_left',
            qr_size: item.qr_size || 'medium',
        }));

        const response = await fetch('/business/merch/order', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...getCsrfHeaders(),
            },
            body: JSON.stringify({
                items,
                shipping: shipping.value,
            }),
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect to checkout
            router.visit(data.checkout_url);
        } else {
            alert(data.message || 'Failed to create order');
        }
    } catch (error) {
        console.error('Order failed:', error);
        alert('Failed to create order. Please try again.');
    } finally {
        loading.value = false;
    }
};

// Get status color
const getStatusColor = (status) => {
    const colors = {
        pending: 'text-yellow-400 bg-yellow-400/20',
        processing: 'text-blue-400 bg-blue-400/20',
        shipped: 'text-purple-400 bg-purple-400/20',
        delivered: 'text-green-400 bg-green-400/20',
        cancelled: 'text-red-400 bg-red-400/20',
    };
    return colors[status] || colors.pending;
};
</script>

<template>
    <Head title="Merch Store" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Merch Store</h1>
                <p class="text-gray-400 mt-1">Print your QR codes on premium merchandise</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-4">
                <Link href="/business/merch/orders" class="text-gray-300 hover:text-white transition-colors">
                    View Orders
                </Link>
                <!-- Cart Button -->
                <button 
                    @click="showCart = true"
                    class="relative btn-primary flex items-center space-x-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Cart</span>
                    <span v-if="cartItemCount > 0" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-accent-500 text-white text-xs flex items-center justify-center font-bold">
                        {{ cartItemCount }}
                    </span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar: QR Code Selection -->
            <div class="lg:col-span-1">
                <div class="glass-card p-6 sticky top-24">
                    <h3 class="text-lg font-semibold text-white mb-4">Select QR Code</h3>
                    <p class="text-gray-400 text-sm mb-4">Choose which QR code to print on your merchandise</p>

                    <!-- Scanability tip -->
                    <div class="mb-4 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
                        <p class="text-xs text-yellow-300 leading-relaxed">
                            <strong class="text-yellow-200">Tip:</strong> For best scan reliability, we strongly recommend
                            <span class="font-semibold text-yellow-200">black &amp; white (high-contrast)</span> QR designs.
                        </p>
                    </div>
                    
                    <div class="space-y-2 max-h-[400px] overflow-y-auto">
                        <button
                            v-for="qr in qrCodes"
                            :key="qr.id"
                            @click="selectQRCode(qr)"
                            :class="[
                                'w-full p-3 rounded-xl border-2 text-left transition-all',
                                selectedQRCode?.id === qr.id
                                    ? 'border-primary-500 bg-primary-500/20'
                                    : 'border-white/20 hover:border-white/40 bg-white/5'
                            ]"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden p-1">
                                    <!-- Show actual QR code image if available -->
                                    <img 
                                        v-if="qr.image_url" 
                                        :src="qr.image_url" 
                                        :alt="qr.name"
                                        class="w-full h-full object-contain"
                                    />
                                    <!-- Fallback to generic QR icon -->
                                    <svg v-else class="w-8 h-8 text-gray-800" viewBox="0 0 100 100">
                                        <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                        <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                        <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                                        <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-white font-medium truncate">{{ qr.name }}</p>
                                    <p class="text-gray-500 text-xs">{{ qr.code }}</p>
                                </div>
                                <!-- Checkmark for selected -->
                                <div v-if="selectedQRCode?.id === qr.id" class="text-primary-400">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                        </button>
                    </div>

                    <div v-if="qrCodes.length === 0" class="text-center py-6 text-gray-500">
                        <p class="mb-2">No QR codes yet</p>
                        <Link href="/business/qr-codes/create" class="text-primary-400 hover:underline text-sm">
                            Create one first →
                        </Link>
                    </div>

                    <!-- Selected QR and Logo Preview -->
                    <div v-if="selectedQRCode" class="mt-6 pt-6 border-t border-white/10">
                        <p class="text-sm text-gray-400 mb-3">Your selections will appear on the product:</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- QR Code Selection -->
                            <div class="bg-white rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-600 font-medium mb-2">QR CODE</p>
                                <!-- Show actual QR code image -->
                                <img
                                    v-if="selectedQRCode.image_url"
                                    :src="selectedQRCode.image_url"
                                    :alt="selectedQRCode.name"
                                    class="w-full max-w-[120px] mx-auto rounded-lg"
                                />
                                <!-- Fallback to generic QR icon -->
                                <svg v-else class="w-20 h-20 text-gray-800 mx-auto" viewBox="0 0 100 100">
                                    <rect x="10" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="65" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="10" y="65" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="65" y="65" width="10" height="10" fill="currentColor"/>
                                    <rect x="80" y="65" width="10" height="10" fill="currentColor"/>
                                    <rect x="65" y="80" width="10" height="10" fill="currentColor"/>
                                </svg>
                                <p class="text-gray-800 font-medium mt-2 text-xs">{{ selectedQRCode.name }}</p>
                            </div>

                            <!-- Business Logo Selection -->
                            <div class="bg-white rounded-xl p-3 text-center">
                                <p class="text-xs text-gray-600 font-medium mb-2">YOUR LOGO</p>
                                <!-- Show business logo -->
                                <img
                                    v-if="businessLogo"
                                    :src="businessLogo"
                                    alt="Your business logo"
                                    class="w-full max-w-[120px] mx-auto rounded-lg object-contain"
                                    style="max-height: 80px;"
                                />
                                <!-- Fallback if no logo -->
                                <div v-else class="w-20 h-20 bg-gray-200 rounded-lg mx-auto flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                                <p class="text-gray-800 font-medium mt-2 text-xs">
                                    {{ businessLogo ? 'Logo Applied' : 'Upload Logo in Settings' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 p-2 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="text-xs text-blue-800 text-center">
                                🎯 Both your QR code and logo will appear on the final product preview
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main: Product Catalog -->
            <div class="lg:col-span-3">
                <!-- Category Tabs -->
                <div class="glass-card p-2 mb-6">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            @click="selectedCategory = cat"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-all capitalize',
                                selectedCategory === cat
                                    ? 'bg-primary-500 text-white'
                                    : 'text-gray-400 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            {{ cat === 'all' ? 'All Products' : formatCategory(cat) }}
                        </button>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="glass-card overflow-hidden card-hover"
                    >
                        <!-- Product Image -->
                        <div class="aspect-square bg-gradient-to-br from-gray-700 to-gray-800 relative">
                            <img
                                :src="getProductImage(product)"
                                :alt="product.name"
                                class="w-full h-full object-cover"
                                @error="$event.target.src = placeholderImages['default']"
                            />
                            <!-- QR Preview Overlay -->
                            <div v-if="selectedQRCode" class="absolute bottom-4 right-4 w-16 h-16 bg-white rounded-lg shadow-lg p-1 opacity-90">
                                <svg class="w-full h-full text-gray-800" viewBox="0 0 100 100">
                                    <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                                    <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                </svg>
                            </div>
                            <!-- Category Badge -->
                            <span class="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-medium bg-white/20 backdrop-blur-sm text-white capitalize">
                                {{ formatCategory(product.category) }}
                            </span>
                        </div>

                        <!-- Preview Mockup Button -->
                        <button
                            @click="openMockupPreview(product)"
                            class="w-full py-2.5 text-sm font-medium text-primary-400 hover:text-white bg-white/5 hover:bg-primary-500/20 border-t border-b border-white/10 transition-all flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview Mockup
                        </button>

                        <!-- Product Info -->
                        <div class="p-5">
                            <h3 class="text-lg font-semibold text-white mb-1">{{ product.name }}</h3>
                            <p class="text-gray-400 text-sm mb-4 line-clamp-2">{{ product.description }}</p>

                            <!-- Variants -->
                            <div v-if="product.variants?.length" class="mb-4">
                                <label class="block text-sm text-gray-400 mb-2">Size / Style</label>
                                <select 
                                    v-model="selectedVariant[product.id]"
                                    class="input-glass text-sm py-2"
                                >
                                    <option v-for="v in product.variants" :key="v.name" :value="v.name" class="bg-gray-800 text-white">
                                        {{ v.name }} {{ v.price_modifier ? `(+${formatCurrency(v.price_modifier)})` : '' }}
                                    </option>
                                </select>
                            </div>

                            <!-- Color Selection (based on selected size) - Only show cached colors -->
                            <div v-if="getAvailableColors(product, selectedVariant[product.id] || product.variants?.[0]?.name).length > 0" class="mt-3">
                                <label class="block text-sm font-medium text-gray-400 mb-2">
                                    Color
                                    <span class="text-xs text-green-400 ml-1">(⚡ Fast Preview)</span>
                                </label>
                                <select
                                    v-model="selectedColor[product.id]"
                                    @change="updateSelectedColorVariant(product)"
                                    class="input-glass text-sm py-2"
                                >
                                    <option
                                        v-for="c in getAvailableColors(product, selectedVariant[product.id] || product.variants?.[0]?.name)"
                                        :key="typeof c === 'object' ? c.variant_id : c"
                                        :value="typeof c === 'object' ? c.name : c"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ typeof c === 'object' ? normalizeColorToAllowed(c.name) || c.name : normalizeColorToAllowed(c) || c }}
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    Only showing colors with instant previews available
                                </p>
                            </div>

                            <!-- Quantity -->
                            <div class="mb-4">
                                <label class="block text-sm text-gray-400 mb-2">Quantity</label>
                                <div class="flex items-center space-x-3">
                                    <button 
                                        @click="quantity[product.id] = Math.max(1, (quantity[product.id] || 1) - 1)"
                                        class="w-10 h-10 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                                    >
                                        -
                                    </button>
                                    <span class="text-white font-medium w-8 text-center">{{ quantity[product.id] || 1 }}</span>
                                    <button 
                                        @click="quantity[product.id] = (quantity[product.id] || 1) + 1"
                                        class="w-10 h-10 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <!-- Price & Add to Cart -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-2xl font-bold text-white">{{ formatCurrency(product.base_price) }}</span>
                                    <span class="text-gray-500 text-sm">/each</span>
                                    <p v-if="product.suggested_retail" class="text-xs text-gray-500 mt-1">
                                        Resale: {{ formatCurrency(product.suggested_retail) }}
                                    </p>
                                </div>
                                <button 
                                    @click="addToCart(product)"
                                    :disabled="!selectedQRCode"
                                    :class="[
                                        'px-4 py-2 rounded-xl font-medium transition-all flex items-center space-x-2',
                                        selectedQRCode 
                                            ? 'bg-primary-500 text-white hover:bg-primary-600' 
                                            : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                    ]"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    <span>Add</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredProducts.length === 0" class="glass-card p-12 text-center">
                    <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <h3 class="text-xl font-semibold text-white mb-2">No products in this category</h3>
                    <p class="text-gray-400">Check back soon for new merchandise!</p>
                </div>

                <!-- Recent Orders -->
                <div v-if="recentOrders?.length" class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Recent Orders</h2>
                        <Link href="/business/merch/orders" class="text-primary-400 text-sm hover:underline">
                            View All →
                        </Link>
                    </div>
                    <div class="glass-card overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-white/5">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="text-white font-medium">{{ order.order_number }}</p>
                                        <p class="text-gray-500 text-sm">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-gray-300">
                                        {{ order.items?.length || 0 }} items
                                    </td>
                                    <td class="px-6 py-4 text-white font-medium">
                                        {{ formatCurrency(order.total) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['px-3 py-1 rounded-full text-xs font-medium capitalize', getStatusColor(order.status)]">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Slide-over -->
    <Teleport to="body">
        <div v-if="showCart" class="fixed inset-0 z-50">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showCart = false"></div>
            
            <!-- Cart Panel -->
            <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-gray-900 border-l border-white/10 shadow-2xl">
                <div class="flex flex-col h-full">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-white/10">
                        <h2 class="text-xl font-semibold text-white">Shopping Cart</h2>
                        <button @click="showCart = false" class="p-2 text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Cart Items -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <div v-if="cart.length === 0" class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p class="text-gray-400">Your cart is empty</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div 
                                v-for="(item, index) in cart" 
                                :key="index"
                                class="flex items-start space-x-4 p-4 rounded-xl bg-white/5"
                            >
                                <!-- Product Image -->
                                <div class="w-20 h-20 rounded-lg bg-gray-700 flex-shrink-0 overflow-hidden relative">
                                    <img 
                                        :src="getProductImage(item.product)" 
                                        :alt="item.product.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <!-- Mini QR overlay -->
                                    <div class="absolute bottom-1 right-1 w-6 h-6 bg-white rounded p-0.5">
                                        <svg class="w-full h-full text-gray-800" viewBox="0 0 100 100">
                                            <rect x="15" y="15" width="25" height="25" fill="currentColor"/>
                                            <rect x="60" y="15" width="25" height="25" fill="currentColor"/>
                                            <rect x="15" y="60" width="25" height="25" fill="currentColor"/>
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Details -->
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-white font-medium truncate">{{ item.product.name }}</h4>
                                    <p class="text-gray-400 text-sm">{{ item.variant }}</p>
                                    <p class="text-gray-500 text-xs mt-1">QR: {{ item.qr_code.name }}</p>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="flex items-center space-x-2 mt-2">
                                        <button 
                                            @click="updateCartQuantity(index, item.quantity - 1)"
                                            class="w-6 h-6 rounded bg-white/10 text-white text-sm hover:bg-white/20"
                                        >-</button>
                                        <span class="text-white text-sm w-6 text-center">{{ item.quantity }}</span>
                                        <button 
                                            @click="updateCartQuantity(index, item.quantity + 1)"
                                            class="w-6 h-6 rounded bg-white/10 text-white text-sm hover:bg-white/20"
                                        >+</button>
                                    </div>
                                </div>
                                
                                <!-- Price & Remove -->
                                <div class="text-right">
                                    <p class="text-white font-medium">{{ formatCurrency(item.product.base_price * item.quantity) }}</p>
                                    <button 
                                        @click="removeFromCart(index)"
                                        class="text-red-400 text-sm hover:text-red-300 mt-2"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div v-if="cart.length > 0" class="border-t border-white/10 p-6 space-y-4">
                        <!-- Totals -->
                        <div class="space-y-2">
                            <div class="flex justify-between text-gray-400">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(cartSubtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-white font-semibold text-lg pt-2 border-t border-white/10">
                                <span>Total</span>
                                <span>{{ formatCurrency(cartTotal) }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 text-center">
                            Shipping & tax are calculated at checkout after you enter an address.
                        </p>

                        <button 
                            @click="showCart = false; showCheckout = true"
                            class="w-full btn-primary"
                        >
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Mockup Preview Modal -->
    <Teleport to="body">
        <div v-if="showMockupPreview && previewProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showMockupPreview = false"></div>
            
            <!-- Modal -->
            <div class="relative bg-gray-900 rounded-2xl border border-white/10 shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-white/10 flex-shrink-0">
                    <div>
                        <h2 class="text-xl font-semibold text-white">{{ previewProduct.name }}</h2>
                        <p class="text-gray-400 text-sm">Preview how your QR code will look</p>
                    </div>
                    <button @click="showMockupPreview = false" class="p-2 text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Dynamic Preview Display -->
                        <div class="relative">
                            <!-- View selector -->
                            <div class="flex flex-wrap gap-2 mb-3">
                                <button
                                    v-for="v in availableViewsForProduct(previewProduct)"
                                    :key="v"
                                    @click="switchView(v, previewProduct)"
                                    class="px-3 py-1 rounded-full text-xs font-medium border transition-all"
                                    :class="[
                                        (selectedView[previewProduct.id] || getDefaultViewForProduct(previewProduct)) === v
                                            ? 'border-primary-500 bg-primary-500/20 text-white'
                                            : 'border-white/10 bg-white/5 text-gray-300 hover:bg-white/10'
                                    ]"
                                >
                                    {{ v === 'sleeve_left' ? 'Left Sleeve' : v === 'sleeve_right' ? 'Right Sleeve' : v.charAt(0).toUpperCase() + v.slice(1) }}
                                </button>
                            </div>
                            <div 
                                class="aspect-square bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl overflow-hidden relative"
                            >
                                <!-- Loading State -->
                                <div v-if="mockupLoading" class="absolute inset-0 flex items-center justify-center z-10">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                                </div>

                                <!-- Generated Preview Image -->
                                <img
                                    v-else-if="previewImage"
                                    :src="previewImage"
                                    :alt="previewProduct.name"
                                    class="w-full h-full object-contain"
                                    ref="previewImageRef"
                                />

                                <!-- Fallback Template -->
                                <img
                                    v-else
                                    :src="previewProduct.preview_template_url"
                                    :alt="previewProduct.name"
                                    class="w-full h-full object-contain"
                                />

                                <!-- Preview Info Overlay -->
                                <div class="absolute bottom-4 left-4 right-4 bg-black/70 backdrop-blur-sm rounded-lg p-3">
                                    <div class="flex items-center justify-between text-xs text-white">
                                        <div class="flex items-center space-x-3">
                                            <div v-if="selectedQRCode" class="flex items-center space-x-1">
                                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                                <span>QR Code</span>
                                            </div>
                                            <div v-if="businessLogo" class="flex items-center space-x-1">
                                                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                                <span>Logo</span>
                                            </div>
                                        </div>
                                        <div v-if="!selectedQRCode" class="text-yellow-400">
                                            Select QR code to see preview with your logo
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="flex items-center justify-center gap-4 mt-3">
                                <div class="flex items-center gap-1 text-xs text-gray-400">
                                    <span class="w-3 h-3 rounded bg-primary-400/50 border border-dashed border-primary-400"></span>
                                    Logo
                                </div>
                                <div class="flex items-center gap-1 text-xs text-gray-400">
                                    <span class="w-3 h-3 rounded bg-white"></span>
                                    QR Code
                                </div>
                            </div>
                            <p class="text-gray-500 text-xs text-center mt-2">
                                {{ previewProduct.category === 't-shirt' || previewProduct.category === 'hoodie' 
                                    ? '👕 Logo on front • QR on sleeve' 
                                    : previewProduct.category === 'mug'
                                    ? '☕ Logo on front • QR on back'
                                    : '📐 Placement varies by product' }}
                            </p>
                            
                            <!-- Placement & size presets (reliable, no drag/scale) -->
                            <div
                                v-if="selectedQRCode && (previewProduct.category === 't-shirt' || previewProduct.category === 'hoodie')"
                                class="mt-4 glass-card p-4"
                            >
                                <p class="text-sm text-gray-300 font-semibold mb-3">Placement & Size</p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-400 font-medium mb-2">Logo</p>
                                        <label class="block text-xs text-gray-500 mb-1">Position</label>
                                        <select v-model="logoPlacement[previewProduct.id]" @change="fetchPreview()" class="input-glass text-sm py-2">
                                            <option value="front_center" class="bg-gray-800 text-white">Front Center</option>
                                            <option value="back_center" class="bg-gray-800 text-white">Back Center</option>
                                        </select>

                                        <label class="block text-xs text-gray-500 mb-1 mt-3">Size</label>
                                        <select v-model="logoSizePreset[previewProduct.id]" @change="fetchPreview()" class="input-glass text-sm py-2">
                                            <option value="small" class="bg-gray-800 text-white">Small</option>
                                            <option value="medium" class="bg-gray-800 text-white">Medium</option>
                                        </select>

                                        <label class="flex items-center gap-2 mt-3 text-xs text-gray-400 cursor-pointer select-none">
                                            <input
                                                type="checkbox"
                                                :checked="logoBgWhite[previewProduct.id] || false"
                                                @change="logoBgWhite[previewProduct.id] = $event.target.checked; fetchPreview()"
                                                class="w-4 h-4 rounded border-white/20 bg-white/10 text-primary-500 focus:ring-primary-500 focus:ring-offset-0"
                                            />
                                            White background behind logo
                                        </label>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-400 font-medium mb-2">QR Code</p>
                                        <label class="block text-xs text-gray-500 mb-1">Position</label>
                                        <select v-model="qrPlacement[previewProduct.id]" @change="fetchPreview()" class="input-glass text-sm py-2">
                                            <option value="sleeve_left" class="bg-gray-800 text-white">Left Sleeve</option>
                                            <option value="sleeve_right" class="bg-gray-800 text-white">Right Sleeve</option>
                                            <option value="back" class="bg-gray-800 text-white">Back</option>
                                        </select>

                                        <label class="block text-xs text-gray-500 mb-1 mt-3">Size</label>
                                        <select v-model="qrSizePreset[previewProduct.id]" @change="fetchPreview()" class="input-glass text-sm py-2">
                                            <option value="small" class="bg-gray-800 text-white">Small</option>
                                            <option value="medium" class="bg-gray-800 text-white">Medium</option>
                                            <option value="xl" class="bg-gray-800 text-white">XL (for back)</option>
                                        </select>
                                    </div>
                                </div>

                                <p class="text-xs text-gray-500 mt-3">
                                    Tip: switch views (Front/Back/Sleeves) to see each placement.
                                </p>
                            </div>
                        </div>

                        <!-- Product Details & Actions -->
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-2">{{ previewProduct.name }}</h3>
                                <p class="text-gray-400">{{ previewProduct.description }}</p>
                            </div>

                            <!-- Selected QR Info -->
                            <div v-if="selectedQRCode" class="glass-card p-4">
                                <p class="text-sm text-gray-400 mb-2">Selected QR Code</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-white rounded-lg p-1">
                                        <svg class="w-full h-full text-gray-800" viewBox="0 0 100 100">
                                            <rect x="15" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                            <rect x="65" y="15" width="20" height="20" rx="2" fill="currentColor"/>
                                            <rect x="15" y="65" width="20" height="20" rx="2" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white font-medium">{{ selectedQRCode.name }}</p>
                                        <p class="text-gray-500 text-xs">{{ selectedQRCode.code }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Print Areas Info -->
                            <div class="glass-card p-4">
                                <p class="text-sm text-gray-400 mb-3">Print Area</p>
                                <div class="space-y-2">
                                    <div v-for="area in (previewProduct.print_areas || [{name: 'front', default: true}])" :key="area.name" class="flex items-center gap-2">
                                        <span :class="area.default ? 'text-green-400' : 'text-gray-500'">
                                            {{ area.default ? '✓' : '○' }}
                                        </span>
                                        <span class="text-white capitalize">{{ area.name }}</span>
                                        <span v-if="area.width" class="text-gray-500 text-xs">
                                            ({{ area.width }} × {{ area.height }}px)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Variants -->
                            <div v-if="previewProduct.variants?.length">
                                <p class="text-sm text-gray-400 mb-2">Available Sizes</p>
                                <div class="flex flex-wrap gap-2">
                                    <span 
                                        v-for="v in previewProduct.variants" 
                                        :key="v.name"
                                        class="px-3 py-1 rounded-full text-sm bg-white/10 text-gray-300"
                                    >
                                        {{ v.name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Price & Add to Cart -->
                            <div class="pt-4 border-t border-white/10">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-3xl font-bold text-white">{{ formatCurrency(previewProduct.base_price) }}</span>
                                        <span class="text-gray-500">your cost</span>
                                    </div>
                                    <div v-if="previewProduct.suggested_retail" class="flex items-center justify-between mt-2 text-sm">
                                        <span class="text-gray-400">Suggested resale:</span>
                                        <span class="text-green-400 font-medium">{{ formatCurrency(previewProduct.suggested_retail) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">
                                        + shipping & tax calculated at checkout
                                    </p>
                                </div>
                                <button 
                                    @click="addToCart(previewProduct); showMockupPreview = false"
                                    :disabled="!selectedQRCode"
                                    :class="[
                                        'w-full py-3 rounded-xl font-medium transition-all flex items-center justify-center gap-2',
                                        selectedQRCode 
                                            ? 'bg-primary-500 text-white hover:bg-primary-600' 
                                            : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                    ]"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    {{ selectedQRCode ? 'Add to Cart' : 'Select a QR Code First' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Checkout Modal -->
    <Teleport to="body">
        <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showCheckout = false"></div>
            
            <!-- Modal -->
            <div class="relative bg-gray-900 rounded-2xl border border-white/10 shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-white/10 sticky top-0 bg-gray-900">
                    <h2 class="text-xl font-semibold text-white">Checkout</h2>
                    <button @click="showCheckout = false" class="p-2 text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitOrder" class="p-6 space-y-6">
                    <!-- Order Summary -->
                    <div class="glass-card p-4">
                        <h3 class="text-white font-medium mb-3">Order Summary</h3>
                        <div class="space-y-2 text-sm">
                            <div v-for="item in cart" :key="item.product.id" class="flex justify-between text-gray-400">
                                <span>{{ item.product.name }} × {{ item.quantity }}</span>
                                <span>{{ formatCurrency(item.product.base_price * item.quantity) }}</span>
                            </div>
                        </div>
                        <div class="border-t border-white/10 mt-3 pt-3 space-y-2 text-sm">
                            <div class="flex justify-between text-gray-400">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(cartSubtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-400">
                                <span>Shipping</span>
                                <span>{{ quote ? formatCurrency(quote.shipping_cost) : '—' }}</span>
                            </div>
                            <div class="flex justify-between text-gray-400">
                                <span>Tax</span>
                                <span>{{ quote ? formatCurrency(quote.tax) : '—' }}</span>
                            </div>
                            <div class="flex justify-between text-white font-semibold pt-2 border-t border-white/10">
                                <span>Total</span>
                                <span>{{ formatCurrency(quote?.total ?? cartTotal) }}</span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <span v-if="quoteLoading">Calculating shipping…</span>
                                <span v-else-if="quoteError">Shipping quote: {{ quoteError }}</span>
                                <span v-else-if="!quote">Enter an address below to calculate shipping & tax.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div>
                        <h3 class="text-white font-medium mb-4">Shipping Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">Full Name *</label>
                                <input v-model="shipping.name" type="text" required class="input-glass" placeholder="John Doe" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">Address Line 1 *</label>
                                <input v-model="shipping.address_1" type="text" required class="input-glass" placeholder="123 Main Street" @input="scheduleQuote" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">Address Line 2</label>
                                <input v-model="shipping.address_2" type="text" class="input-glass" placeholder="Apt 4B (optional)" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">City *</label>
                                <input v-model="shipping.city" type="text" required class="input-glass" placeholder="New York" @input="scheduleQuote" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">State *</label>
                                <input v-model="shipping.state" type="text" required class="input-glass" placeholder="NY" @input="scheduleQuote" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">ZIP Code *</label>
                                <input v-model="shipping.zip" type="text" required class="input-glass" placeholder="10001" @input="scheduleQuote" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Country *</label>
                                <select v-model="shipping.country" required class="input-glass" @change="scheduleQuote">
                                    <option value="US" class="bg-gray-800 text-white">United States</option>
                                    <option value="CA" class="bg-gray-800 text-white">Canada</option>
                                    <option value="GB" class="bg-gray-800 text-white">United Kingdom</option>
                                    <option value="AU" class="bg-gray-800 text-white">Australia</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">Phone (optional)</label>
                                <input v-model="shipping.phone" type="tel" class="input-glass" placeholder="+1 (555) 123-4567" />
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="pt-4 border-t border-white/10">
                        <button 
                            type="submit"
                            :disabled="loading"
                            class="w-full btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="loading">Processing...</span>
                            <span v-else>Place Order - {{ formatCurrency(quote?.total ?? cartTotal) }}</span>
                        </button>
                        <p class="text-gray-500 text-xs text-center mt-3">
                            You'll be redirected to complete payment securely via Stripe
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
