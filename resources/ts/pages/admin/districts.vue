<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface District {
  id: number
  name: string
  governorate_id: number
  governorate?: { id: number; name: string }
  is_active: boolean
  sort_order: number
  users_count?: number
  technicians_count?: number
}

const governorates = useLookup('/admin/governorates')

const headers = computed<TableHeader[]>(() => [
  { title: 'القضاء', key: 'name' },
  {
    title: 'المحافظة',
    key: 'governorate',
    filterKey: 'governorate_id',
    filter: {
      type: 'select',
      options: governorates.items.value.map(g => ({ title: g.name, value: g.id })),
    },
  },
  { title: 'الزبائن', key: 'users_count', align: 'center', sortable: false },
  { title: 'الفنيون', key: 'technicians_count', align: 'center', sortable: false },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'مفعّل', value: 1 }, { title: 'مخفي', value: 0 }] },
  },
  { title: 'الترتيب', key: 'sort_order', align: 'center' },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<District>('/admin/districts', {
  defaultSort: 'sort_order',
  filters: { is_active: null, governorate_id: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/districts',
  blank: () => ({ name: '', governorate_id: null as number | null, sort_order: 0, is_active: true }),
  onSaved: () => table.refresh(),
})

const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<District | null>(null)

const toggle = (row: District) =>
  run(row.id, () => $api(`/admin/districts/${row.id}/toggle`, { method: 'POST' }))

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
      title="الأقضية"
      create-label="إضافة قضاء"
      :headers="headers"
      :table="table"
      @create="drawer.openCreate()"
    >
      <template #item.name="{ item }">
        <span class="text-high-emphasis font-weight-medium">{{ item.name }}</span>
      </template>

      <template #item.governorate="{ item }">
        {{ item.governorate?.name ?? '—' }}
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
              <VListItem @click="drawer.openEdit(item)">
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
      :title="`${drawer.title.value} قضاء`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppSelect
        v-model="drawer.form.value.governorate_id"
        label="المحافظة"
        placeholder="اختر المحافظة"
        :items="governorates.items.value"
        item-title="name"
        item-value="id"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('governorate_id')"
        class="mb-4"
      />

      <AppTextField
        v-model="drawer.form.value.name"
        label="اسم القضاء"
        placeholder="الكرخ"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('name')"
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

      <VSwitch v-model="drawer.form.value.is_active" label="مفعّل" />
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
