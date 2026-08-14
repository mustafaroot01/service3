<script setup lang="ts">
import type { ServerTable } from '@/composables/useServerTable'
import { themeConfig } from '@themeConfig'
import type { TableHeader } from '@/types/api'

interface Props {
  title: string
  headers: TableHeader[]
  table: ServerTable<any>
  /** Label for the primary toolbar button; omit to hide it. */
  createLabel?: string
  searchPlaceholder?: string
  showSerial?: boolean
  itemKey?: string
}

const tableTheme = themeConfig.table
const { icons, labels } = tableTheme
const serialTheme = tableTheme.serial
const searchTheme = tableTheme.search

const props = withDefaults(defineProps<Props>(), {
  searchPlaceholder: themeConfig.table.search.placeholder,
  showSerial: themeConfig.table.serial.enabled,
  itemKey: 'id',
})

const emit = defineEmits<{ (e: 'create'): void }>()

const hiddenColumns = ref<string[]>([])

const filterableHeaders = computed(() => props.headers.filter(h => h.filter))

const visibleHeaders = computed<TableHeader[]>(() => {
  const serial: TableHeader[] = props.showSerial
    ? [{ title: serialTheme.title, key: '__serial', sortable: true, align: 'start', width: serialTheme.width }]
    : []

  return [...serial, ...props.headers.filter(h => !hiddenColumns.value.includes(h.key))]
})

/** A continuous number across pages, so row 1 of page 2 reads 11 not 1. */
const serialFor = (index: number) =>
  (props.table.page.value - 1) * props.table.itemsPerPage.value + index + 1

const filterKeyOf = (header: TableHeader) => header.filterKey ?? header.key

const isFiltered = (header: TableHeader) => {
  const value = props.table.filters.value[filterKeyOf(header)]

  return value !== null && value !== undefined && value !== ''
}

const setFilter = (header: TableHeader, value: any) => {
  props.table.filters.value = {
    ...props.table.filters.value,
    [filterKeyOf(header)]: value,
  }
}

const clearFilter = (header: TableHeader) => setFilter(header, null)

/** Vuetify sorts by the real column; __serial stands in for the id. */
const onUpdateOptions = (options: any) => {
  const sort = options?.sortBy?.[0]

  props.table.updateOptions({
    ...options,
    sortBy: sort?.key === '__serial'
      ? [{ key: serialTheme.sortKey, order: sort.order }]
      : options?.sortBy,
  })
}
</script>

<template>
  <VCard>
    <VCardText class="d-flex flex-wrap align-center gap-4 pb-4">
      <h5 class="text-h5 mb-0">
        {{ title }}
      </h5>

      <VSpacer />

      <VTextField
        v-model="table.search.value"
        :placeholder="searchPlaceholder"
        density="compact"
        variant="outlined"
        hide-details
        clearable
        :prepend-inner-icon="icons.search"
        :style="{ inlineSize: searchTheme.width }"
      />

      <VMenu :close-on-content-click="false">
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            icon
            variant="tonal"
            color="default"
            size="small"
            :aria-label="labels.columnsMenu"
          >
            <VIcon :icon="icons.columns" />
          </VBtn>
        </template>

        <VList density="compact" min-width="200">
          <VListSubheader>{{ labels.columnsMenu }}</VListSubheader>
          <VListItem
            v-for="header in headers"
            :key="header.key"
          >
            <VCheckbox
              :model-value="!hiddenColumns.includes(header.key)"
              :label="header.title"
              density="compact"
              hide-details
              @update:model-value="value => hiddenColumns = value
                ? hiddenColumns.filter(k => k !== header.key)
                : [...hiddenColumns, header.key]"
            />
          </VListItem>
        </VList>
      </VMenu>

      <slot name="toolbar" />

      <VBtn
        v-if="createLabel"
        :prepend-icon="icons.create"
        @click="emit('create')"
      >
        {{ createLabel }}
      </VBtn>
    </VCardText>

    <div
      v-if="table.activeFilterCount.value"
      class="d-flex flex-wrap align-center gap-2 px-6 pb-4"
    >
      <span class="text-body-2 text-disabled">{{ labels.filtersLabel }}</span>

      <VChip
        v-for="header in filterableHeaders.filter(isFiltered)"
        :key="header.key"
        size="small"
        closable
        color="primary"
        variant="tonal"
        @click:close="clearFilter(header)"
      >
        {{ header.title }}
      </VChip>

      <VBtn
        variant="text"
        size="small"
        color="secondary"
        @click="table.resetFilters()"
      >
        {{ labels.clearFilters }}
      </VBtn>
    </div>

    <VDivider />

    <VDataTableServer
      :model-value="[]"
      :headers="visibleHeaders as any"
      :items="table.items.value"
      :items-length="table.total.value"
      :loading="table.loading.value"
      :item-value="itemKey"
      :items-per-page="table.itemsPerPage.value"
      :page="table.page.value"
      hide-default-footer
      class="text-no-wrap"
      @update:options="onUpdateOptions"
    >
      <template
        v-for="header in filterableHeaders"
        :key="`head-${header.key}`"
        #[`header.${header.key}`]="{ column }"
      >
        <div class="d-inline-flex align-center gap-1">
          <span>{{ column.title }}</span>

          <VMenu :close-on-content-click="true">
            <template #activator="{ props: menuProps }">
              <VIcon
                v-bind="menuProps"
                :icon="icons.filter"
                size="16"
                :color="isFiltered(header) ? 'primary' : 'disabled'"
                class="cursor-pointer"
              />
            </template>

            <VList density="compact" min-width="180">
              <VListItem
                :active="!isFiltered(header)"
                @click="clearFilter(header)"
              >
                <VListItemTitle>{{ labels.filterAll }}</VListItemTitle>
              </VListItem>

              <VDivider />

              <VListItem
                v-for="option in header.filter?.options ?? []"
                :key="String(option.value)"
                :active="table.filters.value[filterKeyOf(header)] === option.value"
                @click="setFilter(header, option.value)"
              >
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #item.__serial="{ index }">
        <span class="text-disabled">{{ serialFor(index) }}</span>
      </template>

      <template
        v-for="(_, name) in $slots"
        :key="name"
        #[name]="slotData"
      >
        <slot :name="name" v-bind="slotData ?? {}" />
      </template>

      <template #no-data>
        <div class="d-flex flex-column align-center gap-2 py-10">
          <VIcon :icon="icons.empty" size="36" class="text-disabled" />
          <span class="text-body-1 text-disabled">
            {{ table.error.value ?? labels.empty }}
          </span>
        </div>
      </template>

      <template #bottom>
        <TablePagination
          :page="table.page.value"
          :items-per-page="table.itemsPerPage.value"
          :total-items="table.total.value"
          @update:page="value => table.page.value = value"
          @update:items-per-page="value => table.itemsPerPage.value = value"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
