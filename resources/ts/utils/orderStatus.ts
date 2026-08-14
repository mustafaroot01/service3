import { themeConfig } from '@themeConfig'
import type { StatusVisual } from '@core/types'

export const orderStatusVisual = (status: string): StatusVisual =>
  themeConfig.orderStatus.byStatus[status] ?? themeConfig.orderStatus.fallback
