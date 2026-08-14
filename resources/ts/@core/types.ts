import type { LiteralUnion, ValueOf } from 'type-fest'
import type { Skins } from './enums'
import type { I18nLanguage, LayoutConfig } from '@layouts/types'

export interface DateTimeThemeConfig {
  locale: string
  hour12: boolean
}

export interface TableThemeConfig {
  serial: {
    enabled: boolean
    title: string
    width: number
    sortKey: string
  }
  search: {
    placeholder: string
    debounce: number
    width: string
  }
  icons: {
    search: string
    filter: string
    columns: string
    create: string
    empty: string
    rowActions: string
  }
  labels: {
    empty: string
    loadFailed: string
    columnsMenu: string
    filtersLabel: string
    clearFilters: string
    filterAll: string
  }
  pagination: {
    perPageOptions: number[]
    defaultPerPage: number
    emptyLabel: string
    rangeLabel: (from: number, to: number, total: number) => string
    labels: {
      perPage: string
      first: string
      previous: string
      next: string
      last: string
    }
    icons: {
      first: string
      previous: string
      next: string
      last: string
    }
  }
}

export interface StatusVisual {
  color: string
  icon: string
}

export interface OrderStatusThemeConfig {
  fallback: StatusVisual
  byStatus: Record<string, StatusVisual>
}

export interface ChartThemeConfig {
  fontFamily: string
  labelSize: string
  valueSize: string
  height: {
    area: number
    donut: number
    bar: number
  }
}

interface ExplicitThemeConfig {
  app: {
    i18n: {
      defaultLocale: string
      langConfig: I18nLanguage[]
    }
    theme: LiteralUnion<'light' | 'dark' | 'system', string>
    skin: ValueOf<typeof Skins>
  }
  verticalNav: {
    isVerticalNavSemiDark: boolean
  }
  datetime: DateTimeThemeConfig
  table: TableThemeConfig
  orderStatus: OrderStatusThemeConfig
  charts: ChartThemeConfig
}

export type UserThemeConfig = LayoutConfig & ExplicitThemeConfig

// SECTION Custom Input
export interface CustomInputContent {
  title: string
  desc?: string
  value: string
  subtitle?: string
  icon?: any
  images?: string
}

export interface GridColumn {
  cols?: string
  sm?: string
  md?: string
  lg?: string
  xl?: string
  xxl?: string
}

// Data table
export interface SortItem { key: string; order?: boolean | 'asc' | 'desc' }

export interface Options {
  page: number
  itemsPerPage: number
  sortBy: readonly SortItem[]
  groupBy: readonly SortItem[]
  search: string | undefined
}
