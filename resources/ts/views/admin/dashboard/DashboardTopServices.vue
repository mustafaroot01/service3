<script setup lang="ts">
import { useTheme } from 'vuetify'
import type { TopService } from './types'
import { themeConfig } from '@themeConfig'
import { useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@layouts/utils'

const props = defineProps<{ services: TopService[] }>()

const vuetifyTheme = useTheme()
const configStore = useConfigStore()
const chartTheme = themeConfig.charts

// The busiest service belongs where reading starts, which flips with the direction.
const ranked = computed(() =>
  configStore.isAppRTL ? [...props.services].reverse() : props.services)

const series = computed(() => [{ name: 'الطلبات', data: ranked.value.map(service => service.orders_count) }])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))},${variableTheme['border-opacity']})`
  const labelColor = `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`
  const labelStyle = { colors: labelColor, fontSize: chartTheme.labelSize, fontFamily: chartTheme.fontFamily }

  return {
    chart: {
      type: 'bar',
      parentHeightOffset: 0,
      fontFamily: chartTheme.fontFamily,
      toolbar: { show: false },
    },
    colors: [currentTheme.primary],
    dataLabels: { enabled: false },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 6, borderRadiusApplication: 'end' } },
    grid: {
      borderColor,
      strokeDashArray: 6,
      xaxis: { lines: { show: false } },
      padding: { top: -10 },
    },
    tooltip: { theme: vuetifyTheme.current.value.dark ? 'dark' : 'light' },
    xaxis: {
      categories: ranked.value.map(service => service.name),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: labelStyle,

        // The full name stays the category so the tooltip can still show it.
        formatter: (name: string) => (name.length > 14 ? `${name.slice(0, 14)}…` : name),
      },
    },
    yaxis: {
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
      <VCardTitle>أكثر الخدمات طلباً</VCardTitle>
      <VCardSubtitle>عدد الطلبات لكل خدمة</VCardSubtitle>

      <template #append>
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'admin-services' }"
        >
          كل الخدمات
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="ranked.length"
        :options="chartOptions"
        :series="series"
        :height="chartTheme.height.bar"
      />

      <div
        v-else
        class="text-body-1 text-disabled text-center py-12"
      >
        لا توجد خدمات مطلوبة بعد
      </div>
    </VCardText>
  </VCard>
</template>
