<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageUpload from '@/components/form/AppImageUpload.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Category {
  id: number
  name: string
  image: string | null
  is_active: boolean
  sort_order: number
  services_count?: number
}

const headers: TableHeader[] = [
  { title: 'الصورة', key: 'image', sortable: false, align: 'center', width: 80 },
  { title: 'القسم', key: 'name' },
  { title: 'الخدمات', key: 'services_count', align: 'center', sortable: false },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'مفعّل', value: 1 }, { title: 'مخفي', value: 0 }] },
  },
  { title: 'الترتيب', key: 'sort_order', align: 'center' },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const table = useServerTable<Category>('/admin/categories', {
  defaultSort: 'sort_order',
  filters: { is_active: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/categories',
  multipart: true,
  blank: () => ({ name: '', image: null as File | null, remove_image: false, sort_order: 0, is_active: true }),
  onSaved: () => table.refresh(),
})

/** The row carries a URL; the form carries a File — keep them apart. */
const currentImage = ref<string | null>(null)

const openEdit = (row: Category) => {
  drawer.openEdit({ ...row, image: null } as any)
  currentImage.value = row.image
}

const openCreate = () => {
  drawer.openCreate()
  currentImage.value = null
}

const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Category | null>(null)

const toggle = (row: Category) =>
  run(row.id, () => $api(`/admin/categories/${row.id}/toggle`, { method: 'POST' }))

const remove = async () => {
  const row = confirmDelete.value
  if (!row)
    return

  confirmDelete.value = null
  await run(row.id, () => drawer.destroy(row.id))
}
</script>

<template>
  <div>
    <AppDataTableServer
      title="الأقسام"
      create-label="إضافة قسم"
      :headers="headers"
      :table="table"
      @create="openCreate"
    >
      <template #item.image="{ item }">
        <VAvatar
          v-if="item.image"
          :image="item.image"
          size="38"
          rounded
        />
        <VAvatar
          v-else
          size="38"
          rounded
          color="secondary"
          variant="tonal"
        >
          <VIcon icon="tabler-category" size="20" />
        </VAvatar>
      </template>

      <template #item.name="{ item }">
        <span class="text-high-emphasis font-weight-medium">{{ item.name }}</span>
      </template>

      <template #item.is_active="{ item }">
        <VChip :color="item.is_active ? 'success' : 'secondary'" size="small" label>
          {{ item.is_active ? 'مفعّل' : 'مخفي' }}
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
      :title="`${drawer.title.value} قسم`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppTextField
        v-model="drawer.form.value.name"
        label="اسم القسم"
        placeholder="تكييف وتبريد"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('name')"
        class="mb-4"
      />

      <AppImageUpload
        v-model="drawer.form.value.image"
        :current-url="currentImage"
        label="صورة القسم"
        :error-message="drawer.fieldError('image')"
        class="mb-4"
        @remove="currentImage = null; drawer.form.value.remove_image = true"
      />

      <AppTextField
        v-model.number="drawer.form.value.sort_order"
        label="ترتيب الفرز"
        type="number"
        min="0"
        :error-messages="drawer.fieldError('sort_order')"
        class="mb-4"
      />

      <VSwitch v-model="drawer.form.value.is_active" label="مفعّل" />
    </AppFormDrawer>

    <VDialog :model-value="confirmDelete !== null" max-width="420" @update:model-value="confirmDelete = null">
      <VCard title="تأكيد الحذف">
        <VCardText>
          سيُحذف القسم «{{ confirmDelete?.name }}» وكل خدماته نهائياً.
          القسم المرتبط بطلبات لا يمكن حذفه — عطّله بدل ذلك. هل أنت متأكد؟
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
