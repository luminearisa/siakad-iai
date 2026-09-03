<script setup lang="ts">
import { Inbox } from 'lucide-vue-next'

interface Props {
  title?: string
  description?: string
  icon?: unknown
}

withDefaults(defineProps<Props>(), {
  title: 'Tidak ada data',
  description: 'Belum ada data yang tersedia untuk ditampilkan.',
})
</script>

<template>
  <div class="flex flex-col items-center justify-center p-8 sm:p-12 text-center">
    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3 border border-slate-200">
      <component :is="icon || Inbox" class="w-6 h-6 stroke-[1.5]" />
    </div>

    <h4 class="text-sm font-semibold text-slate-800 mb-1">
      {{ title }}
    </h4>

    <p class="text-xs text-slate-500 max-w-sm mb-4 leading-relaxed">
      {{ description }}
    </p>

    <div v-if="$slots.action" class="mt-1">
      <slot name="action" />
    </div>
  </div>
</template>
