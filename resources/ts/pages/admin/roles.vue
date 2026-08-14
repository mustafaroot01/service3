<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

interface Role {
  id: number
  name: string
  label: string
  is_locked: boolean
  permissions_count: number
  permissions_total: number
  admins_count: number
  permissions: string[]
}

interface CatalogModule {
  key: string
  label: string
  group: string
  actions: string[]
}

interface Catalog {
  actions: { key: string; label: string }[]
  modules: CatalogModule[]
}

const toast = useToast()

const roles = ref<Role[]>([])
const catalog = ref<Catalog>({ actions: [], modules: [] })
const loading = ref(true)
const saving = ref(false)

const dialog = ref(false)
const editing = ref<Role | null>(null)
const draft = ref<Set<string>>(new Set())
const label = ref('')
const errors = ref<Record<string, string[]>>({})
const removing = ref<Role | null>(null)
const deleting = ref(false)

const isNew = computed(() => editing.value === null)
const isLocked = computed(() => editing.value?.is_locked ?? false)

const load = async () => {
  loading.value = true
  try {
    const [list, grid] = await Promise.all([
      $api<ApiResponse<Role[]>>('/admin/roles'),
      $api<ApiResponse<Catalog>>('/admin/roles/permissions'),
    ])

    roles.value = list.data ?? []
    catalog.value = grid.data ?? { actions: [], modules: [] }
  }
  finally {
    loading.value = false
  }
}

/** The modules in reading order, split under their section heading. */
const sections = computed(() => {
  const grouped = new Map<string, CatalogModule[]>()

  for (const module of catalog.value.modules) {
    const bucket = grouped.get(module.group) ?? []

    bucket.push(module)
    grouped.set(module.group, bucket)
  }

  return [...grouped].map(([group, modules]) => ({ group, modules }))
})

const openCreate = () => {
  editing.value = null
  label.value = ''
  draft.value = new Set()
  errors.value = {}
  dialog.value = true
}

const openEdit = (role: Role) => {
  editing.value = role
  label.value = role.label
  draft.value = new Set(role.permissions)
  errors.value = {}
  dialog.value = true
}

const has = (module: string, action: string) => draft.value.has(`${module}.${action}`)

const toggle = (module: string, action: string, on: boolean) => {
  const next = new Set(draft.value)

  on ? next.add(`${module}.${action}`) : next.delete(`${module}.${action}`)
  draft.value = next
}

const moduleState = (module: CatalogModule) => {
  const on = module.actions.filter(action => has(module.key, action)).length

  return { all: on === module.actions.length, some: on > 0 && on < module.actions.length }
}

const toggleModule = (module: CatalogModule, on: boolean) => {
  const next = new Set(draft.value)

  for (const action of module.actions)
    on ? next.add(`${module.key}.${action}`) : next.delete(`${module.key}.${action}`)

  draft.value = next
}

const everything = computed(() =>
  catalog.value.modules.every(module => module.actions.every(action => has(module.key, action))))

const toggleEverything = (on: boolean) => {
  const next = new Set<string>()

  if (on) {
    for (const module of catalog.value.modules)
      for (const action of module.actions) next.add(`${module.key}.${action}`)
  }

  draft.value = next
}

const save = async () => {
  saving.value = true
  errors.value = {}
  try {
    const body = { label: label.value, permissions: [...draft.value] }

    const res = isNew.value
      ? await $api<ApiResponse<Role>>('/admin/roles', { method: 'POST', body })
      : await $api<ApiResponse<Role>>(`/admin/roles/${editing.value!.id}`, { method: 'PUT', body })

    toast.success(res.message)

    roles.value = isNew.value
      ? [...roles.value, res.data]
      : roles.value.map(role => (role.id === res.data.id ? res.data : role))

    dialog.value = false
  }
  catch (e: any) {
    errors.value = e?.data?.errors ?? {}

    const first = Object.values(errors.value).flat()[0] as string | undefined

    toast.error(first ?? e?.data?.message ?? 'تعذّر الحفظ')
  }
  finally {
    saving.value = false
  }
}

