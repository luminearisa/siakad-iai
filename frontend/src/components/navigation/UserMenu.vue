<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut, User as UserIcon, KeyRound, ChevronDown } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import Dropdown from '../ui/Dropdown.vue'
import Avatar from '../ui/Avatar.vue'
import Badge from '../ui/Badge.vue'
import ChangePasswordModal from './ChangePasswordModal.vue'
import UserProfileModal from './UserProfileModal.vue'

const router = useRouter()
const { user, roles, logout, isStudent } = useAuth()

const passwordModalOpen = ref<boolean>(false)
const profileModalOpen = ref<boolean>(false)

function handleOpenProfile() {
  if (isStudent.value) {
    router.push('/student/profile')
  } else {
    profileModalOpen.value = true
  }
}

function handleOpenPassword() {
  passwordModalOpen.value = true
}

async function handleLogout() {
  await logout()
  router.push('/auth/login')
}
</script>

<template>
  <div>
    <Dropdown align="right" width="w-56">
      <template #trigger="{ isOpen }">
        <button
          type="button"
          class="flex items-center gap-2 p-1 rounded-md hover:bg-slate-100/80 transition-colors text-left select-none cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
        >
          <Avatar :name="user?.name || 'User'" size="sm" />
          <div class="hidden md:block text-xs">
            <div class="font-semibold text-slate-800 leading-tight truncate max-w-[120px]">
              {{ user?.name || 'User' }}
            </div>
            <div class="text-2xs text-slate-500 truncate max-w-[120px]">
              {{ roles[0]?.display_name || roles[0]?.name || 'Akun' }}
            </div>
          </div>
          <ChevronDown :class="['w-3.5 h-3.5 text-slate-400 transition-transform duration-150', isOpen ? 'rotate-180' : '']" />
        </button>
      </template>

      <template #content>
        <div class="px-3 py-2 border-b border-slate-100">
          <p class="text-xs font-semibold text-slate-900 truncate">{{ user?.name }}</p>
          <p class="text-2xs text-slate-500 truncate">{{ user?.email }}</p>
          <div class="mt-1.5 flex flex-wrap gap-1">
            <Badge v-for="r in roles" :key="r.id" variant="primary" size="xs">
              {{ r.display_name || r.name }}
            </Badge>
          </div>
        </div>

        <div class="py-1">
          <button
            type="button"
            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
            @click="handleOpenProfile"
          >
            <UserIcon class="w-3.5 h-3.5 text-slate-400" />
            <span>Profil Saya</span>
          </button>
          <button
            type="button"
            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
            @click="handleOpenPassword"
          >
            <KeyRound class="w-3.5 h-3.5 text-slate-400" />
            <span>Ubah Password</span>
          </button>
        </div>

        <div class="border-t border-slate-100 pt-1">
          <button
            type="button"
            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer font-medium"
            @click="handleLogout"
          >
            <LogOut class="w-3.5 h-3.5 text-rose-500" />
            <span>Keluar (Logout)</span>
          </button>
        </div>
      </template>
    </Dropdown>

    <!-- Modals -->
    <ChangePasswordModal v-model:open="passwordModalOpen" />
    <UserProfileModal v-model:open="profileModalOpen" />
  </div>
</template>
