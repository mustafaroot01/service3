<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

interface Order {
  id: number
  order_number: string
  status: string
  status_label: string
  is_final: boolean
  allowed_next_statuses: { value: string; label: string }[]
  description: string
  scheduled_date: string | null
  time_from: string | null
  time_to: string | null
  visit_ends_next_day: boolean
  latitude: number
  longitude: number
  landmark: string | null
  map_url: string
  inspection_note: string | null
  cancelled_by: string | null
  cancelled_by_label: string | null
  cancellation_note: string | null
  cancelled_at: string | null
  user_id: number
  technician_id: number | null
  user?: { id: number; name: string; phone: string }
  service?: { id: number; name: string; category?: { id: number; name: string } }
  technician?: { id: number; name: string; phone: string; specializations?: { id: number; name: string }[] } | null
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  images: { id: number; url: string }[]
  created_at: string | null
  status_histories: {
    id: number
    to_status: string
    from_status_label: string | null
    to_status_label: string
    actor_type_label: string
    actor_name: string | null
    note: string | null
    created_at: string | null
  }[]
  whatsapp: { customer: string | null; technician: string | null }
}

interface Technician {
  id: number
  name: string
  phone: string
  district?: { name: string } | null
  specializations?: { id: number; name: string }[]
}

type AssignMode = 'assign' | 'reassign'

const route = useRoute('admin-orders-id')
const toast = useToast()

const order = ref<Order | null>(null)
const loading = ref(true)
const acting = ref(false)

const technicians = ref<Technician[]>([])
const assignDialog = ref(false)
const chosenTechnician = ref<number | null>(null)
const assignMode = ref<AssignMode>('assign')
const pickedSpecialization = ref<number | null>(null)
const technicianSearch = ref('')
const showingAllTechnicians = ref(false)
const loadingTechnicians = ref(false)
const preview = ref<string | null>(null)

const inspectDialog = ref(false)
const inspectionNote = ref('')

const cancelDialog = ref(false)
const cancelNote = ref('')

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<Order>>(`/admin/orders/${route.params.id}`)

    order.value = res.data
  }
  finally {
    loading.value = false
  }
}

const act = async (path: string, body?: Record<string, any>) => {
  acting.value = true
  try {
    const res = await $api<ApiResponse<Order>>(`/admin/orders/${route.params.id}/${path}`, {
      method: 'POST',
      body,
    })

    order.value = res.data
    toast.success(res.message)

    return true
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر تنفيذ العملية')

    return false
  }
  finally {
    acting.value = false
  }
}

const openAssign = async (mode: AssignMode) => {
  loadingTechnicians.value = true
  try {
    const res = await $api<ApiResponse<Technician[]>>(`/admin/orders/${route.params.id}/available-technicians`)

    technicians.value = res.data ?? []
    chosenTechnician.value = null
    assignMode.value = mode
    pickedSpecialization.value = null
    technicianSearch.value = ''
    showingAllTechnicians.value = false
    assignDialog.value = true
  }
  catch (e: any) {
    // Opening onto an empty list would look like "no technicians exist".
    toast.error(e?.data?.message ?? 'تعذّر جلب قائمة الفنيين')
  }
  finally {
    loadingTechnicians.value = false
  }
}

/** Skips the specialization step and lists everyone assignable. */
function showAllTechnicians() {
  pickedSpecialization.value = null
  technicianSearch.value = ''
  showingAllTechnicians.value = true
}

/**
 * Arabic is typed inconsistently (أ إ آ ا · ى ي · ة ه), so both the query and
 * the name are folded to one form before they are compared.
 */
const fold = (value: string) => value
  .toLowerCase()
  .replace(/[\u064B-\u0652\u0640]/g, '')
  .replace(/[إأآٱ]/g, 'ا')
  .replace(/ى/g, 'ي')
  .replace(/ة/g, 'ه')
  .trim()

const digits = (value: string) => value.replace(/\D/g, '')

