<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  Calendar,
  Clock,
  MapPin,
  User,
  BookOpen,
  LayoutGrid,
  List,
} from 'lucide-vue-next'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import { studentPortalApi } from '@/services/api/student-portal'
import type { StudentScheduleItem } from '@/types/student-portal'

const loading = ref(true)
const error = ref<string | null>(null)
const schedules = ref<StudentScheduleItem[]>([])
const viewMode = ref<'cards' | 'grid'>('cards')

const days = [
  { id: 1, name: 'Senin' },
  { id: 2, name: 'Selasa' },
  { id: 3, name: 'Rabu' },
  { id: 4, name: 'Kamis' },
  { id: 5, name: 'Jumat' },
  { id: 6, name: 'Sabtu' },
]

async function loadSchedules() {
  loading.value = true
  error.value = null
  try {
    const res = await studentPortalApi.getSchedules()
    schedules.value = res.data || []
  } catch (err: any) {
    error.value = err.message || 'Gagal memuat jadwal kuliah.'
  } finally {
    loading.value = false
  }
}

const schedulesByDay = computed(() => {
  const map: Record<number, StudentScheduleItem[]> = {}
  days.forEach(d => {
    map[d.id] = []
  })
  schedules.value.forEach(s => {
    if (map[s.day_of_week]) {
      map[s.day_of_week].push(s)
    }
  })
  return map
})

const totalCredits = computed(() => {
  return schedules.value.reduce((sum, s) => sum + (s.course.credits || 0), 0)
})

onMounted(() => {
  loadSchedules()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <PageHeader
      title="Jadwal Kuliah Saya"
      subtitle="Jadwal mingguan mata kuliah yang diambil pada semester aktif."
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <div class="bg-white border border-slate-200 p-1 rounded-lg flex items-center gap-1 shadow-xs">
            <button
              type="button"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-colors cursor-pointer',
                viewMode === 'cards'
                  ? 'bg-brand-600 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
              ]"
              @click="viewMode = 'cards'"
            >
              <List class="w-3.5 h-3.5" />
              Kartu Hari
            </button>
            <button
              type="button"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-colors cursor-pointer',
                viewMode === 'grid'
                  ? 'bg-brand-600 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
              ]"
              @click="viewMode = 'grid'"
            >
              <LayoutGrid class="w-3.5 h-3.5" />
              Grid Jadwal
            </button>
          </div>
        </div>
      </template>
    </PageHeader>

    <!-- Alert Error -->
    <Alert v-if="error" type="danger" dismissible @dismiss="error = null">
      {{ error }}
    </Alert>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center text-slate-500">
      <div class="inline-block animate-spin w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full mb-3" />
      <p class="text-xs">Memuat jadwal perkuliahan mingguan...</p>
    </div>

    <!-- Schedule Content -->
    <div v-else class="space-y-6">
      <!-- Quick Metric Info -->
      <Card class="p-4 bg-white border border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-brand-50 border border-brand-100 text-brand-600 flex items-center justify-center shrink-0">
            <BookOpen class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-xs font-semibold text-slate-900">Beban Studi Semester Ini</h3>
            <p class="text-2xs text-slate-500">Total {{ schedules.length }} Kelas Terdaftar &bull; {{ totalCredits }} SKS</p>
          </div>
        </div>
      </Card>

      <!-- Mode 1: Cards Per Day -->
      <div v-if="viewMode === 'cards'" class="space-y-6">
        <Card
          v-for="day in days"
          :key="day.id"
          class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm"
        >
          <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Calendar class="w-4 h-4 text-brand-600" />
              <h2 class="text-sm font-bold text-slate-900">{{ day.name }}</h2>
            </div>
            <span class="text-2xs text-slate-500 font-mono">
              {{ schedulesByDay[day.id]?.length || 0 }} Perkuliahan
            </span>
          </div>

          <div class="p-5">
            <div
              v-if="schedulesByDay[day.id]?.length > 0"
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
            >
              <Card
                v-for="item in schedulesByDay[day.id]"
                :key="item.id"
                class="bg-slate-50/60 border border-slate-200 hover:border-brand-300 p-4 rounded-xl transition-all space-y-3 relative overflow-hidden group shadow-2xs"
              >
                <div class="flex items-center justify-between">
                  <Badge variant="primary" size="sm" class="font-mono">
                    {{ item.course.code }} &bull; Kelas {{ item.section }}
                  </Badge>
                  <span class="text-2xs font-semibold text-slate-600">
                    {{ item.course.credits }} SKS
                  </span>
                </div>

                <div>
                  <h3 class="text-sm font-bold text-slate-900 group-hover:text-brand-700 transition-colors">
                    {{ item.course.name }}
                  </h3>
                </div>

                <div class="space-y-1.5 text-xs text-slate-600 pt-2 border-t border-slate-200/80">
                  <div class="flex items-center gap-2 text-slate-700">
                    <Clock class="w-3.5 h-3.5 text-brand-600 shrink-0" />
                    <span class="font-mono text-slate-900">{{ item.start_time.substring(0, 5) }} - {{ item.end_time.substring(0, 5) }} WIB</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <MapPin class="w-3.5 h-3.5 text-sky-600 shrink-0" />
                    <span>Ruang {{ item.room?.code || 'Online' }} ({{ item.room?.name || 'Daring' }})</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <User class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                    <span class="truncate">{{ item.lecturers.join(', ') || 'Dosen Pengampu' }}</span>
                  </div>
                </div>
              </Card>
            </div>

            <div v-else class="text-center py-6 text-slate-400 text-xs">
              Tidak ada jadwal perkuliahan di hari {{ day.name }}.
            </div>
          </div>
        </Card>
      </div>

      <!-- Mode 2: Grid Timetable -->
      <div v-else class="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm p-4">
        <div class="grid grid-cols-6 gap-3 min-w-[760px]">
          <div
            v-for="day in days"
            :key="day.id"
            class="space-y-3"
          >
            <div class="text-center py-2 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-800 uppercase tracking-wider">
              {{ day.name }}
            </div>

            <div class="space-y-3">
              <Card
                v-for="item in schedulesByDay[day.id]"
                :key="item.id"
                class="bg-slate-50 border border-slate-200 p-3 rounded-lg text-xs space-y-2 hover:border-brand-400 transition-colors shadow-2xs"
              >
                <div class="font-bold text-slate-900 truncate">{{ item.course.name }}</div>
                <div class="text-2xs text-brand-700 font-mono font-medium">{{ item.start_time.substring(0, 5) }} - {{ item.end_time.substring(0, 5) }}</div>
                <div class="text-2xs text-slate-500 flex items-center gap-1">
                  <MapPin class="w-3 h-3 text-sky-600 shrink-0" />
                  <span>R. {{ item.room?.code || 'Online' }}</span>
                </div>
              </Card>

              <div v-if="schedulesByDay[day.id]?.length === 0" class="text-center py-8 text-slate-400 text-2xs">
                Libur
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
