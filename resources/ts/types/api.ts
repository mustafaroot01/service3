export interface ApiMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  meta?: ApiMeta
  errors?: Record<string, string[]>
}

export type TableFilterType = 'select' | 'boolean' | 'text'

export interface TableFilterOption {
  title: string
  value: string | number | boolean
}

export interface TableFilter {
  type: TableFilterType
  options?: TableFilterOption[]
  placeholder?: string
}

export interface TableHeader {
  title: string
  key: string
  sortable?: boolean
  align?: 'start' | 'center' | 'end'
  width?: string | number
  nowrap?: boolean
  filter?: TableFilter
  filterKey?: string
}