/** Only the specializations actually present in this governorate, each with its count. */
const specializationOptions = computed(() => {
  const tally = new Map<number, { name: string; count: number }>()

  for (const technician of technicians.value) {
    for (const specialization of technician.specializations ?? []) {
      const row = tally.get(specialization.id) ?? { name: specialization.name, count: 0 }

      row.count++
      tally.set(specialization.id, row)
    }
  }

  return [...tally.entries()]
    .sort(([, a], [, b]) => a.name.localeCompare(b.name, 'ar'))
    .map(([id, row]) => ({ value: id, title: `${row.name} (${row.count})` }))
})

const filteredTechnicians = computed(() => {
  const query = fold(technicianSearch.value)
  const queryDigits = digits(technicianSearch.value)

  return technicians.value.filter(technician => {
    if (pickedSpecialization.value !== null
      && !(technician.specializations ?? []).some(s => s.id === pickedSpecialization.value))
      return false

    if (!query)
      return true

    return fold(technician.name).includes(query)
      || (queryDigits !== '' && digits(technician.phone).includes(queryDigits))
  })
})

const isFiltered = computed(() => pickedSpecialization.value !== null || technicianSearch.value.trim() !== '')

/** The specialization is the first step — the list stays closed until it, a search or the escape button opens it. */
const isPicking = computed(() => showingAllTechnicians.value || isFiltered.value)

const visibleTechnicians = computed(() => isPicking.value ? filteredTechnicians.value : [])

// Confirming a technician who is no longer on screen would be a blind assignment.
watch(visibleTechnicians, list => {
  if (chosenTechnician.value !== null && !list.some(t => t.id === chosenTechnician.value))
    chosenTechnician.value = null
})

const confirmAssign = async () => {
  if (!chosenTechnician.value)
    return

  const path = assignMode.value === 'reassign' ? 'reassign-technician' : 'assign-technician'

  if (await act(path, { technician_id: chosenTechnician.value }))
    assignDialog.value = false
}

/** Swapping is possible once someone is assigned and the order is still open. */
const canReassign = computed(() => !!order.value?.technician && !order.value?.is_final)

const confirmInspect = async () => {
  if (await act('inspect', { inspection_note: inspectionNote.value })) {
    inspectDialog.value = false
    inspectionNote.value = ''
  }
}

const confirmCancel = async () => {
  if (await act('cancel', { note: cancelNote.value || undefined })) {
    cancelDialog.value = false
    cancelNote.value = ''
  }
}

/** Milestone times come from the audit trail, so there is one source of truth. */
const timeOf = (status: string) =>
  order.value?.status_histories.find(h => h.to_status === status)?.created_at ?? null

const inspectionNote_ = computed(() =>
  order.value?.inspection_note
  || order.value?.status_histories.find(h => h.to_status === 'inspected')?.note
  || null)

/** The happy path, so the header can show how far along the order is. */
const STAGES = [
  { value: 'pending', label: 'معلّق' },
  { value: 'confirmed', label: 'مؤكّد' },
  { value: 'assigned', label: 'الفني' },
  { value: 'inspected', label: 'الكشف' },
  { value: 'completed', label: 'الإنجاز' },
]

const stages = computed(() => {
  const reached = STAGES.findIndex(stage => stage.value === order.value?.status)

  return STAGES.map((stage, index) => ({
    ...stage,
    done: reached >= 0 && index <= reached,
    current: index === reached,
    at: timeOf(stage.value),
  }))
})

const isCancelled = computed(() => order.value?.status === 'cancelled')

/** How long the order has been open, or how long it took to finish. */
const age = computed(() => {
  const from = order.value?.created_at
  if (!from)
    return '—'

  const end = order.value?.is_final
    ? (timeOf('completed') ?? order.value?.cancelled_at ?? new Date().toISOString())
    : new Date().toISOString()

  const minutes = Math.max(0, Math.round((Date.parse(end) - Date.parse(from)) / 60000))

  if (minutes < 60)
    return `${minutes} دقيقة`

  const hours = Math.floor(minutes / 60)

  return hours < 24 ? `${hours} ساعة` : `${Math.floor(hours / 24)} يوم`
})

