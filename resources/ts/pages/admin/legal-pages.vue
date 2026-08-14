<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

interface LegalPage {
  key: string
  key_label: string
  title: string
  content: string
  updated_at: string | null
}

const toast = useToast()
const pages = ref<LegalPage[]>([])
const activeTab = ref<string>('')
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const active = computed(() => pages.value.find(p => p.key === activeTab.value))

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<LegalPage[]>>('/admin/legal-pages')

    pages.value = res.data ?? []
    activeTab.value ||= pages.value[0]?.key ?? ''
  }
  finally {
    loading.value = false
  }
}

const save = async () => {
  const page = active.value
  if (!page)
    return

  saving.value = true
  errors.value = {}

  try {
    const res = await $api<ApiResponse<LegalPage>>(`/admin/legal-pages/${page.key}`, {
      method: 'PUT',
      body: { title: page.title, content: page.content },
    })

    toast.success(res.message)
    await load()
  }
  catch (e: any) {
    errors.value = e?.data?.errors ?? {}
    if (!Object.keys(errors.value).length)
      toast.error(e?.data?.message ?? 'تعذّر الحفظ')
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center flex-wrap gap-4 pb-2">
      <h5 class="text-h5 mb-0">الصفحات القانونية</h5>
      <VSpacer />
      <span v-if="active" class="text-caption text-disabled">
        آخر تحديث: {{ formatDateTime(active.updated_at) }}
      </span>
    </VCardText>

    <VTabs v-model="activeTab" class="px-6">
      <VTab
        v-for="page in pages"
        :key="page.key"
        :value="page.key"
      >
        {{ page.key_label }}
      </VTab>
    </VTabs>

    <VDivider />

    <VCardText>
      <VProgressLinear v-if="loading" indeterminate />

      <template v-else-if="active">
        <AppTextField
          v-model="active.title"
          label="العنوان"
          :error-messages="errors.title?.[0]"
          class="mb-4"
        />

        <AppTextarea
          v-model="active.content"
          label="المحتوى (HTML)"
          rows="16"
          :error-messages="errors.content?.[0]"
          class="mb-4"
        />

        <div class="d-flex gap-3">
          <VBtn :loading="saving" @click="save">حفظ</VBtn>
          <VBtn color="secondary" variant="tonal" :disabled="saving" @click="load">استرجاع</VBtn>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>
