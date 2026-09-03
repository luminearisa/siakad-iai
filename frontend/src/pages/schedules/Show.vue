<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { scheduleService } from '@/services/api/schedules'
import { useToast } from '@/composables/useToast'
import type { ClassSchedule } from '@/types/schedule'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import Card from '@/components/ui/Card.vue'
import Avatar from '@/components/ui/Avatar.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import ScheduleHeader from './components/ScheduleHeader.vue'
import ScheduleStatusBadge from './components/ScheduleStatusBadge.vue'
import ScheduleTimeDisplay from './components/ScheduleTimeDisplay.vue'
import RoomTypeBadge from '@/pages/rooms/components/RoomTypeBadge.vue'
import RoomCapacityBadge from '@/pages/rooms/components/RoomCapacityBadge.vue'
import ClassCapacityBadge from '@/pages/classes/components/ClassCapacityBadge.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const scheduleId = route.params.id as string
const schedule = ref<ClassSchedule | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)

// Delete Modal
const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

async function loadSchedule() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await scheduleService.get(scheduleId)
    schedule.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data jadwal perkuliahan.'
  } finally {
    loading.value = false
  }
}

async function handleDelete() {
  deleteLoading.value = true
  try {
    await scheduleService.delete(scheduleId)
    toast.success('Jadwal perkuliahan berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/schedules')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus jadwal.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadSchedule()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Jadwal Kuliah', to: '/schedules' },
          { label: schedule ? `${schedule.day_of_week} ${schedule.start_time}` : 'Detail Jadwal' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Skeleton height="15rem" rounded="lg" />
        <Skeleton height="15rem" rounded="lg" />
      </div>
    </div>

    <!-- Error State -->
    <Alert v-else-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Content -->
    <div v-else-if="schedule" class="space-y-5">
      <!-- Header -->
      <ScheduleHeader
        :schedule="schedule"
        @delete="deleteModalOpen = true"
      />

      <!-- Grid Detail -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- 1. Jadwal & Waktu Perkuliahan -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Waktu & Siklus Perkuliahan
            </h3>
          </template>

          <dl class="divide-y divide-slate-100 text-xs">
            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Hari & Jam Kuliah</dt>
              <dd class="col-span-2">
                <ScheduleTimeDisplay
                  :day-of-week="schedule.day_of_week"
                  :start-time="schedule.start_time"
                  :end-time="schedule.end_time"
                  show-icon
                />
              </dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Status Jadwal</dt>
              <dd class="col-span-2">
                <ScheduleStatusBadge :status="schedule.status" size="xs" />
              </dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Periode Keberlakuan</dt>
              <dd class="col-span-2 text-slate-800">
                <template v-if="schedule.effective_from || schedule.effective_until">
                  {{ schedule.effective_from || 'Awal Semester' }} s/d {{ schedule.effective_until || 'Akhir Semester' }}
                </template>
                <span v-else class="text-slate-500">Sepanjang Semester Aktif</span>
              </dd>
            </div>

            <div v-if="schedule.notes" class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Catatan Jadwal</dt>
              <dd class="col-span-2 text-slate-700 whitespace-pre-line">{{ schedule.notes }}</dd>
            </div>
          </dl>
        </Card>

        <!-- 2. Alokasi Ruangan -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Ruangan Perkuliahan
            </h3>
          </template>

          <div v-if="schedule.room">
            <dl class="divide-y divide-slate-100 text-xs">
              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Kode Ruangan</dt>
                <dd class="col-span-2 font-mono font-bold text-slate-900">
                  <router-link :to="`/rooms/${schedule.room.id}`" class="hover:text-brand-900 underline">
                    {{ schedule.room.code }}
                  </router-link>
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Nama Ruangan</dt>
                <dd class="col-span-2 font-semibold text-slate-800">{{ schedule.room.name }}</dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Gedung & Lokasi</dt>
                <dd class="col-span-2 text-slate-800">
                  {{ schedule.room.building || '-' }} <template v-if="schedule.room.floor">· Lantai {{ schedule.room.floor }}</template>
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Kapasitas Kursi</dt>
                <dd class="col-span-2">
                  <RoomCapacityBadge :capacity="schedule.room.capacity" />
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Klasifikasi Ruang</dt>
                <dd class="col-span-2">
                  <RoomTypeBadge :type="schedule.room.room_type" size="xs" />
                </dd>
              </div>
            </dl>
          </div>
          <div v-else class="py-6 text-center text-xs text-slate-400 italic">
            Ruangan belum dialokasikan untuk jadwal ini.
          </div>
        </Card>

        <!-- 3. Kelas & Mata Kuliah -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Kelas & Mata Kuliah Terkait
            </h3>
          </template>

          <div v-if="schedule.academic_class">
            <dl class="divide-y divide-slate-100 text-xs">
              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Mata Kuliah</dt>
                <dd class="col-span-2">
                  <router-link
                    v-if="schedule.academic_class.course"
                    :to="`/courses/${schedule.academic_class.course.id}`"
                    class="font-bold text-slate-900 hover:text-brand-900 underline"
                  >
                    {{ schedule.academic_class.course.code }} — {{ schedule.academic_class.course.name }}
                  </router-link>
                  <span v-else>{{ schedule.academic_class.name }}</span>
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Seksi / Paralel</dt>
                <dd class="col-span-2">
                  <router-link
                    :to="`/classes/${schedule.academic_class.id}`"
                    class="font-bold text-brand-900 hover:text-brand-700 underline"
                  >
                    Kelas {{ schedule.academic_class.section }} ({{ schedule.academic_class.code }})
                  </router-link>
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Bobot Kredit</dt>
                <dd class="col-span-2 font-bold text-slate-900">
                  {{ schedule.academic_class.course?.credits || 0 }} SKS
                </dd>
              </div>

              <div class="py-2.5 grid grid-cols-3 gap-2">
                <dt class="text-slate-500 font-medium">Kapasitas Mahasiswa</dt>
                <dd class="col-span-2">
                  <ClassCapacityBadge
                    :capacity="schedule.academic_class.capacity"
                    :enrolled="schedule.academic_class.enrolled_count || 0"
                  />
                </dd>
              </div>
            </dl>
          </div>
        </Card>

        <!-- 4. Dosen Pengampu -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Dosen Pengampu Perkuliahan
            </h3>
          </template>

          <div
            v-if="schedule.academic_class?.lecturers && schedule.academic_class.lecturers.length > 0"
            class="space-y-3"
          >
            <div
              v-for="lec in schedule.academic_class.lecturers"
              :key="lec.id"
              class="flex items-center gap-3 p-2.5 bg-slate-50 rounded-lg border border-slate-200"
            >
              <Avatar :name="lec.full_name" size="sm" />
              <div class="min-w-0">
                <h5 class="text-xs font-bold text-slate-900 truncate">
                  {{ lec.full_name }}<template v-if="lec.academic_degree">, {{ lec.academic_degree }}</template>
                </h5>
                <span class="text-3xs font-mono text-slate-500 block">
                  NIDN: {{ lec.nidn || lec.nip || '-' }} · {{ lec.functional_position || 'Dosen' }}
                </span>
              </div>
            </div>
          </div>
          <div v-else class="py-6 text-center text-xs text-slate-400 italic">
            Belum ada dosen pengampu yang ditugaskan ke kelas ini.
          </div>
        </Card>
      </div>
    </div>

    <!-- Modals -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Jadwal Perkuliahan"
      :message="`Apakah Anda yakin ingin menghapus jadwal perkuliahan ${schedule?.academic_class?.course?.name || ''} pada ${schedule?.day_of_week} ${schedule?.start_time} - ${schedule?.end_time}?`"
      confirm-text="Ya, Hapus Jadwal"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
