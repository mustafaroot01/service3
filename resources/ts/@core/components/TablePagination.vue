<script setup lang="ts">
import { themeConfig } from '@themeConfig'

interface Props {
  page: number
  itemsPerPage: number
  totalItems: number
  perPageOptions?: number[]
}

const props = withDefaults(defineProps<Props>(), {
  perPageOptions: () => themeConfig.table.pagination.perPageOptions,
})

const emit = defineEmits<{
  (e: 'update:page', value: number): void
  (e: 'update:itemsPerPage', value: number): void
}>()

const { icons, labels } = themeConfig.table.pagination

const lastPage = computed(() => Math.max(1, Math.ceil(props.totalItems / props.itemsPerPage)))

const goTo = (target: number) => {
  const clamped = Math.min(Math.max(1, target), lastPage.value)

  if (clamped !== props.page)
    emit('update:page', clamped)
}

const changePerPage = (value: number) => {
  emit('update:itemsPerPage', value)
  emit('update:page', 1)
}
</script>

<template>
  <div>
    <VDivider />

    <div class="d-flex align-center flex-wrap gap-4 px-6 py-3">
      <div class="d-flex align-center gap-2">
        <span class="text-body-2 text-disabled text-no-wrap">{{ labels.perPage }}</span>

        <VSelect
          :model-value="itemsPerPage"
          :items="perPageOptions"
          variant="outlined"
          density="compact"
          hide-details
          style="inline-size: 5.5rem;"
          @update:model-value="changePerPage"
        />
      </div>

      <span class="text-body-2 text-disabled text-no-wrap">
        {{ paginationMeta({ page, itemsPerPage }, totalItems) }}
      </span>

      <VSpacer />

      <div class="d-flex align-center gap-1">
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          :disabled="page <= 1"
          :aria-label="labels.first"
          @click="goTo(1)"
        >
          <VIcon :icon="icons.first" />
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          :disabled="page <= 1"
          :aria-label="labels.previous"
          @click="goTo(page - 1)"
        >
          <VIcon :icon="icons.previous" />
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          :disabled="page >= lastPage"
          :aria-label="labels.next"
          @click="goTo(page + 1)"
        >
          <VIcon :icon="icons.next" />
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          :disabled="page >= lastPage"
          :aria-label="labels.last"
          @click="goTo(lastPage)"
        >
          <VIcon :icon="icons.last" />
        </VBtn>
      </div>
    </div>
  </div>
</template>
