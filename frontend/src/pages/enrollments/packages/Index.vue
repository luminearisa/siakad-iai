<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check, Search } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { courseService } from '@/services/api/courses'
import { krsPackageService } from '@/services/api/krsPackages'
import { useToast } from '@/composables/useToast'
import type { StudyProgram } from '@/types/academic'
import type { Course } from '@/types/course'
import type { KrsPackage } from '@/types/enrollment'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const deleting = ref<boolean>(false)
const search = ref<string>('')
const studyPrograms = ref<StudyProgram[]>([])
const courses = ref<Course[]>([])
const packages = ref<KrsPackage[]>([])

// Drawer Tambah / Edit
const drawerOpen = ref<boolean>(false)
const isEditing = ref<boolean>(false)
const editingId = ref<number | null>(null)
const courseSearch = ref<string>('')

const form = reactive({
  name: '',
  study_program_id: null as number | null,
  semester: 1,
  description: '',
  course_ids: [] as number[],
})

// Modal Konfirmasi Hapus
const deleteModalOpen = ref<boolean>(false)
const itemToDelete = ref<KrsPackage | null>(null)

const filteredPackages = computed(() => {
  if (!search.value) return packages.value
  const q = search.value.toLowerCase()
  return packages.value.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      p.study_program?.name.toLowerCase().includes(q) ||
      p.study_program?.code.toLowerCase().includes(q)
  )
})

const filteredCoursesForSelection = computed(() => {
  let list = courses.value
  if (form.study_program_id) {
    list = list.filter((c) => !c.study_program_id || c.study_program_id === form.study_program_id)
  }
  if (!courseSearch.value) return list
  const q = courseSearch.value.toLowerCase()
  return list.filter((c) => c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q))
})

const selectedCoursesDetails = computed(() => {
  return courses.value.filter((c) => form.course_ids.includes(c.id))
})

const calculatedTotalCredits = computed(() => {
  return selectedCoursesDetails.value.reduce((sum, c) => sum + (c.credits || 0), 0)
})

