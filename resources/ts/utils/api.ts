import { ofetch } from 'ofetch'
import { router } from '@/plugins/1.router'

/** Drop the session and send the reader back to the login screen. */
const signOut = () => {
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null

  const current = router.currentRoute.value

  if (current.name !== 'login')
    router.replace({ name: 'login', query: { to: current.fullPath } })
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',

  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value

    options.headers.set('Accept', 'application/json')

    if (accessToken)
      options.headers.set('Authorization', `Bearer ${accessToken}`)
  },

  async onResponseError({ response }) {
    // A rejected token is stale by definition; keeping it only produces
    // blank pages, so clear it and ask for a fresh sign-in.
    if (response.status === 401)
      signOut()
  },
})
