<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Plus,
  ClipboardList,
  CheckCircle2,
  HelpCircle,
  BookOpen,
  Edit2,
  Trash2,
  Search,
  Layers,
  Sliders,
  Check,
} from 'lucide-vue-next'
import { surveyTemplateService } from '@/services/api/surveyTemplate'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { SurveyTemplate } from '@/types/surveyTemplate'
import type { Course } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()

const loading = ref<boolean>(true)
const templates = ref<SurveyTemplate[]>([])
const search = ref<string>('')
const statusFilter = ref<string>('all')

// Template Form Modal (Create / Edit)
const templateModalOpen = ref<boolean>(false)
const isEditingTemplate = ref<boolean>(false)
const templateSaving = ref<boolean>(false)
const editingTemplateId = ref<number | null>(null)
const templateForm = reactive({
  name: '',
  description: '',
  is_active: true,
})

// Delete Modal
const deleteModalOpen = ref<boolean>(false)
const templateToDelete = ref<SurveyTemplate | null>(null)
const deleteLoading = ref<boolean>(false)

// Bulk Assign Courses Modal
const assignModalOpen = ref<boolean>(false)
const currentTemplateForAssign = ref<SurveyTemplate | null>(null)
const coursesLoading = ref<boolean>(false)
const assignSaving = ref<boolean>(false)
const allCourses = ref<Course[]>([])
const courseSearch = ref<string>('')
const selectedCourseIds = ref<number[]>([])

// Stats
const totalTemplates = computed(() => templates.value.length)
const activeTemplates = computed(() => templates.value.filter((t) => t.is_active).length)
const totalQuestions = computed(() => templates.value.reduce((acc, t) => acc + (t.questions_count || 0), 0))
const totalAssignedCourses = computed(() => templates.value.reduce((acc, t) => acc + (t.courses_count || 0), 0))

// Filtered templates
const filteredTemplates = computed(() => {
  return templates.value.filter((t) => {
    const matchSearch =
      t.name.toLowerCase().includes(search.value.toLowerCase()) ||
      (t.description && t.description.toLowerCase().includes(search.value.toLowerCase()))
    const matchStatus =
      statusFilter.value === 'all' ||
      (statusFilter.value === 'active' && t.is_active) ||
      (statusFilter.value === 'inactive' && !t.is_active)
    return matchSearch && matchStatus
  })
})

async function loadTemplates() {
  loading.value = true
  try {
    const res = await surveyTemplateService.getTemplates()
    templates.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat template survey.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  isEditingTemplate.value = false
  editingTemplateId.value = null
  templateForm.name = ''
  templateForm.description = ''
  templateForm.is_active = true
  templateModalOpen.value = true
}

function openEditModal(tpl: SurveyTemplate) {
  isEditingTemplate.value = true
  editingTemplateId.value = tpl.id
  templateForm.name = tpl.name
  templateForm.description = tpl.description || ''
  templateForm.is_active = tpl.is_active
  templateModalOpen.value = true
}

async function handleSaveTemplate() {
  if (!templateForm.name.trim()) {
    toast.warning('Nama template survey wajib diisi.')
    return
  }

  templateSaving.value = true
  try {
    if (isEditingTemplate.value && editingTemplateId.value) {
      await surveyTemplateService.updateTemplate(editingTemplateId.value, templateForm)
      toast.success('Template survey berhasil diperbarui.')
    } else {
      await surveyTemplateService.createTemplate(templateForm)
      toast.success('Template survey baru berhasil ditambahkan.')
    }
    templateModalOpen.value = false
    await loadTemplates()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menyimpan template survey.')
  } finally {
    templateSaving.value = false
  }
}

function confirmDelete(tpl: SurveyTemplate) {
  templateToDelete.value = tpl
  deleteModalOpen.value = true
}

async function handleDeleteTemplate() {
  if (!templateToDelete.value) return
  deleteLoading.value = true
  try {
    await surveyTemplateService.deleteTemplate(templateToDelete.value.id)
    toast.success('Template survey berhasil dihapus.')
    deleteModalOpen.value = false
    await loadTemplates()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menghapus template survey.')
  } finally {
    deleteLoading.value = false
  }
}

// Bulk Assign Courses Logic
async function openAssignModal(tpl: SurveyTemplate) {
  currentTemplateForAssign.value = tpl
  assignModalOpen.value = true
  coursesLoading.value = true
  selectedCourseIds.value = []
  courseSearch.value = ''

  try {
    // Load detail template to see currently assigned courses
    const [detailRes, coursesRes] = await Promise.all([
      surveyTemplateService.getTemplate(tpl.id),
      courseService.list({ per_page: 200 }),
    ])

    const assigned = detailRes.data?.courses || []
    selectedCourseIds.value = assigned.map((c) => c.id)
    allCourses.value = coursesRes.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat daftar mata kuliah.')
  } finally {
    coursesLoading.value = false
  }
}

const filteredCoursesForAssign = computed(() => {
  if (!courseSearch.value.trim()) return allCourses.value
  const q = courseSearch.value.toLowerCase()
  return allCourses.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.code.toLowerCase().includes(q) ||
      (c.study_program && c.study_program.name.toLowerCase().includes(q))
  )
})