async function loadData() {
  loading.value = true
  try {
    const [prodiRes, courseRes, packageRes] = await Promise.all([
      academicService.getStudyPrograms(),
      courseService.list({ per_page: 200 }),
      krsPackageService.list(),
    ])
    studyPrograms.value = prodiRes.data || []
    courses.value = courseRes.data || []
    packages.value = packageRes.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data paket KRS')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  isEditing.value = false
  editingId.value = null
  form.name = ''
  form.study_program_id = studyPrograms.value[0]?.id || null
  form.semester = 1
  form.description = ''
  form.course_ids = []
  courseSearch.value = ''
  drawerOpen.value = true
}

function openEditDrawer(pkg: KrsPackage) {
  isEditing.value = true
  editingId.value = pkg.id
  form.name = pkg.name
  form.study_program_id = pkg.study_program_id
  form.semester = pkg.semester
  form.description = pkg.description || ''
  form.course_ids = (pkg.items || []).map((i) => i.course_id)
  courseSearch.value = ''
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

function toggleCourse(courseId: number) {
  const idx = form.course_ids.indexOf(courseId)
  if (idx > -1) {
    form.course_ids.splice(idx, 1)
  } else {
    form.course_ids.push(courseId)
  }
}

async function handleSave() {
  if (!form.name.trim()) {
    toast.error('Nama paket wajib diisi')
    return
  }
  if (!form.study_program_id) {
    toast.error('Program studi wajib dipilih')
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name,
      study_program_id: form.study_program_id,
      semester: Number(form.semester),
      description: form.description,
      course_ids: form.course_ids,
    }

    if (isEditing.value && editingId.value) {
      await krsPackageService.update(editingId.value, payload)
      toast.success('Paket KRS berhasil diperbarui')
    } else {
      await krsPackageService.create(payload)
      toast.success('Paket KRS berhasil ditambahkan')
    }

    drawerOpen.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan paket KRS')
  } finally {
    saving.value = false
  }
}

function confirmDelete(pkg: KrsPackage) {
  itemToDelete.value = pkg
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return

  deleting.value = true
  try {
    await krsPackageService.delete(itemToDelete.value.id)
    toast.success('Paket KRS berhasil dihapus')
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus paket KRS')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Paket KRS</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen template paket kartu rencana studi mahasiswa per semester</p>
      </div>

      <Button
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 self-start sm:self-auto shadow-2xs font-semibold"
        @click="openCreateDrawer"
      >
        <Plus class="w-4 h-4" />
        Tambah Paket
      </Button>
    </div>

    <!-- Card Table -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-slate-50/50">
        <div class="relative w-48 sm:w-64">
          <Input
            v-model="search"
            placeholder="Cari paket..."
            class="w-full text-xs pr-8"
          />
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA PAKET</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4 text-center w-24">SEMESTER</th>
              <th class="py-3 px-4 text-center w-28">TOTAL SKS</th>
              <th class="py-3 px-4 text-center w-32">JUMLAH MATKUL</th>
              <th class="py-3 px-4 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="7" class="py-12 text-center text-slate-400">
                Memuat data paket KRS...
              </td>
            </tr>
            <tr v-else-if="filteredPackages.length === 0" class="hover:bg-transparent">
              <td colspan="7" class="py-12 text-center text-slate-400">
                Tidak ada data paket KRS yang tersedia
              </td>
            </tr>
            <tr
              v-for="(pkg, idx) in filteredPackages"
              :key="pkg.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">{{ idx + 1 }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900">{{ pkg.name }}</td>
              <td class="py-3.5 px-4 text-slate-800">
                {{ pkg.study_program?.degree ? `${pkg.study_program.degree} - ${pkg.study_program.name}` : (pkg.study_program?.name || '-') }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-800">{{ pkg.semester }}</td>
              <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-900">{{ pkg.total_credits }} SKS</td>
              <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-700">
                {{ pkg.items?.length || 0 }} Matkul
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Paket"
                    @click="openEditDrawer(pkg)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Paket"
                    @click="confirmDelete(pkg)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Slide-over Drawer Tambah / Edit Paket KRS -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-lg bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              {{ isEditing ? 'Edit Paket KRS' : 'Tambah Paket KRS' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Atur daftar mata kuliah yang diambil secara paket</p>
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
          <!-- Nama Paket -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Paket <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Paket Semester 1 - DKV"
              required
            />
          </div>

          <!-- Program Studi -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Program Studi <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.study_program_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option :value="null">Pilih Program Studi</option>
              <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
                {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
              </option>
            </select>
          </div>

          <!-- Semester -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Semester <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model.number="form.semester"
              type="number"
              min="1"
              max="14"
              placeholder="1"
              required
            />
          </div>

          <!-- Deskripsi -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Keterangan / Deskripsi
            </label>
            <textarea
              v-model="form.description"
              rows="2"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              placeholder="Keterangan opsional..."
            />
          </div>

          <!-- Pilihan Mata Kuliah (Multi Select) -->
          <div class="pt-2 border-t border-slate-100">
            <div class="flex items-center justify-between mb-2">
              <label class="font-bold text-slate-800">
                Pilih Mata Kuliah ({{ form.course_ids.length }} dipilih • {{ calculatedTotalCredits }} SKS)
              </label>
            </div>

            <!-- Search Course -->
            <div class="relative mb-2">
              <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
              <input
                v-model="courseSearch"
                type="text"
                placeholder="Cari mata kuliah..."
                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              />
            </div>

            <!-- Courses List -->
            <div class="max-h-56 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100 bg-slate-50/30">
              <div v-if="filteredCoursesForSelection.length === 0" class="p-4 text-center text-slate-400 text-3xs">
                Tidak ada mata kuliah ditemukan
              </div>
              <label
                v-for="c in filteredCoursesForSelection"
                :key="c.id"
                class="p-2.5 flex items-start gap-2 hover:bg-white cursor-pointer transition-colors"
                :class="{ 'bg-brand-50/50': form.course_ids.includes(c.id) }"
              >
                <input
                  type="checkbox"
                  :checked="form.course_ids.includes(c.id)"
                  class="rounded text-brand-600 focus:ring-brand-500 mt-0.5 cursor-pointer"
                  @change="toggleCourse(c.id)"
                />
                <div class="flex-1">
                  <div class="font-bold text-slate-900 flex items-center justify-between">
                    <span>{{ c.name }}</span>
                    <span class="text-3xs font-mono px-1.5 py-0.5 bg-slate-200/80 rounded font-semibold text-slate-700">
                      {{ c.credits }} SKS
                    </span>
                  </div>
                  <div class="text-3xs text-slate-500 font-mono mt-0.5">
                    {{ c.code }} • {{ c.study_program?.name || 'Umum' }}
                  </div>
                </div>
              </label>
            </div>
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
            @click="handleSave"
          >
            <Check class="w-3.5 h-3.5" />
            <span>{{ isEditing ? 'Simpan Perubahan' : 'Simpan Paket' }}</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- Modal Hapus -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Paket KRS"
      :message="`Apakah Anda yakin ingin menghapus paket ${itemToDelete?.name || ''}? Tindakan ini tidak dapat dibatalkan.`"
      confirm-text="Hapus Paket"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
      @cancel="deleteModalOpen = false"
    />
  </PageContainer>
</template>