const remove = async () => {
  if (!removing.value)
    return

  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/roles/${removing.value.id}`, { method: 'DELETE' })

    toast.success(res.message)
    roles.value = roles.value.filter(role => role.id !== removing.value!.id)
    removing.value = null
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر الحذف')
  }
  finally {
    deleting.value = false
  }
}

const roleColor = (role: Role) =>
  role.is_locked ? 'error' : role.permissions_count === 0 ? 'secondary' : 'primary'

onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate class="mb-6" />

    <VRow class="match-height">
      <VCol
        v-for="role in roles"
        :key="role.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <VCard>
          <VCardText class="d-flex align-center pb-4">
            <div class="text-body-1">
              {{ role.admins_count }} مشرف
            </div>

            <VSpacer />

            <VChip
              :color="roleColor(role)"
              size="small"
              variant="tonal"
              label
            >
              {{ role.permissions_count }} من {{ role.permissions_total }} صلاحية
            </VChip>
          </VCardText>

          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <h5 class="text-h5 mb-1">
                  {{ role.label }}
                </h5>
                <div class="text-caption text-disabled" dir="ltr">
                  {{ role.name }}
                </div>
              </div>

              <div class="d-flex align-center gap-1">
                <VBtn
                  :variant="role.is_locked ? 'text' : 'tonal'"
                  :color="role.is_locked ? 'secondary' : 'primary'"
                  size="small"
                  :prepend-icon="role.is_locked ? 'tabler-lock' : 'tabler-edit'"
                  @click="openEdit(role)"
                >
                  {{ role.is_locked ? 'عرض' : 'تعديل' }}
                </VBtn>

                <VBtn
                  v-if="!role.is_locked"
                  icon
                  variant="text"
                  size="small"
                  color="error"
                  @click="removing = role"
                >
                  <VIcon icon="tabler-trash" />
                  <VTooltip activator="parent" location="top">حذف الدور</VTooltip>
                </VBtn>
              </div>
            </div>

            <VProgressLinear
              :model-value="(role.permissions_count / role.permissions_total) * 100"
              :color="roleColor(role)"
              height="6"
              rounded
              class="mt-4"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" lg="4">
        <VCard class="h-100 add-role-card" @click="openCreate">
          <VCardText class="d-flex flex-column align-center justify-center text-center h-100 py-10 gap-2">
            <VAvatar color="primary" variant="tonal" rounded size="52">
              <VIcon icon="tabler-plus" size="28" />
            </VAvatar>
            <h5 class="text-h5 mt-2">إضافة دور</h5>
            <div class="text-body-2 text-disabled">دور جديد بصلاحيات تختارها</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog v-model="dialog" max-width="980" scrollable>
      <VCard>
        <VCardItem>
          <VCardTitle>
            {{ isNew ? 'دور جديد' : `دور «${editing?.label}»` }}
          </VCardTitle>
          <VCardSubtitle>
            <template v-if="isLocked">
              هذا الدور يملك كل الصلاحيات دائماً ولا يمكن تعديله
            </template>
            <template v-else>
              اختر ما يسمح به هذا الدور في كل قسم
            </template>
          </VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pb-0">
          <AppTextField
            v-model="label"
            label="اسم الدور"
            placeholder="مثال: مشرف المحتوى"
            :disabled="isLocked"
            :error-messages="errors.label?.[0]"
          />
        </VCardText>

        <VCardText style="max-block-size: 52vh;">
          <VTable class="permission-table">
            <thead>
              <tr>
                <th class="text-start">
                  <VCheckbox
                    :model-value="everything"
                    :disabled="isLocked"
                    label="كل الصلاحيات"
                    hide-details
                    density="compact"
                    @update:model-value="toggleEverything($event as boolean)"
                  />
                </th>
                <th
                  v-for="action in catalog.actions"
                  :key="action.key"
                  class="text-center text-no-wrap"
                >
                  {{ action.label }}
                </th>
              </tr>
            </thead>

            <tbody>
              <template v-for="section in sections" :key="section.group">
                <tr class="permission-section">
                  <td :colspan="catalog.actions.length + 1" class="text-caption text-disabled">
                    {{ section.group }}
                  </td>
                </tr>

                <tr v-for="module in section.modules" :key="module.key">
                  <td>
                    <VCheckbox
                      :model-value="moduleState(module).all"
                      :indeterminate="moduleState(module).some"
                      :disabled="isLocked"
                      :label="module.label"
                      hide-details
                      density="compact"
                      @update:model-value="toggleModule(module, $event as boolean)"
                    />
                  </td>

                  <td
                    v-for="action in catalog.actions"
                    :key="action.key"
                    class="text-center"
                  >
                    <VCheckbox
                      v-if="module.actions.includes(action.key)"
                      :model-value="has(module.key, action.key)"
                      :disabled="isLocked"
                      hide-details
                      density="compact"
                      class="d-inline-flex"
                      @update:model-value="toggle(module.key, action.key, $event as boolean)"
                    />
                    <span v-else class="text-disabled">—</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </VTable>
        </VCardText>

        <VDivider />

        <VCardActions class="px-6 py-4">
          <span class="text-body-2 text-disabled">
            المحدَّد: {{ draft.size }} من {{ roles[0]?.permissions_total ?? 0 }}
          </span>

          <VSpacer />

          <VBtn color="secondary" variant="tonal" @click="dialog = false">
            {{ isLocked ? 'إغلاق' : 'إلغاء' }}
          </VBtn>
          <VBtn
            v-if="!isLocked"
            :disabled="!label.trim()"
            :loading="saving"
            @click="save"
          >
            {{ isNew ? 'إنشاء الدور' : 'حفظ' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog :model-value="removing !== null" max-width="460" @update:model-value="removing = null">
      <VCard title="حذف الدور">
        <VCardText>
          سيتم حذف دور «{{ removing?.label }}» وصلاحياته الـ{{ removing?.permissions_count }} نهائياً.
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="removing = null">إلغاء</VBtn>
          <VBtn color="error" :loading="deleting" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.add-role-card {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: border-color 0.15s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}

.permission-table {
  th {
    font-weight: 500;
  }

  .permission-section td {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
    padding-block: 0.35rem;
  }
}
</style>
