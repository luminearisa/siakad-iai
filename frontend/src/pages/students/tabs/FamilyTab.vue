<script setup lang="ts">
import { Plus, Users } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Student } from '@/types/student'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  student: Student
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-family'): void
}>()

const { can } = usePermissions()

function getRelationLabel(rel: string): string {
  switch (rel) {
    case 'father': return 'Ayah Kandung'
    case 'mother': return 'Ibu Kandung'
    case 'guardian': return 'Wali Mahasiswa'
    default: return rel
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Data Keluarga & Orang Tua / Wali
        </h3>
        <p class="text-2xs text-slate-500">
          Informasi kontak dan data orang tua atau wali penanggung jawab mahasiswa
        </p>
      </div>

      <Button
        v-if="can('students.update')"
        variant="primary"
        size="sm"
        @click="emit('add-family')"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>Tambah Keluarga</span>
      </Button>
    </div>

    <!-- Family Members Grid -->
    <div v-if="student.families && student.families.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="fam in student.families" :key="fam.id" dense>
        <div class="flex items-start justify-between mb-2">
          <Badge variant="primary" size="xs">
            {{ getRelationLabel(fam.relationship) }}
          </Badge>
        </div>

        <h4 class="text-sm font-bold text-slate-900 mb-1">
          {{ fam.full_name }}
        </h4>

        <dl class="text-xs space-y-1 text-slate-600 divide-y divide-slate-100">
          <div v-if="fam.phone" class="pt-1.5 flex justify-between">
            <dt class="text-slate-400">Telepon / HP:</dt>
            <dd class="font-medium text-slate-800">{{ fam.phone }}</dd>
          </div>
          <div v-if="fam.occupation" class="pt-1.5 flex justify-between">
            <dt class="text-slate-400">Pekerjaan:</dt>
            <dd class="font-medium text-slate-800">{{ fam.occupation }}</dd>
          </div>
          <div v-if="fam.address" class="pt-1.5">
            <dt class="text-slate-400 text-2xs mb-0.5">Alamat:</dt>
            <dd class="text-slate-700 text-2xs leading-relaxed">{{ fam.address }}</dd>
          </div>
        </dl>
      </Card>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="Users"
      title="Belum Ada Data Keluarga"
      description="Data orang tua atau wali untuk mahasiswa ini belum ditambahkan."
    >
      <template v-if="can('students.update')" #action>
        <Button variant="outline" size="sm" @click="emit('add-family')">
          <Plus class="w-3.5 h-3.5" />
          <span>Tambah Anggota Keluarga</span>
        </Button>
      </template>
    </EmptyState>
  </div>
</template>
