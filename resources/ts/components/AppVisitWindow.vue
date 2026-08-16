<script setup lang="ts">
/**
 * A visit may run past midnight, so a range like 23:30 - 00:30 finishes the
 * next day. Without saying so the technician reads it as the same evening.
 *
 * The clock is shown the friendly Arabic way (3:55 ظهراً) rather than raw 24h,
 * matching the backend's App\Support\VisitWindow so both sides read alike.
 */
const props = defineProps<{
  from?: string | null
  to?: string | null
  endsNextDay?: boolean
}>()

const period = (hour: number) => {
  if (hour < 12) return 'صباحاً'
  if (hour < 17) return 'ظهراً'
  if (hour < 20) return 'مساءً'
  return 'ليلاً'
}

// "15:55" → "3:55 ظهراً"
const pretty = (time: string) => {
  const [h, m] = time.split(':').map(Number)
  const clock = `${h % 12 || 12}:${String(m).padStart(2, '0')}`

  return { clock, period: period(h) }
}

const fromLabel = computed(() => (props.from ? pretty(props.from) : null))
const toLabel = computed(() => (props.to ? pretty(props.to) : null))
</script>

<template>
  <span v-if="fromLabel && toLabel" class="d-inline-flex align-center flex-wrap gap-1">
    <span><span dir="ltr">{{ fromLabel.clock }}</span> {{ fromLabel.period }}</span>
    <span>-</span>
    <span><span dir="ltr">{{ toLabel.clock }}</span> {{ toLabel.period }}</span>
    <span v-if="endsNextDay" class="text-warning">(اليوم التالي)</span>
  </span>
  <span v-else class="text-disabled">—</span>
</template>
