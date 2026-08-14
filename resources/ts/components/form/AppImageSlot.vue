<script setup lang="ts">
interface Props {
  label: string
  url?: string | null
  /** Shows the delete affordance and emits `remove` when clicked. */
  removable?: boolean
  uploading?: boolean
  height?: number
  error?: string
}

withDefaults(defineProps<Props>(), { height: 150 })

const emit = defineEmits<{
  (e: 'pick', file: File): void
  (e: 'remove'): void
}>()

const input = ref<HTMLInputElement>()
const preview = ref(false)

const choose = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (file)
    emit('pick', file)

  if (input.value)
    input.value.value = ''
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-1">
      <span class="text-body-2 text-high-emphasis">{{ label }}</span>
      <VIcon
        v-if="url"
        icon="tabler-circle-check"
        color="success"
        size="16"
      />
    </div>

    <VCard
      variant="outlined"
      :class="error ? 'border-error' : ''"
      class="overflow-hidden"
    >
      <div
        class="position-relative d-flex align-center justify-center bg-surface"
        :style="{ blockSize: `${height}px` }"
      >
        <VImg
          v-if="url"
          :src="url"
          cover
          class="w-100 h-100 cursor-pointer"
          @click="preview = true"
        />

        <div
          v-else
          class="d-flex flex-column align-center gap-1 text-disabled cursor-pointer w-100 h-100 justify-center"
          @click="input?.click()"
        >
          <VIcon icon="tabler-photo-plus" size="26" />
          <span class="text-caption">اختر صورة</span>
        </div>

        <VProgressCircular
          v-if="uploading"
          indeterminate
          color="primary"
          class="position-absolute"
        />

        <div
          v-if="url && !uploading"
          class="position-absolute d-flex gap-1"
          style="inset-block-end: 8px; inset-inline-end: 8px;"
        >
          <VBtn
            icon
            size="x-small"
            color="surface"
            variant="flat"
            @click.stop="input?.click()"
          >
            <VIcon icon="tabler-replace" size="15" />
            <VTooltip activator="parent" location="top">استبدال</VTooltip>
          </VBtn>

          <VBtn
            v-if="removable"
            icon
            size="x-small"
            color="error"
            variant="flat"
            @click.stop="emit('remove')"
          >
            <VIcon icon="tabler-trash" size="15" />
            <VTooltip activator="parent" location="top">حذف</VTooltip>
          </VBtn>
        </div>
      </div>
    </VCard>

    <div v-if="error" class="text-caption text-error mt-1">{{ error }}</div>

    <input
      ref="input"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      hidden
      @change="choose"
    >

    <VDialog v-model="preview" max-width="900">
      <VCard>
        <VImg :src="url ?? ''" max-height="80vh" contain />
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="preview = false">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
