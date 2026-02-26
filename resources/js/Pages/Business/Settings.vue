<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    business: Object,
    promotions: Array,
    canFeaturePromo: Boolean,
});

const logoUploading = ref(false);
const logoInput = ref(null);

const daysOfWeek = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
    { value: 'sunday', label: 'Sunday' },
];

const businessTypes = [
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'fast_food', label: 'Fast Food' },
    { value: 'coffee_shop', label: 'Coffee Shop' },
    { value: 'bakery', label: 'Bakery' },
    { value: 'bar', label: 'Bar' },
    { value: 'brewery', label: 'Brewery' },
    { value: 'winery', label: 'Winery' },
    { value: 'grocery', label: 'Grocery' },
    { value: 'convenience', label: 'Convenience Store' },
    { value: 'liquor', label: 'Liquor Store' },
    { value: 'retail', label: 'Retail' },
    { value: 'boutique', label: 'Boutique' },
    { value: 'electronics', label: 'Electronics' },
    { value: 'furniture', label: 'Furniture' },
    { value: 'bookstore', label: 'Bookstore' },
    { value: 'gym', label: 'Gym / Fitness' },
    { value: 'yoga', label: 'Yoga / Pilates' },
    { value: 'sports', label: 'Sports / Recreation' },
    { value: 'salon', label: 'Salon' },
    { value: 'barber', label: 'Barber' },
    { value: 'spa', label: 'Spa' },
    { value: 'massage', label: 'Massage' },
    { value: 'nails', label: 'Nail Salon' },
    { value: 'medical', label: 'Medical / Clinic' },
    { value: 'dental', label: 'Dental' },
    { value: 'chiropractic', label: 'Chiropractic' },
    { value: 'pharmacy', label: 'Pharmacy' },
    { value: 'auto_repair', label: 'Auto Repair' },
    { value: 'car_wash', label: 'Car Wash' },
    { value: 'hotel', label: 'Hotel / Lodging' },
    { value: 'entertainment', label: 'Entertainment' },
    { value: 'arcade', label: 'Arcade' },
    { value: 'event_venue', label: 'Event Venue' },
    { value: 'home_services', label: 'Home Services' },
    { value: 'professional_services', label: 'Professional Services' },
    { value: 'other', label: 'Other' },
];

const initializeBusinessHours = () => {
    if (props.business?.business_hours && Array.isArray(props.business.business_hours) && props.business.business_hours.length > 0) {
        return props.business.business_hours;
    }
    return daysOfWeek.map(day => ({
        day: day.value,
        open: '',
        close: '',
        closed: false,
    }));
};

const form = useForm({
    name: props.business?.name || '',
    type: props.business?.type || '',
    description: props.business?.description || '',
    business_hours: initializeBusinessHours(),
    website: props.business?.website || '',
    facebook_url: props.business?.facebook_url || '',
    instagram_url: props.business?.instagram_url || '',
    phone: props.business?.phone || '',
    email: props.business?.email || '',
    address_line1: props.business?.address_line1 || '',
    address_line2: props.business?.address_line2 || '',
    city: props.business?.city || '',
    state: props.business?.state || '',
    postal_code: props.business?.postal_code || '',
    country: props.business?.country || 'CA',
    primary_color: props.business?.primary_color || '#8B5CF6',
    secondary_color: props.business?.secondary_color || '#EC4899',
    featured_promotion_id: props.business?.settings?.featured_promotion_id || null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/business/settings', {
        onSuccess: () => {
            // Success handled by Inertia
        },
    });
};

const updatePassword = () => {
    passwordForm.put('/account/password', {
        onSuccess: () => passwordForm.reset(),
    });
};

// Handle logo upload
const triggerLogoUpload = () => {
    logoInput.value?.click();
};

