<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageUpload from '@/components/form/AppImageUpload.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Service {
  id: number
  name: string
  image: string | null
  description: string | null
  category_id: number
  category?: { id: number; name: string }
  is_active: boolean
  sort_order: number
  orders_count?: number
}

const categories = useLookup('/admin/categories')

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
    name: '', category_id: null as number | null, image: null as File | null,
    description: '', sort_order: 0, is_active: true,
  }),
  onSaved: () => table.refresh(),
})

const currentImage = ref<string | null>(null)
const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Service | null>(null)

const openCreate = () => { drawer.openCreate(); currentImage.value = null }
const openEdit = (row: Service) => { drawer.openEdit({ ...row, image: null } as any); currentImage.value = row.image }


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
        <VAvatar v-if="item.image" :image="item.image" size="38" rounded />
        <VAvatar v-else size="38" rounded color="secondary" variant="tonal">
          <VIcon icon="tabler-tool" size="20" />
        </VAvatar>
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

      <AppImageUpload
        v-model="drawer.form.value.image"
        :current-url="currentImage"
        label="صورة الخدمة"
        :error-message="drawer.fieldError('image')"
        class="mb-4"
      />

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
        <VCardText>سيتم حذف «{{ confirmDelete?.name }}». هل أنت متأكد؟</VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmDelete = null">إلغاء</VBtn>
          <VBtn color="error" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
