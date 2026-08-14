<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

interface SettingItem {
  key: string
  label: string
  type: 'text' | 'secret' | 'boolean'
  hint: string | null
  is_secret: boolean
  is_set: boolean
  is_readable: boolean
  value: string | null
}

interface SettingGroup {
  group: string
  group_label: string
  items: SettingItem[]
}

const toast = useToast()
const groups = ref<SettingGroup[]>([])
const drafts = ref<Record<string, string>>({})
const revealed = ref<Record<string, boolean>>({})
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<SettingGroup[]>>('/admin/settings')

    groups.value = res.data ?? []
    drafts.value = {}
    for (const group of groups.value)
      for (const item of group.items) drafts.value[item.key] = item.value ?? ''
  }
  finally {
    loading.value = false
  }
}

const save = async () => {
  saving.value = true
  errors.value = {}

  try {
    const res = await $api<ApiResponse<SettingGroup[]>>('/admin/settings', {
      method: 'PUT',
      body: { settings: Object.entries(drafts.value).map(([key, value]) => ({ key, value })) },
    })

    toast.success(res.message)
    groups.value = res.data ?? []
    await load()
  }
  catch (e: any) {
    errors.value = e?.data?.errors ?? {}
    toast.error(e?.data?.message ?? 'تعذّر الحفظ')
  }
  finally {
    saving.value = false
  }
}

/** A switch is a boolean on screen but a '1'/'0' string on the wire. */
const asSwitch = (key: string) => computed({
  get: () => drafts.value[key] === '1',
  set: (on: boolean) => { drafts.value[key] = on ? '1' : '0' },
})

const errorFor = (key: string) => {
  const match = Object.entries(errors.value).find(([k]) => k.includes(key))

  return match?.[1]?.[0]
}

onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate class="mb-4" />

    <VCard
      v-for="group in groups"
      :key="group.group"
      :title="group.group_label"
      class="mb-6"
    >
      <VCardText>
        <div
          v-for="item in group.items"
          :key="item.key"
          class="mb-4"
        >
          <template v-if="item.type === 'boolean'">
            <VSwitch
              :model-value="asSwitch(item.key).value"
              :label="item.label"
              :error-messages="errorFor(item.key)"
              @update:model-value="asSwitch(item.key).value = $event as boolean"
            />

            <div class="d-flex align-center gap-2 mt-1">
              <VIcon
                :icon="drafts[item.key] === '1' ? 'tabler-circle-check' : 'tabler-circle-x'"
                :color="drafts[item.key] === '1' ? 'success' : 'disabled'"
                size="16"
              />
              <span class="text-caption text-disabled">
                {{ drafts[item.key] === '1' ? 'مفعّل' : 'مطفأ' }}
                <template v-if="item.hint"> — {{ item.hint }}</template>
              </span>
            </div>
          </template>

          <template v-else>
            <AppTextField
              v-model="drafts[item.key]"
              :label="item.label"
              :type="item.is_secret && !revealed[item.key] ? 'password' : 'text'"
              :placeholder="item.is_set ? '' : 'غير مضبوط'"
              :error-messages="errorFor(item.key)"
              :append-inner-icon="item.is_secret ? (revealed[item.key] ? 'tabler-eye-off' : 'tabler-eye') : undefined"
              @click:append-inner="revealed[item.key] = !revealed[item.key]"
            />

            <VAlert
              v-if="!item.is_readable"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-2"
            >
              القيمة المخزّنة غير قابلة للقراءة — تغيّر مفتاح التشفير <code>APP_KEY</code>. أعد إدخالها واحفظ.
            </VAlert>

            <div class="d-flex align-center gap-2 mt-1">
              <VIcon
                :icon="item.is_set ? 'tabler-circle-check' : 'tabler-circle-x'"
                :color="item.is_set ? 'success' : 'disabled'"
                size="16"
              />
              <span class="text-caption text-disabled">
                {{ item.is_set ? 'مضبوط' : 'غير مضبوط' }}
                <template v-if="item.is_secret">
                  — يُخزَّن مشفّراً، وحفظ القيمة المقنّعة لا يمسح المفتاح
                </template>
              </span>
            </div>
          </template>
        </div>
      </VCardText>
    </VCard>

    <div class="d-flex gap-3">
      <VBtn :loading="saving" @click="save">حفظ الإعدادات</VBtn>
      <VBtn color="secondary" variant="tonal" :disabled="saving" @click="load">استرجاع</VBtn>
    </div>
  </div>
</template>
