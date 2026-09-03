<script setup lang="ts">
import type { AcademicClass, ClassLecturer } from '@/types/class'
import ClassLecturerList from '../components/ClassLecturerList.vue'

interface Props {
  academicClass: AcademicClass
  classLecturers: ClassLecturer[]
  isReadOnly?: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-lecturer'): void
  (e: 'remove-lecturer', lecturerId: number, lecturerName: string): void
}>()
</script>

<template>
  <div>
    <ClassLecturerList
      :academic-class="academicClass"
      :class-lecturers="classLecturers"
      :is-read-only="isReadOnly"
      @add-lecturer="emit('add-lecturer')"
      @remove-lecturer="(id, name) => emit('remove-lecturer', id, name)"
    />
  </div>
</template>
