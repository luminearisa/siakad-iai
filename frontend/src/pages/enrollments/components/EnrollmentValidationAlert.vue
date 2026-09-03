<script setup lang="ts">
import { AlertCircle, X } from 'lucide-vue-next'

interface Props {
  errors: string[] | Record<string, string[]> | null
  title?: string
  dismissible?: boolean
}

withDefaults(defineProps<Props>(), {
  title: 'Validasi Akademik KRS Tidak Terpenuhi',
  dismissible: false,
})

const emit = defineEmits<{
  (e: 'dismiss'): void
}>()

function formatErrors(errs: string[] | Record<string, string[]> | null): string[] {
  if (!errs) return []
  if (Array.isArray(errs)) return errs
  const list: string[] = []
  Object.values(errs).forEach((val) => {
    if (Array.isArray(val)) {
      list.push(...val)
    } else if (typeof val === 'string') {
      list.push(val)
    }
  })
  return list
}
</script>

<template>
  <div
    v-if="errors && formatErrors(errors).length > 0"
    class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-subtle text-rose-900 animate-fadeIn"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-start gap-2.5">
        <AlertCircle class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <h4 class="text-xs font-bold uppercase tracking-wider text-rose-900">
            {{ title }}
          </h4>
          <p class="text-xs text-rose-800 leading-relaxed">
            Pengambilan kelas perkuliahan tidak dapat diproses karena kendala validasi akademik berikut:
          </p>

          <ul class="list-disc list-inside space-y-1 pt-1.5 text-xs text-rose-950 font-medium">
            <li v-for="(msg, idx) in formatErrors(errors)" :key="idx">
              {{ msg }}
            </li>
          </ul>
        </div>
      </div>

      <button
        v-if="dismissible"
        type="button"
        class="text-rose-400 hover:text-rose-700 transition-colors p-1"
        @click="emit('dismiss')"
      >
        <X class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
