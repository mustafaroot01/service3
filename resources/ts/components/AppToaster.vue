<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const iconFor = (color: string) => ({
  success: 'tabler-circle-check',
  error: 'tabler-alert-circle',
  warning: 'tabler-alert-triangle',
  info: 'tabler-info-circle',
}[color] ?? 'tabler-info-circle')
</script>

<template>
  <VSnackbar
    v-for="(toast, index) in toasts"
    :key="toast.id"
    :model-value="true"
    :color="toast.color"
    location="bottom center"
    :timeout="3500"
    :style="{ marginBlockEnd: `${index * 64}px` }"
    @update:model-value="dismiss(toast.id)"
  >
    <div class="d-flex align-center gap-2">
      <VIcon :icon="iconFor(toast.color)" size="20" />
      <span>{{ toast.text }}</span>
    </div>

    <template #actions>
      <VBtn
        icon
        variant="text"
        size="small"
        @click="dismiss(toast.id)"
      >
        <VIcon icon="tabler-x" size="18" />
      </VBtn>
    </template>
  </VSnackbar>
</template>
