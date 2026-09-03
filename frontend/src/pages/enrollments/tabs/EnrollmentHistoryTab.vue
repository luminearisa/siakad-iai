<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, Clock, FileEdit, Send, Lock, XCircle, RotateCcw } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'
import Card from '@/components/ui/Card.vue'
import { formatDate } from '@/utils/format'

interface Props {
  enrollment: StudentEnrollment
}

const props = defineProps<Props>()

interface TimelineEvent {
  id: string
  title: string
  description?: string
  date?: string | null
  icon: any
  color: 'emerald' | 'brand' | 'amber' | 'rose' | 'slate'
  actor?: string | null
}

const timelineEvents = computed<TimelineEvent[]>(() => {
  const events: TimelineEvent[] = []

  // 1. Created
  events.push({
    id: 'created',
    title: 'Rancangan KRS Dibuat (Draft)',
    description: 'Mahasiswa membuat dokumen rencana studi untuk semester aktif.',
    date: props.enrollment.created_at,
    icon: FileEdit,
    color: 'slate',
    actor: props.enrollment.student?.full_name,
  })

  // 2. Submitted
  if (props.enrollment.submitted_at) {
    events.push({
      id: 'submitted',
      title: 'KRS Diajukan ke Dosen Pembimbing',
      description: 'Mahasiswa telah menyelesaikan pemilihan kelas dan mengajukan verifikasi beban studi.',
      date: props.enrollment.submitted_at,
      icon: Send,
      color: 'brand',
      actor: props.enrollment.student?.full_name,
    })
  }

  // 3. Approved
  if (props.enrollment.approved_at) {
    events.push({
      id: 'approved',
      title: 'KRS Disetujui (Approved)',
      description: props.enrollment.notes ? `Catatan Dosen PA: "${props.enrollment.notes}"` : 'Disetujui oleh Dosen Pembimbing Akademik.',
      date: props.enrollment.approved_at,
      icon: CheckCircle2,
      color: 'emerald',
      actor: props.enrollment.approver?.name || 'Dosen Pembimbing Akademik',
    })
  }

  // 4. Status specific
  if (props.enrollment.status === 'revision_required') {
    events.push({
      id: 'revision',
      title: 'Permintaan Revisi Rencana Studi',
      description: props.enrollment.notes ? `Instruksi revisi: "${props.enrollment.notes}"` : 'KRS perlu direvisi sebelum dapat disetujui.',
      date: props.enrollment.updated_at,
      icon: RotateCcw,
      color: 'amber',
      actor: props.enrollment.approver?.name || 'Dosen Pembimbing Akademik',
    })
  } else if (props.enrollment.status === 'rejected') {
    events.push({
      id: 'rejected',
      title: 'Pengajuan KRS Ditolak',
      description: props.enrollment.notes ? `Alasan penolakan: "${props.enrollment.notes}"` : 'Pengajuan rencana studi ditolak.',
      date: props.enrollment.updated_at,
      icon: XCircle,
      color: 'rose',
      actor: props.enrollment.approver?.name || 'Reviewer Akademik',
    })
  } else if (props.enrollment.status === 'locked') {
    events.push({
      id: 'locked',
      title: 'KRS Dikunci Secara Permanen (Locked)',
      description: 'Administrasi rencana studi telah selesai dan dikunci dari perubahan.',
      date: props.enrollment.updated_at,
      icon: Lock,
      color: 'slate',
      actor: 'Admin Akademik',
    })
  }

  return events
})
</script>

<template>
  <Card>
    <template #header>
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
        <Clock class="w-3.5 h-3.5 text-brand-900" />
        Riwayat & Jejak Audit Persetujuan KRS
      </h3>
    </template>

    <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
      <div
        v-for="event in timelineEvents"
        :key="event.id"
        class="relative flex items-start gap-3"
      >
        <!-- Icon Marker -->
        <div
          :class="[
            'absolute -left-6 w-5 h-5 rounded-full flex items-center justify-center text-white ring-4 ring-white shrink-0',
            event.color === 'emerald' ? 'bg-emerald-600' : '',
            event.color === 'brand' ? 'bg-brand-900' : '',
            event.color === 'amber' ? 'bg-amber-500' : '',
            event.color === 'rose' ? 'bg-rose-600' : '',
            event.color === 'slate' ? 'bg-slate-500' : '',
          ]"
        >
          <component :is="event.icon" class="w-3 h-3 stroke-[2.5]" />
        </div>

        <div class="space-y-1 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <h4 class="font-bold text-slate-900">{{ event.title }}</h4>
            <span v-if="event.date" class="font-mono text-3xs text-slate-400">
              {{ formatDate(event.date) }}
            </span>
          </div>

          <p v-if="event.description" class="text-slate-600 leading-relaxed text-2xs">
            {{ event.description }}
          </p>

          <span v-if="event.actor" class="text-3xs font-medium text-slate-500 block">
            Oleh: <strong class="text-slate-700">{{ event.actor }}</strong>
          </span>
        </div>
      </div>
    </div>
  </Card>
</template>
