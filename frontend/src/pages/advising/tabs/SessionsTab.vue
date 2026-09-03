<script setup lang="ts">
import { ref, computed } from 'vue'
import { Search, MessageSquarePlus } from 'lucide-vue-next'
import type { AcademicAdvisor, AdvisingSession } from '@/types/advising'
import type { Lecturer } from '@/types/lecturer'
import AdvisingSessionList from '../components/AdvisingSessionList.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps<{
  lecturer: Lecturer
  advisees: AcademicAdvisor[]
  sessions: AdvisingSession[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'create-session'): void
  (e: 'edit-session', session: AdvisingSession): void
  (e: 'delete-session', session: AdvisingSession): void
}>()

const searchQuery = ref<string>('')
const statusFilter = ref<string>('')
const { can } = usePermissions()

const filteredSessions = computed(() => {
  let list = props.sessions

  if (statusFilter.value) {
    list = list.filter((s) => s.status === statusFilter.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(
      (s) =>
        s.topic?.toLowerCase().includes(q) ||
        s.notes?.toLowerCase().includes(q) ||
        s.student?.full_name?.toLowerCase().includes(q) ||
        s.student?.student_number?.toLowerCase().includes(q)
    )
  }

  return list
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header & Filter Bar -->
    <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div class="flex flex-1 items-center gap-2 max-w-md">
        <div class="relative flex-1">
          <Input
            v-model="searchQuery"
            placeholder="Cari topik, catatan, atau nama mahasiswa..."
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
          <option value="">Semua Status Sesi</option>
          <option value="completed">Selesai</option>
          <option value="scheduled">Dijadwalkan</option>
          <option value="cancelled">Dibatalkan</option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <Button
          v-if="can('advising.create_session')"
          variant="primary"
          size="sm"
          @click="emit('create-session')"
        >
          <MessageSquarePlus class="w-3.5 h-3.5" />
          <span>Buat Sesi Bimbingan</span>
        </Button>
      </div>
    </div>

    <!-- Sessions List -->
    <AdvisingSessionList
      :sessions="filteredSessions"
      :loading="loading"
      @edit="emit('edit-session', $event)"
      @delete="emit('delete-session', $event)"
    />
  </div>
</template>
