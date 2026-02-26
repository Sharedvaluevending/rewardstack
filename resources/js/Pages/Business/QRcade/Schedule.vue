<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    businessGames: Array,
    daysOfWeek: Array,
});

const editingGame = ref(null);

const scheduleForm = useForm({
    schedule: {},
});

const openEdit = (businessGame) => {
    scheduleForm.schedule = JSON.parse(JSON.stringify(businessGame.schedule || {}));
    editingGame.value = businessGame;
};

const toggleDay = (day) => {
    if (scheduleForm.schedule[day]) {
        delete scheduleForm.schedule[day];
    } else {
        scheduleForm.schedule[day] = { start: '09:00', end: '21:00' };
    }
};

const saveSchedule = () => {
    scheduleForm.put(`/business/qrcade/schedule/${editingGame.value.id}`, {
        preserveScroll: true,
        onSuccess: () => editingGame.value = null,
    });
};

const formatDay = (day) => {
    return day.charAt(0).toUpperCase() + day.slice(1);
};

const getDayAbbrev = (day) => {
    return day.substring(0, 3).toUpperCase();
};

const getScheduleSummary = (schedule) => {
    if (!schedule || Object.keys(schedule).length === 0) {
        return 'Always available';
    }
    const days = Object.keys(schedule).map(d => getDayAbbrev(d)).join(', ');
    return days || 'Always available';
};
</script>

<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <Link href="/business/qrcade" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                    ← Back to QRcade
                </Link>
                <h1 class="text-3xl font-bold text-white">Game Schedule</h1>
                <p class="text-gray-400 mt-1">Set when each game is available to play</p>
            </div>

            <!-- Tips -->
            <div class="glass-card p-4 mb-8 border-l-4 border-primary-500">
                <h3 class="text-white font-semibold mb-2">💡 Scheduling Tips</h3>
                <ul class="text-gray-400 text-sm space-y-1">
                    <li>• Enable kid-friendly games on weekends for family dining</li>
                    <li>• Run dessert games during slow afternoon hours (2-5 PM)</li>
                    <li>• Schedule high-engagement games during peak business hours</li>
                </ul>
            </div>

            <!-- Games Schedule Grid -->
            <div class="space-y-4">
                <div v-for="bg in businessGames" :key="bg.id" class="glass-card p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                                🎮
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">{{ bg.game?.name }}</h3>
                                <p class="text-gray-400 text-sm">{{ getScheduleSummary(bg.schedule) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <!-- Quick Day Toggle -->
                            <div class="hidden md:flex gap-1">
                                <span v-for="day in daysOfWeek" :key="day"
                                    :class="['w-8 h-8 rounded flex items-center justify-center text-xs font-medium',
                                        bg.schedule?.[day] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-gray-500']">
                                    {{ getDayAbbrev(day) }}
                                </span>
                            </div>
                            <button @click="openEdit(bg)"
                                class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors">
                                Edit Schedule
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!businessGames.length" class="glass-card p-12 text-center">
                    <p class="text-gray-400">No games enabled yet.</p>
                    <Link href="/business/qrcade/games" class="text-primary-400 hover:underline mt-2 inline-block">
                        Enable games first →
                    </Link>
                </div>
            </div>

            <!-- Edit Modal -->
            <div v-if="editingGame" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="glass-card p-6 w-full max-w-lg">
                    <h2 class="text-xl font-semibold text-white mb-4">
                        {{ editingGame.game?.name }} Schedule
                    </h2>
                    
                    <form @submit.prevent="saveSchedule" class="space-y-4">
                        <p class="text-gray-400 text-sm">
                            Select the days and times when this game is available. Leave unchecked for unavailable days.
                        </p>

                        <div class="space-y-3">
                            <div v-for="day in daysOfWeek" :key="day"
                                class="bg-white/5 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" 
                                            :checked="!!scheduleForm.schedule[day]"
                                            @change="toggleDay(day)"
                                            class="w-5 h-5 rounded bg-white/10 border-white/20 text-primary-500 focus:ring-primary-500">
                                        <span class="text-white font-medium">{{ formatDay(day) }}</span>
                                    </label>
                                </div>
                                
                                <div v-if="scheduleForm.schedule[day]" class="flex items-center gap-4 mt-3">
                                    <div class="flex-1">
                                        <label class="text-xs text-gray-400">Start Time</label>
                                        <input type="time" v-model="scheduleForm.schedule[day].start"
                                            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                    </div>
                                    <div class="flex-1">
                                        <label class="text-xs text-gray-400">End Time</label>
                                        <input type="time" v-model="scheduleForm.schedule[day].end"
                                            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="editingGame = null"
                                class="flex-1 px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20">
                                Cancel
                            </button>
                            <button type="submit" :disabled="scheduleForm.processing"
                                class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                Save Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>

