<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageUpload from '@/components/form/AppImageUpload.vue'
import { useLookup } from '@/composables/useLookups'
import { useResourceForm } from '@/composables/useResourceForm'
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

export interface TechnicianDocument { id: number; type: string; type_label: string; url: string }

export interface TechnicianRecord {
  id: number
  name: string
  phone: string
  governorate_id: number
  district_id: number
  specializations?: { id: number; name: string }[]
  documents?: Record<string, TechnicianDocument | TechnicianDocument[] | null>
}

/** Mirrors MediaType on the server: five single slots plus up to four samples. */
const SINGLE_SLOTS = [
  { key: 'personal', label: 'صورة شخصية' },
  { key: 'id_front', label: 'وجه البطاقة الوطنية' },
  { key: 'id_back', label: 'ظهر البطاقة الوطنية' },
  { key: 'residence_front', label: 'وجه بطاقة السكن' },
  { key: 'residence_back', label: 'ظهر بطاقة السكن' },
] as const

const WORK_SAMPLE_LIMIT = 4

const emit = defineEmits<{ (e: 'saved'): void }>()

const toast = useToast()
const governorates = useLookup('/admin/governorates')
const specializations = useLookup('/admin/specializations')

const drawer = useResourceForm({
  endpoint: '/admin/technicians',
  multipart: true,
  blank: () => ({
    name: '', phone: '', governorate_id: null as number | null, district_id: null as number | null,
    specialization_ids: [] as number[],
    personal: null as File | null, id_front: null as File | null, id_back: null as File | null,
    residence_front: null as File | null, residence_back: null as File | null,
    work_samples: [] as File[],
  }),
  onSaved: () => emit('saved'),
})

const districts = ref<{ id: number; name: string }[]>([])
const existing = ref<Record<string, string | null>>({})
const existingSamples = ref<TechnicianDocument[]>([])

const loadDistricts = async (governorateId: number | null) => {
  districts.value = []
  if (!governorateId)
    return

  const res = await $api<ApiResponse<any[]>>('/admin/districts', {
    query: { governorate_id: governorateId, per_page: 200 },
  })

  districts.value = res.data ?? []
}

watch(() => drawer.form.value.governorate_id, async (next, prev) => {
  if (prev !== undefined && next !== prev)
    drawer.form.value.district_id = null

  await loadDistricts(next as number | null)
})

const openCreate = () => {
  drawer.openCreate()
  existing.value = {}
  existingSamples.value = []
  districts.value = []
  releasePending()
}

const openEdit = async (id: number) => {
  const res = await $api<ApiResponse<TechnicianRecord>>(`/admin/technicians/${id}`)
  const full = res.data

  drawer.openEdit({
    ...full,
    specialization_ids: full.specializations?.map(s => s.id) ?? [],
    personal: null, id_front: null, id_back: null,
    residence_front: null, residence_back: null, work_samples: [],
  } as any)

  const docs = full.documents ?? {}

  existing.value = Object.fromEntries(
    SINGLE_SLOTS.map(s => [s.key, (docs[s.key] as TechnicianDocument | null)?.url ?? null]),
  )
  existingSamples.value = (docs.work_sample as TechnicianDocument[]) ?? []
  releasePending()

  await loadDistricts(full.governorate_id)
}

const dropSample = async (media: TechnicianDocument) => {
  try {
    const res = await $api<{ message: string }>(
      `/admin/technicians/${drawer.editingId.value}/media/${media.id}`,
      { method: 'DELETE' },
    )

    toast.success(res.message)
    existingSamples.value = existingSamples.value.filter(s => s.id !== media.id)
    emit('saved')
  }
  catch (e: any) {
    toast.error(e?.data?.message ?? 'تعذّر حذف الملف')
  }
}

/** Object URLs for files not uploaded yet, so the admin sees what he picked. */
const pending = ref<{ file: File; url: string }[]>([])
const samplesInput = ref<HTMLInputElement>()
const preview = ref<string | null>(null)

const releasePending = () => {
  pending.value.forEach(p => URL.revokeObjectURL(p.url))
  pending.value = []
}

const room = computed(() => WORK_SAMPLE_LIMIT - existingSamples.value.length - pending.value.length)

const addSamples = (event: Event) => {
  const input = event.target as HTMLInputElement
  const picked = Array.from(input.files ?? [])

  input.value = ''

  if (!picked.length)
    return

  const accepted = picked.slice(0, Math.max(0, room.value))

  if (accepted.length < picked.length)
    toast.error(`الحد الأقصى ${WORK_SAMPLE_LIMIT} نماذج، أُضيف ${accepted.length} فقط`)

  pending.value = [...pending.value, ...accepted.map(file => ({ file, url: URL.createObjectURL(file) }))]
  drawer.form.value.work_samples = pending.value.map(p => p.file)
}

const dropPending = (index: number) => {
  URL.revokeObjectURL(pending.value[index].url)
  pending.value = pending.value.filter((_, i) => i !== index)
  drawer.form.value.work_samples = pending.value.map(p => p.file)
}

onBeforeUnmount(releasePending)

defineExpose({ openCreate, openEdit })
</script>

