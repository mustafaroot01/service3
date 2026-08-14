<script setup lang="ts">
import AppImageSlot from '@/components/form/AppImageSlot.vue'

interface Doc { id: number; type: string; type_label: string; url: string }

const props = defineProps<{
  documents?: Record<string, Doc | Doc[] | null>
  missing?: { type: string; label: string }[]
  uploading?: string | null
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'upload', slot: string, file: File): void
  (e: 'addSamples', files: File[]): void
  (e: 'remove', doc: Doc): void
}>()

const SINGLE_SLOTS = [
  { key: 'personal', label: 'صورة شخصية' },
  { key: 'id_front', label: 'وجه البطاقة الوطنية' },
  { key: 'id_back', label: 'ظهر البطاقة الوطنية' },
  { key: 'residence_front', label: 'وجه بطاقة السكن' },
  { key: 'residence_back', label: 'ظهر بطاقة السكن' },
] as const

const WORK_SAMPLE_LIMIT = 4

const samplesInput = ref<HTMLInputElement>()
const lightbox = ref<string | null>(null)

const slotDoc = (key: string) => (props.documents?.[key] as Doc | null) ?? null
const samples = computed(() => (props.documents?.work_sample as Doc[]) ?? [])
const room = computed(() => WORK_SAMPLE_LIMIT - samples.value.length)

const pick = (event: Event) => {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])

  if (samplesInput.value)
    samplesInput.value.value = ''

  if (files.length)
    emit('addSamples', files)
}
</script>

<template>
  <VCard title="الأوراق الرسمية" class="mb-6">
    <VCardText>
      <VAlert
        v-if="missing?.length"
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-5"
      >
        ناقص: {{ missing.map(d => d.label).join(' · ') }} — لا يمكن تفعيل الفني قبل رفعها
      </VAlert>

      <div class="d-flex flex-wrap gap-4">
        <div
          v-for="slot in SINGLE_SLOTS"
          :key="slot.key"
          class="doc-slot"
        >
          <AppImageSlot
            :label="slot.label"
            :url="slotDoc(slot.key)?.url ?? null"
            :uploading="uploading === slot.key"
            :error="errors?.[slot.key]?.[0]"
            :height="150"
            removable
            @pick="file => emit('upload', slot.key, file)"
            @remove="slotDoc(slot.key) && emit('remove', slotDoc(slot.key)!)"
          />
        </div>
      </div>
    </VCardText>
  </VCard>

  <VCard>
    <VCardText class="d-flex align-center flex-wrap gap-3 pb-2">
      <h5 class="text-h5 mb-0">نماذج الأعمال</h5>
      <VChip size="small" label>{{ samples.length }} / {{ WORK_SAMPLE_LIMIT }}</VChip>
      <VSpacer />
      <VBtn
        size="small"
        prepend-icon="tabler-photo-plus"
        :disabled="room <= 0"
        :loading="uploading === 'work_samples'"
        @click="samplesInput?.click()"
      >
        {{ room > 0 ? `إضافة (متبقٍ ${room})` : 'بلغت الحد الأقصى' }}
      </VBtn>
    </VCardText>

    <VCardText>
      <VRow v-if="samples.length">
        <VCol
          v-for="doc in samples"
          :key="doc.id"
          cols="6"
          sm="4"
          md="3"
          lg="2"
        >
          <VCard variant="outlined" class="overflow-hidden">
            <div class="position-relative">
              <VImg
                :src="doc.url"
                aspect-ratio="1"
                cover
                class="cursor-pointer"
                @click="lightbox = doc.url"
              />
              <VBtn
                icon
                size="x-small"
                color="error"
                variant="flat"
                class="position-absolute"
                style="inset-block-end: 8px; inset-inline-end: 8px;"
                @click.stop="emit('remove', doc)"
              >
                <VIcon icon="tabler-trash" size="15" />
              </VBtn>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <div v-else class="d-flex flex-column align-center gap-2 py-8 text-disabled">
        <VIcon icon="tabler-photo-off" size="32" />
        <span>لا توجد نماذج أعمال</span>
      </div>

      <div v-if="errors?.work_samples?.[0]" class="text-caption text-error mt-2">
        {{ errors.work_samples[0] }}
      </div>
    </VCardText>

    <input
      ref="samplesInput"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      multiple
      hidden
      @change="pick"
    >

    <VDialog :model-value="lightbox !== null" max-width="900" @update:model-value="lightbox = null">
      <VCard>
        <VImg :src="lightbox ?? ''" max-height="80vh" contain />
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="lightbox = null">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style lang="scss" scoped>
.doc-slot {
  flex: 1 1 170px;
  max-inline-size: 240px;
}
</style>
