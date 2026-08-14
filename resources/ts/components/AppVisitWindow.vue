<script setup lang="ts">
/**
 * A visit may run past midnight, so a range like 23:30 - 00:30 finishes the
 * next day. Without saying so the technician reads it as the same evening.
 */
defineProps<{
  from?: string | null
  to?: string | null
  endsNextDay?: boolean
}>()
</script>

<template>
  <span v-if="from && to" class="d-inline-flex align-center flex-wrap gap-1">
    <span dir="ltr">{{ from }} - {{ to }}</span>
    <span v-if="endsNextDay" class="text-warning">(اليوم التالي)</span>
  </span>
  <span v-else class="text-disabled">—</span>
</template>
