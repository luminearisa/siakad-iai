<script setup lang="ts">
import type { AcademicClass } from '@/types/class'
import Card from '@/components/ui/Card.vue'
import ClassStatusBadge from '../components/ClassStatusBadge.vue'
import ClassCapacityBadge from '../components/ClassCapacityBadge.vue'
import CourseTypeBadge from '@/pages/courses/components/CourseTypeBadge.vue'

interface Props {
  academicClass: AcademicClass
}

defineProps<Props>()
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Informasi Kelas & Kapasitas -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Informasi Kelas Perkuliahan
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Kode Kelas</dt>
          <dd class="col-span-2 font-mono font-bold text-slate-900">{{ academicClass.code }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Seksi / Paralel</dt>
          <dd class="col-span-2 font-bold text-brand-900">Kelas {{ academicClass.section }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Status Penyelenggaraan</dt>
          <dd class="col-span-2">
            <ClassStatusBadge :status="academicClass.status" size="xs" />
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Kapasitas & Kuota Kursi</dt>
          <dd class="col-span-2">
            <ClassCapacityBadge
              :capacity="academicClass.capacity"
              :enrolled="academicClass.enrolled_count || 0"
            />
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Semester Akademik</dt>
          <dd class="col-span-2 font-semibold text-slate-800">
            {{ academicClass.semester?.name || '-' }}
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Program Studi</dt>
          <dd class="col-span-2 text-slate-800">
            {{ academicClass.study_program?.name || 'Mata Kuliah Umum / Lintas Prodi' }}
          </dd>
        </div>
      </dl>
    </Card>

    <!-- Informasi Mata Kuliah Terkait -->
    <div class="space-y-5">
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Mata Kuliah Terkait
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Kode & Nama MK</dt>
            <dd class="col-span-2">
              <router-link
                v-if="academicClass.course"
                :to="`/courses/${academicClass.course.id}`"
                class="font-bold text-slate-900 hover:text-brand-900 underline"
              >
                {{ academicClass.course.code }} — {{ academicClass.course.name }}
              </router-link>
              <span v-else>-</span>
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Bobot Kredit</dt>
            <dd class="col-span-2 font-bold text-slate-900">
              {{ academicClass.course?.credits || 0 }} SKS ({{ academicClass.course?.theory_credits || 0 }}T · {{ academicClass.course?.practical_credits || 0 }}P)
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tipe Perkuliahan</dt>
            <dd class="col-span-2">
              <CourseTypeBadge v-if="academicClass.course" :type="academicClass.course.type" size="xs" />
              <span v-else>-</span>
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Kategori</dt>
            <dd class="col-span-2 text-slate-800 font-medium">{{ academicClass.course?.category || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Catatan Kelas -->
      <Card v-if="academicClass.notes">
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Catatan Penyelenggaraan Kelas
          </h3>
        </template>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ academicClass.notes }}</p>
      </Card>
    </div>
  </div>
</template>
