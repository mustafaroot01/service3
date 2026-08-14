<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { ApiResponse, TableHeader } from '@/types/api'

interface AdminAccount {
  id: number
  name: string
  email: string
  status: string
  status_label: string
  role_id: number | null
  role?: { id: number; label: string; is_locked: boolean } | null
  is_self: boolean
  created_at: string | null
}

const roles = ref<{ id: number; label: string }[]>([])

const statusOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'غير نشط', value: 'inactive' },
  { title: 'موقوف', value: 'suspended' },
]

const headers = computed<TableHeader[]>(() => [
  { title: 'المشرف', key: 'name' },
  { title: 'البريد الإلكتروني', key: 'email' },
  {
    title: 'الدور',
    key: 'role',
    sortable: false,
    filterKey: 'role_id',
    filter: { type: 'select', options: roles.value.map(r => ({ title: r.label, value: r.id })) },
  },
  { title: 'الحالة', key: 'status', filter: { type: 'select', options: statusOptions } },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<AdminAccount>('/admin/admins', {
  defaultSort: 'created_at',
  defaultOrder: 'desc',
  filters: { status: null, role_id: null },
})

const form = useResourceForm({
  endpoint: '/admin/admins',
  blank: () => ({
    name: '',
    email: '',
    password: '',
    role_id: null as number | null,
    status: 'active',
  }),
  onSaved: () => table.refresh(),
})

const { busyRow, run } = useRowAction(() => table.refresh())

const setStatus = (row: AdminAccount, status: string) =>
  run(row.id, () => $api(`/admin/admins/${row.id}/status`, { method: 'PATCH', body: { status } }))

/** The password field is only ever a new value; it is never sent back down. */
const openEdit = (row: AdminAccount) => {
  form.openEdit({ ...row, password: '' } as any)
}

const statusColor = (status: string) => ({
  active: 'success', inactive: 'secondary', suspended: 'error',
}[status] ?? 'secondary')

onMounted(async () => {
  const res = await $api<ApiResponse<{ id: number; label: string }[]>>('/admin/roles')

  roles.value = res.data ?? []
})
</script>

<template>
  <div>
    <AppDataTableServer
      title="المشرفون"
      :headers="headers"
      :table="table"
      search-placeholder="بحث بالاسم أو البريد"
      create-label="إضافة مشرف"
      @create="form.openCreate"
    >
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar size="34" color="primary" variant="tonal">
            <span class="text-caption">{{ avatarText(item.name) }}</span>
          </VAvatar>
          <div>
            <div class="text-high-emphasis font-weight-medium">
              {{ item.name }}
              <VChip v-if="item.is_self" size="x-small" color="info" variant="tonal" label class="ms-1">
                أنت
              </VChip>
            </div>
          </div>
        </div>
      </template>

      <template #item.email="{ item }">
        <span dir="ltr">{{ item.email }}</span>
      </template>

      <template #item.role="{ item }">
        <VChip
          v-if="item.role"
          :color="item.role.is_locked ? 'error' : 'primary'"
          size="small"
          variant="tonal"
          label
        >
          {{ item.role.label }}
        </VChip>
        <span v-else class="text-disabled">بلا دور</span>
      </template>

      <template #item.status="{ item }">
        <VChip :color="statusColor(item.status)" size="small" label>{{ item.status_label }}</VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center">
          <VBtn icon variant="text" size="small" color="default" @click="openEdit(item)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">تعديل</VTooltip>
          </VBtn>

          <VBtn icon variant="text" size="small" color="default" :loading="busyRow === item.id">
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListSubheader>تغيير الحالة</VListSubheader>
                <VListItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :active="item.status === option.value"
                  @click="setStatus(item, option.value)"
                >
                  <VListItemTitle>{{ option.title }}</VListItemTitle>
                </VListItem>

                <VDivider class="my-1" />

                <VListItem
                  :disabled="item.is_self"
                  base-color="error"
                  @click="form.destroy(item.id)"
                >
                  <template #prepend>
                    <VIcon icon="tabler-trash" size="20" />
                  </template>
                  <VListItemTitle>حذف الحساب</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </div>
      </template>
    </AppDataTableServer>

    <AppFormDrawer
      v-model="form.isOpen.value"
      :title="`${form.title.value} مشرف`"
      :saving="form.isSaving.value"
      @submit="form.save"
    >
      <VRow>
        <VCol cols="12">
          <AppTextField
            v-model="form.form.value.name"
            label="الاسم"
            placeholder="اسم المشرف"
            :error-messages="form.fieldError('name')"
          />
        </VCol>

        <VCol cols="12">
          <AppTextField
            v-model="form.form.value.email"
            label="البريد الإلكتروني"
            dir="ltr"
            placeholder="admin@hoame.iq"
            :error-messages="form.fieldError('email')"
          />
        </VCol>

        <VCol cols="12">
          <AppTextField
            v-model="form.form.value.password"
            label="كلمة المرور"
            type="password"
            dir="ltr"
            :placeholder="form.isEditing.value ? 'اتركها فارغة لإبقاء الحالية' : '8 خانات على الأقل'"
            :error-messages="form.fieldError('password')"
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="form.form.value.role_id"
            label="الدور"
            placeholder="اختر الدور"
            :items="roles"
            item-title="label"
            item-value="id"
            :error-messages="form.fieldError('role_id')"
          />
        </VCol>

        <VCol cols="12">
          <AppSelect
            v-model="form.form.value.status"
            label="الحالة"
            :items="statusOptions"
            :error-messages="form.fieldError('status')"
          />
        </VCol>
      </VRow>
    </AppFormDrawer>
  </div>
</template>
