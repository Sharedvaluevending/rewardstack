<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, watch } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    product: Object,
    previewConfig: Object,
    printConfig: Object,
});

const form = useForm({
    name: props.product.name,
    description: props.product.description,
    base_price: props.product.base_price,
    is_active: props.product.is_active,
    sort_order: props.product.sort_order,
    preview_template: props.product.preview_template,
    preview_config: props.previewConfig,
    print_config: props.printConfig,
});

// Watch for changes and update preview
const updatePreview = async () => {
    // Could implement real-time preview updates here
    console.log('Preview config updated:', form.preview_config);
};

watch(() => form.preview_config, updatePreview, { deep: true });

const submit = () => {
    form.put(route('admin.products.update', props.product.id), {
        onSuccess: () => {
            // Success handling
        },
    });
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Product</h1>
                <p class="text-gray-600">{{ product.name }}</p>
            </div>
            <Link
                :href="route('admin.products.index')"
                class="btn-secondary"
            >
                Back to Products
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Base Price</label>
                        <input
                            v-model="form.base_price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div v-if="form.errors.base_price" class="mt-1 text-sm text-red-600">{{ form.errors.base_price }}</div>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-center">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm text-gray-900">Active</label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sort Order</label>
                        <input
                            v-model="form.sort_order"
                            type="number"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Preview Template</label>
                        <input
                            v-model="form.preview_template"
                            type="text"
                            placeholder="t-shirt-blank.png"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Preview Configuration -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Preview Configuration</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Configure where logos and QR codes appear in the preview. Coordinates are in pixels from the top-left corner.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Logo Position -->
                    <div>
                        <h3 class="text-md font-medium text-gray-900 mb-3">Logo Position</h3>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">X Position</label>
                                    <input
                                        v-model="form.preview_config.logo.x"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Y Position</label>
                                    <input
                                        v-model="form.preview_config.logo.y"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Width</label>
                                    <input
                                        v-model="form.preview_config.logo.width"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Height</label>
                                    <input
                                        v-model="form.preview_config.logo.height"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Position -->
                    <div>
                        <h3 class="text-md font-medium text-gray-900 mb-3">QR Code Position</h3>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">X Position</label>
                                    <input
                                        v-model="form.preview_config.qr_code.x"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Y Position</label>
                                    <input
                                        v-model="form.preview_config.qr_code.y"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Width</label>
                                    <input
                                        v-model="form.preview_config.qr_code.width"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Height</label>
                                    <input
                                        v-model="form.preview_config.qr_code.height"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Configuration -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Print Configuration</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Configure where logos and QR codes appear on the actual printed product (sent to Printful).
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Print Logo Position -->
                    <div>
                        <h3 class="text-md font-medium text-gray-900 mb-3">Print Logo Position</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Placement</label>
                                <select
                                    v-model="form.print_config.logo.placement"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="front">Front</option>
                                    <option value="back">Back</option>
                                    <option value="left">Left Sleeve</option>
                                    <option value="right">Right Sleeve</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Top</label>
                                    <input
                                        v-model="form.print_config.logo.top"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Left</label>
                                    <input
                                        v-model="form.print_config.logo.left"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Width</label>
                                    <input
                                        v-model="form.print_config.logo.width"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Print QR Position -->
                    <div>
                        <h3 class="text-md font-medium text-gray-900 mb-3">Print QR Code Position</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Placement</label>
                                <select
                                    v-model="form.print_config.qr_code.placement"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="front">Front</option>
                                    <option value="back">Back</option>
                                    <option value="left">Left Sleeve</option>
                                    <option value="right">Right Sleeve</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Top</label>
                                    <input
                                        v-model="form.print_config.qr_code.top"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Left</label>
                                    <input
                                        v-model="form.print_config.qr_code.left"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Width</label>
                                    <input
                                        v-model="form.print_config.qr_code.width"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="btn-primary"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</template>
