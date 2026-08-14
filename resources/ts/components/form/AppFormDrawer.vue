<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'

interface Props {
  modelValue: boolean
  title: string
  /** Server-side validation errors keyed by field, as ApiResponse returns them. */
  errors?: Record<string, string[]>
  loading?: boolean
  submitLabel?: string
  cancelLabel?: string
  width?: number
}

const props = withDefaults(defineProps<Props>(), {
  submitLabel: 'حفظ',
  cancelLabel: 'إلغاء',
  width: 420,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'submit'): void
  (e: 'closed'): void
}>()

const form = ref<VForm>()

const close = () => {
  emit('update:modelValue', false)
  emit('closed')
}

const submit = async () => {
  const { valid } = await form.value?.validate() ?? { valid: false }

  if (valid)
    emit('submit')
}

/** First message per field, so pages can bind :error-messages directly. */
const fieldError = (field: string) => props.errors?.[field]?.[0]

defineExpose({ fieldError, resetValidation: () => form.value?.resetValidation() })
</script>

<template>
  <VNavigationDrawer
    :model-value="modelValue"
    temporary
    location="end"
    :width="width"
    class="scrollable-content"
    @update:model-value="value => !value && close()"
  >
    <AppDrawerHeaderSection
      :title="title"
      @cancel="close"
    />

    <VDivider />

    <PerfectScrollbar :options="{ wheelPropagation: false }">
      <VCard flat>
        <VCardText>
          <VForm
            ref="form"
            @submit.prevent="submit"
          >
            <VRow>
              <VCol cols="12">
                <slot :field-error="fieldError" />
              </VCol>

              <VCol
                cols="12"
                class="d-flex gap-3 pt-2"
              >
                <VBtn
                  type="submit"
                  :loading="loading"
                  :disabled="loading"
                >
                  {{ submitLabel }}
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  :disabled="loading"
                  @click="close"
                >
                  {{ cancelLabel }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </PerfectScrollbar>
  </VNavigationDrawer>
</template>
