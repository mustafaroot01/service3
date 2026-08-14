<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Specialization {
  id: number
  name: string
  is_active: boolean
  technicians_count?: number
}

const headers: TableHeader[] = [
  { title: 'الاختصاص', key: 'name' },
  { title: 'الفنيون', key: 'technicians_count', align: 'center', sortable: false },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'مفعّل', value: 1 }, { title: 'مخفي', value: 0 }] },
  },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const table = useServerTable<Specialization>('/admin/specializations', {
  defaultSort: 'name',
  filters: { is_active: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/specializations',
  blank: () => ({ name: '', is_active: true }),
  onSaved: () => table.refresh(),
})

const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Specialization | null>(null)

const toggle = (row: Specialization) =>
  run(row.id, () => $api(`/admin/specializations/${row.id}/toggle`, { method: 'POST' }))

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
      title="الاختصاصات"
      create-label="إضافة اختصاص"
      :headers="headers"
      :table="table"
      @create="drawer.openCreate()"
    >
      <template #item.name="{ item }">
        <span class="text-high-emphasis font-weight-medium">{{ item.name }}</span>
      </template>

      <template #item.technicians_count="{ item }">
        <VChip size="small" label>{{ item.technicians_count ?? 0 }}</VChip>
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
      :title="`${drawer.title.value} اختصاص`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppTextField
        v-model="drawer.form.value.name"
        label="اسم الاختصاص"
        placeholder="كهربائي"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('name')"
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
