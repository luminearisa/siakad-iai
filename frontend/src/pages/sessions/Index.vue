<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Calendar,
  Filter,
  MoreVertical,
  Eye,
  Edit2,
  UserCheck,
  Users,
  ChevronDown,
  X,
  Check,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { TeachingSession } from '@/types/attendance'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/data-display/Pagination.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const sessions = ref<TeachingSession[]>([])
const studyPrograms = ref<StudyProgram[]>([])

const search = ref<string>('')
const filterDate = ref<string>('2026-08-25')
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const selectedIds = ref<number[]>([])

// Filter Popover
const filterOpen = ref<boolean>(false)
const filterProdi = ref<string>('')
const filterStatus = ref<string>('')

// Action menu dropdown state per session
const openActionId = ref<number | null>(null)
const statusDropdownOpen = ref<boolean>(false)

// Edit Session Modal/Drawer
const drawerOpen = ref<boolean>(false)
const editingSession = ref<TeachingSession | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  topic: '',
  notes: '',
  status: 'scheduled',
  teaching_method: 'offline',
})

const filteredSessions = computed(() => {
  let list = sessions.value

  if (filterProdi.value) {
    list = list.filter((s) => String(s.academic_class?.study_program_id) === filterProdi.value)
  }
  if (filterStatus.value) {
    list = list.filter((s) => s.status === filterStatus.value)
  }

  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (s) =>
      s.academic_class?.name?.toLowerCase().includes(q) ||
      s.academic_class?.code?.toLowerCase().includes(q) ||
      s.lecturer?.full_name?.toLowerCase().includes(q) ||
      s.room?.name?.toLowerCase().includes(q) ||
      s.topic?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredSessions.value.length / perPage.value))
})

const paginatedSessions = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredSessions.value.slice(start, start + perPage.value)
})

const isAllSelected = computed(() => {
  const current = paginatedSessions.value
  return current.length > 0 && current.every((s) => selectedIds.value.includes(s.id))
})

function toggleSelectAll() {
  const current = paginatedSessions.value
  if (isAllSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !current.some((c) => c.id === id))
  } else {
    const idsToAdd = current.map((c) => c.id).filter((id) => !selectedIds.value.includes(id))
    selectedIds.value.push(...idsToAdd)
  }
}

