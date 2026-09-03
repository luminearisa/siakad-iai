<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileCheck, Search } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'
import type { Lecturer } from '@/types/lecturer'
import KrsReviewCard from '../components/KrsReviewCard.vue'
import KrsReviewModal from '../components/KrsReviewModal.vue'
import Input from '@/components/ui/Input.vue'

const props = defineProps<{
  lecturer: Lecturer
  enrollments: StudentEnrollment[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'approve', payload: { enrollment: StudentEnrollment; notes?: string }): void
  (e: 'request-revision', payload: { enrollment: StudentEnrollment; notes: string }): void
  (e: 'reject', payload: { enrollment: StudentEnrollment; reason: string }): void
  (e: 'refresh'): void
}>()

const searchQuery = ref<string>('')
const statusFilter = ref<string>('submitted')

// Modal state
const modalOpen = ref<boolean>(false)
const selectedEnrollment = ref<StudentEnrollment | null>(null)
const actionType = ref<'approve' | 'revision' | 'reject'>('approve')

const filteredEnrollments = computed(() => {
  let list = props.enrollments

  if (statusFilter.value) {
    list = list.filter((e) => e.status === statusFilter.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(
      (e) =>
        e.student?.full_name?.toLowerCase().includes(q) ||
        e.student?.student_number?.toLowerCase().includes(q) ||
        e.semester?.name?.toLowerCase().includes(q)
    )
  }

  return list
})

function handleOpenModal(enrollment: StudentEnrollment, type: 'approve' | 'revision' | 'reject') {
  selectedEnrollment.value = enrollment
  actionType.value = type
  modalOpen.value = true
}

function handleConfirmModal(data: { actionType: 'approve' | 'revision' | 'reject'; notes: string }) {
  if (!selectedEnrollment.value) return

  if (data.actionType === 'approve') {
    emit('approve', { enrollment: selectedEnrollment.value, notes: data.notes })
  } else if (data.actionType === 'revision') {
    emit('request-revision', { enrollment: selectedEnrollment.value, notes: data.notes })
  } else {
    emit('reject', { enrollment: selectedEnrollment.value, reason: data.notes })
  }
  modalOpen.value = false
  selectedEnrollment.value = null
}
</script>

<template>
  <div class="space-y-4">
    <!-- Filter Bar -->
    <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div class="flex flex-1 items-center gap-2 max-w-md">
        <div class="relative flex-1">
          <Input
            v-model="searchQuery"
            placeholder="Cari KRS mahasiswa (NIM/Nama)..."
            class="text-xs"
          >
            <template #prefix>
              <Search class="w-3.5 h-3.5 text-slate-400" />
            </template>
          </Input>
        </div>

        <select
          v-model="statusFilter"
          class="text-xs rounded-md border border-slate-300 py-1.5 px-2.5 bg-white text-slate-700 font-medium"
        >
          <option value="">Semua Status KRS</option>
          <option value="submitted">Menunggu Persetujuan (Submitted)</option>
          <option value="revision_required">Perlu Revisi (Revision Required)</option>
          <option value="approved">Telah Disetujui (Approved)</option>
          <option value="draft">Draft Mahasiswa</option>
          <option value="locked">Dikunci (Locked)</option>
        </select>
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Menampilkan <strong>{{ filteredEnrollments.length }}</strong> KRS
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="filteredEnrollments.length === 0 && !loading"
      class="text-center py-10 bg-white border border-slate-200 rounded-lg p-6 text-xs text-slate-500 space-y-2"
    >
      <FileCheck class="w-8 h-8 text-slate-300 mx-auto" />
      <p class="font-medium text-slate-700">Tidak ada KRS mahasiswa bimbingan pada filter ini</p>
      <p class="text-slate-400">Pilih opsi "Semua Status KRS" untuk melihat daftar rencana studi mahasiswa bimbingan lainnya.</p>
    </div>

    <!-- KRS Cards Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <KrsReviewCard
        v-for="e in filteredEnrollments"
        :key="e.id"
        :enrollment="e"
        :loading="loading"
        @approve="handleOpenModal($event, 'approve')"
        @request-revision="handleOpenModal($event, 'revision')"
        @reject="handleOpenModal($event, 'reject')"
      />
    </div>

    <!-- Review / Approval Modal with Notes -->
    <KrsReviewModal
      :open="modalOpen"
      :enrollment="selectedEnrollment"
      :action-type="actionType"
      :loading="loading"
      @update:open="modalOpen = $event"
      @confirm="handleConfirmModal"
    />
  </div>
</template>
