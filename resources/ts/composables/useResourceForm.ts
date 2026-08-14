import { useToast } from '@/composables/useToast'

export interface ResourceFormOptions<T extends Record<string, any>> {
  endpoint: string
  /** A fresh, empty record — also used to reset the drawer between opens. */
  blank: () => T
  /** Send as multipart when the payload carries files. */
  multipart?: boolean
  onSaved?: () => void | Promise<void>
}

export function useResourceForm<T extends Record<string, any>>(options: ResourceFormOptions<T>) {
  const { endpoint, blank, multipart = false, onSaved } = options
  const toast = useToast()

  const isOpen = ref(false)
  const isSaving = ref(false)
  const editingId = ref<number | null>(null)
  const form = ref<T>(blank()) as Ref<T>
  const errors = ref<Record<string, string[]>>({})
  const generalError = ref<string | null>(null)

  const isEditing = computed(() => editingId.value !== null)
  const title = computed(() => isEditing.value ? 'تعديل' : 'إضافة')

  function reset() {
    form.value = blank()
    editingId.value = null
    errors.value = {}
    generalError.value = null
  }

  function openCreate() {
    reset()
    isOpen.value = true
  }

  function openEdit(record: T & { id: number }) {
    reset()
    editingId.value = record.id
    form.value = { ...blank(), ...record }
    isOpen.value = true
  }

  /**
   * Laravel cannot read multipart bodies on PUT, so an update is POSTed with
   * the method spoofed — the same trick the framework's own forms use.
   */
  function buildBody() {
    if (!multipart)
      return form.value

    const body = new FormData()

    for (const [key, value] of Object.entries(form.value)) {
      if (value === null || value === undefined || value === '')
        continue

      if (value instanceof File)
        body.append(key, value)
      else if (Array.isArray(value))
        value.forEach(v => body.append(`${key}[]`, String(v)))
      else if (typeof value === 'boolean')
        body.append(key, value ? '1' : '0')
      else
        body.append(key, String(value))
    }

    if (isEditing.value)
      body.append('_method', 'PUT')

    return body
  }

  async function save() {
    isSaving.value = true
    errors.value = {}
    generalError.value = null

    const url = isEditing.value ? `${endpoint}/${editingId.value}` : endpoint
    const method = isEditing.value && !multipart ? 'PUT' : 'POST'

    try {
      const res = await $api<{ message?: string }>(url, { method, body: buildBody() })

      toast.success(res?.message ?? '')
      isOpen.value = false
      reset()
      await onSaved?.()

      return true
    }
    catch (e: any) {
      const body = e?.data

      errors.value = body?.errors ?? {}

      if (!Object.keys(errors.value).length) {
        const message = body?.message ?? 'تعذّر الحفظ، حاول مرة أخرى'

        generalError.value = message
        toast.error(message)
      }

      return false
    }
    finally {
      isSaving.value = false
    }
  }

  async function destroy(id: number) {
    try {
      const res = await $api<{ message?: string }>(`${endpoint}/${id}`, { method: 'DELETE' })

      toast.success(res?.message ?? '')
      await onSaved?.()

      return true
    }
    catch (e: any) {
      const message = e?.data?.message ?? 'تعذّر الحذف'

      generalError.value = message
      toast.error(message)

      return false
    }
  }

  const fieldError = (field: string) => errors.value[field]?.[0]

  return {
    isOpen,
    isSaving,
    isEditing,
    editingId,
    title,
    form,
    errors,
    generalError,
    fieldError,
    openCreate,
    openEdit,
    reset,
    save,
    destroy,
  }
}
