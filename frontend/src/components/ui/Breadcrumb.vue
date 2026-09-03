<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'

export interface BreadcrumbItem {
  label: string
  to?: string
}

interface Props {
  items: BreadcrumbItem[]
}

defineProps<Props>()
</script>

<template>
  <nav class="flex items-center space-x-1.5 text-xs text-slate-500 mb-2 overflow-x-auto whitespace-nowrap py-1">
    <template v-for="(item, index) in items" :key="index">
      <router-link
        v-if="item.to && index < items.length - 1"
        :to="item.to"
        class="hover:text-slate-900 transition-colors duration-150"
      >
        {{ item.label }}
      </router-link>
      <span v-else :class="index === items.length - 1 ? 'font-medium text-slate-800' : ''">
        {{ item.label }}
      </span>

      <ChevronRight v-if="index < items.length - 1" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
    </template>
  </nav>
</template>
