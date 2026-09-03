<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Calendar, Edit2 } from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import type { TeachingSession } from '@/types/attendance'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'

interface Props {
  classId: string | number
}

const props = defineProps<Props>()
const toast = useToast()

const sessions = ref<TeachingSession[]>([])
const loading = ref<boolean>(false)
const selectedSession = ref<TeachingSession | null>(null)

async function loadSessions() {
  loading.value = true
  try {
    const res = await attendanceService.listSessions({
      academic_class_id: props.classId,
      per_page: 50,
    })
    sessions.value = res.data || []
    if (sessions.value.length > 0 && !selectedSession.value) {
      selectedSession.value = sessions.value[0]
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat sesi perkuliahan')
  } finally {
    loading.value = false
  }
}

function selectSession(session: TeachingSession) {
  selectedSession.value = session
}

function formatStatusBadge(status: string) {
  switch (status) {
    case 'open':
      return { variant: 'success' as const, label: 'Sedang Berlangsung' }
    case 'closed':
      return { variant: 'neutral' as const, label: 'Selesai / Terkunci' }
    case 'cancelled':
      return { variant: 'danger' as const, label: 'Dibatalkan' }
    case 'scheduled':
    default:
      return { variant: 'warning' as const, label: 'Belum Dimulai' }
  }
}

watch(() => props.classId, () => {
  loadSessions()
})

onMounted(() => {
  loadSessions()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-base font-bold text-slate-900">Sesi Perkuliahan</h3>
        <p class="text-xs text-slate-500 mt-0.5">Daftar sesi pertemuan yang digenerate otomatis dari jadwal kuliah</p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="loadSessions"
        >
          <Edit2 class="w-3.5 h-3.5" />
          <span>Ubah</span>
        </Button>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-slate-400 text-xs">
      Memuat daftar sesi perkuliahan...
    </div>

    <div v-else-if="sessions.length === 0" class="py-12 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
      Belum ada sesi perkuliahan. Buat jadwal kuliah terlebih dahulu untuk meng-generate sesi secara otomatis.
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Session List (Left Column) -->
      <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
        <div
          v-for="s in sessions"
          :key="s.id"
          class="p-3.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between"
          :class="selectedSession?.id === s.id ? 'border-rose-500 bg-rose-50/30 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'"
          @click="selectSession(s)"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs"
              :class="selectedSession?.id === s.id ? 'bg-brand-900 text-white' : 'bg-slate-100 text-slate-700'"
            >
              {{ s.meeting_number }}
            </div>
            <div>
              <div class="font-bold text-xs text-slate-900">
                Sesi {{ s.meeting_number }}
              </div>
              <div class="text-3xs text-slate-500 flex items-center gap-1.5 mt-0.5">
                <Calendar class="w-3 h-3 text-slate-400" />
                <span>{{ s.session_date }}</span>
              </div>
            </div>
          </div>

          <Badge :variant="formatStatusBadge(s.status).variant" size="sm" class="text-3xs">
            {{ formatStatusBadge(s.status).label }}
          </Badge>
        </div>
      </div>

      <!-- Session Detail Card (Right Column - Matching Screenshot 4) -->
      <div v-if="selectedSession" class="lg:col-span-2">
        <Card class="border border-slate-200 shadow-2xs overflow-hidden">
          <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-900">
              <Calendar class="w-4 h-4 text-brand-600" />
              <span>Jadwal Sesi Perkuliahan #{{ selectedSession.meeting_number }}</span>
            </div>
            <Badge :variant="formatStatusBadge(selectedSession.status).variant" size="sm">
              {{ formatStatusBadge(selectedSession.status).label }}
            </Badge>
          </div>

          <div class="p-5 space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Sesi</span>
                <span class="font-bold text-slate-900 text-sm">{{ selectedSession.meeting_number }}</span>
              </div>
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Status Perkuliahan</span>
                <span class="font-bold text-slate-800">{{ formatStatusBadge(selectedSession.status).label }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Tanggal Perkuliahan</span>
                <span class="font-bold text-slate-900">{{ selectedSession.session_date }}</span>
              </div>
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Jenis Perkuliahan</span>
                <span class="font-bold text-slate-900 uppercase">{{ selectedSession.teaching_method || 'Offline' }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Jam Mulai & Selesai</span>
                <span class="font-bold text-slate-900 font-mono">{{ selectedSession.start_time || '-' }} s.d. {{ selectedSession.end_time || '-' }}</span>
              </div>
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Ruang Kuliah</span>
                <span class="font-bold text-slate-900">{{ selectedSession.room?.name || selectedSession.room?.code || 'K201' }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Dosen Pengajar</span>
                <span class="font-bold text-slate-900">{{ selectedSession.lecturer?.full_name || '-' }}</span>
              </div>
              <div>
                <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Topik / Bahasan</span>
                <span class="font-bold text-slate-900">{{ selectedSession.topic || `Pertemuan ke-${selectedSession.meeting_number}` }}</span>
              </div>
            </div>

            <div>
              <span class="text-slate-400 text-3xs block mb-0.5 uppercase tracking-wider font-semibold">Berita Acara Perkuliahan (BAP) / Catatan</span>
              <p class="text-slate-700 text-xs bg-slate-50 p-3 rounded-lg border border-slate-200/60">
                {{ selectedSession.notes || 'Belum ada catatan perkuliahan.' }}
              </p>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
