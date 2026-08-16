import { useToast } from '@/composables/useToast'

/**
 * Runs a one-off action on a table row, tracks which row is busy, and shows
 * whatever message the API returned — success or failure.
 */
export function useRowAction(refresh: () => Promise<unknown>) {
  const busyRow = ref<number | null>(null)
  const toast = useToast()

  async function run(id: number, request: () => Promise<{ message?: string } | any>) {
    busyRow.value = id

    try {
      const res = await request()

      toast.success(res?.message ?? '')
      await refresh()

      return true
    }
    catch (e: any) {
      // A 422 carries the useful message under errors.field[0] while message
      // stays the generic "البيانات المدخلة غير صحيحة" — show the specific one.
      const body = e?.data
      const firstError = body?.errors ? (Object.values(body.errors)[0] as string[] | undefined)?.[0] : undefined

      toast.error(firstError ?? body?.message ?? 'تعذّر تنفيذ العملية')

      return false
    }
    finally {
      busyRow.value = null
    }
  }

  return { busyRow, run }
}
