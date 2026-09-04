<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Info, Plus, Trash2, CheckCircle2, AlertTriangle, Clock, BookOpen, Calendar, Package } from 'lucide-vue-next'
import { enrollmentService } from '@/services/api/enrollments'
import { krsPackageService } from '@/services/api/krsPackages'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import type { StudentEnrollment } from '@/types/enrollment'
import type { KrsPackage } from '@/types/enrollment'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import SelectClassModal from './components/SelectClassModal.vue'
import EnrollmentWorkflowActions from './components/EnrollmentWorkflowActions.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { isStudent } = useAuth()

const enrollmentId = route.params.id as string
const enrollment = ref<StudentEnrollment | null>(null)
const loading = ref<boolean>(true)
const submitting = ref<boolean>(false)
const selectClassOpen = ref<boolean>(false)

// Load KRS Package
const loadPackageModalOpen = ref<boolean>(false)
const packages = ref<KrsPackage[]>([])
const loadingPackages = ref<boolean>(false)
const loadingPackageId = ref<number | null>(null)

function formatDateIndo(dateStr?: string | null) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const isKrsExpired = computed(() => {
  const sem = enrollment.value?.semester
  if (!sem || !sem.krs_end_date) return false
  const end = new Date(sem.krs_end_date)
  end.setHours(23, 59, 59, 999)
  return new Date() > end
})

const isKrsNotStarted = computed(() => {
  const sem = enrollment.value?.semester
  if (!sem || !sem.krs_start_date) return false
  const start = new Date(sem.krs_start_date)
  start.setHours(0, 0, 0, 0)
  return new Date() < start
})

async function loadDetail() {
  loading.value = true
  try {
    const res = await enrollmentService.get(enrollmentId)
    enrollment.value = res.data
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat detail KRS')
  } finally {
    loading.value = false
  }
}

async function handleRemoveItem(itemId: number) {
  if (!enrollment.value) return
  if (!confirm('Apakah Anda yakin ingin membatalkan/menghapus mata kuliah ini dari KRS?')) return

  try {
    await enrollmentService.removeItem(enrollment.value.id, itemId)
    toast.success('Mata kuliah berhasil dihapus dari KRS')
    loadDetail()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus mata kuliah dari KRS')
  }
}

async function handleSubmitKrs() {
  if (!enrollment.value) return
  if (!enrollment.value.items || enrollment.value.items.length === 0) {
    toast.error('Silakan ambil minimal 1 mata kuliah sebelum mengajukan KRS.')
    return
  }

  submitting.value = true
  try {
    const res = await enrollmentService.submit(enrollment.value.id)
    enrollment.value = res.data
    toast.success('KRS berhasil diajukan ke Dosen Pembimbing Akademik!')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal mengajukan KRS')
  } finally {
    submitting.value = false
  }
}

// ─── Workflow Handlers ───────────────────────────────────────────────────────

async function handleApproveKrs(notes?: string) {
  if (!enrollment.value) return
  submitting.value = true
  try {
    const res = await enrollmentService.approve(enrollment.value.id, notes)
    enrollment.value = res.data
    toast.success('KRS mahasiswa berhasil disetujui!')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyetujui KRS')
  } finally {
    submitting.value = false
  }
}

async function handleRejectKrs(reason: string) {
  if (!enrollment.value) return
  submitting.value = true
  try {
    const res = await enrollmentService.reject(enrollment.value.id, reason)
    enrollment.value = res.data
    toast.warning('KRS mahasiswa telah ditolak.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menolak KRS')
  } finally {
    submitting.value = false
  }
}

async function handleRequestRevision(notes: string) {
  if (!enrollment.value) return
  submitting.value = true
  try {
    const res = await enrollmentService.requestRevision(enrollment.value.id, notes)
    enrollment.value = res.data
    toast.warning('Permintaan revisi KRS berhasil dikirim ke mahasiswa.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal meminta revisi KRS')
  } finally {
    submitting.value = false
  }
}

