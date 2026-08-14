export type ToastColor = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  text: string
  color: ToastColor
}

const queue = ref<Toast[]>([])
let nextId = 0

/**
 * Messages come from the API envelope, so the wording lives on the server and
 * the interface never invents its own copy.
 */
export function useToast() {
  const push = (text: string, color: ToastColor = 'success') => {
    if (!text)
      return

    queue.value = [...queue.value, { id: nextId++, text, color }]
  }

  const dismiss = (id: number) => {
    queue.value = queue.value.filter(t => t.id !== id)
  }

  return {
    toasts: queue,
    dismiss,
    success: (text: string) => push(text, 'success'),
    error: (text: string) => push(text, 'error'),
    warning: (text: string) => push(text, 'warning'),
    info: (text: string) => push(text, 'info'),
  }
}
