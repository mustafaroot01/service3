<script setup lang="ts">
import { useTheme } from 'vuetify'
import type { OrdersTrendPoint } from './types'
import { themeConfig } from '@themeConfig'
import { useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@layouts/utils'

const props = defineProps<{
  points: OrdersTrendPoint[]
  today: number
  thisMonth: number
  open: number
  unassigned: number
}>()

const vuetifyTheme = useTheme()
const configStore = useConfigStore()
const chartTheme = themeConfig.charts

const stats = computed(() => [
  { title: 'طلبات اليوم', value: props.today },
  { title: 'طلبات هذا الشهر', value: props.thisMonth },
  { title: 'طلبات مفتوحة', value: props.open },
])

const series = computed(() => [{ name: 'الطلبات', data: props.points.map(point => point.total) }])

const dayLabel = (date: string) => formatDate(`${date}T00:00:00`, { day: 'numeric', month: 'short' })

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))},${variableTheme['border-opacity']})`
  const labelColor = `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`
  const labelStyle = { colors: labelColor, fontSize: chartTheme.labelSize, fontFamily: chartTheme.fontFamily }

  return {
    chart: {
      type: 'area',
      parentHeightOffset: 0,
      fontFamily: chartTheme.fontFamily,
      toolbar: { show: false },
      zoom: { enabled: false },
    },
    colors: [currentTheme.primary],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] },
    },
    grid: {
      borderColor,
      strokeDashArray: 6,
      xaxis: { lines: { show: false } },
      padding: { top: -10, left: -6, right: -6 },
    },
    tooltip: { theme: vuetifyTheme.current.value.dark ? 'dark' : 'light' },
    xaxis: {
      categories: props.points.map(point => dayLabel(point.date)),
      axisBorder: { show: false },
      axisTicks: { show: false },
      tickPlacement: 'on',
      labels: { style: labelStyle },
    },
    yaxis: {
      // The value axis belongs on the side the reader starts from.
      opposite: configStore.isAppRTL,
      tickAmount: 4,
      labels: {
        style: labelStyle,
        formatter: (value: number) => String(Math.round(value)),
      },
    },
  }
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>حركة الطلبات</VCardTitle>
      <VCardSubtitle>آخر {{ points.length }} يوماً</VCardSubtitle>

      <template #append>
        <VBtn
          size="small"
          variant="tonal"
          color="error"
          prepend-icon="tabler-user-question"
          :to="{ name: 'admin-orders', query: { unassigned: 1 } }"
        >
          بلا فني: {{ unassigned }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="d-flex flex-wrap gap-x-8 gap-y-4 pb-2">
      <div
        v-for="stat in stats"
        :key="stat.title"
      >
        <div class="text-body-2 text-disabled">
          {{ stat.title }}
        </div>
        <h5 class="text-h5">
          {{ stat.value }}
        </h5>
      </div>
    </VCardText>

    <VCardText>
      <VueApexCharts
        :options="chartOptions"
        :series="series"
        :height="chartTheme.height.area"
      />
    </VCardText>
  </VCard>
</template>
