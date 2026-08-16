<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import type { Rule } from '@/plugins/casl/ability'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()

const refVForm = ref<VForm>()
const isPasswordVisible = ref(false)
const isLoading = ref(false)

const credentials = ref({ email: '', password: '' })
const errors = ref<Record<string, string | undefined>>({})
const loginError = ref<string | null>(null)

/**
 * `?to=` reaches us straight from the address bar. Anything but a single-slash
 * path is someone else's origin — `//evil.com` resolves as a location the
 * browser then refuses to push, which strands the reader on a blank screen.
 */
const destination = () => {
  const to = String(route.query.to ?? '')

  return /^\/(?!\/)/.test(to) ? to : '/'
}

const login = async () => {
  isLoading.value = true
  loginError.value = null
  errors.value = {}

  try {
    const res = await $api('/admin/auth/login', {
      method: 'POST',
      body: { email: credentials.value.email, password: credentials.value.password },
    })

    const { admin, token } = res.data

    useCookie('userData').value = admin
    useCookie('accessToken').value = token

    // CASL still gates the layout, so grant everything until roles are wired up.
    const abilityRules: Rule[] = [{ action: 'manage', subject: 'all' }]

    useCookie<Rule[]>('userAbilityRules').value = abilityRules
    ability.update(abilityRules)

    await nextTick(() => {
      router.replace(destination())
    })
  }
  catch (err: any) {
    const body = err?.data

    if (body?.errors && Object.keys(body.errors).length)
      errors.value = Object.fromEntries(Object.entries(body.errors).map(([k, v]) => [k, (v as string[])[0]]))
    else
      loginError.value = body?.message ?? 'تعذّر الاتصال بالخادم، تأكد من الشبكة'
  }
  finally {
    isLoading.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid }) => {
    if (valid)
      login()
  })
}
</script>

<template>
  <div class="d-flex align-center justify-center pa-4" style="min-block-size: 100dvh;">
    <VCard flat :max-width="420" width="100%" class="pa-sm-6 pa-2">
      <VCardText>
        <h4 class="text-h4 text-center mb-6">
          تسجيل الدخول
        </h4>
          <VForm ref="refVForm" @submit.prevent="onSubmit">
            <VAlert
              v-if="loginError"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-5"
            >
              {{ loginError }}
            </VAlert>

            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.email"
                  label="البريد الإلكتروني"
                  placeholder="admin@hoame.iq"
                  type="email"
                  dir="ltr"
                  autofocus
                  autocomplete="username"
                  :rules="[requiredValidator, emailValidator]"
                  :error-messages="errors.email"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="credentials.password"
                  label="كلمة المرور"
                  placeholder="············"
                  dir="ltr"
                  autocomplete="current-password"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <VCol cols="12">
                <VBtn
                  block
                  size="large"
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  تسجيل الدخول
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
