<script setup lang="ts">
import type { GovernorateOrders } from './types'

const props = defineProps<{ governorates: GovernorateOrders[] }>()

const rows = computed(() => {
  const highest = Math.max(...props.governorates.map(row => row.total), 1)

  return props.governorates.map(row => ({ ...row, share: Math.round((row.total / highest) * 100) }))
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>الطلبات حسب المحافظة</VCardTitle>
      <VCardSubtitle>توزيع الطلبات جغرافياً</VCardSubtitle>

      <template #append>
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'admin-governorates' }"
        >
          المحافظات
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <div
        v-for="row in rows"
        :key="row.id"
        class="mb-5"
      >
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-body-1">{{ row.name }}</span>
          <span class="text-body-2 text-disabled">{{ row.total }}</span>
        </div>

        <VProgressLinear
          :model-value="row.share"
          color="primary"
          height="6"
          rounded
        />
      </div>

      <div
        v-if="!rows.length"
        class="text-body-1 text-disabled text-center py-12"
      >
        لا توجد طلبات بعد
      </div>
    </VCardText>
  </VCard>
</template>
