export default [
  { title: 'الرئيسية', icon: { icon: 'tabler-smart-home', color: 'primary' }, to: 'admin-dashboard' },

  { heading: 'المواقع' },
  { title: 'المحافظات', icon: { icon: 'tabler-map-2', color: 'info' }, to: 'admin-governorates' },
  { title: 'الأقضية', icon: { icon: 'tabler-map-pin', color: 'success' }, to: 'admin-districts' },

  { heading: 'الكتالوج' },
  { title: 'الأقسام', icon: { icon: 'tabler-category-2', color: 'warning' }, to: 'admin-categories' },
  { title: 'الخدمات', icon: { icon: 'tabler-tool', color: 'error' }, to: 'admin-services' },
  { title: 'الاختصاصات', icon: { icon: 'tabler-certificate', color: 'primary' }, to: 'admin-specializations' },

  { heading: 'المحتوى' },
  { title: 'السلايدرات', icon: { icon: 'tabler-slideshow', color: 'info' }, to: 'admin-sliders' },
  { title: 'المدوّنة', icon: { icon: 'tabler-article', color: 'success' }, to: 'admin-blog' },
  { title: 'الصفحات القانونية', icon: { icon: 'tabler-file-text', color: 'warning' }, to: 'admin-legal-pages' },

  { heading: 'الأشخاص' },
  { title: 'الفنيون', icon: { icon: 'tabler-users-group', color: 'success' }, to: 'admin-technicians' },
  { title: 'استمارات الفنيين', icon: { icon: 'tabler-file-text-ai', color: 'warning' }, to: 'admin-technician-applications' },
  { title: 'الزبائن', icon: { icon: 'tabler-users', color: 'primary' }, to: 'admin-users' },

  { heading: 'العمليات' },
  { title: 'الطلبات', icon: { icon: 'tabler-clipboard-list', color: 'error' }, to: 'admin-orders' },

  { heading: 'النظام' },
  { title: 'المشرفون', icon: { icon: 'tabler-user-shield', color: 'primary' }, to: 'admin-admins' },
  { title: 'الأدوار', icon: { icon: 'tabler-shield-lock', color: 'error' }, to: 'admin-roles' },
  { title: 'الصلاحيات', icon: { icon: 'tabler-key', color: 'warning' }, to: 'admin-permissions' },
  { title: 'الإعدادات', icon: { icon: 'tabler-settings', color: 'secondary' }, to: 'admin-settings' },
]
