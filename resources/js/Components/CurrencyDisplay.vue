<template>
  <span class="currency-display">{{ formattedAmount }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  amount: {
    type: Number,
    default: 0
  }
})

const formattedAmount = computed(() => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(props.amount || 0)
})
</script>

