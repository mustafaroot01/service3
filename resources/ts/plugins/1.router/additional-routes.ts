import type { RouteRecordRaw } from 'vue-router'

export const redirects: RouteRecordRaw[] = [
  { path: '/', name: 'index', redirect: () => ({ name: 'admin-dashboard' }) },
]

export const routes: RouteRecordRaw[] = []
