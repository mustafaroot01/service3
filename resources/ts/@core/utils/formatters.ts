import { themeConfig } from '@themeConfig'
import { isToday } from './helpers'

export const avatarText = (value: string) => {
  if (!value)
    return ''
  const nameArray = value.split(' ')

  return nameArray.map(word => word.charAt(0).toUpperCase()).join('')
}

// TODO: Try to implement this: https://twitter.com/fireship_dev/status/1565424801216311297
export const kFormatter = (num: number) => {
  const regex = /\B(?=(\d{3})+(?!\d))/g

  return Math.abs(num) > 9999 ? `${Math.sign(num) * +((Math.abs(num) / 1000).toFixed(1))}k` : Math.abs(num).toFixed(0).replace(regex, ',')
}

/**
 * Every date and clock in the panel goes through here, so the format lives in
 * themeConfig.datetime rather than in each call site.
 */
const intlFormat = (value: string | null | undefined, formatting: Intl.DateTimeFormatOptions) => {
  if (!value)
    return '—'

  const date = new Date(value)

  if (Number.isNaN(date.getTime()))
    return '—'

  return new Intl.DateTimeFormat(themeConfig.datetime.locale, {
    hour12: themeConfig.datetime.hour12,
    ...formatting,
  }).format(date)
}

/**
 * Format and return date in Humanize format
 * Intl docs: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat/format
 * Intl Constructor: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat/DateTimeFormat
 * @param {string} value date to format
 * @param {Intl.DateTimeFormatOptions} formatting Intl object to format with
 */
export const formatDate = (
  value: string | null | undefined,
  formatting: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
) => intlFormat(value, formatting)

/** Date and clock together, e.g. 14/08/2026، 23:30 */
export const formatDateTime = (
  value: string | null | undefined,
  formatting: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
) => intlFormat(value, formatting)

/** The Arabic day-part, matching the backend App\Support\VisitWindow. */
const arabicPeriod = (hour: number) =>
  hour < 12 ? 'صباحاً' : hour < 17 ? 'ظهراً' : hour < 20 ? 'مساءً' : 'ليلاً'

/** Clock the friendly Arabic way, e.g. 3:55 ظهراً — reads like the visit window. */
export const formatClock = (value: string | null | undefined) => {
  if (!value)
    return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  const h = date.getHours()

  return `${h % 12 || 12}:${String(date.getMinutes()).padStart(2, '0')} ${arabicPeriod(h)}`
}

/** Date then the friendly Arabic clock, e.g. 16/08/2026 · 3:55 ظهراً */
export const formatDateTimeArabic = (value: string | null | undefined) => {
  if (!value)
    return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  return `${formatDate(value)} · ${formatClock(value)}`
}

export const formatTime = (
  value: string | null | undefined,
  formatting: Intl.DateTimeFormatOptions = { timeStyle: 'short' },
) => intlFormat(value, formatting)

/**
 * Return short human friendly month representation of date
 * Can also convert date to only time if date is of today (Better UX)
 * @param {string} value date to format
 * @param {boolean} toTimeForCurrentDay Shall convert to time if day is today/current
 */
export const formatDateToMonthShort = (value: string, toTimeForCurrentDay = true) => {
  const date = new Date(value)
  let formatting: Record<string, string> = { month: 'short', day: 'numeric' }

  if (toTimeForCurrentDay && isToday(date))
    formatting = { hour: 'numeric', minute: 'numeric' }

  return new Intl.DateTimeFormat(themeConfig.datetime.locale, formatting).format(new Date(value))
}

export const prefixWithPlus = (value: number) => value > 0 ? `+${value}` : value
