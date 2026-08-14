<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Governorate {
  id: number
  name: string
  is_active: boolean
  sort_order: number
  districts_count?: number
  users_count?: number
  technicians_count?: number
}

const headers: TableHeader[] = [
  { title: 'المحافظة', key: 'name' },
  { title: 'الأقضية', key: 'districts_count', align: 'center', sortable: false },
  { title: 'الزبائن', key: 'users_count', align: 'center', sortable: false },
  { title: 'الفنيون', key: 'technicians_count', align: 'center', sortable: false },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: {
      type: 'select',
      options: [
        { title: 'مفعّلة', value: 1 },
        { title: 'مخفية', value: 0 },
      ],
    },
  },
  { title: 'الترتيب', key: 'sort_order', align: 'center' },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const table = useServerTable<Governorate>('/admin/governorates', {
  defaultSort: 'sort_order',
  filters: { is_active: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/governorates',
  blank: () => ({ name: '', sort_order: 0, is_active: true }),
  onSaved: () => table.refresh(),
})

const { busyRow, run } = useRowAction(() => table.refresh())

const toggle = (row: Governorate) =>
  run(row.id, () => $api(`/admin/governorates/${row.id}/toggle`, { method: 'POST' }))

const confirmDelete = ref<Governorate | null>(null)

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
      title="المحافظات"
      create-label="إضافة محافظة"
      :headers="headers"
      :table="table"
      @create="drawer.openCreate()"
    >
      <template #item.name="{ item }">
        <span class="text-high-emphasis font-weight-medium">{{ item.name }}</span>
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="item.is_active ? 'success' : 'secondary'"
          size="small"
          label
        >
          {{ item.is_active ? 'مفعّلة' : 'مخفية' }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          :loading="busyRow === item.id"
        >
          <VIcon icon="tabler-dots-vertical" />

          <VMenu activator="parent">
            <VList density="compact">
              <VListItem @click="drawer.openEdit(item)">
                <template #prepend>
                  <VIcon icon="tabler-edit" size="20" />
                </template>
                <VListItemTitle>تعديل</VListItemTitle>
              </VListItem>

              <VListItem @click="toggle(item)">
                <template #prepend>
                  <VIcon :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'" size="20" />
                </template>
                <VListItemTitle>{{ item.is_active ? 'إخفاء' : 'إظهار' }}</VListItemTitle>
              </VListItem>

              <VDivider />

              <VListItem @click="confirmDelete = item">
                <template #prepend>
                  <VIcon icon="tabler-trash" size="20" color="error" />
                </template>
                <VListItemTitle class="text-error">
                  حذف
                </VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </AppDataTableServer>

    <AppFormDrawer
      v-model="drawer.isOpen.value"
      :title="`${drawer.title.value} محافظة`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      @submit="drawer.save()"
    >
      <VAlert
        v-if="drawer.generalError.value"
        type="error"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        {{ drawer.generalError.value }}
      </VAlert>

      <AppTextField
        v-model="drawer.form.value.name"
        label="اسم المحافظة"
        placeholder="بغداد"
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

      <VSwitch
        v-model="drawer.form.value.is_active"
        label="مفعّلة"
        :error-messages="drawer.fieldError('is_active')"
      />
    </AppFormDrawer>

    <VDialog
      :model-value="confirmDelete !== null"
      max-width="420"
      @update:model-value="confirmDelete = null"
    >
      <VCard title="تأكيد الحذف">
        <VCardText>
          سيتم حذف «{{ confirmDelete?.name }}» وكل أقضيتها. هل أنت متأكد؟
        </VCardText>

        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="confirmDelete = null"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            @click="remove"
          >
            حذف
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
