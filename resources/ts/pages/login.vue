<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import type { Rule } from '@/plugins/casl/ability'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const authThemeImg = useGenerateImageVariant(authV2LoginIllustrationLight, authV2LoginIllustrationDark, authV2LoginIllustrationBorderedLight, authV2LoginIllustrationBorderedDark, true)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

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
      router.replace(route.query.to ? String(route.query.to) : '/')
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
  <div class="auth-logo d-flex align-center gap-x-3">
    <VNodeRenderer :nodes="themeConfig.app.logo" />
    <h1 class="auth-title">
      {{ themeConfig.app.title }}
    </h1>
  </div>

  <VRow no-gutters class="auth-wrapper bg-surface">
    <VCol md="8" class="d-none d-md-flex">
      <div class="position-relative bg-background w-100 me-0">
        <div class="d-flex align-center justify-center w-100 h-100" style="padding-inline: 6.25rem;">
          <VImg max-width="613" :src="authThemeImg" class="auth-illustration mt-16 mb-2" />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt=""
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard flat :max-width="500" class="mt-12 mt-sm-0 pa-4">
        <VCardText>
          <h4 class="text-h4 mb-1">
            أهلاً بك في {{ themeConfig.app.title }}
          </h4>
          <p class="mb-0 text-body-1">
            سجّل دخولك للوصول إلى لوحة التحكم
          </p>
        </VCardText>

        <VCardText>
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

              <VCol cols="12">
                <p class="text-caption text-disabled text-center mb-0">
                  الدخول للمشرفين فقط. لإنشاء حساب أو استعادة كلمة المرور راجع المدير العام.
                </p>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