async function handleLockKrs() {
  if (!enrollment.value) return
  submitting.value = true
  try {
    const res = await enrollmentService.lock(enrollment.value.id)
    enrollment.value = res.data
    toast.success('KRS berhasil dikunci (locked).')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal mengunci KRS')
  } finally {
    submitting.value = false
  }
}

// ─── Load Package ──────────────────────────────────────────────────────────────

async function handleOpenLoadPackage() {
  if (!enrollment.value) return
  loadingPackages.value = true
  loadPackageModalOpen.value = true
  try {
    const programId = enrollment.value.student?.study_program_id
    const res = await krsPackageService.list(programId ? { study_program_id: programId } : undefined)
    packages.value = res.data || []
  } catch {
    toast.error('Gagal memuat daftar paket KRS.')
  } finally {
    loadingPackages.value = false
  }
}

async function handleLoadPackage(pkg: KrsPackage) {
  if (!enrollment.value) return
  loadingPackageId.value = pkg.id
  try {
    const res = await enrollmentService.loadPackage(enrollment.value.id, pkg.id)
    const { added, failed } = res.data
    if (added.length > 0) {
      toast.success(`${added.length} mata kuliah berhasil ditambahkan dari paket "${pkg.name}".`)
    }
    if (failed.length > 0) {
      const failMessages = failed.map((f) => `• ${f.course}: ${f.reason}`).join('\n')
      toast.warning(`${failed.length} mata kuliah tidak dapat ditambahkan:\n${failMessages}`)
    }
    loadPackageModalOpen.value = false
    await loadDetail()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat paket KRS.')
  } finally {
    loadingPackageId.value = null
  }
}

function goBack() {
  router.push('/enrollments')
}

