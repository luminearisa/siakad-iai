<script setup lang="ts" generic="T extends Record<string, any>">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next'
import Skeleton from '../ui/Skeleton.vue'
import EmptyState from '../ui/EmptyState.vue'

export interface Column<RowType = any> {
  key: string
  label: string
  sortable?: boolean
  align?: 'left' | 'center' | 'right'
  width?: string
  formatter?: (val: any, row: RowType) => string | number
}

interface Props {
  columns: Column<T>[]
  rows: T[]
  loading?: boolean
  sortBy?: string
  sortDirection?: 'asc' | 'desc'
  rowKey?: string
  emptyTitle?: string
  emptyDescription?: string
}

withDefaults(defineProps<Props>(), {
  loading: false,
  rowKey: 'id',
  emptyTitle: 'Tidak ada data',
  emptyDescription: 'Belum ada data untuk kriteria yang dipilih.',
})

const emit = defineEmits<{
  (e: 'sort', columnKey: string): void
}>()

function handleSort(col: Column<T>) {
  if (col.sortable) {
    emit('sort', col.key)
  }
}

function getAlignmentClass(align?: 'left' | 'center' | 'right') {
  switch (align) {
    case 'center': return 'text-center justify-center'
    case 'right': return 'text-right justify-end'
    case 'left':
    default:
      return 'text-left justify-start'
  }
}
</script>

<template>
  <div class="w-full bg-white border border-slate-200 rounded-lg shadow-subtle overflow-hidden">
    <!-- Desktop / Tablet Table View -->
    <div class="overflow-x-auto">
      <table class="w-full text-xs text-slate-700 text-left border-collapse">
        <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 select-none">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :style="col.width ? { width: col.width } : {}"
              :class="[
                'py-2.5 px-3 whitespace-nowrap',
                col.sortable ? 'cursor-pointer hover:bg-slate-100/80 transition-colors' : '',
                getAlignmentClass(col.align),
              ]"
              @click="handleSort(col)"
            >
              <div :class="['inline-flex items-center gap-1.5', getAlignmentClass(col.align)]">
                <span>{{ col.label }}</span>
                <span v-if="col.sortable" class="text-slate-400">
                  <ArrowUp v-if="sortBy === col.key && sortDirection === 'asc'" class="w-3 h-3 text-brand-900" />
                  <ArrowDown v-else-if="sortBy === col.key && sortDirection === 'desc'" class="w-3 h-3 text-brand-900" />
                  <ArrowUpDown v-else class="w-3 h-3 opacity-50" />
                </span>
              </div>
            </th>
            <th v-if="$slots.actions" class="py-2.5 px-3 text-right whitespace-nowrap w-24">
              Aksi
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          <!-- Loading Skeletons -->
          <tr v-if="loading" v-for="i in 5" :key="'skel-' + i" class="animate-pulse">
            <td v-for="col in columns" :key="'skel-col-' + col.key" class="py-3 px-3">
              <Skeleton height="0.875rem" />
            </td>
            <td v-if="$slots.actions" class="py-3 px-3">
              <Skeleton height="0.875rem" width="3rem" />
            </td>
          </tr>

          <!-- Data Rows -->
          <template v-else-if="rows && rows.length > 0">
            <tr
              v-for="(row, idx) in rows"
              :key="row[rowKey] || idx"
              class="hover:bg-slate-50/70 transition-colors duration-100 group"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                :class="['py-2.5 px-3 align-middle', getAlignmentClass(col.align)]"
              >
                <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]">
                  <span v-if="col.formatter">
                    {{ col.formatter(row[col.key], row) }}
                  </span>
                  <span v-else>
                    {{ row[col.key] !== null && row[col.key] !== undefined && row[col.key] !== '' ? row[col.key] : '-' }}
                  </span>
                </slot>
              </td>

              <td v-if="$slots.actions" class="py-2.5 px-3 text-right align-middle whitespace-nowrap">
                <slot name="actions" :row="row" />
              </td>
            </tr>
          </template>

          <!-- Empty State -->
          <tr v-else>
            <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="py-8">
              <EmptyState :title="emptyTitle" :description="emptyDescription">
                <template v-if="$slots.emptyAction" #action>
                  <slot name="emptyAction" />
                </template>
              </EmptyState>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination slot -->
    <slot name="pagination" />
  </div>
</template>