const summary = computed(() => {
  const record = order.value
  if (!record)
    return []

  return [
    { icon: 'tabler-clock-plus', label: 'وقت تقديم الطلب', value: formatDateTime(record.created_at) },
    { icon: 'tabler-calendar-clock', label: 'موعد الزيارة', value: formatDate(record.scheduled_date), window: true },
    { icon: 'tabler-tool', label: 'الخدمة', value: record.service?.name ?? '—', hint: record.service?.category?.name },
    { icon: 'tabler-hourglass', label: record.is_final ? 'استغرق' : 'عمر الطلب', value: age.value },
  ]
})

/** The backend decides which transitions are legal; the buttons follow it. */
const canMoveTo = (status: string) =>
  order.value?.allowed_next_statuses.some(s => s.value === status) ?? false

onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate />

    <template v-else-if="order">
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center gap-4">
            <div>
              <div class="d-flex align-center flex-wrap gap-3">
                <h4 class="text-h4 mb-0" dir="ltr">{{ order.order_number }}</h4>
                <VChip :color="orderStatusVisual(order.status).color" label>{{ order.status_label }}</VChip>
              </div>
              <div class="text-body-2 text-disabled mt-1">
                {{ order.user?.name }} — {{ order.governorate?.name }} / {{ order.district?.name }}
              </div>
            </div>

            <VSpacer />

            <div class="d-flex flex-wrap align-center gap-2">
              <VBtn
                v-if="canMoveTo('confirmed')"
                :loading="acting"
                prepend-icon="tabler-check"
                @click="act('confirm')"
              >
                تأكيد الطلب
              </VBtn>

              <VBtn
                v-if="canMoveTo('assigned')"
                :loading="loadingTechnicians"
                color="primary"
                prepend-icon="tabler-user-plus"
                @click="openAssign('assign')"
              >
                تعيين فني
              </VBtn>

              <VBtn
                v-if="canMoveTo('inspected')"
                :loading="acting"
                color="secondary"
                prepend-icon="tabler-clipboard-check"
                @click="inspectDialog = true"
              >
                تسجيل الكشف
              </VBtn>

              <VBtn
                v-if="canMoveTo('completed')"
                :loading="acting"
                color="success"
                prepend-icon="tabler-circle-check"
                @click="act('complete')"
              >
                إنجاز الخدمة
              </VBtn>

              <VBtn
                v-if="canMoveTo('cancelled')"
                :loading="acting"
                color="error"
                variant="tonal"
                icon
                @click="cancelDialog = true"
              >
                <VIcon icon="tabler-x" />
                <VTooltip activator="parent" location="top">إلغاء الطلب</VTooltip>
              </VBtn>

              <VChip v-if="order.is_final" color="secondary" label>
                الطلب في حالة نهائية
              </VChip>
            </div>
          </div>

          <VDivider class="my-5" />

          <div v-if="isCancelled" class="d-flex align-center gap-2 text-error">
            <VIcon icon="tabler-circle-x" size="20" />
            <span class="text-body-2">أُلغي الطلب من {{ order.cancelled_by_label ?? '—' }} — {{ formatDateTime(order.cancelled_at) }}</span>
          </div>

          <div v-else class="stages">
            <template v-for="(stage, index) in stages" :key="stage.value">
              <div class="stage" :class="{ 'stage--done': stage.done, 'stage--current': stage.current }">
                <div class="stage__dot">
                  <VIcon :icon="stage.done ? 'tabler-check' : 'tabler-point'" size="14" />
                </div>
                <div class="stage__label">{{ stage.label }}</div>
                <div class="stage__time">{{ stage.at ? formatDateTime(stage.at) : '—' }}</div>
              </div>

              <div
                v-if="index < stages.length - 1"
                class="stage-line"
                :class="{ 'stage-line--done': stages[index + 1].done }"
              />
            </template>
          </div>
        </VCardText>
      </VCard>

      <VCard class="mb-6">
        <VCardText class="summary">
          <div v-for="item in summary" :key="item.label" class="summary__cell">
            <VAvatar rounded size="38" color="primary" variant="tonal">
              <VIcon :icon="item.icon" size="20" />
            </VAvatar>
            <div class="overflow-hidden">
              <div class="text-caption text-disabled">{{ item.label }}</div>
              <div class="text-body-1 text-high-emphasis text-truncate">{{ item.value }}</div>
              <AppVisitWindow
                v-if="item.window"
                class="text-caption text-disabled"
                :from="order.time_from"
                :to="order.time_to"
                :ends-next-day="order.visit_ends_next_day"
              />
              <div v-else-if="item.hint" class="text-caption text-disabled">{{ item.hint }}</div>
            </div>
          </div>
        </VCardText>
      </VCard>

      <VRow>
        <VCol cols="12" lg="8">
          <VCard title="المشكلة" class="mb-6">
            <VCardText>
              <p class="text-body-1 text-high-emphasis mb-0">{{ order.description }}</p>

              <template v-if="order.images.length">
                <VDivider class="my-4" />
                <div class="text-caption text-disabled mb-2">صور أرفقها الزبون ({{ order.images.length }})</div>
                <VRow>
                  <VCol v-for="img in order.images" :key="img.id" cols="6" sm="3">
                    <VCard variant="outlined" class="image-tile" @click="preview = img.url">
                      <VImg :src="img.url" height="120" cover />
                    </VCard>
                  </VCol>
                </VRow>
              </template>
            </VCardText>
          </VCard>

          <VCard title="موقع الزيارة" class="mb-6">
            <VCardText>
              <VRow>
                <VCol cols="12" sm="6">
                  <div class="text-caption text-disabled">المحافظة والقضاء</div>
                  <div class="text-body-1">{{ order.governorate?.name }} / {{ order.district?.name }}</div>
                  <div class="text-caption text-disabled">حسب اختيار الزبون عند التسجيل</div>
                </VCol>

                <VCol cols="12" sm="6">
                  <div class="text-caption text-disabled">الإحداثيات</div>
                  <div class="text-body-1" dir="ltr">{{ order.latitude }}, {{ order.longitude }}</div>
                </VCol>

                <VCol cols="12">
                  <div class="text-caption text-disabled">أقرب نقطة دالة</div>
                  <div class="text-body-1">{{ order.landmark || 'لم يذكرها الزبون' }}</div>
                </VCol>
              </VRow>

              <VBtn
                :href="order.map_url"
                target="_blank"
                variant="tonal"
                prepend-icon="tabler-map-pin"
                class="mt-4"
              >
                فتح الموقع على الخريطة
              </VBtn>
            </VCardText>
          </VCard>

          <VCard v-if="inspectionNote_ || timeOf('inspected')" title="الكشف" class="mb-6">
            <VCardText>
              <div class="text-caption text-disabled">وقت الكشف</div>
              <div class="text-body-1 mb-3">{{ formatDateTime(timeOf('inspected')) }}</div>

              <div class="text-caption text-disabled">ما كُتب في الكشف</div>
              <VAlert type="info" variant="tonal" density="compact" class="mt-1">
                {{ inspectionNote_ || 'لم تُكتب ملاحظة' }}
              </VAlert>
            </VCardText>
          </VCard>

          <VCard title="سجل الأحداث">
            <VCardText>
              <VTimeline side="end" align="start" density="compact" truncate-line="both">
                <VTimelineItem
                  v-for="entry in order.status_histories"
                  :key="entry.id"
                  :dot-color="orderStatusVisual(entry.to_status).color"
                  size="x-small"
                >
                  <div class="d-flex justify-space-between flex-wrap gap-2 mb-1">
                    <span class="font-weight-medium">{{ entry.to_status_label }}</span>
                    <span class="text-caption text-disabled">{{ formatDateTime(entry.created_at) }}</span>
                  </div>
                  <div class="text-caption text-disabled">
                    {{ entry.actor_type_label }}<template v-if="entry.actor_name"> — {{ entry.actor_name }}</template>
                  </div>
                  <div v-if="entry.note" class="text-body-2 mt-1">{{ entry.note }}</div>
                </VTimelineItem>
              </VTimeline>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" lg="4">
          <VCard title="الزبون" class="mb-6">
            <VCardText>
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar size="42" color="primary" variant="tonal">
                  <span>{{ avatarText(order.user?.name ?? '') }}</span>
                </VAvatar>
                <div>
                  <RouterLink
                    :to="{ name: 'admin-users-id', params: { id: order.user_id } }"
                    class="text-body-1 font-weight-medium text-primary"
                  >
                    {{ order.user?.name ?? '—' }}
                  </RouterLink>
                  <div class="text-body-2 text-disabled" dir="ltr">{{ order.user?.phone }}</div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  v-if="order.whatsapp.customer"
                  :href="order.whatsapp.customer"
                  target="_blank"
                  color="success"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-brand-whatsapp"
                >
                  مراسلة الزبون
                </VBtn>

                <VBtn
                  size="small"
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-user"
                  :to="{ name: 'admin-users-id', params: { id: order.user_id } }"
                >
                  ملفه
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <VCard title="الفني" class="mb-6">
            <VCardText>
              <template v-if="order.technician">
                <div class="d-flex align-center gap-3 mb-4">
                  <VAvatar size="42" color="success" variant="tonal">
                    <span>{{ avatarText(order.technician.name) }}</span>
                  </VAvatar>
                  <div>
                    <RouterLink
                      :to="{ name: 'admin-technicians-id', params: { id: order.technician.id } }"
                      class="text-body-1 font-weight-medium text-primary"
                    >
                      {{ order.technician.name }}
                    </RouterLink>
                    <div class="text-body-2 text-disabled" dir="ltr">{{ order.technician.phone }}</div>
                  </div>
                </div>

                <div v-if="order.technician.specializations?.length" class="mb-4">
                  <div class="text-caption text-disabled mb-1">الاختصاصات</div>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="s in order.technician.specializations"
                      :key="s.id"
                      size="small"
                      color="primary"
                      variant="tonal"
                      label
                    >
                      {{ s.name }}
                    </VChip>
                  </div>
                </div>

                <div v-if="timeOf('assigned')" class="mb-4">
                  <div class="text-caption text-disabled">وقت تعيينه</div>
                  <div class="text-body-2">{{ formatDateTime(timeOf('assigned')) }}</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <VBtn
                    v-if="order.whatsapp.technician"
                    :href="order.whatsapp.technician"
                    target="_blank"
                    color="success"
                    variant="tonal"
                    size="small"
                    prepend-icon="tabler-brand-whatsapp"
                  >
                    مراسلته
                  </VBtn>

                  <VBtn
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-user"
                    :to="{ name: 'admin-technicians-id', params: { id: order.technician.id } }"
                  >
                    ملفه
                  </VBtn>

                  <VBtn
                    v-if="canReassign"
                    :loading="loadingTechnicians"
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="tabler-user-cog"
                    @click="openAssign('reassign')"
                  >
                    استبدال
                  </VBtn>
                </div>
              </template>

              <div v-else class="text-center py-4">
                <VIcon icon="tabler-user-question" size="38" class="text-disabled mb-2" />
                <p class="text-body-2 text-disabled mb-3">لم يُعيَّن فني بعد</p>
                <VBtn
                  v-if="canMoveTo('assigned')"
                  :loading="loadingTechnicians"
                  size="small"
                  prepend-icon="tabler-user-plus"
                  @click="openAssign('assign')"
                >
                  تعيين فني
                </VBtn>
                <div v-else class="text-caption text-disabled">أكّد الطلب أولاً</div>
              </div>
            </VCardText>
          </VCard>

          <VCard v-if="isCancelled" title="الإلغاء">
            <VCardText>
              <div class="text-caption text-disabled">من ألغاه</div>
              <div class="text-body-1 mb-3">{{ order.cancelled_by_label ?? '—' }}</div>

              <div class="text-caption text-disabled">وقت الإلغاء</div>
              <div class="text-body-1 mb-3">{{ formatDateTime(order.cancelled_at) }}</div>

              <div class="text-caption text-disabled">السبب</div>
              <VAlert type="error" variant="tonal" density="compact" class="mt-1">
                {{ order.cancellation_note || 'لم يُذكر سبب' }}
              </VAlert>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog v-model="assignDialog" max-width="620" scrollable>
      <VCard :title="assignMode === 'reassign' ? 'استبدال الفني' : 'تعيين فني'">
        <VCardText class="pb-0">
          <p class="text-body-2 text-disabled mb-4">
            الفنيون النشطون في {{ order?.governorate?.name }} فقط
            <template v-if="assignMode === 'reassign'">
              — الفني الحالي: {{ order?.technician?.name }}
            </template>
          </p>

          <VRow>
            <VCol cols="12" md="6">
              <AppSelect
                v-model="pickedSpecialization"
                :items="specializationOptions"
                label="الاختصاص"
                placeholder="اختر الاختصاص"
                prepend-inner-icon="tabler-certificate"
                clearable
                :disabled="!specializationOptions.length"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="technicianSearch"
                label="بحث عن فني"
                placeholder="الاسم أو رقم الهاتف"
                prepend-inner-icon="tabler-search"
                clearable
              />
            </VCol>
          </VRow>

          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mt-4">
            <span class="text-caption text-disabled">
              <template v-if="isPicking">{{ visibleTechnicians.length }} من {{ technicians.length }} فني</template>
              <template v-else>{{ technicians.length }} فني نشط في هذه المحافظة</template>
            </span>

            <VBtn
              v-if="isFiltered || !showingAllTechnicians"
              size="small"
              variant="text"
              prepend-icon="tabler-users-group"
              @click="showAllTechnicians"
            >
              عرض كل الفنيين
            </VBtn>
          </div>
        </VCardText>

        <VDivider class="mt-2" />

        <VCardText style="max-block-size: 22rem;">
          <VRadioGroup v-if="visibleTechnicians.length" v-model="chosenTechnician" hide-details>
            <VRadio
              v-for="t in visibleTechnicians"
              :key="t.id"
              :value="t.id"
              :disabled="t.id === order?.technician?.id"
              class="technician-option mb-2 pa-3 rounded"
            >
              <template #label>
                <div class="w-100">
                  <div class="d-flex align-center flex-wrap gap-2">
                    <span class="text-body-1 font-weight-medium text-high-emphasis">{{ t.name }}</span>
                    <span class="text-caption text-disabled" dir="ltr">{{ t.phone }}</span>
                    <VChip
                      v-if="t.id === order?.technician?.id"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      label
                    >
                      الحالي
                    </VChip>
                  </div>

                  <div class="d-flex align-center flex-wrap gap-1 mt-1">
                    <VChip
                      v-for="s in t.specializations ?? []"
                      :key="s.id"
                      size="x-small"
                      :color="s.id === pickedSpecialization ? 'primary' : undefined"
                      variant="tonal"
                      label
                    >
                      {{ s.name }}
                    </VChip>

                    <span v-if="t.district" class="text-caption text-disabled">
                      <VIcon icon="tabler-map-pin" size="14" class="me-1" />{{ t.district.name }}
                    </span>
                  </div>
                </div>
              </template>
            </VRadio>
          </VRadioGroup>

          <VAlert v-if="!technicians.length" type="warning" variant="tonal" density="compact">
            لا يوجد فني نشط في هذه المحافظة
          </VAlert>

          <div v-else-if="!isPicking" class="text-center py-6">
            <VIcon icon="tabler-certificate" size="38" class="text-disabled mb-2" />
            <p class="text-body-2 text-disabled mb-3">
              اختر الاختصاص أولاً، أو ابحث بالاسم أو رقم الهاتف
            </p>
            <VBtn size="small" variant="tonal" prepend-icon="tabler-users-group" @click="showAllTechnicians">
              عرض كل الفنيين
            </VBtn>
          </div>

          <div v-else-if="!visibleTechnicians.length" class="text-center py-6">
            <VIcon icon="tabler-user-search" size="38" class="text-disabled mb-2" />
            <p class="text-body-2 text-disabled mb-3">لا يوجد فني مطابق</p>
            <VBtn size="small" variant="tonal" prepend-icon="tabler-users-group" @click="showAllTechnicians">
              عرض كل الفنيين
            </VBtn>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="px-6 py-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="assignDialog = false">إلغاء</VBtn>
          <VBtn :disabled="!chosenTechnician" :loading="acting" @click="confirmAssign">
            {{ assignMode === 'reassign' ? 'استبدال' : 'تعيين' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog :model-value="preview !== null" max-width="900" @update:model-value="preview = null">
      <VCard title="صورة الطلب">
        <VCardText>
          <VImg :src="preview ?? ''" max-height="70vh" contain />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="preview = null">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="inspectDialog" max-width="480">
      <VCard title="تسجيل الكشف">
        <VCardText>
          <AppTextarea
            v-model="inspectionNote"
            label="ملاحظة الكشف"
            rows="4"
            placeholder="يحتاج تبديل الكمبريسر"
          />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="inspectDialog = false">إلغاء</VBtn>
          <VBtn :loading="acting" @click="confirmInspect">حفظ</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="cancelDialog" max-width="480">
      <VCard title="إلغاء الطلب">
        <VCardText>
          <AppTextarea v-model="cancelNote" label="سبب الإلغاء (اختياري)" rows="3" />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="cancelDialog = false">تراجع</VBtn>
          <VBtn color="error" :loading="acting" @click="confirmCancel">إلغاء الطلب</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.image-tile {
  cursor: pointer;
  transition: border-color 0.15s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}

.summary {
  display: flex;
  flex-wrap: wrap;

  &__cell {
    display: flex;
    flex: 1 1 13rem;
    align-items: center;
    gap: 0.75rem;
    padding-inline: 1rem;
    padding-block: 0.25rem;

    + .summary__cell {
      border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
  }
}

@media (max-width: 960px) {
  .summary__cell + .summary__cell {
    border-inline-start: none;
  }
}

.stages {
  display: flex;
  align-items: flex-start;
  overflow-x: auto;
  padding-block-end: 0.25rem;
}

.stage {
  flex: 0 0 auto;
  inline-size: 6.5rem;
  text-align: center;

  &__dot {
    display: grid;
    place-items: center;
    inline-size: 26px;
    block-size: 26px;
    margin-inline: auto;
    border-radius: 50%;
    background-color: rgba(var(--v-theme-on-surface), 0.08);
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  }

  &__label {
    margin-block-start: 0.4rem;
    font-size: 0.8125rem;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  &__time {
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  }

  &--done &__dot {
    background-color: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
  }

  &--done &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-weight: 500;
  }

  &--current &__dot {
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.16);
  }
}

.stage-line {
  flex: 1 1 1.5rem;
  min-inline-size: 1rem;
  block-size: 2px;
  margin-block-start: 12px;
  background-color: rgba(var(--v-theme-on-surface), 0.08);

  &--done {
    background-color: rgb(var(--v-theme-primary));
  }
}

.technician-option {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: border-color 0.15s ease, background-color 0.15s ease;

  &.v-selection-control--dirty {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.08);
  }

  :deep(.v-label) {
    inline-size: 100%;
    opacity: 1;
  }
}
</style>
