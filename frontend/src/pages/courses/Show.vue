<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { Course } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import Card from '@/components/ui/Card.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import CourseHeader from './components/CourseHeader.vue'
import CoursePrerequisiteList from './components/CoursePrerequisiteList.vue'
import AddPrerequisiteModal from './components/AddPrerequisiteModal.vue'
import CourseTypeBadge from './components/CourseTypeBadge.vue'
import CourseQuestionnaireTab from './components/CourseQuestionnaireTab.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const courseId = route.params.id as string
const course = ref<Course | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)
const activeTab = ref<string>('overview')

// Modals
const prereqModalOpen = ref<boolean>(false)
const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

const tabs = ref<TabItem[]>([
  { id: 'overview', label: 'Informasi Umum' },
  { id: 'prerequisites', label: 'Prasyarat & Relasi', badge: 0 },
  { id: 'questionnaires', label: 'Kelola Kuisioner' },
])

async function loadCourse() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await courseService.get(courseId)
    course.value = res.data
    tabs.value[1].badge = (res.data.prerequisites?.length || 0)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data mata kuliah.'
  } finally {
    loading.value = false
  }
}

function handlePrereqSuccess(updated: Course) {
  course.value = updated
  tabs.value[1].badge = (updated.prerequisites?.length || 0)
}

async function handleDelete() {
  if (!course.value) return
  deleteLoading.value = true
  try {
    await courseService.delete(course.value.id)
    toast.success('Mata kuliah berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/courses')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus mata kuliah.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadCourse()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Mata Kuliah', to: '/courses' },
          { label: course ? `${course.code} — ${course.name}` : 'Detail Mata Kuliah' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <Skeleton height="2.5rem" rounded="md" width="40%" />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Skeleton height="12rem" rounded="lg" />
        <Skeleton height="12rem" rounded="lg" />
      </div>
    </div>

    <!-- Error State -->
    <Alert v-else-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Content -->
    <div v-else-if="course" class="space-y-5">
      <!-- Header -->
      <CourseHeader
        :course="course"
        @delete="deleteModalOpen = true"
      />

      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- Tab 1: Overview -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Rincian Akademik -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Rincian Bobot SKS & Tipe
            </h3>
          </template>

          <dl class="divide-y divide-slate-100 text-xs">
            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Kode Mata Kuliah</dt>
              <dd class="col-span-2 font-mono font-bold text-slate-900">{{ course.code }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Nama Lengkap</dt>
              <dd class="col-span-2 font-bold text-slate-900">{{ course.name }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Nama Singkat / Alias</dt>
              <dd class="col-span-2 text-slate-800">{{ course.short_name || '-' }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Total Bobot SKS</dt>
              <dd class="col-span-2 font-bold text-slate-900">{{ course.credits }} SKS</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">SKS Teori</dt>
              <dd class="col-span-2 text-slate-800">{{ course.theory_credits }} SKS</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">SKS Praktikum</dt>
              <dd class="col-span-2 text-slate-800">{{ course.practical_credits }} SKS</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Tipe Perkuliahan</dt>
              <dd class="col-span-2">
                <CourseTypeBadge :type="course.type" size="xs" />
              </dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Kategori</dt>
              <dd class="col-span-2 text-slate-800 font-medium">{{ course.category || '-' }}</dd>
            </div>
          </dl>
        </Card>

        <!-- Silabus & Deskripsi -->
        <div class="space-y-5">
          <Card>
            <template #header>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                Deskripsi & Capaian Pembelajaran
              </h3>
            </template>
            <p v-if="course.description" class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">
              {{ course.description }}
            </p>
            <p v-else class="text-xs text-slate-400 italic">
              Belum ada deskripsi atau silabus ringkas untuk mata kuliah ini.
            </p>
          </Card>
        </div>
      </div>

      <!-- Tab 2: Prerequisites -->
      <div v-else-if="activeTab === 'prerequisites'">
        <CoursePrerequisiteList
          :course="course"
          @add-prerequisite="prereqModalOpen = true"
          @remove-prerequisite="prereqModalOpen = true"
        />
      </div>

      <!-- Tab 3: Questionnaires (Kelola Kuisioner) -->
      <div v-else-if="activeTab === 'questionnaires'">
        <CourseQuestionnaireTab :course="course" />
      </div>
    </div>

    <!-- Modals -->
    <AddPrerequisiteModal
      v-if="course"
      :open="prereqModalOpen"
      :course="course"
      @update:open="prereqModalOpen = $event"
      @success="handlePrereqSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Mata Kuliah"
      :message="`Apakah Anda yakin ingin menghapus mata kuliah ${course?.name} (${course?.code})? Data yang sudah terkait dengan kurikulum aktif tidak dapat dihapus.`"
      confirm-text="Ya, Hapus Mata Kuliah"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
