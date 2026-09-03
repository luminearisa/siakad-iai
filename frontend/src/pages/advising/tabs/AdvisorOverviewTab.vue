<script setup lang="ts">
import { computed } from 'vue'
import { MessageSquare } from 'lucide-vue-next'
import type { AcademicAdvisor, AdvisingSession } from '@/types/advising'
import type { Lecturer } from '@/types/lecturer'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import AdvisingSummary from '../components/AdvisingSummary.vue'
import AdvisingSessionStatusBadge from '../components/AdvisingSessionStatusBadge.vue'
import { formatDate } from '@/utils/format'

const props = defineProps<{
  lecturer: Lecturer
  advisees: AcademicAdvisor[]
  sessions: AdvisingSession[]
  pendingKrsCount?: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'switch-tab', tabId: string): void
  (e: 'create-session'): void
  (e: 'assign-student'): void
}>()

const activeAdvisees = computed(() => props.advisees.filter((a) => a.status === 'active'))
const recentSessions = computed(() => props.sessions.slice(0, 5))
</script>

<template>
  <div class="space-y-5">
    <!-- Top Metrics Summary -->
    <AdvisingSummary
      :total-advisees="advisees.length"
      :active-advisees="activeAdvisees.length"
      :total-sessions="sessions.length"
      :pending-krs-count="pendingKrsCount || 0"
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left 2 Cols: Recent Advising Sessions -->
      <div class="lg:col-span-2 space-y-4">
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <MessageSquare class="w-4 h-4 text-brand-800" />
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                  Aktivitas Bimbingan Terbaru
                </h3>
              </div>
              <Button
                variant="ghost"
                size="xs"
                class="text-brand-900"
                @click="emit('switch-tab', 'sessions')"
              >
                Lihat Semua Sesi &rarr;
              </Button>
            </div>
          </template>

          <div v-if="recentSessions.length === 0" class="text-center py-8 text-xs text-slate-500">
            Belum ada catatan aktivitas bimbingan.
          </div>

          <div v-else class="divide-y divide-slate-100">
            <div
              v-for="s in recentSessions"
              :key="s.id"
              class="py-3 first:pt-0 last:pb-0 space-y-1.5 text-xs"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-900">
                  {{ s.topic || 'Sesi Bimbingan Akademik' }}
                </span>
                <AdvisingSessionStatusBadge :status="s.status" />
              </div>

              <div class="flex items-center gap-3 text-2xs text-slate-500">
                <span class="font-medium text-slate-700">{{ s.student?.full_name }}</span>
                <span>•</span>
                <span>{{ formatDate(s.session_date) }}</span>
              </div>

              <p class="text-2xs text-slate-600 line-clamp-2 bg-slate-50 p-2 rounded">
                {{ s.notes }}
              </p>
            </div>
          </div>
        </Card>
      </div>

      <!-- Right 1 Col: Lecturer Profile & Study Program Info -->
      <div class="space-y-4">
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Informasi Dosen Pembimbing
            </h3>
          </template>

          <dl class="divide-y divide-slate-100 text-xs">
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">NIDN</dt>
              <dd class="font-mono font-medium text-slate-900">{{ lecturer.nidn || '-' }}</dd>
            </div>
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">NIP / ID Dosen</dt>
              <dd class="font-mono font-medium text-slate-900">{{ lecturer.lecturer_number || '-' }}</dd>
            </div>
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">Program Studi</dt>
              <dd class="font-medium text-slate-900 text-right">{{ lecturer.homebase_study_program?.name || '-' }}</dd>
            </div>
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">Fakultas</dt>
              <dd class="font-medium text-slate-900 text-right">{{ lecturer.homebase_study_program?.faculty?.name || '-' }}</dd>
            </div>
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">Status Dosen</dt>
              <dd class="font-medium text-slate-900 capitalize">{{ lecturer.status || 'Aktif' }}</dd>
            </div>
            <div class="py-2.5 flex justify-between">
              <dt class="text-slate-500">Total Mahasiswa Aktif</dt>
              <dd class="font-bold text-brand-900">{{ activeAdvisees.length }} Mahasiswa</dd>
            </div>
          </dl>
        </Card>
      </div>
    </div>
  </div>
</template>