<template>
  <AppFormDrawer
    v-model="drawer.isOpen.value"
    :title="`${drawer.title.value} فني`"
    :errors="drawer.errors.value"
    :loading="drawer.isSaving.value"
    :width="560"
    @submit="drawer.save()"
  >
    <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
      {{ drawer.generalError.value }}
    </VAlert>

    <AppTextField
      v-model="drawer.form.value.name"
      label="اسم الفني"
      :rules="[requiredValidator]"
      :error-messages="drawer.fieldError('name')"
      class="mb-4"
    />

    <AppTextField
      v-model="drawer.form.value.phone"
      label="رقم الهاتف"
      placeholder="07712345678"
      dir="ltr"
      :rules="[requiredValidator]"
      :error-messages="drawer.fieldError('phone')"
      class="mb-4"
    />

    <AppSelect
      v-model="drawer.form.value.governorate_id"
      label="المحافظة"
      :items="governorates.items.value"
      item-title="name"
      item-value="id"
      :rules="[requiredValidator]"
      :error-messages="drawer.fieldError('governorate_id')"
      class="mb-4"
    />

    <AppSelect
      v-model="drawer.form.value.district_id"
      label="القضاء"
      :items="districts"
      item-title="name"
      item-value="id"
      :disabled="!drawer.form.value.governorate_id"
      :placeholder="drawer.form.value.governorate_id ? 'اختر القضاء' : 'اختر المحافظة أولاً'"
      :rules="[requiredValidator]"
      :error-messages="drawer.fieldError('district_id')"
      class="mb-4"
    />

    <AppSelect
      v-model="drawer.form.value.specialization_ids"
      label="الاختصاصات"
      :items="specializations.items.value"
      item-title="name"
      item-value="id"
      multiple
      chips
      closable-chips
      :error-messages="drawer.fieldError('specialization_ids')"
      class="mb-6"
    />

    <VDivider class="mb-4" />
    <div class="text-body-1 font-weight-medium mb-3">الأوراق الرسمية</div>

    <AppImageUpload
      v-for="slot in SINGLE_SLOTS"
      :key="slot.key"
      v-model="drawer.form.value[slot.key]"
      :current-url="existing[slot.key]"
      :label="slot.label"
      :max-size-mb="4"
      :height="130"
      :error-message="drawer.fieldError(slot.key)"
      class="mb-4"
    />

    <VDivider class="mb-4" />

    <div class="d-flex align-center justify-space-between mb-1">
      <span class="text-body-1 font-weight-medium">نماذج الأعمال</span>
      <span class="text-caption text-disabled">
        {{ existingSamples.length + pending.length }} من {{ WORK_SAMPLE_LIMIT }}
      </span>
    </div>
    <div class="text-caption text-disabled mb-3">
      الصور الجديدة <strong>تُضاف</strong> للموجود ولا تستبدله. احذف صورة لتفريغ مكان.
    </div>

    <VRow v-if="existingSamples.length || pending.length" class="mb-3">
      <VCol
        v-for="sample in existingSamples"
        :key="`saved-${sample.id}`"
        cols="6"
        sm="3"
      >
        <VCard variant="outlined" class="sample" @click="preview = sample.url">
          <VImg :src="sample.url" aspect-ratio="1" cover />
          <VBtn
            icon
            size="x-small"
            color="error"
            variant="flat"
            class="sample__x"
            @click.stop="dropSample(sample)"
          >
            <VIcon icon="tabler-x" size="14" />
          </VBtn>
        </VCard>
      </VCol>

      <VCol
        v-for="(item, index) in pending"
        :key="`new-${index}`"
        cols="6"
        sm="3"
      >
        <VCard variant="outlined" class="sample sample--new" @click="preview = item.url">
          <VImg :src="item.url" aspect-ratio="1" cover />
          <VBtn
            icon
            size="x-small"
            color="error"
            variant="flat"
            class="sample__x"
            @click.stop="dropPending(index)"
          >
            <VIcon icon="tabler-x" size="14" />
          </VBtn>
          <div class="sample__tag">جديدة</div>
        </VCard>
      </VCol>
    </VRow>

    <VBtn
      v-if="room > 0"
      block
      variant="tonal"
      color="primary"
      prepend-icon="tabler-photo-plus"
      @click="samplesInput?.click()"
    >
      إضافة صور ({{ room }} متبقّية)
    </VBtn>

    <VAlert
      v-else
      type="info"
      variant="tonal"
      density="compact"
    >
      بلغت الحد الأقصى — احذف صورة لإضافة غيرها
    </VAlert>

    <input
      ref="samplesInput"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      multiple
      hidden
      @change="addSamples"
    >

    <div v-if="drawer.fieldError('work_samples')" class="text-error text-caption mt-2">
      {{ drawer.fieldError('work_samples') }}
    </div>

    <VDialog :model-value="preview !== null" max-width="720" @update:model-value="preview = null">
      <VCard title="معاينة">
        <VCardText>
          <VImg :src="preview ?? ''" max-height="70vh" contain />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="preview = null">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </AppFormDrawer>
</template>

<style lang="scss" scoped>
.sample {
  position: relative;
  cursor: pointer;

  &__x {
    position: absolute;
    inset-block-start: 4px;
    inset-inline-end: 4px;
  }

  &__tag {
    position: absolute;
    inset-block-end: 0;
    inset-inline: 0;
    padding-block: 1px;
    font-size: 0.65rem;
    text-align: center;
    color: rgb(var(--v-theme-on-primary));
    background-color: rgb(var(--v-theme-primary));
  }

  &--new {
    border-color: rgb(var(--v-theme-primary));
  }
}
</style>
