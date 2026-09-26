<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Users,
  FileText,
  BookOpen,
  Award,
  AlertTriangle,
  TrendingUp,
  Building2,
  Bell,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  APPLICATION_STATUS_LABELS,
  APPLICATION_STATUS_VARIANTS,
  LOGBOOK_STATUS_LABELS,
  LOGBOOK_STATUS_VARIANTS,
  PARTICIPANT_STATUS_LABELS,
  PARTICIPANT_STATUS_VARIANTS,
  PROGRAM_STATUS_LABELS,
  PROGRAM_STATUS_VARIANTS,
} from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import MbkmStatCard from '@/pages/mbkm/components/MbkmStatCard.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(true)
const dashboard = ref<any>(null)
const notifications = ref<any[]>([])

const role = computed(() => dashboard.value?.role ?? (auth.isStudent ? 'mahasiswa' : auth.isLecturer ? 'dosen' : 'admin'))

async function load() {
  loading.value = true
  try {
    const [dashRes, notifRes] = await Promise.allSettled([
      mbkmService.dashboard(),
      mbkmService.getNotifications({ per_page: 10 }),
    ])
    if (dashRes.status === 'fulfilled') dashboard.value = dashRes.value.data
    if (notifRes.status === 'fulfilled') notifications.value = notifRes.value.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat dashboard MBKM')
  } finally {
    loading.value = false
  }
}

function counters(): Record<string, number> {
  return dashboard.value?.counters ?? {}
}

function counterLabel(key: string) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return value
  }
}

async function markAllRead() {
  try {
    await mbkmService.markAllNotificationsRead()
    toast.success('Semua notifikasi ditandai terbaca')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menandai notifikasi')
  }
}

