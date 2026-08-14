import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'

// ❗ Logo SVG must be imported with ?raw suffix
import logo from '@images/logo.svg?raw'

import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

export const { themeConfig, layoutConfig } = defineThemeConfig({
  app: {
    title: 'هوام سيرفس',
    logo: h('div', { innerHTML: logo, style: 'line-height:0; color: rgb(var(--v-global-theme-primary))' }),
    contentWidth: ContentWidth.Boxed,
    contentLayoutNav: AppContentLayoutNav.Vertical,
    overlayNavFromBreakpoint: breakpointsVuetifyV3.lg - 1, // 1 for matching with vuetify breakpoint. Docs: https://next.vuetifyjs.com/en/features/display-and-platform/
    // The panel is Arabic only: no switcher, and RTL is the single possible state.
    i18n: {
      enable: false,
      defaultLocale: 'ar',
      langConfig: [
        {
          label: 'العربية',
          i18nLang: 'ar',
          isRTL: true,
        },
      ],
    },
    theme: 'system',
    skin: Skins.Default,
    iconRenderer: VIcon,
  },
  navbar: {
    type: NavbarType.Sticky,
    navbarBlur: true,
  },
  footer: { type: FooterType.Static },
  verticalNav: {
    isVerticalNavCollapsed: false,
    defaultNavItemIconProps: { icon: 'tabler-circle' },
    isVerticalNavSemiDark: false,
  },
  horizontalNav: {
    type: 'sticky',
    transition: 'slide-y-reverse-transition',
    popoverOffset: 6,
  },

  /*
  // ℹ️  In below Icons section, you can specify icon for each component. Also you can use other props of v-icon component like `color` and `size` for each icon.
  // Such as: chevronDown: { icon: 'tabler-chevron-down', color:'primary', size: '24' },
  */
  icons: {
    chevronDown: { icon: 'tabler-chevron-down' },
    chevronRight: { icon: 'tabler-chevron-right', size: 20 },
    close: { icon: 'tabler-x', size: 20 },
    verticalNavPinned: { icon: 'tabler-circle-dot', size: 20 },
    verticalNavUnPinned: { icon: 'tabler-circle', size: 20 },
    sectionTitlePlaceholder: { icon: 'tabler-minus' },
  },

  /*
  // ℹ️  Every AppDataTableServer in the app reads from here — change a label,
  // an icon or the page sizes once and every table follows.
  */
  // One clock for the whole panel: 24-hour, Latin digits, Arabic ordering —
  // so a timestamp never reads differently from the visit window beside it.
  datetime: {
    locale: 'ar-IQ-u-nu-latn',
    hour12: false,
  },

  table: {
    serial: {
      enabled: true,
      title: '#',
      width: 72,
      /** Column the serial header sorts by, since the number itself is derived. */
      sortKey: 'id',
    },
    search: {
      placeholder: 'بحث',
      debounce: 350,
      width: '15rem',
    },
    icons: {
      search: 'tabler-search',
      filter: 'tabler-filter',
      columns: 'tabler-columns-3',
      create: 'tabler-plus',
      empty: 'tabler-database-off',
      rowActions: 'tabler-dots-vertical',
    },
    labels: {
      empty: 'لا توجد بيانات',
      loadFailed: 'تعذّر جلب البيانات',
      columnsMenu: 'الأعمدة الظاهرة',
      filtersLabel: 'المصفّيات:',
      clearFilters: 'مسح الكل',
      filterAll: 'الكل',
    },
    pagination: {
      perPageOptions: [10, 25, 50, 100],
      defaultPerPage: 10,
      emptyLabel: 'لا توجد نتائج',
      rangeLabel: (from: number, to: number, total: number) => `${from}-${to} من ${total}`,
      labels: {
        perPage: 'صفوف لكل صفحة',
        first: 'الصفحة الأولى',
        previous: 'السابق',
        next: 'التالي',
        last: 'الصفحة الأخيرة',
      },
      /** Chevrons point the RTL way: "next" walks toward the start of the line. */
      icons: {
        first: 'tabler-chevrons-right',
        previous: 'tabler-chevron-right',
        next: 'tabler-chevron-left',
        last: 'tabler-chevrons-left',
      },
    },
  },

  /*
  // ℹ️  One order status, one look — the dashboard cards, the list chips and the
  //     order timeline all read from here so they can never drift apart.
  */
  orderStatus: {
    fallback: { color: 'secondary', icon: 'tabler-circle' },
    byStatus: {
      pending: { color: 'warning', icon: 'tabler-clock' },
      confirmed: { color: 'info', icon: 'tabler-circle-check' },
      assigned: { color: 'primary', icon: 'tabler-user-check' },
      inspected: { color: 'secondary', icon: 'tabler-zoom-check' },
      completed: { color: 'success', icon: 'tabler-checks' },
      cancelled: { color: 'error', icon: 'tabler-ban' },
    },
  },

  charts: {
    fontFamily: 'Tajawal',
    /** ApexCharts re-appends `px` to legend sizes, so these must stay unitless-safe. */
    labelSize: '13px',
    valueSize: '24px',
    height: {
      area: 260,
      donut: 300,
      bar: 280,
    },
  },
})