const handleLogoChange = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'].includes(file.type)) {
        alert('Please upload a valid image (JPEG, PNG, GIF, or SVG)');
        return;
    }

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        alert('Image must be less than 2MB');
        return;
    }

    logoUploading.value = true;

    const formData = new FormData();
    formData.append('logo', file);

    try {
        const response = await window.axios.post('/business/settings/logo', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json',
            },
        });

        if (response.status === 200) {
            alert('Logo uploaded successfully!');

            // Reload the page to ensure all data is fresh from server
            router.reload({ only: ['business'] });

            // Fallback: force a full page reload after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    } catch (error) {
        console.error('Logo upload error:', error);

        let errorMessage = 'Failed to upload logo. Please try again.';
        if (error.response) {
            if (error.response.status === 401 || error.response.status === 403) {
                errorMessage = 'Authentication error. Please refresh the page and try again.';
            } else if (error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        } else if (error.request) {
            errorMessage = 'Network error. Please check your connection and try again.';
        }

        alert(errorMessage);
    } finally {
        logoUploading.value = false;
        if (logoInput.value) logoInput.value.value = '';
    }
};

const removeLogo = async () => {
    if (confirm('Remove your logo? This will remove it from all promotion QR codes.')) {
        try {
            const response = await window.axios.delete('/business/settings/logo', {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (response.status === 200) {
                // Reload the page to show updated state
                router.reload({ only: ['business'] });
                alert('Logo removed successfully!');

                // Fallback: force a full page reload after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        } catch (error) {
            console.error('Logo removal error:', error);
            let errorMessage = 'Failed to remove logo. Please try again.';

            if (error.response) {
                if (error.response.status === 401 || error.response.status === 403) {
                    errorMessage = 'Authentication error. Please refresh the page and try again.';
                } else if (error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }
            }

            alert(errorMessage);
        }
    }
};
</script>

<template>
    <Head title="Business Settings" />

    <div class="max-w-4xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Business Settings</h1>
            <p class="text-gray-400 mt-1">Manage your business profile and branding</p>
        </div>

        <!-- Logo Upload Section -->
        <div class="glass-card p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Business Logo</h2>
            <p class="text-gray-400 text-sm mb-4">
                Your logo will appear on promotion QR codes and your business profile
            </p>
            
            <div class="flex items-start gap-6">
                <!-- Logo Preview -->
                <div class="relative group">
                    <div 
                        @click="triggerLogoUpload"
                        :class="[
                            'w-32 h-32 rounded-2xl flex items-center justify-center cursor-pointer overflow-hidden transition-all border-2',
                            props.business?.logo_url 
                                ? 'bg-white p-2 border-white/20' 
                                : 'bg-gradient-to-br from-primary-500 to-purple-600 border-dashed border-white/20 text-white text-4xl font-bold'
                        ]"
                    >
                        <img
                            v-if="props.business?.logo_url"
                            :src="props.business.logo_url + '?t=' + Date.now()"
                            :alt="props.business.name"
                            class="w-full h-full object-contain rounded-xl"
                        />
                        <span v-else>{{ props.business?.name?.charAt(0).toUpperCase() }}</span>
                        
                        <!-- Upload overlay -->
                        <div class="absolute inset-0 bg-black/50 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <svg v-if="!logoUploading" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg v-else class="w-8 h-8 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Remove button -->
                    <button 
                        v-if="props.business?.logo_url"
                        @click.stop="removeLogo"
                        class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 rounded-full flex items-center justify-center text-white text-xs hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100"
                    >
                        ✕
                    </button>
                    
                    <!-- Hidden file input -->
                    <input 
                        ref="logoInput"
                        type="file" 
                        accept="image/jpeg,image/png,image/gif,image/svg+xml"
                        @change="handleLogoChange"
                        class="hidden"
                    />
                </div>

                <!-- Instructions -->
                <div class="flex-1">
                    <button
                        @click="triggerLogoUpload"
                        class="btn-primary mb-3"
                        :disabled="logoUploading"
                    >
                        {{ logoUploading ? 'Uploading...' : (props.business?.logo_url ? 'Change Logo' : 'Upload Logo') }}
                    </button>

                    <ul class="text-sm text-gray-400 space-y-1">
                        <li>• Click logo area or button above to upload</li>
                        <li>• Recommended: Square image, 512x512px or larger</li>
                        <li>• Formats: JPEG, PNG, GIF, SVG</li>
                        <li>• Max size: 2MB</li>
                        <li>• Logo appears on promotion QR codes automatically and is the logo that goes on merch also</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Password -->
        <div class="glass-card p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">🔒 Account Password</h2>
            <div class="grid gap-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Current Password</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">New Password</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Confirm New Password</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">Minimum 8 characters.</p>
                    <button
                        @click="updatePassword"
                        :disabled="passwordForm.processing"
                        class="px-4 py-2 rounded-lg bg-primary-500 text-white text-sm hover:bg-primary-600 disabled:opacity-50"
                    >
                        Update Password
                    </button>
                </div>
                <div v-if="passwordForm.errors?.current_password" class="text-xs text-red-400">
                    {{ passwordForm.errors.current_password }}
                </div>
                <div v-if="passwordForm.errors?.password" class="text-xs text-red-400">
                    {{ passwordForm.errors.password }}
                </div>
            </div>
        </div>

        <!-- Business Information Form -->
        <form @submit.prevent="submit" class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold text-white mb-4">Business Information</h2>

            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Business Name *</label>
                    <input 
                        type="text" 
                        v-model="form.name"
                        required
                        class="input-glass w-full"
                        placeholder="Your Business Name"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Business Type</label>
                    <select
                        v-model="form.type"
                        class="input-glass w-full"
                    >
                        <option value="" class="bg-gray-800 text-white">Select type (optional)</option>
                        <option v-for="t in businessTypes" :key="t.value" :value="t.value" class="bg-gray-800 text-white">
                            {{ t.label }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">This helps customers browse businesses on the portal.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input 
                        type="email" 
                        v-model="form.email"
                        class="input-glass w-full"
                        placeholder="business@example.com"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
                    <input 
                        type="tel" 
                        v-model="form.phone"
                        class="input-glass w-full"
                        placeholder="(555) 123-4567"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Website</label>
                    <input 
                        type="url" 
                        v-model="form.website"
                        class="input-glass w-full"
                        placeholder="https://yourbusiness.com"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Facebook</label>
                    <input 
                        type="url" 
                        v-model="form.facebook_url"
                        class="input-glass w-full"
                        placeholder="https://facebook.com/yourbusiness"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Instagram</label>
                    <input 
                        type="url" 
                        v-model="form.instagram_url"
                        class="input-glass w-full"
                        placeholder="https://instagram.com/yourbusiness"
                    />
                </div>
            </div>

            <!-- About Us -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">About Us</label>
                <textarea 
                    v-model="form.description"
                    rows="4"
                    class="input-glass w-full"
                    placeholder="Tell customers about your business..."
                ></textarea>
            </div>

            <!-- Featured Promo (Growth+ only) -->
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-white">Featured Promo</h3>
                    <span
                        v-if="!canFeaturePromo"
                        class="text-xs px-2 py-1 rounded-full bg-yellow-500/10 text-yellow-300 border border-yellow-500/20"
                    >
                        Upgrade to Growth
                    </span>
                </div>
                <p class="text-gray-400 text-sm">
                    Choose one promotion to highlight on the customer portal home page and your public business page.
                    We show the offer only (no QR/code) so customers still need to scan in-store to redeem.
                </p>

                <div v-if="canFeaturePromo" class="glass-card p-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Select Promotion</label>
                    <select v-model="form.featured_promotion_id" class="input-glass w-full">
                        <option :value="null" class="bg-gray-800 text-white">None</option>
                        <option
                            v-for="p in (promotions || [])"
                            :key="p.id"
                            :value="p.id"
                            class="bg-gray-800 text-white"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2">
                        Tip: pick your best offer. You can change this anytime.
                    </p>
                </div>

                <div v-else class="glass-card p-4 border border-white/10">
                    <p class="text-gray-300 text-sm">
                        Featured promo placement is available on <span class="text-white font-semibold">Growth</span>,
                        <span class="text-white font-semibold">Pro</span>, and <span class="text-white font-semibold">Enterprise</span>.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Upgrade in Billing to unlock a featured promo on the customer home page and your business page.
                    </p>
                    <Link href="/business/billing" class="inline-block mt-3 px-4 py-2 rounded-lg bg-white/5 text-gray-200 border border-white/10 hover:bg-white/10">
                        View Plans →
                    </Link>
                </div>
            </div>

            <!-- Business Hours -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Business Hours</h3>
                <p class="text-gray-400 text-sm mb-4">Set your operating hours for each day of the week</p>
                
                <div class="space-y-3">
                    <div v-for="(day, index) in form.business_hours" :key="day.day" 
                        class="flex items-center gap-4 p-3 bg-white/5 rounded-lg border border-white/10">
                        <div class="w-24 flex-shrink-0">
                            <label class="text-sm font-medium text-gray-300">
                                {{ daysOfWeek.find(d => d.value === day.day)?.label }}
                            </label>
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    v-model="day.closed"
                                    class="rounded border-white/20 bg-white/5 text-primary-500 focus:ring-primary-500"
                                />
                                <span class="text-sm text-gray-400">Closed</span>
                            </label>
                        </div>
                        <div v-if="!day.closed" class="flex items-center gap-2 flex-1">
                            <input 
                                type="time" 
                                v-model="day.open"
                                class="input-glass flex-1"
                                placeholder="Open"
                            />
                            <span class="text-gray-400">to</span>
                            <input 
                                type="time" 
                                v-model="day.close"
                                class="input-glass flex-1"
                                placeholder="Close"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Address</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Address Line 1</label>
                    <input 
                        type="text" 
                        v-model="form.address_line1"
                        class="input-glass w-full"
                        placeholder="123 Main Street"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Address Line 2</label>
                    <input 
                        type="text" 
                        v-model="form.address_line2"
                        class="input-glass w-full"
                        placeholder="Suite 100 (optional)"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">City</label>
                        <input 
                            type="text" 
                            v-model="form.city"
                            class="input-glass w-full"
                            placeholder="City"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">State</label>
                        <input 
                            type="text" 
                            v-model="form.state"
                            class="input-glass w-full"
                            placeholder="State"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">ZIP Code</label>
                        <input 
                            type="text" 
                            v-model="form.postal_code"
                            class="input-glass w-full"
                            placeholder="12345"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                        <select v-model="form.country" class="input-glass w-full">
                            <option value="CA" class="bg-gray-800 text-white">Canada</option>
                            <option value="US" class="bg-gray-800 text-white">United States</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">
                            This sets your default shipping country for merch and the default currency display.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Brand Colors -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-white">Brand Colors</h3>
                <p class="text-gray-400 text-sm">These colors are used in your promotion scan pages (the page customers see when they scan your QR code), QR code images, and your public business page</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Primary Color</label>
                        <div class="flex gap-2">
                            <input 
                                type="color" 
                                v-model="form.primary_color"
                                class="w-16 h-10 rounded-lg border border-white/20 cursor-pointer"
                            />
                            <input 
                                type="text" 
                                v-model="form.primary_color"
                                class="input-glass flex-1"
                                placeholder="#8B5CF6"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Secondary Color</label>
                        <div class="flex gap-2">
                            <input 
                                type="color" 
                                v-model="form.secondary_color"
                                class="w-16 h-10 rounded-lg border border-white/20 cursor-pointer"
                            />
                            <input 
                                type="text" 
                                v-model="form.secondary_color"
                                class="input-glass flex-1"
                                placeholder="#EC4899"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-4 border-t border-white/10">
                <button 
                    type="submit"
                    :disabled="form.processing"
                    class="btn-primary"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Settings</span>
                </button>
            </div>
        </form>

        <!-- Account Actions -->
        <div class="glass-card p-6 mt-6">
            <h2 class="text-xl font-semibold text-white mb-4">Account</h2>

            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-white">Sign Out</h3>
                    <p class="text-gray-400 text-sm">Sign out of your business account</p>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="btn-secondary hover:bg-red-500/20 hover:border-red-500/50 hover:text-red-400 transition-colors"
                >
                    Sign Out
                </Link>
            </div>
        </div>
    </div>
</template>
