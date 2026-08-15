<script setup lang="ts">
interface AdminCookie {
  id: number
  name: string
  email: string
  status_label?: string
  role?: { id: number; label: string } | null
}

const router = useRouter()
const ability = useAbility()

const userData = useCookie<AdminCookie | null>('userData')
const isLoggingOut = ref(false)

const logout = async () => {
  isLoggingOut.value = true

  // The server keeps issuing tokens until one is spent: clearing the cookie
  // alone would leave a working key behind on every machine he ever used.
  try {
    await $api('/admin/auth/logout', { method: 'POST' })
  }
  catch {
    // A rejected token is already dead — the local cleanup below still runs.
  }

  useCookie('accessToken').value = null
  userData.value = null

  await router.push('/login')

  useCookie('userAbilityRules').value = null
  ability.update([])

  isLoggingOut.value = false
}
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar size="38" class="cursor-pointer" color="primary" variant="tonal">
      {{ avatarText(userData.name) }}

      <VMenu
        activator="parent"
        width="250"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <div class="d-flex gap-3 align-center">
              <VAvatar color="primary" variant="tonal">
                {{ avatarText(userData.name) }}
              </VAvatar>

              <div class="overflow-hidden">
                <h6 class="text-h6 font-weight-medium text-truncate">
                  {{ userData.name }}
                </h6>
                <VListItemSubtitle class="text-disabled text-truncate">
                  {{ userData.role?.label ?? 'مشرف' }}
                </VListItemSubtitle>
              </div>
            </div>
          </VListItem>

          <VDivider class="my-2" />

          <VListItem>
            <template #prepend>
              <VIcon icon="tabler-mail" size="22" />
            </template>
            <VListItemTitle class="text-body-2" dir="ltr" style="text-align: start;">
              {{ userData.email }}
            </VListItemTitle>
          </VListItem>

          <VListItem :to="{ name: 'admin-settings' }">
            <template #prepend>
              <VIcon icon="tabler-settings" size="22" />
            </template>
            <VListItemTitle>إعدادات النظام</VListItemTitle>
          </VListItem>

          <VDivider class="my-2" />

          <div class="px-4 py-2">
            <VBtn
              block
              size="small"
              color="error"
              append-icon="tabler-logout"
              :loading="isLoggingOut"
              @click="logout"
            >
              تسجيل الخروج
            </VBtn>
          </div>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
