<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageUpload from '@/components/form/AppImageUpload.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Slider {
  id: number
  image: string | null
  link: string | null
  is_active: boolean
  sort_order: number
}

const headers: TableHeader[] = [
  { title: 'الصورة', key: 'image', sortable: false, width: 120 },
  { title: 'الرابط', key: 'link', sortable: false },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'ظاهر', value: 1 }, { title: 'مخفي', value: 0 }] },
  },
  { title: 'الترتيب', key: 'sort_order', align: 'center' },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const table = useServerTable<Slider>('/admin/sliders', { defaultSort: 'sort_order', filters: { is_active: null } })

const drawer = useResourceForm({
  endpoint: '/admin/sliders',
  multipart: true,
  blank: () => ({ image: null as File | null, link: '', sort_order: 0, is_active: true }),
  onSaved: () => table.refresh(),
})

const currentImage = ref<string | null>(null)
const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Slider | null>(null)

const openCreate = () => { drawer.openCreate(); currentImage.value = null }
const openEdit = (row: Slider) => { drawer.openEdit({ ...row, image: null } as any); currentImage.value = row.image }


const toggle = (row: Slider) => run(row.id, () => $api(`/admin/sliders/${row.id}/toggle`, { method: 'POST' }))

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
      title="السلايدرات"
      create-label="إضافة سلايدر"
      :headers="headers"
      :table="table"
      @create="openCreate"
    >
      <template #item.image="{ item }">
        <VImg v-if="item.image" :src="item.image" width="96" height="48" cover class="rounded" />
        <VAvatar v-else size="48" rounded color="secondary" variant="tonal">
          <VIcon icon="tabler-photo" size="20" />
        </VAvatar>
      </template>

      <template #item.link="{ item }">
        <a v-if="item.link" :href="item.link" target="_blank" rel="noopener" class="text-primary">
          {{ item.link }}
        </a>
        <span v-else class="text-disabled">—</span>
      </template>

      <template #item.is_active="{ item }">
        <VChip :color="item.is_active ? 'success' : 'secondary'" size="small" label>
          {{ item.is_active ? 'ظاهر' : 'مخفي' }}
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
      :title="`${drawer.title.value} سلايدر`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppImageUpload
        v-model="drawer.form.value.image"
        :current-url="currentImage"
        label="صورة السلايدر"
        hint="الصورة مطلوبة عند الإضافة"
        :height="180"
        :error-message="drawer.fieldError('image')"
        class="mb-4"
      />

      <AppTextField
        v-model="drawer.form.value.link"
        label="الرابط (اختياري)"
        placeholder="https://example.com"
        :error-messages="drawer.fieldError('link')"
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

      <VSwitch v-model="drawer.form.value.is_active" label="ظاهر" />
    </AppFormDrawer>

    <VDialog :model-value="confirmDelete !== null" max-width="420" @update:model-value="confirmDelete = null">
      <VCard title="تأكيد الحذف">
        <VCardText>سيتم حذف السلايدر نهائياً. هل أنت متأكد؟</VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmDelete = null">إلغاء</VBtn>
          <VBtn color="error" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
