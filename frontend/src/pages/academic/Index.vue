<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { academicService } from '@/services/api/academic'
import type { Faculty } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'

const faculties = ref<Faculty[]>([])
const loading = ref<boolean>(false)

const columns: Column<Faculty>[] = [
  { key: 'code', label: 'Kode Fakultas', width: '150px' },
  { key: 'name', label: 'Nama Fakultas' },
  { key: 'dean_name', label: 'Dekan / Pimpinan' },
]

async function loadFaculties() {
  loading.value = true
  try {
    const res = await academicService.getFaculties()
    faculties.value = res.data || []
  } catch {
    faculties.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadFaculties()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Struktur Akademik"
      subtitle="Manajemen data institusi, fakultas, program studi, tahun akademik, dan semester"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Akademik' }]"
    />

    <div class="space-y-4">
      <DataTable
        :columns="columns"
        :rows="faculties"
        :loading="loading"
        empty-title="Belum ada data fakultas"
        empty-description="Data fakultas akan dimuat dari backend Laravel."
      />
    </div>
  </PageContainer>
</template>
