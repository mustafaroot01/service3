<script setup lang="ts">
import type { RecentOrder } from './types'

defineProps<{ orders: RecentOrder[] }>()

const shortDate = (value: string | null) => formatDate(value, { month: 'short', day: 'numeric' })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>آخر الطلبات</VCardTitle>
      <VCardSubtitle>أحدث ما وصل من التطبيق</VCardSubtitle>

      <template #append>
        <VBtn
          size="small"
          variant="tonal"
          append-icon="tabler-chevron-left"
          :to="{ name: 'admin-orders' }"
        >
          كل الطلبات
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VTable class="text-no-wrap">
      <thead>
        <tr>
          <th>رقم الطلب</th>
          <th>الزبون</th>
          <th>الخدمة</th>
          <th>التاريخ</th>
          <th>الحالة</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="order in orders"
          :key="order.id"
        >
          <td>
            <RouterLink
              :to="{ name: 'admin-orders-id', params: { id: order.id } }"
              class="text-primary font-weight-medium"
            >
              {{ order.order_number }}
            </RouterLink>
          </td>
          <td>{{ order.user_name ?? '—' }}</td>
          <td>{{ order.service_name ?? '—' }}</td>
          <td class="text-disabled">
            {{ formatDate(order.created_at) }}
          </td>
          <td>
            <VChip
              :color="orderStatusVisual(order.status).color"
              size="small"
              label
            >
              {{ order.status_label }}
            </VChip>
          </td>
        </tr>
      </tbody>
    </VTable>

    <VCardText
      v-if="!orders.length"
      class="text-body-1 text-disabled text-center py-12"
    >
      لا توجد طلبات بعد
    </VCardText>
  </VCard>
</template>
