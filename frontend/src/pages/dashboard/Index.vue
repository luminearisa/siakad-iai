<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import PageContainer from '@/components/data-display/PageContainer.vue'
import StudentDashboard from './components/StudentDashboard.vue'
import LecturerDashboard from './components/LecturerDashboard.vue'
import AdminDashboard from './components/AdminDashboard.vue'

const authStore = useAuthStore()

const isStudent = computed(() => authStore.isStudent)
const isLecturer = computed(() => authStore.isLecturer)
</script>

<template>
  <PageContainer>
    <!-- 1. Dedicated Student Portal Dashboard -->
    <StudentDashboard v-if="isStudent" />

    <!-- 2. Dedicated Lecturer Academic Dashboard -->
    <LecturerDashboard v-else-if="isLecturer" />

    <!-- 3. Dedicated Super Admin / Academic Admin Dashboard -->
    <AdminDashboard v-else />
  </PageContainer>
</template>
