<script setup lang="ts">
import type { OrderStatusCount } from './types'

const props = defineProps<{ statuses: OrderStatusCount[] }>()

const cards = computed(() => props.statuses.map(row => ({
  ...row,
  ...orderStatusVisual(row.status),
})))
</script>

<template>
  <VRow class="match-height">
    <VCol
      v-for="card in cards"
      :key="card.status"
      cols="6"
      sm="4"
      lg="2"
    >
      <VCard
        :to="{ name: 'admin-orders', query: { status: card.status } }"
        class="text-center"
      >
        <VCardText class="d-flex flex-column align-center gap-2">
          <VAvatar
            :color="card.color"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              :icon="card.icon"
              size="26"
            />
          </VAvatar>

          <h4 class="text-h4">
            {{ card.total }}
          </h4>

          <div class="text-body-2 text-medium-emphasis">
            {{ card.label }}
          </div>

          <div
            class="d-flex align-center gap-1 text-caption"
            :class="`text-${card.color}`"
          >
            <span>عرض الطلبات</span>
            <VIcon
              icon="tabler-chevron-left"
              size="14"
            />
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
