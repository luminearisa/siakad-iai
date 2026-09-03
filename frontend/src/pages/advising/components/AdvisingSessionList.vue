<script setup lang="ts">
import { Calendar, FileText, Edit2, Trash2, MessageSquare, BookOpen } from 'lucide-vue-next'
import type { AdvisingSession } from '@/types/advising'
import AdvisingSessionStatusBadge from './AdvisingSessionStatusBadge.vue'
import Button from '@/components/ui/Button.vue'
import { formatDate } from '@/utils/format'
import { usePermissions } from '@/composables/usePermissions'

defineProps<{
  sessions: AdvisingSession[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', session: AdvisingSession): void
  (e: 'delete', session: AdvisingSession): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="space-y-3">
    <div v-if="sessions.length === 0 && !loading" class="text-center py-10 bg-white border border-slate-200 rounded-lg p-6 text-xs text-slate-500 space-y-2">
      <MessageSquare class="w-8 h-8 text-slate-300 mx-auto" />
      <p class="font-medium text-slate-700">Belum ada sesi bimbingan akademik</p>
      <p class="text-slate-400">Klik tombol "Buat Sesi Bimbingan" untuk mencatat konsultasi rencana studi atau bimbingan mahasiswa.</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="session in sessions"
        :key="session.id"
        class="bg-white border border-slate-200 rounded-lg p-4 shadow-subtle space-y-3 hover:border-slate-300 transition-colors"
      >
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <div class="p-2 rounded-md bg-brand-50 text-brand-800 shrink-0">
              <BookOpen class="w-4 h-4" />
            </div>
            <div class="min-w-0">
              <h4 class="text-xs font-bold text-slate-900 truncate">
                {{ session.topic || 'Konsultasi Bimbingan Akademik' }}
              </h4>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-2xs text-slate-500">
                <span class="font-medium text-slate-700">
                  {{ session.student?.full_name || 'Mahasiswa' }}
                </span>
                <span v-if="session.student?.student_number" class="font-mono">
                  ({{ session.student.student_number }})
                </span>
                <span class="inline-flex items-center gap-1">
                  <Calendar class="w-3 h-3 text-slate-400" />
                  {{ formatDate(session.session_date) }}
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 self-end sm:self-center">
            <AdvisingSessionStatusBadge :status="session.status" />

            <div class="flex items-center gap-1 border-l border-slate-200 pl-2">
              <Button
                v-if="can('advising.update_session')"
                variant="ghost"
                size="xs"
                title="Edit Sesi Bimbingan"
                class="text-slate-600 hover:text-slate-900"
                @click="emit('edit', session)"
              >
                <Edit2 class="w-3.5 h-3.5" />
              </Button>
              <Button
                v-if="can('advising.update_session')"
                variant="ghost"
                size="xs"
                title="Hapus Sesi Bimbingan"
                class="text-rose-600 hover:bg-rose-50"
                @click="emit('delete', session)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </Button>
            </div>
          </div>
        </div>

        <!-- Notes / Content -->
        <div class="bg-slate-50 border border-slate-100 rounded-md p-3 text-xs text-slate-700 leading-relaxed whitespace-pre-line">
          {{ session.notes }}
        </div>

        <!-- Enrollment Context if linked -->
        <div v-if="session.enrollment" class="text-2xs text-slate-500 flex items-center gap-1.5">
          <FileText class="w-3.5 h-3.5 text-slate-400" />
          <span>Terkait KRS: <strong>{{ session.enrollment.semester?.name || 'Semester Aktif' }}</strong> ({{ session.enrollment.total_credits }} SKS)</span>
        </div>
      </div>
    </div>
  </div>
</template>
