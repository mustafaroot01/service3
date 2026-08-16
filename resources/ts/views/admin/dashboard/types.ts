export interface OrderStatusCount {
  status: string
  label: string
  total: number
}

export interface OrdersTrendPoint {
  date: string
  total: number
}

export interface TopService {
  id: number
  name: string
  orders_count: number
}

export interface GovernorateOrders {
  id: number
  name: string
  total: number
}

export interface RecentOrder {
  id: number
  order_number: string
  status: string
  status_label: string
  user_name: string | null
  service_name: string | null
  created_at: string | null
}

export interface DashboardSummary {
  orders: {
    total: number
    open: number
    unassigned: number
    today: number
    this_month: number
    by_status: OrderStatusCount[]
    trend: OrdersTrendPoint[]
  }
  people: {
    users_total: number
    users_active: number
    technicians_total: number
    technicians_active: number
    technicians_pending: number
  }
  catalog: {
    categories: number
    categories_active: number
    services: number
    services_active: number
  }
  top_services: TopService[]
  orders_per_governorate: GovernorateOrders[]
  recent_orders: RecentOrder[]
}
