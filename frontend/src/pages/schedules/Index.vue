<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus, List, CalendarDays, Calendar } from 'lucide-vue-next'
import { scheduleService } from '@/services/api/schedules'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import type { ClassSchedule, ScheduleFilters as ScheduleFiltersType } from '@/types/schedule'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import ScheduleFilters from './components/ScheduleFilters.vue'
import ScheduleListView from './views/ScheduleListView.vue'
import WeeklyScheduleView from './views/WeeklyScheduleView.vue'
import DailyScheduleView from './views/DailyScheduleView.vue'

const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

type ViewMode = 'list' | 'weekly' | 'daily'
const currentView = ref<ViewMode>((route.query.view as ViewMode) || 'list')

const schedules = ref<ClassSchedule[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<ScheduleFiltersType>({
  search: (route.query.search as string) || '',
  semester_id: (route.query.semester_id as string) || '',
  day_of_week: (route.query.day_of_week as any) || '',
  room_id: (route.query.room_id as string) || '',
  class_id: (route.query.class_id as string) || '',
  status: (route.query.status as any) || '',
  sort: 'day_of_week',
  direction: 'asc',
  page: Number(route.query.page) || 1,
  per_page: 50,
})

// Delete Modal
const deleteModalOpen = ref<boolean>(false)
const scheduleToDelete = ref<ClassSchedule | null>(null)
const deleteLoading = ref<boolean>(false)

async function loadSchedules() {
  loading.value = true
  try {
    const res = await scheduleService.list(filters.value)
    schedules.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    schedules.value = []
  } finally {
    loading.value = false
  }
}

function setView(view: ViewMode) {
  currentView.value = view
  router.replace({
    query: {
      ...route.query,
      view: view === 'list' ? undefined : view,
    },
  })
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadSchedules()
}

function confirmDelete(s: ClassSchedule) {
  scheduleToDelete.value = s
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!scheduleToDelete.value) return
  deleteLoading.value = true
  try {
    await scheduleService.delete(scheduleToDelete.value.id)
    toast.success('Jadwal perkuliahan berhasil dihapus.')
    deleteModalOpen.value = false
    scheduleToDelete.value = null
    loadSchedules()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus jadwal.')
  } finally {
    deleteLoading.value = false
  }
}

watch(
  () => route.query.view,
  (newView) => {
    if (newView && ['list', 'weekly', 'daily'].includes(newView as string)) {
      currentView.value = newView as ViewMode
    }
  }
)

onMounted(() => {
  loadSchedules()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Jadwal Perkuliahan"
      subtitle="Kelola jadwal perkuliahan, alokasi ruangan, slot waktu, dan resolusi konflik jadwal"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Jadwal Kuliah' }]"
    >
      <template #actions>
        <!-- View Switcher -->
        <div class="inline-flex p-1 bg-slate-100 rounded-lg border border-slate-200">
          <button
            type="button"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-all cursor-pointer',
              currentView === 'list' ? 'bg-white text-slate-900 shadow-subtle' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="setView('list')"
          >
            <List class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </button>

          <button
            type="button"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-all cursor-pointer',
              currentView === 'weekly' ? 'bg-white text-slate-900 shadow-subtle' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="setView('weekly')"
          >
            <CalendarDays class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Mingguan (Grid)</span>
          </button>

          <button
            type="button"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-all cursor-pointer',
              currentView === 'daily' ? 'bg-white text-slate-900 shadow-subtle' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="setView('daily')"
          >
            <Calendar class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Harian</span>
          </button>
        </div>

        <router-link v-if="can('schedules.create')" to="/schedules/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Jadwal</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search & Filters -->
      <ScheduleFilters v-model="filters" @change="loadSchedules" />

      <!-- View 1: List -->
      <ScheduleListView
        v-if="currentView === 'list'"
        :schedules="schedules"
        :loading="loading"
        :meta="meta"
        @page-change="handlePageChange"
        @delete="confirmDelete"
      />

      <!-- View 2: Weekly Grid -->
      <WeeklyScheduleView
        v-else-if="currentView === 'weekly'"
        :schedules="schedules"
        :loading="loading"
      />

      <!-- View 3: Daily -->
      <DailyScheduleView
        v-else-if="currentView === 'daily'"
        :schedules="schedules"
        :loading="loading"
      />
    </div>

    <!-- Modals -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Jadwal Perkuliahan"
      :message="`Apakah Anda yakin ingin menghapus jadwal perkuliahan ${scheduleToDelete?.academic_class?.course?.name || ''} (${scheduleToDelete?.day_of_week} ${scheduleToDelete?.start_time} - ${scheduleToDelete?.end_time})?`"
      confirm-text="Ya, Hapus Jadwal"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
