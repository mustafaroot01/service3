import hoame from '../vertical/hoame'
import type { HorizontalNavItems } from '@layouts/types'

// The panel ships one menu; the horizontal layout mirrors the vertical one
// instead of keeping a second list that would drift out of sync.
export default [...hoame] as HorizontalNavItems
