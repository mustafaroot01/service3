<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageSlot from '@/components/form/AppImageSlot.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import { useToast } from '@/composables/useToast'
import type { TableHeader } from '@/types/api'

interface ServiceImage {
  id: number
  url: string
  sort: number
}

interface Service {
  id: number
  name: string
  image: string | null
  images: ServiceImage[]
  description: string | null
  category_id: number
  category?: { id: number; name: string }
  is_active: boolean
  sort_order: number
  orders_count?: number
}

type Envelope<T> = { message?: string; data: T }

const MAX_IMAGES = 4

const categories = useLookup('/admin/categories')
const toast = useToast()

const headers = computed<TableHeader[]>(() => [
  { title: 'الصورة', key: 'image', sortable: false, align: 'center', width: 80 },
  { title: 'الخدمة', key: 'name' },
  {
    title: 'القسم',
    key: 'category',
    filterKey: 'category_id',
    filter: { type: 'select', options: categories.items.value.map(c => ({ title: c.name, value: c.id })) },
  },
  { title: 'الطلبات', key: 'orders_count', align: 'center' },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'مفعّلة', value: 1 }, { title: 'مخفية', value: 0 }] },
  },
  { title: 'الترتيب', key: 'sort_order', align: 'center' },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<Service>('/admin/services', {
  defaultSort: 'sort_order',
  filters: { is_active: null, category_id: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/services',
  multipart: true,
  blank: () => ({
    name: '', category_id: null as number | null, images: [] as File[],
    description: '', sort_order: 0, is_active: true,
  }),
  onSaved: () => { table.refresh(); releasePending() },
})

/** Gallery of the service being edited — refreshed from every API reply. */
const gallery = ref<ServiceImage[]>([])

/** Files chosen on the create form, previewed locally until the service is saved. */
const pending = ref<{ file: File; preview: string }[]>([])
const slotBusy = ref<number | null>(null)

const releasePending = () => {
  pending.value.forEach(p => URL.revokeObjectURL(p.preview))
  pending.value = []
}

const syncPending = () => { drawer.form.value.images = pending.value.map(p => p.file) }

const slots = Array.from({ length: MAX_IMAGES }, (_, i) => i)
const slotLabel = (i: number) => (i === 0 ? 'الغلاف' : `صورة ${i + 1}`)
const slotUrl = (i: number) => (drawer.isEditing.value ? gallery.value[i]?.url : pending.value[i]?.preview) ?? null

const galleryError = computed(() => {
  const errors = drawer.errors.value
  const key = Object.keys(errors).find(k => k === 'images' || k.startsWith('images.'))

  return key ? errors[key]?.[0] : undefined
})

const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Service | null>(null)

const openCreate = () => { drawer.openCreate(); gallery.value = []; releasePending() }
const openEdit = (row: Service) => {
  drawer.openEdit({ ...row, images: [] } as any)
  gallery.value = row.images ?? []
  releasePending()
}

/** Edit mode talks to the API per image, so every action shows at once. */
const galleryRequest = async (url: string, method: 'POST' | 'DELETE', body?: FormData) => {
  const res = await $api<Envelope<Service>>(url, { method, body })

  gallery.value = res.data.images
  toast.success(res.message ?? '')
  table.refresh()
}

const reportError = (e: any, fallback: string) => {
  const body = e?.data
  const first = body?.errors ? (Object.values(body.errors)[0] as string[] | undefined)?.[0] : undefined

  toast.error(first ?? body?.message ?? fallback)
}

const pickSlot = async (i: number, file: File) => {
  if (!drawer.isEditing.value) {
    const entry = { file, preview: URL.createObjectURL(file) }
    const current = pending.value[i]

    if (current) {
      URL.revokeObjectURL(current.preview)
      pending.value.splice(i, 1, entry)
    }
    else {
      pending.value.push(entry)
    }

    syncPending()

    return
  }

  const id = drawer.editingId.value
  const existing = gallery.value[i]
  const body = new FormData()

  slotBusy.value = i

  try {
    if (existing) {
      body.append('image', file)
      await galleryRequest(`/admin/services/${id}/images/${existing.id}`, 'POST', body)
    }
    else {
      body.append('images[]', file)
      await galleryRequest(`/admin/services/${id}/images`, 'POST', body)
    }
  }
  catch (e: any) {
    reportError(e, 'تعذّر رفع الصورة')
  }
  finally {
    slotBusy.value = null
  }
}

const removeSlot = async (i: number) => {
  if (!drawer.isEditing.value) {
    const current = pending.value[i]

    if (current) {
      URL.revokeObjectURL(current.preview)
      pending.value.splice(i, 1)
      syncPending()
    }

    return
  }

  const existing = gallery.value[i]

  if (!existing)
    return

  slotBusy.value = i

  try {
    await galleryRequest(`/admin/services/${drawer.editingId.value}/images/${existing.id}`, 'DELETE')
  }
  catch (e: any) {
    reportError(e, 'تعذّر حذف الصورة')
  }
  finally {
    slotBusy.value = null
  }
}

onBeforeUnmount(releasePending)

const toggle = (row: Service) => run(row.id, () => $api(`/admin/services/${row.id}/toggle`, { method: 'POST' }))

const remove = async () => {
  const row = confirmDelete.value
  if (!row) return
  confirmDelete.value = null
  await run(row.id, () => drawer.destroy(row.id))
}
</script>

<template>
  <div>
    <AppDataTableServer
      title="الخدمات"
      create-label="إضافة خدمة"
      :headers="headers"
      :table="table"
      @create="openCreate"
    >
      <template #item.image="{ item }">
        <VBadge
          :model-value="(item.images?.length ?? 0) > 1"
          :content="item.images?.length"
          color="primary"
          offset-x="4"
          offset-y="4"
        >
          <VAvatar v-if="item.image" :image="item.image" size="38" rounded />
          <VAvatar v-else size="38" rounded color="secondary" variant="tonal">
            <VIcon icon="tabler-tool" size="20" />
          </VAvatar>
        </VBadge>
      </template>

      <template #item.name="{ item }">
        <div>
          <div class="text-high-emphasis font-weight-medium">{{ item.name }}</div>
          <div v-if="item.description" class="text-caption text-disabled text-truncate" style="max-inline-size: 260px;">
            {{ item.description }}
          </div>
        </div>
      </template>

      <template #item.category="{ item }">{{ item.category?.name ?? '—' }}</template>

      <template #item.orders_count="{ item }">
        <VChip size="small" label color="info">{{ item.orders_count ?? 0 }}</VChip>
      </template>

      <template #item.is_active="{ item }">
        <VChip :color="item.is_active ? 'success' : 'secondary'" size="small" label>
          {{ item.is_active ? 'مفعّلة' : 'مخفية' }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <VBtn icon variant="text" size="small" color="default" :loading="busyRow === item.id">
          <VIcon icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList density="compact">
              <VListItem @click="openEdit(item)">
                <template #prepend><VIcon icon="tabler-edit" size="20" /></template>
                <VListItemTitle>تعديل</VListItemTitle>
              </VListItem>
              <VListItem @click="toggle(item)">
                <template #prepend><VIcon :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'" size="20" /></template>
                <VListItemTitle>{{ item.is_active ? 'إخفاء' : 'إظهار' }}</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="confirmDelete = item">
                <template #prepend><VIcon icon="tabler-trash" size="20" color="error" /></template>
                <VListItemTitle class="text-error">حذف</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </AppDataTableServer>

    <AppFormDrawer
      v-model="drawer.isOpen.value"
      :title="`${drawer.title.value} خدمة`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppSelect
        v-model="drawer.form.value.category_id"
        label="القسم"
        placeholder="اختر القسم"
        :items="categories.items.value"
        item-title="name"
        item-value="id"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('category_id')"
        class="mb-4"
      />

      <AppTextField
        v-model="drawer.form.value.name"
        label="اسم الخدمة"
        placeholder="صيانة مكيّف"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('name')"
        class="mb-4"
      />

      <div class="mb-4">
        <div class="d-flex align-center justify-space-between mb-2">
          <span class="text-body-2 text-high-emphasis">صور الخدمة</span>
          <span class="text-caption text-disabled">حتى {{ MAX_IMAGES }} صور · الأولى هي الغلاف</span>
        </div>

        <VRow dense>
          <VCol v-for="i in slots" :key="i" cols="6">
            <AppImageSlot
              :label="slotLabel(i)"
              :url="slotUrl(i)"
              removable
              :uploading="slotBusy === i"
              :height="120"
              @pick="file => pickSlot(i, file)"
              @remove="removeSlot(i)"
            />
          </VCol>
        </VRow>

        <div v-if="galleryError" class="text-caption text-error mt-1">{{ galleryError }}</div>
        <div v-else-if="drawer.isEditing.value" class="text-caption text-disabled mt-1">
          كل رفع أو استبدال أو حذف يُحفظ فوراً.
        </div>
      </div>

      <AppTextarea
        v-model="drawer.form.value.description"
        label="الوصف"
        rows="3"
        :error-messages="drawer.fieldError('description')"
        class="mb-4"
      />

      <AppTextField
        v-model.number="drawer.form.value.sort_order"
        label="ترتيب الفرز"
        type="number"
        min="0"
        :error-messages="drawer.fieldError('sort_order')"
        class="mb-4"
      />

      <VSwitch v-model="drawer.form.value.is_active" label="مفعّلة" />
    </AppFormDrawer>

    <VDialog :model-value="confirmDelete !== null" max-width="420" @update:model-value="confirmDelete = null">
      <VCard title="تأكيد الحذف">
        <VCardText>
          سيُحذف «{{ confirmDelete?.name }}» نهائياً مع كل صوره.
          الخدمة المرتبطة بطلبات لا يمكن حذفها — عطّلها بدل ذلك. هل أنت متأكد؟
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmDelete = null">إلغاء</VBtn>
          <VBtn color="error" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