function toggleSelectOne(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function handlePageChange(page: number) {
  currentPage.value = page
}

function handlePerPageChange() {
  currentPage.value = 1
}

function toggleActionMenu(id: number) {
  openActionId.value = openActionId.value === id ? null : id
}

async function loadData() {
  loading.value = true
  try {
    const [sessRes, prodiRes] = await Promise.all([
      attendanceService.listSessions({
        per_page: 200,
      }),
      academicService.getStudyPrograms(),
    ])
    sessions.value = sessRes.data || []
    studyPrograms.value = prodiRes.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat monitoring sesi')
  } finally {
    loading.value = false
  }
}

function openEditSession(session: TeachingSession) {
  editingSession.value = session
  form.topic = session.topic || ''
  form.notes = session.notes || ''
  form.status = session.status || 'scheduled'
  form.teaching_method = session.teaching_method || 'offline'
  openActionId.value = null
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSaveDrawer() {
  if (!editingSession.value) return

  saving.value = true
  try {
    await attendanceService.updateSession(editingSession.value.id, {
      topic: form.topic,
      notes: form.notes,
      status: form.status as any,
      teaching_method: form.teaching_method as any,
    })
    toast.success('Sesi perkuliahan berhasil diperbarui')
    drawerOpen.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memperbarui sesi perkuliahan')
  } finally {
    saving.value = false
  }
}

function openPresensiDosen(session: TeachingSession) {
  openActionId.value = null
  router.push(`/attendance/classes/${session.academic_class_id}?session=${session.id}`)
}

function openPresensiMahasiswa(session: TeachingSession) {
  openActionId.value = null
  router.push(`/attendance/classes/${session.academic_class_id}?session=${session.id}`)
}

function viewDetail(session: TeachingSession) {
  openActionId.value = null
  router.push(`/classes/${session.academic_class_id}?tab=sessions`)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Screenshot 3) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-semibold tracking-wider uppercase">AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Monitoring Sesi Perkuliahan</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Sesi Perkuliahan</p>
      </div>

      <!-- Ubah Status Sesi Dropdown Button -->
      <div class="relative">
        <Button
          variant="outline"
          size="sm"
          class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="statusDropdownOpen = !statusDropdownOpen"
        >
          <span>Ubah Status Sesi</span>
          <ChevronDown class="w-3.5 h-3.5" />
        </Button>

        <div
          v-if="statusDropdownOpen"
          class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-30 py-1 text-xs"
        >
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-700 font-medium"
            @click="statusDropdownOpen = false"
          >
            Buka Presensi Sesi Terpilih
          </button>
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-700 font-medium"
            @click="statusDropdownOpen = false"
          >
            Tutup Presensi Sesi Terpilih
          </button>
        </div>
      </div>
    </div>

    <!-- Main Card & Table -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex items-center gap-2 w-full sm:w-auto text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            @change="handlePerPageChange"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <!-- Tanggal Picker -->
          <div class="flex items-center gap-1.5 bg-white border border-slate-300 px-2.5 py-1 rounded-lg text-xs">
            <Calendar class="w-3.5 h-3.5 text-slate-400" />
            <input
              v-model="filterDate"
              type="date"
              class="outline-none text-xs text-slate-700 bg-transparent"
            />
          </div>

          <!-- Filter Button -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-semibold transition-colors"
            :class="filterOpen || filterProdi || filterStatus ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
            @click="filterOpen = !filterOpen"
          >
            <Filter class="w-3.5 h-3.5 text-rose-600" />
            <span>Filter *</span>
          </button>

          <div class="relative w-40 sm:w-56">
            <Input
              v-model="search"
              placeholder="Cari data..."
              class="w-full text-xs pr-8"
            />
          </div>
          <Button variant="primary" size="sm" class="bg-brand-700 hover:bg-brand-800 text-white text-xs px-3">
            Cari
          </Button>
        </div>
      </div>

      <!-- Filter Panel (Collapsible) -->
      <div
        v-if="filterOpen"
        class="p-4 bg-rose-50/30 border-b border-rose-100 flex flex-wrap items-center gap-4 text-xs"
      >
        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Program Studi:</label>
          <select
            v-model="filterProdi"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Program Studi</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="String(sp.id)">
              {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Status Sesi:</label>
          <select
            v-model="filterStatus"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Status</option>
            <option value="scheduled">Belum Dimulai</option>
            <option value="open">Sedang Berlangsung</option>
            <option value="closed">Selesai</option>
          </select>
        </div>

        <button
          v-if="filterProdi || filterStatus"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="filterProdi = ''; filterStatus = ''"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table (Matching Screenshot 3) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="py-3 px-4 w-56">KELAS</th>
              <th class="py-3 px-3 text-center w-16">SESI</th>
              <th class="py-3 px-4 w-52">JADWAL</th>
              <th class="py-3 px-4 w-32">JENIS PERTEMUAN</th>
              <th class="py-3 px-4 w-36">RUANG KULIAH</th>
              <th class="py-3 px-4 w-44">PENGAJAR</th>
              <th class="py-3 px-4 w-44">PROGRAM STUDI</th>
              <th class="py-3 px-4 text-center w-28">STATUS</th>
              <th class="py-3 px-3 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="10" class="py-12 text-center text-slate-400">
                Memuat data monitoring sesi perkuliahan...
              </td>
            </tr>
            <tr v-else-if="filteredSessions.length === 0" class="hover:bg-transparent">
              <td colspan="10" class="py-12 text-center text-slate-400">
                Belum ada data sesi perkuliahan
              </td>
            </tr>
            <tr
              v-for="item in paginatedSessions"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Checkbox -->
              <td class="py-3.5 px-3 text-center">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(item.id)"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectOne(item.id)"
                />
              </td>

              <!-- Kelas -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ item.academic_class?.course?.name || item.academic_class?.name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.academic_class?.code }}
                </div>
              </td>

              <!-- Sesi -->
              <td class="py-3.5 px-3 text-center font-bold text-slate-900">
                {{ item.meeting_number }}
              </td>

              <!-- Jadwal -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-800">
                  {{ item.session_date }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.start_time || '08:00' }} s.d. {{ item.end_time || '10:00' }}
                </div>
              </td>

              <!-- Jenis Pertemuan -->
              <td class="py-3.5 px-4 text-slate-700">
                Perkuliahan
              </td>

              <!-- Ruang Kuliah -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-800">
                  Offline
                </div>
                <div class="text-3xs text-slate-500 mt-0.5">
                  {{ item.room?.name || item.room?.code || 'K201' }}
                </div>
              </td>

              <!-- Pengajar -->
              <td class="py-3.5 px-4 text-slate-800 font-medium">
                {{ item.lecturer?.full_name || '--' }}
              </td>

              <!-- Program Studi -->
              <td class="py-3.5 px-4 text-slate-700">
                {{ item.academic_class?.study_program?.name || 'S1 - Sistem Informasi' }}
              </td>

              <!-- Status -->
              <td class="py-3.5 px-4 text-center">
                <span
                  class="inline-flex items-center justify-center px-2.5 py-1 rounded text-3xs font-bold text-white shadow-2xs"
                  :class="{
                    'bg-slate-500': item.status === 'scheduled' || !item.status,
                    'bg-emerald-600': item.status === 'open',
                    'bg-slate-700': item.status === 'closed',
                  }"
                >
                  {{ item.status === 'open' ? 'Sedang Berlangsung' : item.status === 'closed' ? 'Selesai' : 'Belum Dimulai' }}
                </span>
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-3">
                <div class="flex items-center justify-center gap-1.5 relative">
                  <!-- 3-Dots Action Button -->
                  <div class="relative">
                    <button
                      type="button"
                      class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                      title="Menu Aksi"
                      @click.stop="toggleActionMenu(item.id)"
                    >
                      <MoreVertical class="w-3.5 h-3.5" />
                    </button>

                    <!-- Action Popup Menu -->
                    <div
                      v-if="openActionId === item.id"
                      class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-xl z-40 py-1 text-xs"
                    >
                      <button
                        type="button"
                        class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center gap-2"
                        @click="openEditSession(item)"
                      >
                        <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                        <span>Ubah</span>
                      </button>
                      <button
                        type="button"
                        class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center gap-2"
                        @click="openPresensiDosen(item)"
                      >
                        <UserCheck class="w-3.5 h-3.5 text-rose-600" />
                        <span>Presensi Dosen</span>
                      </button>
                      <button
                        type="button"
                        class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center gap-2"
                        @click="openPresensiMahasiswa(item)"
                      >
                        <Users class="w-3.5 h-3.5 text-rose-600" />
                        <span>Presensi Mahasiswa</span>
                      </button>
                    </div>
                  </div>

                  <!-- View Button -->
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Lihat Detail"
                    @click="viewDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <Pagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="filteredSessions.length"
        :per-page="perPage"
        :from="filteredSessions.length === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filteredSessions.length)"
        @page-change="handlePageChange"
      />
    </Card>

    <!-- Slide-over Drawer: Ubah Sesi Perkuliahan -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              Ubah Sesi Perkuliahan
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">
              {{ editingSession?.academic_class?.name }} (Sesi {{ editingSession?.meeting_number }})
            </p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <!-- Topik Bahasan -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Topik / Rencana Bahasan
            </label>
            <Input
              v-model="form.topic"
              placeholder="Masukkan topik pertemuan..."
            />
          </div>

          <!-- Status Sesi -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Status Perkuliahan
            </label>
            <select
              v-model="form.status"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="scheduled">Belum Dimulai / Dijadwalkan</option>
              <option value="open">Sedang Berlangsung (Buka Presensi)</option>
              <option value="closed">Selesai / Ditutup</option>
            </select>
          </div>

          <!-- Berita Acara Perkuliahan -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Berita Acara Perkuliahan (BAP) / Catatan
            </label>
            <textarea
              v-model="form.notes"
              rows="4"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              placeholder="Catatan pelaksanaan perkuliahan..."
            />
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5"
            @click="closeDrawer"
          >
            <X class="w-3.5 h-3.5" />
            <span>Batal</span>
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
            @click="handleSaveDrawer"
          >
            <Check class="w-3.5 h-3.5" />
            <span>Simpan Data</span>
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
