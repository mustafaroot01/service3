import { themeConfig } from '@themeConfig'

export const paginationMeta = <T extends { page: number; itemsPerPage: number }>(options: T, total: number) => {
  const { emptyLabel, rangeLabel } = themeConfig.table.pagination

  if (total === 0)
    return emptyLabel

  const start = (options.page - 1) * options.itemsPerPage + 1
  const end = Math.min(options.page * options.itemsPerPage, total)

  return rangeLabel(start, end, total)
}
