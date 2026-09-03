<script setup lang="ts">
import { Users } from 'lucide-vue-next'

interface Props {
  capacity?: number | null
}

withDefaults(defineProps<Props>(), {
  capacity: 0,
})
</script>

<template>
  <div class="inline-flex items-center gap-1 font-mono text-2xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
    <Users class="w-3 h-3 text-slate-400" />
    <span>{{ capacity || 0 }} Kursi</span>
  </div>
</template>
