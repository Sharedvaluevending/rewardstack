<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tag: Object,
    business: Object,
    qrCode: Object,
    owner: Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const claim = () => {
    router.post(`/m/${props.tag.code}/claim`);
};
</script>

<template>
    <Head title="Claim Merch" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900/20 to-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md text-center">
            <div class="glass-card p-6">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center p-2">
                        <img v-if="business?.logo_url" :src="business.logo_url" alt="Business logo" class="w-full h-full object-contain" />
                        <span v-else class="text-white text-2xl">👕</span>
                    </div>
                </div>

                <h1 class="text-xl font-semibold text-white mb-2">
                    Claim Merch
                </h1>
                <p class="text-sm text-gray-400 mb-4">
                    Link this merch to your account to unlock ambassador rewards for
                    <span class="text-white font-medium">{{ business?.name || 'this business' }}</span>.
                </p>

                <div class="bg-white/5 rounded-xl p-4 text-left mb-4">
                    <div class="text-xs text-gray-400">Merch Code</div>
                    <div class="text-sm text-white font-mono">{{ tag.code }}</div>
                </div>

                <div v-if="tag.is_claimed" class="mb-4 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20 text-yellow-200 text-sm">
                    This merch code has already been claimed.
                    <span v-if="owner?.name">Owner: {{ owner.name }}</span>
                </div>

                <div class="space-y-3">
                    <button
                        v-if="!tag.is_claimed && user"
                        class="w-full btn-primary"
                        @click="claim"
                    >
                        Claim This Merch
                    </button>

                    <Link
                        v-else-if="!tag.is_claimed && !user"
                        :href="`/login?redirect_to=/m/${tag.code}/claim`"
                        class="block w-full btn-primary text-center"
                    >
                        Sign In to Claim
                    </Link>

                    <Link
                        :href="`/s/${qrCode.code}`"
                        class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all"
                    >
                        Continue to Offer
                    </Link>

                    <div v-if="!user" class="text-xs text-gray-500">
                        Not signed in? You can claim after logging in.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