onMounted(load)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Dashboard MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          <template v-if="role === 'mahasiswa'">Ringkasan pendaftaran, kegiatan, dan hasil studi Anda</template>
          <template v-else-if="role === 'dosen'">Ringkasan peserta bimbingan dan tindakan yang menunggu</template>
          <template v-else>Ringkasan operasional program MBKM</template>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" @click="router.push('/mbkm/reports')">
          Laporan
        </Button>
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-900 text-white"
          @click="router.push(role === 'mahasiswa' ? '/mbkm/catalog' : role === 'dosen' ? '/mbkm/monitoring' : '/mbkm/programs')"
        >
          {{ role === 'mahasiswa' ? 'Katalog Program' : role === 'dosen' ? 'Monitoring' : 'Kelola Program' }}
        </Button>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat dashboard MBKM...</div>

    <template v-else>
      <!-- Counters -->
      <div v-if="Object.keys(counters()).length > 0" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <MbkmStatCard
          v-for="(value, key) in counters()"
          :key="key"
          :label="counterLabel(String(key))"
          :value="value"
          :tone="String(key).includes('pending') ? 'warning' : 'primary'"
        />
      </div>

      <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <MbkmStatCard label="Program Aktif" :value="dashboard?.active_programs?.length ?? 0" :icon="FileText" tone="primary" />
        <MbkmStatCard label="Peserta Aktif" :value="dashboard?.active_participants?.length ?? 0" :icon="Users" tone="success" />
        <MbkmStatCard label="Logbook Menunggu" :value="dashboard?.pending_logbooks?.length ?? 0" :icon="BookOpen" tone="warning" />
        <MbkmStatCard label="Rekognisi Menunggu" :value="dashboard?.pending_recognitions?.length ?? 0" :icon="Award" tone="warning" />
      </div>

      <!-- MAHASISWA -->
      <div v-if="role === 'mahasiswa'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Kepesertaan Aktif</p>
          </template>

          <div v-if="dashboard?.active_participant" class="space-y-3 text-xs">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900">{{ dashboard.active_participant.program?.name }}</p>
                <p class="text-2xs text-slate-500 mt-0.5 font-mono">
                  {{ dashboard.active_participant.participant_number }}
                </p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ formatDate(dashboard.active_participant.start_date) }} –
                  {{ formatDate(dashboard.active_participant.end_date) }}
                </p>
                <p v-if="dashboard.active_participant.placement?.partner" class="text-2xs text-slate-500 mt-0.5 flex items-center gap-1">
                  <Building2 class="w-3 h-3" /> {{ dashboard.active_participant.placement.partner.name }}
                </p>
              </div>
              <MbkmStatusBadge
                :value="dashboard.active_participant.status"
                :labels="PARTICIPANT_STATUS_LABELS"
                :variants="PARTICIPANT_STATUS_VARIANTS"
              />
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
              <div>
                <p class="text-2xs text-slate-500">Progress Logbook</p>
                <p class="font-bold text-slate-900">{{ dashboard.logbook_summary?.progress_percentage ?? 0 }}%</p>
              </div>
              <div>
                <p class="text-2xs text-slate-500">Kehadiran</p>
                <p class="font-bold text-slate-900">{{ dashboard.attendance_summary?.attendance_percentage ?? 0 }}%</p>
              </div>
              <div>
                <p class="text-2xs text-slate-500">Nilai Akhir</p>
                <p class="font-bold text-slate-900">{{ dashboard.active_participant.final_score ?? '—' }}</p>
              </div>
              <div>
                <p class="text-2xs text-slate-500">SKS Diakui</p>
                <p class="font-bold text-slate-900">{{ dashboard.recognition_summary?.recognized_credits ?? 0 }}</p>
              </div>
            </div>

            <Button variant="outline" size="sm" @click="router.push('/mbkm/my')">
              Buka Kegiatan Saya
            </Button>
          </div>

          <div v-else class="py-8 text-center text-xs text-slate-400">
            Anda belum terdaftar sebagai peserta MBKM.
          </div>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header><p class="font-bold text-slate-800 text-sm">Pendaftaran Saya</p></template>
          <div v-if="(dashboard?.applications ?? []).length === 0" class="py-6 text-center text-xs text-slate-400">
            Belum ada pendaftaran.
          </div>
          <div v-else class="space-y-2.5 text-xs">
            <div v-for="app in dashboard.applications" :key="app.id" class="p-2.5 border border-slate-200 rounded-lg">
              <p class="font-semibold text-slate-900">{{ app.program?.name }}</p>
              <div class="flex items-center justify-between gap-2 mt-1.5">
                <span class="text-2xs text-slate-500 font-mono">{{ app.registration_number }}</span>
                <MbkmStatusBadge
                  :value="app.status"
                  :labels="APPLICATION_STATUS_LABELS"
                  :variants="APPLICATION_STATUS_VARIANTS"
                />
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- DOSEN -->
      <div v-else-if="role === 'dosen'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Peserta Bimbingan</p>
            <Button variant="outline" size="sm" @click="router.push('/mbkm/monitoring')">Monitoring</Button>
          </template>

          <div v-if="(dashboard?.participants ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
            Belum ada peserta bimbingan.
          </div>

          <div v-else class="space-y-2.5 text-xs">
            <div
              v-for="p in dashboard.participants"
              :key="p.id"
              class="p-3 border border-slate-200 rounded-lg flex items-start justify-between gap-3"
            >
              <div class="min-w-0">
                <p class="font-semibold text-slate-900">{{ p.student?.full_name }}</p>
                <p class="text-2xs text-slate-500 mt-0.5">{{ p.program?.name }}</p>
                <p class="text-2xs text-slate-400">{{ formatDate(p.start_date) }} – {{ formatDate(p.end_date) }}</p>
              </div>
              <div class="shrink-0 flex flex-col items-end gap-1.5">
                <MbkmStatusBadge :value="p.status" :labels="PARTICIPANT_STATUS_LABELS" :variants="PARTICIPANT_STATUS_VARIANTS" />
                <button
                  type="button"
                  class="text-2xs text-brand-900 hover:text-brand-950 font-semibold"
                  @click="router.push(`/mbkm/participants/${p.id}`)"
                >
                  Detail →
                </button>
              </div>
            </div>
          </div>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header><p class="font-bold text-slate-800 text-sm">Logbook Menunggu Review</p></template>
          <div v-if="(dashboard?.pending_logbooks ?? []).length === 0" class="py-6 text-center text-xs text-slate-400">
            Tidak ada logbook menunggu.
          </div>
          <div v-else class="space-y-2.5 text-xs">
            <div v-for="log in dashboard.pending_logbooks" :key="log.id" class="p-2.5 border border-slate-200 rounded-lg">
              <p class="font-semibold text-slate-900">{{ log.activity }}</p>
              <p class="text-2xs text-slate-500 mt-0.5">
                {{ log.participant?.student?.full_name }} • {{ formatDate(log.log_date) }}
              </p>
              <div class="mt-1.5">
                <MbkmStatusBadge :value="log.status" :labels="LOGBOOK_STATUS_LABELS" :variants="LOGBOOK_STATUS_VARIANTS" />
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- ADMIN -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Program MBKM Aktif</p>
            <Button variant="outline" size="sm" @click="router.push('/mbkm/programs')">Kelola</Button>
          </template>

          <div v-if="(dashboard?.active_programs ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
            Belum ada program aktif.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                  <th class="py-2.5 px-3">PROGRAM</th>
                  <th class="py-2.5 px-3 w-32 text-center">PESERTA / KUOTA</th>
                  <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="p in dashboard.active_programs" :key="p.id">
                  <td class="py-3 px-3">
                    <p class="font-semibold text-slate-900">{{ p.name }}</p>
                    <p class="text-2xs text-slate-500">{{ p.program_type?.name }}</p>
                  </td>
                  <td class="py-3 px-3 text-center font-semibold text-slate-800">
                    {{ p.quota_used ?? p.participants_count ?? 0 }} / {{ p.quota ?? '∞' }}
                  </td>
                  <td class="py-3 px-3 text-center">
                    <MbkmStatusBadge :value="p.status" :labels="PROGRAM_STATUS_LABELS" :variants="PROGRAM_STATUS_VARIANTS" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>

        <div class="space-y-5">
          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header>
              <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-amber-600" /> Butuh Tindakan
              </p>
            </template>
            <div v-if="(dashboard?.action_items ?? []).length === 0" class="py-6 text-center text-xs text-slate-400">
              Tidak ada tindakan tertunda.
            </div>
            <div v-else class="space-y-2 text-xs">
              <div
                v-for="item in dashboard.action_items"
                :key="item.code"
                class="flex items-center justify-between p-2.5 bg-amber-50 border border-amber-200 rounded-lg"
              >
                <span class="text-amber-800">{{ item.label }}</span>
                <span class="font-bold text-amber-900">{{ item.total }}</span>
              </div>
            </div>
          </Card>

          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header>
              <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
                <TrendingUp class="w-3.5 h-3.5 text-brand-800" /> Peserta per Program Studi
              </p>
            </template>
            <div v-if="(dashboard?.participants_by_study_program ?? []).length === 0" class="py-6 text-center text-xs text-slate-400">
              Belum ada data.
            </div>
            <div v-else class="space-y-1.5 text-xs">
              <div
                v-for="row in dashboard.participants_by_study_program"
                :key="row.id"
                class="flex items-center justify-between py-1.5 px-2.5 bg-slate-50 rounded-lg border border-slate-200"
              >
                <span class="text-slate-700 truncate">{{ row.name }}</span>
                <span class="font-bold text-slate-900 shrink-0">{{ row.total }}</span>
              </div>
            </div>
          </Card>

          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header>
              <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
                <Building2 class="w-3.5 h-3.5 text-slate-500" /> Peserta per Mitra
              </p>
            </template>
            <div v-if="(dashboard?.participants_by_partner ?? []).length === 0" class="py-6 text-center text-xs text-slate-400">
              Belum ada data.
            </div>
            <div v-else class="space-y-1.5 text-xs">
              <div
                v-for="row in dashboard.participants_by_partner"
                :key="row.id"
                class="flex items-center justify-between py-1.5 px-2.5 bg-slate-50 rounded-lg border border-slate-200"
              >
                <span class="text-slate-700 truncate">{{ row.name }}</span>
                <span class="font-bold text-slate-900 shrink-0">{{ row.total }}</span>
              </div>
            </div>
          </Card>
        </div>
      </div>

      <!-- Notifications -->
      <Card class="border border-slate-200/80 shadow-2xs mt-5">
        <template #header>
          <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
            <Bell class="w-3.5 h-3.5 text-rose-600" /> Notifikasi MBKM
          </p>
          <Button v-if="notifications.length > 0" variant="outline" size="sm" @click="markAllRead">
            Tandai Semua Terbaca
          </Button>
        </template>

        <div v-if="notifications.length === 0" class="py-6 text-center text-xs text-slate-400">
          Belum ada notifikasi.
        </div>

        <div v-else class="space-y-2 text-xs">
          <div
            v-for="n in notifications"
            :key="n.id"
            class="p-3 rounded-lg border"
            :class="n.read_at ? 'bg-white border-slate-200' : 'bg-brand-50/50 border-brand-200'"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900">{{ n.title ?? n.event ?? 'Notifikasi MBKM' }}</p>
                <p class="text-2xs text-slate-600 mt-0.5">{{ n.message }}</p>
              </div>
              <span class="text-2xs text-slate-400 shrink-0">{{ formatDate(n.created_at) }}</span>
            </div>
          </div>
        </div>
      </Card>
    </template>
  </PageContainer>
</template>
