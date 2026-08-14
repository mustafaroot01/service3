<script setup lang="ts">
import DashboardOrdersByGovernorate from '@/views/admin/dashboard/DashboardOrdersByGovernorate.vue'
import DashboardOrdersTrend from '@/views/admin/dashboard/DashboardOrdersTrend.vue'
import DashboardRecentOrders from '@/views/admin/dashboard/DashboardRecentOrders.vue'
import DashboardStatusCards from '@/views/admin/dashboard/DashboardStatusCards.vue'
import DashboardStatusDonut from '@/views/admin/dashboard/DashboardStatusDonut.vue'
import DashboardTopServices from '@/views/admin/dashboard/DashboardTopServices.vue'
import type { DashboardSummary } from '@/views/admin/dashboard/types'
import type { ApiResponse } from '@/types/api'

const summary = ref<DashboardSummary | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const headline = computed(() => {
  const data = summary.value
  if (!data)
    return []

  return [
    { title: 'إجمالي الطلبات', stats: String(data.orders.total), icon: 'tabler-clipboard-list', color: 'primary' },
    { title: 'الزبائن', stats: String(data.people.users_total), icon: 'tabler-users', color: 'info' },
    { title: 'الفنيون', stats: String(data.people.technicians_total), icon: 'tabler-users-group', color: 'success' },
    { title: 'الخدمات المفعّلة', stats: String(data.catalog.services_active), icon: 'tabler-tool', color: 'warning' },
  ]
})

onMounted(async () => {
  try {
    const response = await $api<ApiResponse<DashboardSummary>>('/admin/dashboard')

    summary.value = response.data
  }
  catch (e: any) {
    error.value = e?.data?.message ?? 'تعذّر جلب بيانات لوحة الإحصائيات'
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <VProgressLinear
      v-if="loading"
      indeterminate
      rounded
      class="mb-6"
    />

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ error }}
    </VAlert>

    <template v-if="summary">
      <VRow class="match-height">
        <VCol
          v-for="card in headline"
          :key="card.title"
          cols="12"
          sm="6"
          lg="3"
        >
          <CardStatisticsHorizontal v-bind="card" />
        </VCol>
      </VRow>

      <h5 class="text-h5 mt-6 mb-4">
        الطلبات حسب الحالة
      </h5>

      <DashboardStatusCards :statuses="summary.orders.by_status" />

      <VRow class="match-height mt-2">
        <VCol
          cols="12"
          lg="8"
        >
          <DashboardOrdersTrend
            :points="summary.orders.trend"
            :today="summary.orders.today"
            :this-month="summary.orders.this_month"
            :open="summary.orders.open"
            :unassigned="summary.orders.unassigned"
          />
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <DashboardStatusDonut :statuses="summary.orders.by_status" />
        </VCol>

        <VCol
          cols="12"
          lg="8"
        >
          <DashboardTopServices :services="summary.top_services" />
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <DashboardOrdersByGovernorate :governorates="summary.orders_per_governorate" />
        </VCol>

        <VCol cols="12">
          <DashboardRecentOrders :orders="summary.recent_orders" />
        </VCol>
      </VRow>
    </template>
  </div>
</template>
