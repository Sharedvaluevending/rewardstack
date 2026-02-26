<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    businesses: Array,
});

const businessTypes = [
    'Restaurant',
    'Retail Store',
    'Salon/Spa',
    'Gym/Fitness',
    'Coffee Shop',
    'Bar/Nightclub',
    'Food Truck',
    'Bakery',
    'Auto Service',
    'Other',
];

const roles = [
    { value: 'admin', label: 'Admin' },
    { value: 'business', label: 'Business Owner' },
    { value: 'employee', label: 'Employee' },
    { value: 'customer', label: 'Customer' },
    { value: 'user', label: 'User' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'customer',
    business_name: '',
    business_type: '',
    business_id: '',
});

const submit = () => {
    const payload = {
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
        role: form.role,
    };
    if (form.role === 'business') {
        payload.business_name = form.business_name;
        payload.business_type = form.business_type;
    }
    if (form.role === 'employee') {
        payload.business_id = form.business_id;
    }
    form.transform(() => payload).post(route('admin.users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Add User - Admin" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/users" class="hover:text-white">Users</Link>
                    <span>/</span>
                    <span class="text-white">Add User</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Add User</h1>
                <p class="text-gray-400 mt-1">Create a new platform user</p>
            </div>
            <Link href="/admin/users" class="btn-secondary">← Back to Users</Link>
        </div>

        <div class="max-w-xl">
            <div class="glass-card p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <h2 class="text-lg font-semibold text-white mb-4">User Account</h2>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Name</label>
                        <input
                            id="name"
                            type="text"
                            v-model="form.name"
                            class="input-glass w-full"
                            placeholder="John Smith"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            class="input-glass w-full"
                            placeholder="user@example.com"
                            required
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            class="input-glass w-full"
                            placeholder="••••••••"
                            required
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            v-model="form.password_confirmation"
                            class="input-glass w-full"
                            placeholder="••••••••"
                            required
                        />
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-300 mb-2">Role</label>
                        <select id="role" v-model="form.role" class="input-glass w-full" required>
                            <option v-for="r in roles" :key="r.value" :value="r.value" class="bg-gray-800 text-white">
                                {{ r.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="mt-2 text-sm text-red-400">{{ form.errors.role }}</p>
                    </div>

                    <!-- Business Owner: create business -->
                    <template v-if="form.role === 'business'">
                        <h2 class="text-lg font-semibold text-white mt-8 mb-4">Business Details</h2>
                        <div>
                            <label for="business_name" class="block text-sm font-medium text-gray-300 mb-2">Business Name</label>
                            <input
                                id="business_name"
                                type="text"
                                v-model="form.business_name"
                                class="input-glass w-full"
                                placeholder="Acme Coffee Shop"
                                :required="form.role === 'business'"
                            />
                            <p v-if="form.errors.business_name" class="mt-2 text-sm text-red-400">{{ form.errors.business_name }}</p>
                        </div>
                        <div>
                            <label for="business_type" class="block text-sm font-medium text-gray-300 mb-2">Business Type</label>
                            <select id="business_type" v-model="form.business_type" class="input-glass w-full">
                                <option value="" class="bg-gray-800 text-white">Select...</option>
                                <option v-for="t in businessTypes" :key="t" :value="t" class="bg-gray-800 text-white">{{ t }}</option>
                            </select>
                        </div>
                    </template>

                    <!-- Employee: select business -->
                    <template v-if="form.role === 'employee'">
                        <h2 class="text-lg font-semibold text-white mt-8 mb-4">Employment</h2>
                        <div>
                            <label for="business_id" class="block text-sm font-medium text-gray-300 mb-2">Business</label>
                            <select id="business_id" v-model="form.business_id" class="input-glass w-full" :required="form.role === 'employee'">
                                <option value="" class="bg-gray-800 text-white">Select a business...</option>
                                <option v-for="b in businesses" :key="b.id" :value="b.id" class="bg-gray-800 text-white">
                                    {{ b.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.business_id" class="mt-2 text-sm text-red-400">{{ form.errors.business_id }}</p>
                        </div>
                    </template>

                    <div class="pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create User</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
.btn-primary {
    @apply px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors;
}
.input-glass {
    @apply bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50;
}
</style>
