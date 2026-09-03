<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Menu, Bell } from 'lucide-vue-next'
import { useAppStore } from '@/stores/app'
import { academicService } from '@/services/api/academic'
import UserMenu from './UserMenu.vue'
import Badge from '../ui/Badge.vue'

const appStore = useAppStore()
const activeSemesterName = ref<string>('Semester Aktif')

onMounted(async () => {
  try {
    const res = await academicService.getSemesters({ status: 'active' })
    if (res.data && res.data.length > 0) {
      activeSemesterName.value = `Semester ${res.data[0].name} Aktif`
    } else {
      activeSemesterName.value = 'Semester Aktif'
    }
  } catch {
    activeSemesterName.value = 'Semester Aktif'
  }
})
</script>

<template>
  <header class="h-14 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-20 shadow-subtle">
    <!-- Left: Mobile Toggle & Context -->
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="lg:hidden p-1.5 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer"
        aria-label="Buka Menu"
        @click="appStore.toggleMobileSidebar"
      >
        <Menu class="w-5 h-5" />
      </button>

      <div class="hidden sm:flex items-center gap-2">
        <Badge variant="primary" size="sm" dot>
          {{ activeSemesterName }}
        </Badge>
      </div>
    </div>

    <!-- Right: Quick actions & User menu -->
    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Notification Icon -->
      <button
        type="button"
        class="p-1.5 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors relative cursor-pointer"
        title="Notifikasi"
      >
        <Bell class="w-4 h-4" />
        <span class="absolute top-1 right-1 w-2 h-2 bg-brand-600 rounded-full ring-2 ring-white" />
      </button>

      <div class="h-4 w-px bg-slate-200 mx-1" />

      <!-- User Profile -->
      <UserMenu />
    </div>
  </header>
</template>
