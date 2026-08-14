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

const pickSamples = (event: Event) => {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])

  drawer.form.value.work_samples = files.slice(0, WORK_SAMPLE_LIMIT)
}

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
    <div class="text-body-1 font-weight-medium mb-1">نماذج الأعمال</div>
    <div class="text-caption text-disabled mb-3">
      حتى {{ WORK_SAMPLE_LIMIT }} صور — رفع صور جديدة يستبدل المجموعة كاملة
    </div>

    <VRow v-if="existingSamples.length" class="mb-2">
      <VCol v-for="sample in existingSamples" :key="sample.id" cols="6" sm="3">
        <div class="position-relative">
          <VImg :src="sample.url" aspect-ratio="1" cover class="rounded" />
          <VBtn
            icon
            size="x-small"
            color="error"
            variant="flat"
            class="position-absolute"
            style="inset-block-start: 4px; inset-inline-end: 4px;"
            @click="dropSample(sample)"
          >
            <VIcon icon="tabler-x" size="14" />
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <VFileInput
      label="اختر نماذج أعمال"
      accept="image/jpeg,image/png,image/webp"
      multiple
      chips
      density="compact"
      variant="outlined"
      prepend-icon=""
      prepend-inner-icon="tabler-photo-plus"
      :error-messages="drawer.fieldError('work_samples')"
      @change="pickSamples"
    />
  </AppFormDrawer>
</template>