onMounted(() => {
  loadDetail()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">{{ isStudent ? 'Portal Mahasiswa' : 'Perwalian' }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Detail Kartu Rencana Studi (KRS)</h1>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="border-slate-300 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 font-semibold text-xs shadow-2xs"
          @click="goBack"
        >
          <ArrowLeft class="w-3.5 h-3.5" />
          <span>Kembali</span>
        </Button>

        <!-- Workflow Actions (Student Submit + Admin/Dosen Approve/Revisi/Tolak/Lock) -->
        <EnrollmentWorkflowActions
          v-if="enrollment"
          :enrollment="enrollment"
          :loading="submitting"
          @submit-krs="handleSubmitKrs"
          @approve="handleApproveKrs"
          @reject="handleRejectKrs"
          @request-revision="handleRequestRevision"
          @lock="handleLockKrs"
        />
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-xs text-slate-400">
      Memuat detail Kartu Rencana Studi...
    </div>

    <div v-else-if="enrollment" class="space-y-5">
      <!-- Student Profile Card -->
      <Card class="p-6 border border-slate-200/80 shadow-2xs bg-white rounded-xl">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
          <!-- Avatar / Profile Picture -->
          <div class="relative shrink-0">
            <div class="w-24 h-28 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shadow-inner">
              <img
                v-if="enrollment.student?.photo_path"
                :src="enrollment.student.photo_path"
                alt="Student Photo"
                class="w-full h-full object-cover"
              />
              <div v-else class="text-center p-2 text-3xs text-slate-400">
                <div class="w-12 h-12 mx-auto mb-1 bg-brand-100 rounded-full flex items-center justify-center text-brand-700 font-bold text-sm">
                  {{ enrollment.student?.full_name?.charAt(0) || 'M' }}
                </div>
                <span>FOTO</span>
              </div>
            </div>
          </div>

          <!-- Student Information Details -->
          <div class="flex-1 w-full space-y-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-slate-900">{{ enrollment.student?.full_name }}</h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-3xs font-bold text-white bg-emerald-600 shadow-2xs">
                  {{ enrollment.student?.status === 'active' ? 'Aktif' : 'Aktif' }}
                </span>
              </div>
              <p class="text-xs font-mono text-slate-600 mt-0.5">{{ enrollment.student?.student_number }}</p>
            </div>

            <!-- Grid 6 Kolom Informasi -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 pt-2 border-t border-slate-100 text-xs">
              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">Program Studi</div>
                <div class="font-bold text-slate-900 mt-0.5">
                  {{ enrollment.student?.study_program?.degree ? `${enrollment.student.study_program.degree} - ${enrollment.student.study_program.name}` : (enrollment.student?.study_program?.name || '-') }}
                </div>
              </div>

              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">Tahun Kurikulum</div>
                <div class="font-bold text-slate-900 mt-0.5 font-mono">
                  {{ enrollment.student?.admission_year || 2025 }}
                </div>
              </div>

              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">Semester</div>
                <div class="font-bold text-slate-900 mt-0.5 font-mono">
                  {{ enrollment.student?.admission_year === 2026 ? 1 : 2 }}
                </div>
              </div>

              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">SKS Diambil</div>
                <div class="font-bold text-slate-900 mt-0.5 font-mono" :class="{ 'text-emerald-700 font-bold': (enrollment.total_credits || 0) > 0 }">
                  {{ enrollment.total_credits || 0 }}/{{ enrollment.max_credits || 24 }} SKS
                </div>
              </div>

              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">Dosen Wali (PA)</div>
                <div class="font-bold text-slate-900 mt-0.5">
                  {{ enrollment.academic_advisor || '-' }}
                </div>
              </div>

              <div>
                <div class="text-3xs text-slate-400 font-medium uppercase tracking-wider">Status KRS</div>
                <div class="mt-0.5">
                  <span
                    class="inline-flex items-center justify-center px-2 py-0.5 rounded text-3xs font-bold text-white shadow-2xs"
                    :class="{
                      'bg-slate-500': enrollment.status === 'draft' || !enrollment.status,
                      'bg-amber-600': enrollment.status === 'submitted',
                      'bg-emerald-600': enrollment.status === 'approved',
                      'bg-rose-600': enrollment.status === 'rejected',
                    }"
                  >
                    {{ enrollment.status === 'approved' ? 'Disetujui' : enrollment.status === 'submitted' ? 'Menunggu Persetujuan' : enrollment.status === 'rejected' ? 'Perlu Revisi' : 'Draft (Belum Diajukan)' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Dynamic Status Banners -->
      <div
        v-if="enrollment.status === 'approved'"
        class="bg-emerald-600 text-white px-4 py-3 rounded-lg flex items-center justify-between shadow-2xs text-xs font-medium"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-white" />
          <span>KRS telah disetujui oleh Dosen Pembimbing Akademik. Mahasiswa telah terdaftar resmi pada seluruh kelas perkuliahan di bawah.</span>
        </div>
      </div>

      <div
        v-else-if="enrollment.status === 'submitted'"
        class="bg-amber-500 text-white px-4 py-3 rounded-lg flex items-center justify-between shadow-2xs text-xs font-medium"
      >
        <div class="flex items-center gap-2">
          <Clock class="w-4 h-4 text-white" />
          <span>KRS telah diajukan dan sedang menunggu persetujuan (approval) dari Dosen Pembimbing Akademik (Dosen Wali).</span>
        </div>
      </div>

      <div
        v-else-if="enrollment.status === 'rejected'"
        class="bg-rose-600 text-white px-4 py-3 rounded-lg flex items-center justify-between shadow-2xs text-xs font-medium"
      >
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-white" />
          <span>KRS memerlukan revisi. Silakan periksa kembali mata kuliah yang diambil lalu ajukan ulang ke Dosen Pembimbing.</span>
        </div>
      </div>

      <!-- KRS Expired Warning -->
      <div
        v-else-if="isKrsExpired"
        class="bg-amber-500 text-white px-4 py-3 rounded-lg flex items-center justify-between shadow-2xs text-xs font-medium"
      >
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-white shrink-0" />
          <span>
            Batas waktu (deadline) pengisian KRS semester ini telah berakhir pada <strong>{{ formatDateIndo(enrollment.semester?.krs_end_date) }}</strong>. Silakan hubungi bagian Akademik jika memerlukan dispensasi.
          </span>
        </div>
      </div>

      <!-- KRS Not Started Warning -->
      <div
        v-else-if="isKrsNotStarted"
        class="bg-blue-600 text-white px-4 py-3 rounded-lg flex items-center justify-between shadow-2xs text-xs font-medium"
      >
        <div class="flex items-center gap-2">
          <Clock class="w-4 h-4 text-white shrink-0" />
          <span>
            Periode pengisian KRS belum dibuka. Jadwal KRS dimulai pada tanggal <strong>{{ formatDateIndo(enrollment.semester?.krs_start_date) }}</strong>.
          </span>
        </div>
      </div>

      <!-- Active Draft Banner with Deadline Info -->
      <div
        v-else
        class="bg-brand-50 border border-brand-200 text-brand-900 px-4 py-3 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs text-xs"
      >
        <div class="flex items-center gap-2.5">
          <Info class="w-4 h-4 text-brand-700 shrink-0" />
          <div>
            <span>
              Periode Pengisian KRS Aktif. Silakan klik tombol <strong>"+ Ambil Mata Kuliah"</strong> untuk memilih kelas perkuliahan.
            </span>
            <div v-if="enrollment.semester?.krs_end_date" class="mt-0.5 text-3xs text-brand-700 font-semibold flex items-center gap-1">
              <Calendar class="w-3 h-3 text-brand-600" />
              <span>Batas Waktu (Deadline) KRS: <strong>{{ formatDateIndo(enrollment.semester?.krs_end_date) }}</strong></span>
            </div>
          </div>
        </div>

        <Button
          variant="primary"
          size="sm"
          class="bg-brand-700 hover:bg-brand-800 text-white font-semibold text-2xs gap-1 self-start sm:self-auto shrink-0 shadow-2xs"
          @click="selectClassOpen = true"
        >
          <Plus class="w-3.5 h-3.5" />
          Ambil Mata Kuliah
        </Button>
      </div>

      <!-- Enrolled Courses Card & Table -->
      <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
        <!-- Toolbar -->
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
          <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Daftar Mata Kuliah yang Diambil ({{ enrollment.items?.length || 0 }} Mata Kuliah)
            </h3>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <!-- Add Course Button if still Draft/Rejected & Within Schedule -->
            <Button
              v-if="(enrollment.status === 'draft' || !enrollment.status || enrollment.status === 'revision_required') && !isKrsExpired && !isKrsNotStarted"
              variant="primary"
              size="sm"
              class="bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold gap-1 shadow-2xs"
              @click="selectClassOpen = true"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>Ambil Mata Kuliah</span>
            </Button>

            <!-- Load Package Button -->
            <Button
              v-if="(enrollment.status === 'draft' || !enrollment.status || enrollment.status === 'revision_required') && !isKrsExpired && !isKrsNotStarted"
              variant="outline"
              size="sm"
              class="border-brand-300 text-brand-700 hover:bg-brand-50 text-xs font-semibold gap-1 shadow-2xs"
              @click="handleOpenLoadPackage"
            >
              <Package class="w-3.5 h-3.5" />
              <span>Muat Paket KRS</span>
            </Button>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4">MATA KULIAH</th>
                <th class="py-3 px-4 text-center w-32">SKS MATA KULIAH</th>
                <th class="py-3 px-4 text-center w-36">SEMESTER</th>
                <th class="py-3 px-4 w-32">NAMA KELAS</th>
                <th class="py-3 px-4">JADWAL & DOSEN</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="!enrollment.items || enrollment.items.length === 0" class="hover:bg-transparent">
                <td colspan="7" class="py-12 text-center text-slate-400 space-y-2">
                  <BookOpen class="w-8 h-8 mx-auto text-slate-300" />
                  <p class="font-medium text-slate-600">Belum ada mata kuliah yang diambil</p>
                  <p class="text-3xs text-slate-400">
                    <span v-if="isKrsExpired" class="text-amber-600 font-medium">Pengisian KRS telah ditutup karena telah melewati batas waktu (deadline).</span>
                    <span v-else-if="isKrsNotStarted" class="text-blue-600 font-medium">Periode pengisian KRS belum dibuka.</span>
                    <span v-else>Klik tombol <strong>"+ Ambil Mata Kuliah"</strong> untuk memilih kelas perkuliahan yang tersedia.</span>
                  </p>
                </td>
              </tr>
              <tr
                v-for="(item, idx) in enrollment.items"
                :key="item.id"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="py-3.5 px-4 text-center font-medium text-slate-500">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900">{{ item.course?.name || item.academic_class?.course?.name || item.academic_class?.name || '-' }}</div>
                  <div class="font-mono text-3xs text-slate-500">{{ item.course?.code || item.academic_class?.course?.code || item.academic_class?.code || '-' }}</div>
                </td>
                <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-700">{{ item.credits }} SKS</td>
                <td class="py-3.5 px-4 text-center font-mono text-slate-700">{{ (item.academic_class?.semester as any)?.semester || enrollment.semester_id || 1 }}</td>
                <td class="py-3.5 px-4 font-mono font-bold text-brand-700">Kelas {{ item.academic_class?.section || 'A' }}</td>
                <td class="py-3.5 px-4 text-slate-600">
                  <div>{{ item.academic_class?.schedules?.[0]?.day ? `${item.academic_class.schedules[0].day}, ${item.academic_class.schedules[0].start_time} - ${item.academic_class.schedules[0].end_time}` : 'Jadwal menyusul' }}</div>
                  <div v-if="item.academic_class?.lecturers && item.academic_class.lecturers.length > 0" class="text-3xs text-slate-500">
                    {{ item.academic_class.lecturers.map(l => l.full_name).join(', ') }}
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <button
                    v-if="(enrollment.status === 'draft' || !enrollment.status || enrollment.status === 'rejected') && !isKrsExpired && !isKrsNotStarted"
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus / Batalkan Mata Kuliah ini"
                    @click="handleRemoveItem(item.id)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                  <span v-else-if="isKrsExpired" class="text-3xs text-amber-600 font-medium">Lewat Deadline</span>
                  <span v-else class="text-3xs text-slate-400">Terkunci</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Modal Ambil Kelas Perkuliahan -->
      <SelectClassModal
        v-if="enrollment"
        :open="selectClassOpen"
        :enrollment="enrollment"
        @update:open="selectClassOpen = $event"
        @item-added="loadDetail"
      />

      <!-- Modal Muat Paket KRS -->
      <div
        v-if="loadPackageModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs"
        @click.self="loadPackageModalOpen = false"
      >
        <div class="w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden">
          <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div>
              <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Package class="w-4 h-4 text-brand-600" />
                Muat Paket KRS
              </h3>
              <p class="text-3xs text-slate-500 mt-0.5">Pilih paket untuk langsung mengisi KRS dari template yang telah dibuat admin</p>
            </div>
            <button
              type="button"
              class="p-1 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100"
              @click="loadPackageModalOpen = false"
            >
              ✕
            </button>
          </div>

          <div class="p-4 max-h-96 overflow-y-auto">
            <div v-if="loadingPackages" class="py-8 text-center text-slate-400 text-xs">Memuat daftar paket...</div>
            <div v-else-if="packages.length === 0" class="py-8 text-center text-slate-400 text-xs">
              Tidak ada paket KRS yang tersedia untuk program studi ini.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="pkg in packages"
                :key="pkg.id"
                class="border border-slate-200 rounded-lg p-3 hover:border-brand-300 hover:bg-brand-50/30 transition-colors"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1">
                    <div class="font-bold text-xs text-slate-900">{{ pkg.name }}</div>
                    <div class="text-3xs text-slate-500 mt-0.5">
                      Semester Ke-{{ pkg.semester_level }} · {{ pkg.total_credits }} SKS · {{ pkg.items?.length || 0 }} Mata Kuliah
                    </div>
                    <div v-if="pkg.description" class="text-3xs text-slate-400 mt-1 italic">{{ pkg.description }}</div>
                  </div>
                  <Button
                    variant="primary"
                    size="sm"
                    class="bg-brand-700 hover:bg-brand-800 text-white text-2xs font-semibold shrink-0"
                    :loading="loadingPackageId === pkg.id"
                    @click="handleLoadPackage(pkg)"
                  >
                    Muat
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
