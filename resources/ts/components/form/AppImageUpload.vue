<script setup lang="ts">
interface Props {
  modelValue?: File | null
  /** Existing image URL coming back from the API. */
  currentUrl?: string | null
  label?: string
  hint?: string
  accept?: string
  maxSizeMb?: number
  errorMessage?: string
  disabled?: boolean
  height?: number
}

const props = withDefaults(defineProps<Props>(), {
  label: 'الصورة',
  accept: 'image/jpeg,image/png,image/webp',
  maxSizeMb: 2,
  height: 160,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: File | null): void
  (e: 'remove'): void
}>()

const input = ref<HTMLInputElement>()
const localError = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

/** The chosen file wins; otherwise fall back to whatever the API stored. */
const preview = computed(() => previewUrl.value ?? props.currentUrl ?? null)

const displayedError = computed(() => props.errorMessage ?? localError.value)

const releasePreview = () => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

const pick = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null

  localError.value = null

  if (!file) {
    return
  }

  if (!props.accept.split(',').includes(file.type)) {
    localError.value = 'نوع الملف غير مدعوم، اختر صورة JPG أو PNG أو WEBP'

    return
  }

  if (file.size > props.maxSizeMb * 1024 * 1024) {
    localError.value = `حجم الصورة يجب ألا يتجاوز ${props.maxSizeMb} ميغابايت`

    return
  }

  releasePreview()
  previewUrl.value = URL.createObjectURL(file)
  emit('update:modelValue', file)
}

const clear = () => {
  releasePreview()
  localError.value = null

  if (input.value)
    input.value.value = ''

  emit('update:modelValue', null)
  emit('remove')
}

onBeforeUnmount(releasePreview)
</script>

<template>
  <div>
    <label class="text-body-2 text-high-emphasis d-block mb-1">{{ label }}</label>

    <VCard
      variant="outlined"
      :class="displayedError ? 'border-error' : ''"
      class="cursor-pointer"
      @click="!disabled && input?.click()"
    >
      <div
        class="d-flex align-center justify-center position-relative"
        :style="{ blockSize: `${height}px` }"
      >
        <VImg
          v-if="preview"
          :src="preview"
          cover
          class="w-100 h-100"
        />

        <div
          v-else
          class="d-flex flex-column align-center gap-2 text-disabled"
        >
          <VIcon icon="tabler-photo-plus" size="32" />
          <span class="text-body-2">اضغط لاختيار صورة</span>
          <span class="text-caption">JPG · PNG · WEBP — حتى {{ maxSizeMb }} ميغابايت</span>
        </div>

        <VBtn
          v-if="preview && !disabled"
          icon
          size="x-small"
          color="error"
          variant="flat"
          class="position-absolute"
          style="inset-block-start: 8px; inset-inline-end: 8px;"
          @click.stop="clear"
        >
          <VIcon icon="tabler-x" size="16" />
        </VBtn>
      </div>
    </VCard>

    <input
      ref="input"
      type="file"
      :accept="accept"
      :disabled="disabled"
      hidden
      @change="pick"
    >

    <div
      v-if="displayedError"
      class="text-caption text-error mt-1"
    >
      {{ displayedError }}
    </div>

    <div
      v-else-if="hint"
      class="text-caption text-disabled mt-1"
    >
      {{ hint }}
    </div>
  </div>
</template>
