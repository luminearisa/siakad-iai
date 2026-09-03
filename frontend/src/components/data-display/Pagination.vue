<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface Props {
  currentPage: number
  lastPage: number
  total?: number
  perPage?: number
  from?: number | null
  to?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  total: 0,
  perPage: 20,
  from: null,
  to: null,
})

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

const visiblePages = computed(() => {
  const current = props.currentPage
  const last = props.lastPage
  const delta = 1
  const range: number[] = []

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i)
  }

  if (current - delta > 2) {
    range.unshift(-1) // Ellipsis
  }
  if (current + delta < last - 1) {
    range.push(-2) // Ellipsis
  }

  range.unshift(1)
  if (last > 1) {
    range.push(last)
  }

  return range
})
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-3 py-2.5 bg-white border-t border-slate-200 text-xs text-slate-600">
    <div class="text-slate-500">
      <span v-if="total > 0">
        Menampilkan <strong class="text-slate-700 font-semibold">{{ from || 1 }}</strong> s/d <strong class="text-slate-700 font-semibold">{{ to || total }}</strong> dari <strong class="text-slate-700 font-semibold">{{ total }}</strong> data
      </span>
      <span v-else>
        Tidak ada data
      </span>
    </div>

    <div v-if="lastPage > 1" class="flex items-center space-x-1">
      <button
        type="button"
        :disabled="currentPage <= 1"
        class="inline-flex items-center justify-center p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
        @click="emit('page-change', currentPage - 1)"
      >
        <ChevronLeft class="w-3.5 h-3.5" />
      </button>

      <template v-for="page in visiblePages" :key="page">
        <span v-if="page < 0" class="px-2 text-slate-400">...</span>
        <button
          v-else
          type="button"
          :class="[
            'min-w-[28px] h-7 text-xs font-medium rounded-md transition-colors cursor-pointer',
            currentPage === page
              ? 'bg-brand-900 text-white font-semibold'
              : 'border border-slate-200 text-slate-700 hover:bg-slate-50',
          ]"
          @click="emit('page-change', page)"
        >
          {{ page }}
        </button>
      </template>

      <button
        type="button"
        :disabled="currentPage >= lastPage"
        class="inline-flex items-center justify-center p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
        @click="emit('page-change', currentPage + 1)"
      >
        <ChevronRight class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
