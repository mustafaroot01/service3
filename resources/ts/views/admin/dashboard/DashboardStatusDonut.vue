<script setup lang="ts">
import { useTheme } from 'vuetify'
import type { OrderStatusCount } from './types'
import { themeConfig } from '@themeConfig'
import { hexToRgb } from '@layouts/utils'

const props = defineProps<{ statuses: OrderStatusCount[] }>()

const vuetifyTheme = useTheme()
const chartTheme = themeConfig.charts

// A donut of nothing but zeros draws an empty ring, so drop the unused slices.
const slices = computed(() => props.statuses.filter(row => row.total > 0))

const series = computed(() => slices.value.map(row => row.total))

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  const legendColor = `rgba(${hexToRgb(currentTheme['on-background'])},${variableTheme['medium-emphasis-opacity']})`
  const primaryTextColor = `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['high-emphasis-opacity']})`

  return {
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
      fontFamily: chartTheme.fontFamily,
    },
    labels: slices.value.map(row => row.label),
    colors: slices.value.map(row => currentTheme[orderStatusVisual(row.status).color]),
    stroke: { width: 0 },
    dataLabels: {
      enabled: true,
      formatter: (value: number) => `${Math.round(value)}%`,
      style: { fontFamily: chartTheme.fontFamily },
    },
    legend: {
      position: 'bottom',
      fontSize: chartTheme.labelSize,
      fontFamily: chartTheme.fontFamily,
      labels: { colors: legendColor },
      markers: { offsetX: 0 },
      itemMargin: { vertical: 3, horizontal: 10 },
    },
    tooltip: { theme: vuetifyTheme.current.value.dark ? 'dark' : 'light' },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: { fontSize: chartTheme.labelSize, fontFamily: chartTheme.fontFamily, color: legendColor },
            value: { fontSize: chartTheme.valueSize, fontFamily: chartTheme.fontFamily, color: primaryTextColor },
            total: {
              show: true,
              label: 'إجمالي الطلبات',
              fontSize: chartTheme.labelSize,
              fontFamily: chartTheme.fontFamily,
              color: legendColor,
              formatter: (chart: any) =>
                String(chart.globals.seriesTotals.reduce((sum: number, value: number) => sum + value, 0)),
            },
          },
        },
      },
    },
  }
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>توزيع الطلبات حسب الحالة</VCardTitle>
      <VCardSubtitle>نسبة كل حالة من مجموع الطلبات</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="slices.length"
        :options="chartOptions"
        :series="series"
        :height="chartTheme.height.donut"
      />

      <div
        v-else
        class="text-body-1 text-disabled text-center py-12"
      >
        لا توجد طلبات بعد
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
// ApexCharts spaces its legend marker with a physical margin, which lands on the
// wrong side once the app flips to RTL and covers the label.
:deep(.apexcharts-legend-marker) {
  margin-inline: 0 0.375rem;
}
</style>