function toggleCourseSelection(courseId: number) {
  const index = selectedCourseIds.value.indexOf(courseId)
  if (index > -1) {
    selectedCourseIds.value.splice(index, 1)
  } else {
    selectedCourseIds.value.push(courseId)
  }
}

function selectAllFilteredCourses() {
  const ids = filteredCoursesForAssign.value.map((c) => c.id)
  const allSelected = ids.every((id) => selectedCourseIds.value.includes(id))

  if (allSelected) {
    selectedCourseIds.value = selectedCourseIds.value.filter((id) => !ids.includes(id))
  } else {
    const set = new Set([...selectedCourseIds.value, ...ids])
    selectedCourseIds.value = Array.from(set)
  }
}

async function handleSaveAssignCourses() {
  if (!currentTemplateForAssign.value) return
  assignSaving.value = true
  try {
    await surveyTemplateService.assignCourses(
      currentTemplateForAssign.value.id,
      selectedCourseIds.value
    )
    toast.success('Mata kuliah berhasil di-assign ke template survey ini.')
    assignModalOpen.value = false
    await loadTemplates()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal meng-assign mata kuliah.')
  } finally {
    assignSaving.value = false
  }
}

onMounted(() => {
  loadTemplates()
})
</script>

<template>
  <PageContainer>
    <div class="mb-4">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Mata Kuliah', to: '/courses' },
          { label: 'Kelola Template Survey' },
        ]"
      />
    </div>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
      <div>
        <h1 class="text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <ClipboardList class="w-5 h-5 text-emerald-600" />
          Kelola Template Survey Evaluasi (EDOM)
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">
          Buat dan kelola template instrumen kuisioner evaluasi dosen & perkuliahan, lalu assign ke mata kuliah terkait.
        </p>
      </div>
      <Button
        variant="primary"
        size="sm"
        @click="openCreateModal"
        class="shrink-0 flex items-center gap-1.5 shadow-sm"
      >
        <Plus class="w-4 h-4" />
        Tambah Template Baru
      </Button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <Card class="p-3.5 flex items-center gap-3 border-emerald-100 bg-gradient-to-br from-white to-emerald-50/20">
        <div class="w-10 h-10 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0">
          <Layers class="w-5 h-5" />
        </div>
        <div>
          <span class="text-2xs font-semibold text-slate-400 uppercase tracking-wider">Total Template</span>
          <p class="text-base font-bold text-slate-900">{{ totalTemplates }}</p>
        </div>
      </Card>

      <Card class="p-3.5 flex items-center gap-3 border-emerald-100 bg-gradient-to-br from-white to-emerald-50/20">
        <div class="w-10 h-10 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0">
          <CheckCircle2 class="w-5 h-5" />
        </div>
        <div>
          <span class="text-2xs font-semibold text-slate-400 uppercase tracking-wider">Template Aktif</span>
          <p class="text-base font-bold text-slate-900">{{ activeTemplates }}</p>
        </div>
      </Card>

      <Card class="p-3.5 flex items-center gap-3 border-emerald-100 bg-gradient-to-br from-white to-emerald-50/20">
        <div class="w-10 h-10 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0">
          <HelpCircle class="w-5 h-5" />
        </div>
        <div>
          <span class="text-2xs font-semibold text-slate-400 uppercase tracking-wider">Total Pertanyaan</span>
          <p class="text-base font-bold text-slate-900">{{ totalQuestions }}</p>
        </div>
      </Card>

      <Card class="p-3.5 flex items-center gap-3 border-emerald-100 bg-gradient-to-br from-white to-emerald-50/20">
        <div class="w-10 h-10 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0">
          <BookOpen class="w-5 h-5" />
        </div>
        <div>
          <span class="text-2xs font-semibold text-slate-400 uppercase tracking-wider">MK Ter-Assign</span>
          <p class="text-base font-bold text-slate-900">{{ totalAssignedCourses }}</p>
        </div>
      </Card>
    </div>

    <!-- Filters & Search -->
    <Card class="p-3 mb-4">
      <div class="flex flex-col sm:flex-row items-center gap-2.5 justify-between">
        <div class="relative w-full sm:w-80">
          <Search class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
          <Input
            v-model="search"
            placeholder="Cari nama atau deskripsi template..."
            class="pl-8 text-xs py-1.5"
          />
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <span class="text-2xs text-slate-500 font-medium">Status:</span>
          <select
            v-model="statusFilter"
            class="text-xs bg-white border border-slate-200 rounded-md px-2.5 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          >
            <option value="all">Semua Status</option>
            <option value="active">Aktif Saja</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
      </div>
    </Card>

    <!-- Table List -->
    <Card class="overflow-hidden">
      <div v-if="loading" class="p-4 space-y-3">
        <Skeleton height="2.5rem" rounded="md" />
        <Skeleton height="2.5rem" rounded="md" />
        <Skeleton height="2.5rem" rounded="md" />
      </div>

      <div v-else-if="filteredTemplates.length === 0" class="py-12 px-4 text-center">
        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
          <ClipboardList class="w-6 h-6" />
        </div>
        <h3 class="text-sm font-bold text-slate-700">Belum Ada Template Survey</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
          Buat template survey baru untuk memulai penyusunan instrumen evaluasi perkuliahan (EDOM).
        </p>
        <Button variant="primary" size="sm" @click="openCreateModal">
          <Plus class="w-4 h-4 mr-1" />
          Buat Template Sekarang
        </Button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50/80 border-b border-slate-100 text-slate-600 font-semibold uppercase tracking-wider text-2xs">
            <tr>
              <th class="py-3 px-4 w-12 text-center">No</th>
              <th class="py-3 px-4">Nama Template & Deskripsi</th>
              <th class="py-3 px-3 text-center">Jumlah Judul</th>
              <th class="py-3 px-3 text-center">Jumlah Pertanyaan</th>
              <th class="py-3 px-3 text-center">MK Ter-assign</th>
              <th class="py-3 px-3 text-center">Status</th>
              <th class="py-3 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="(tpl, idx) in filteredTemplates"
              :key="tpl.id"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <td class="py-3 px-4 text-center font-medium text-slate-400">{{ idx + 1 }}</td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                  <router-link
                    :to="`/courses/survey-templates/${tpl.id}`"
                    class="hover:text-emerald-700 hover:underline flex items-center gap-1.5"
                  >
                    {{ tpl.name }}
                  </router-link>
                </div>
                <p v-if="tpl.description" class="text-2xs text-slate-500 line-clamp-1 mt-0.5">
                  {{ tpl.description }}
                </p>
              </td>
              <td class="py-3 px-3 text-center">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-slate-100 text-slate-700">
                  {{ tpl.topics_count || 0 }} Judul
                </span>
              </td>
              <td class="py-3 px-3 text-center">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                  {{ tpl.questions_count || 0 }} Butir
                </span>
              </td>
              <td class="py-3 px-3 text-center">
                <button
                  type="button"
                  @click="openAssignModal(tpl)"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-2xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors cursor-pointer border border-blue-200/60"
                  title="Klik untuk kelola mata kuliah ter-assign"
                >
                  <BookOpen class="w-3 h-3" />
                  {{ tpl.courses_count || 0 }} Mata Kuliah
                </button>
              </td>
              <td class="py-3 px-3 text-center">
                <Badge :variant="tpl.is_active ? 'success' : 'neutral'" size="sm">
                  {{ tpl.is_active ? 'Aktif' : 'Nonaktif' }}
                </Badge>
              </td>
              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <router-link
                    :to="`/courses/survey-templates/${tpl.id}`"
                    class="p-1.5 text-emerald-700 hover:bg-emerald-50 rounded-md transition-colors"
                    title="Buka Builder (Kelola Judul & Pertanyaan)"
                  >
                    <Sliders class="w-3.5 h-3.5" />
                  </router-link>

                  <button
                    type="button"
                    @click="openAssignModal(tpl)"
                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                    title="Assign ke Mata Kuliah"
                  >
                    <BookOpen class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    @click="openEditModal(tpl)"
                    class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                    title="Edit Nama/Deskripsi Template"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    @click="confirmDelete(tpl)"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                    title="Hapus Template"
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

    <!-- Modal Form Tambah / Edit Template -->
    <Modal
      :open="templateModalOpen"
      :title="isEditingTemplate ? 'Edit Template Survey' : 'Tambah Template Survey Baru'"
      @update:open="templateModalOpen = $event"
    >
      <form @submit.prevent="handleSaveTemplate" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Nama Template Survey <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="templateForm.name"
            placeholder="Contoh: Survey Evaluasi Perkuliahan Teori (EDOM)"
            class="text-xs"
            required
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Deskripsi / Keterangan Template
          </label>
          <textarea
            v-model="templateForm.description"
            rows="3"
            placeholder="Contoh: Template evaluasi perkuliahan semester ganjil/genap untuk mata kuliah tatap muka..."
            class="w-full text-xs rounded-md border border-slate-200 p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          ></textarea>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input
            id="template-active-check"
            type="checkbox"
            v-model="templateForm.is_active"
            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4"
          />
          <label for="template-active-check" class="text-xs font-medium text-slate-700 cursor-pointer">
            Aktifkan template ini agar dapat digunakan pada mata kuliah
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="templateModalOpen = false"
            :disabled="templateSaving"
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :loading="templateSaving"
          >
            {{ isEditingTemplate ? 'Simpan Perubahan' : 'Buat Template' }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Modal Bulk Assign ke Mata Kuliah -->
    <Modal
      :open="assignModalOpen"
      :title="`Assign Template: ${currentTemplateForAssign?.name || ''}`"
      @update:open="assignModalOpen = $event"
      width="max-w-2xl"
    >
      <div class="space-y-3">
        <p class="text-xs text-slate-600">
          Pilih mata kuliah yang akan menggunakan template survey ini. Anda dapat memilih beberapa mata kuliah sekaligus.
        </p>

        <!-- Search & Select All -->
        <div class="flex flex-col sm:flex-row items-center gap-2 justify-between">
          <div class="relative w-full sm:w-72">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <Input
              v-model="courseSearch"
              placeholder="Cari kode atau nama mata kuliah..."
              class="pl-8 text-xs py-1"
            />
          </div>

          <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-2">
            <span class="text-2xs text-slate-500 font-medium">
              Terpilih: <b>{{ selectedCourseIds.length }}</b> mata kuliah
            </span>
            <Button
              type="button"
              variant="outline"
              size="xs"
              @click="selectAllFilteredCourses"
            >
              Pilih / Batal Semua
            </Button>
          </div>
        </div>

        <!-- Course List Scroll -->
        <div class="border border-slate-200 rounded-lg max-h-72 overflow-y-auto divide-y divide-slate-100">
          <div v-if="coursesLoading" class="p-4 space-y-2">
            <Skeleton height="2rem" rounded="md" />
            <Skeleton height="2rem" rounded="md" />
            <Skeleton height="2rem" rounded="md" />
          </div>

          <div v-else-if="filteredCoursesForAssign.length === 0" class="p-6 text-center text-xs text-slate-400">
            Tidak ada mata kuliah yang cocok dengan pencarian.
          </div>

          <div
            v-else
            v-for="c in filteredCoursesForAssign"
            :key="c.id"
            @click="toggleCourseSelection(c.id)"
            class="p-2.5 flex items-center justify-between hover:bg-slate-50 cursor-pointer transition-colors"
            :class="{ 'bg-emerald-50/50': selectedCourseIds.includes(c.id) }"
          >
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <div
                class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                :class="selectedCourseIds.includes(c.id) ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300 bg-white'"
              >
                <Check v-if="selectedCourseIds.includes(c.id)" class="w-3 h-3" />
              </div>
              <div class="min-w-0">
                <div class="text-xs font-semibold text-slate-900 truncate flex items-center gap-1.5">
                  <span class="font-mono text-2xs px-1.5 py-0.2 bg-slate-100 rounded text-slate-700 font-normal">
                    {{ c.code }}
                  </span>
                  {{ c.name }}
                </div>
                <div class="text-2xs text-slate-400 flex items-center gap-2 mt-0.5">
                  <span>{{ c.credits }} SKS</span>
                  <span v-if="c.study_program">• {{ c.study_program.name }}</span>
                </div>
              </div>
            </div>
            <span
              v-if="selectedCourseIds.includes(c.id)"
              class="text-2xs font-semibold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded shrink-0"
            >
              Terpilih
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="assignModalOpen = false"
            :disabled="assignSaving"
          >
            Batal
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            @click="handleSaveAssignCourses"
            :loading="assignSaving"
          >
            Simpan Assignment ({{ selectedCourseIds.length }} MK)
          </Button>
        </div>
      </div>
    </Modal>

    <!-- Modal Konfirmasi Hapus Template -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Template Survey"
      :message="`Apakah Anda yakin ingin menghapus template '${templateToDelete?.name}'? Seluruh judul topik dan butir pertanyaan di dalamnya akan ikut terhapus.`"
      confirm-text="Ya, Hapus Template"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDeleteTemplate"
    />
  </PageContainer>
</template>
