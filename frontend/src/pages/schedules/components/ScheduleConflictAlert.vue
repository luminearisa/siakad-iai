<script setup lang="ts">
import { AlertTriangle, X } from 'lucide-vue-next'

interface Props {
  conflicts: string[] | Record<string, string[]> | null
  title?: string
  dismissible?: boolean
}

withDefaults(defineProps<Props>(), {
  title: 'Peringatan Konflik Jadwal Perkuliahan',
  dismissible: false,
})

const emit = defineEmits<{
  (e: 'dismiss'): void
}>()

function formatConflictList(c: string[] | Record<string, string[]> | null): string[] {
  if (!c) return []
  if (Array.isArray(c)) return c
  const res: string[] = []
  Object.values(c).forEach((val) => {
    if (Array.isArray(val)) {
      res.push(...val)
    } else if (typeof val === 'string') {
      res.push(val)
    }
  })
  return res
}
</script>

<template>
  <div
    v-if="conflicts && formatConflictList(conflicts).length > 0"
    class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg shadow-subtle text-amber-900 animate-fadeIn"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-start gap-2.5">
        <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900">
            {{ title }}
          </h4>
          <p class="text-xs text-amber-800 leading-relaxed">
            Sistem mendeteksi bentrok jadwal pada ruangan, dosen pengampu, atau kelas perkuliahan berikut:
          </p>

          <ul class="list-disc list-inside space-y-1 pt-1.5 text-xs text-amber-950 font-medium">
            <li v-for="(msg, idx) in formatConflictList(conflicts)" :key="idx">
              {{ msg }}
            </li>
          </ul>
        </div>
      </div>

      <button
        v-if="dismissible"
        type="button"
        class="text-amber-500 hover:text-amber-800 transition-colors p-1"
        @click="emit('dismiss')"
      >
        <X class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
