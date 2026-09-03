<script setup lang="ts">
import { ref, computed } from 'vue'
import { Search, UserPlus } from 'lucide-vue-next'
import type { AcademicAdvisor } from '@/types/advising'
import type { Lecturer } from '@/types/lecturer'
import StudentAdviseeTable from '../components/StudentAdviseeTable.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps<{
  lecturer: Lecturer
  advisees: AcademicAdvisor[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'assign-student'): void
  (e: 'create-session', advisor: AcademicAdvisor): void
  (e: 'reassign', advisor: AcademicAdvisor): void
}>()

const searchQuery = ref<string>('')
const statusFilter = ref<string>('active')
const { can } = usePermissions()

const filteredAdvisees = computed(() => {
  let list = props.advisees

  if (statusFilter.value) {
    list = list.filter((a) => a.status === statusFilter.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(
      (a) =>
        a.student?.full_name?.toLowerCase().includes(q) ||
        a.student?.student_number?.toLowerCase().includes(q) ||
        a.student?.study_program?.name?.toLowerCase().includes(q)
    )
  }

  return list
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header & Controls -->
    <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div class="flex flex-1 items-center gap-2 max-w-md">
        <div class="relative flex-1">
          <Input
            v-model="searchQuery"
            placeholder="Cari mahasiswa bimbingan (NIM/Nama)..."
            class="text-xs"
          >
            <template #prefix>
              <Search class="w-3.5 h-3.5 text-slate-400" />
            </template>
          </Input>
        </div>

        <select
          v-model="statusFilter"
          class="text-xs rounded-md border border-slate-300 py-1.5 px-2.5 bg-white text-slate-700"
        >
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="transferred">Dialihkan</option>
          <option value="completed">Selesai</option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <Button
          v-if="can('advising.assign')"
          variant="primary"
          size="sm"
          @click="emit('assign-student')"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>Tambah Mahasiswa</span>
        </Button>
      </div>
    </div>

    <!-- Advisee Table -->
    <StudentAdviseeTable
      :advisees="filteredAdvisees"
      :loading="loading"
      @create-session="emit('create-session', $event)"
      @reassign="emit('reassign', $event)"
    />
  </div>
</template>
