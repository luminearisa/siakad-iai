<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Avatar from '@/components/ui/Avatar.vue'
import { User, Mail, ShieldCheck, Calendar, Hash } from 'lucide-vue-next'

interface Props {
  open: boolean
}

interface Emits {
  (e: 'update:open', val: boolean): void
}

defineProps<Props>()
defineEmits<Emits>()

const { user, roles } = useAuth()
</script>

<template>
  <Modal
    :open="open"
    title="Profil Akun Pengguna"
    max-width="max-w-md"
    @update:open="$emit('update:open', $event)"
  >
    <div class="space-y-4">
      <!-- User Identity Header -->
      <div class="flex items-center gap-3.5 p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
        <Avatar :name="user?.name || 'User'" size="lg" />
        <div class="min-w-0 flex-1">
          <h3 class="text-sm font-bold text-slate-900 truncate">
            {{ user?.name }}
          </h3>
          <p class="text-xs text-slate-500 truncate flex items-center gap-1 mt-0.5">
            <Mail class="w-3.5 h-3.5 text-slate-400" />
            {{ user?.email }}
          </p>
          <div class="mt-1.5 flex flex-wrap gap-1">
            <Badge v-for="r in roles" :key="r.id" variant="primary" size="xs">
              {{ r.display_name || r.name }}
            </Badge>
          </div>
        </div>
      </div>

      <!-- Account Details Grid -->
      <div class="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 text-xs">
        <div class="p-3 flex items-center justify-between">
          <span class="text-slate-500 flex items-center gap-1.5 font-medium">
            <Hash class="w-3.5 h-3.5 text-slate-400" /> User ID
          </span>
          <span class="font-mono text-slate-900 font-bold">#{{ user?.id }}</span>
        </div>

        <div class="p-3 flex items-center justify-between">
          <span class="text-slate-500 flex items-center gap-1.5 font-medium">
            <ShieldCheck class="w-3.5 h-3.5 text-slate-400" /> Status Akun
          </span>
          <Badge variant="success" size="xs" dot>
            {{ user?.status === 'active' ? 'Aktif' : user?.status }}
          </Badge>
        </div>

        <div class="p-3 flex items-center justify-between">
          <span class="text-slate-500 flex items-center gap-1.5 font-medium">
            <User class="w-3.5 h-3.5 text-slate-400" /> Tipe Akun
          </span>
          <span class="text-slate-900 font-medium">
            {{ roles[0]?.display_name || roles[0]?.name || 'Staff Akademik' }}
          </span>
        </div>

        <div class="p-3 flex items-center justify-between">
          <span class="text-slate-500 flex items-center gap-1.5 font-medium">
            <Calendar class="w-3.5 h-3.5 text-slate-400" /> Institusi
          </span>
          <span class="text-slate-800 font-medium text-right truncate max-w-[200px]">
            IAI Al-Irsyad Jakarta
          </span>
        </div>
      </div>

      <div class="pt-2 flex justify-end">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          @click="$emit('update:open', false)"
        >
          Tutup
        </Button>
      </div>
    </div>
  </Modal>
</template>
