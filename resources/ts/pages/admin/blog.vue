<script setup lang="ts">
import AppFormDrawer from '@/components/form/AppFormDrawer.vue'
import AppImageUpload from '@/components/form/AppImageUpload.vue'
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useResourceForm } from '@/composables/useResourceForm'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Post {
  id: number
  title: string | null
  image: string | null
  content: string
  published_at: string | null
  is_active: boolean
  is_announced: boolean
  notified_at: string | null
}

const headers: TableHeader[] = [
  { title: 'الصورة', key: 'image', sortable: false, align: 'center', width: 80 },
  { title: 'العنوان', key: 'title' },
  { title: 'تاريخ النشر', key: 'published_at' },
  { title: 'إشعار التطبيق', key: 'is_announced', sortable: false, align: 'center' },
  {
    title: 'الحالة',
    key: 'is_active',
    filter: { type: 'select', options: [{ title: 'منشور', value: 1 }, { title: 'مسودة', value: 0 }] },
  },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const table = useServerTable<Post>('/admin/blog', {
  defaultSort: 'published_at',
  defaultOrder: 'desc',
  filters: { is_active: null },
})

const drawer = useResourceForm({
  endpoint: '/admin/blog',
  multipart: true,
  blank: () => ({
    title: '', image: null as File | null, content: '',
    published_at: new Date().toISOString().slice(0, 10), is_active: true,
  }),
  onSaved: () => table.refresh(),
})

const currentImage = ref<string | null>(null)
const { busyRow, run } = useRowAction(() => table.refresh())
const confirmDelete = ref<Post | null>(null)

const openCreate = () => { drawer.openCreate(); currentImage.value = null }

const openEdit = (row: Post) => {
  drawer.openEdit({ ...row, image: null, published_at: row.published_at?.slice(0, 10) ?? '' } as any)
  currentImage.value = row.image
}


const toggle = (row: Post) => run(row.id, () => $api(`/admin/blog/${row.id}/toggle`, { method: 'POST' }))

const remove = async () => {
  const row = confirmDelete.value
  if (!row) return
  confirmDelete.value = null
  await run(row.id, () => drawer.destroy(row.id))
}

const publishedOn = (value: string | null) => formatDate(value, { dateStyle: 'long' })
</script>

<template>
  <div>
    <AppDataTableServer
      title="المدوّنة"
      create-label="إضافة مقال"
      :headers="headers"
      :table="table"
      @create="openCreate"
    >
      <template #item.image="{ item }">
        <VAvatar v-if="item.image" :image="item.image" size="38" rounded />
        <VAvatar v-else size="38" rounded color="secondary" variant="tonal">
          <VIcon icon="tabler-article" size="20" />
        </VAvatar>
      </template>

      <template #item.title="{ item }">
        <div>
          <div class="text-high-emphasis font-weight-medium">{{ item.title || 'بلا عنوان' }}</div>
          <div class="text-caption text-disabled text-truncate" style="max-inline-size: 320px;">
            {{ item.content }}
          </div>
        </div>
      </template>

      <template #item.published_at="{ item }">{{ formatDate(item.published_at) }}</template>

      <template #item.is_active="{ item }">
        <VChip :color="item.is_active ? 'success' : 'secondary'" size="small" label>
          {{ item.is_active ? 'منشور' : 'مسودة' }}
        </VChip>
      </template>

      <template #item.is_announced="{ item }">
        <VChip
          v-if="item.is_announced"
          color="success"
          size="small"
          variant="tonal"
          label
          prepend-icon="tabler-bell-check"
        >
          أُرسل
          <VTooltip activator="parent" location="top">{{ formatDateTime(item.notified_at) }}</VTooltip>
        </VChip>
        <VChip
          v-else
          color="secondary"
          size="small"
          variant="tonal"
          label
          prepend-icon="tabler-bell-off"
        >
          لم يُرسل
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
                <VListItemTitle>{{ item.is_active ? 'تحويل لمسودة' : 'نشر' }}</VListItemTitle>
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
      :title="`${drawer.title.value} مقال`"
      :errors="drawer.errors.value"
      :loading="drawer.isSaving.value"
      :width="520"
      @submit="drawer.save()"
    >
      <VAlert v-if="drawer.generalError.value" type="error" variant="tonal" density="compact" class="mb-4">
        {{ drawer.generalError.value }}
      </VAlert>

      <AppTextField
        v-model="drawer.form.value.title"
        label="العنوان (اختياري)"
        :error-messages="drawer.fieldError('title')"
        class="mb-4"
      />

      <AppImageUpload
        v-model="drawer.form.value.image"
        :current-url="currentImage"
        label="صورة المقال"
        :error-message="drawer.fieldError('image')"
        class="mb-4"
      />

      <AppTextarea
        v-model="drawer.form.value.content"
        label="المحتوى"
        rows="8"
        :rules="[requiredValidator]"
        :error-messages="drawer.fieldError('content')"
        class="mb-4"
      />

      <AppDateTimePicker
        v-model="drawer.form.value.published_at"
        label="تاريخ النشر"
        :config="{ dateFormat: 'Y-m-d' }"
        :error-messages="drawer.fieldError('published_at')"
        class="mb-4"
      />

      <VSwitch v-model="drawer.form.value.is_active" label="منشور" />
    </AppFormDrawer>

    <VDialog :model-value="confirmDelete !== null" max-width="420" @update:model-value="confirmDelete = null">
      <VCard title="تأكيد الحذف">
        <VCardText>سيتم حذف المقال نهائياً. هل أنت متأكد؟</VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmDelete = null">إلغاء</VBtn>
          <VBtn color="error" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
