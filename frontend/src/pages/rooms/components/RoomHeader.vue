<script setup lang="ts">
import { ArrowLeft, Edit3, Trash2, RefreshCw, Building2 } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Room } from '@/types/room'
import Button from '@/components/ui/Button.vue'
import RoomTypeBadge from './RoomTypeBadge.vue'
import RoomStatusBadge from './RoomStatusBadge.vue'
import RoomCapacityBadge from './RoomCapacityBadge.vue'

interface Props {
  room: Room
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'change-status'): void
  (e: 'delete'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <!-- Left Info -->
      <div class="flex items-start sm:items-center gap-3.5">
        <div class="w-12 h-12 rounded-lg bg-brand-50 text-brand-900 flex items-center justify-center shrink-0 border border-brand-100">
          <Building2 class="w-6 h-6" />
        </div>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              {{ room.code }}
            </span>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ room.name }}
            </h1>
            <RoomStatusBadge :status="room.status" size="xs" />
          </div>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500">
            <span v-if="room.building" class="font-medium text-slate-800">
              {{ room.building }} <template v-if="room.floor">· Lantai {{ room.floor }}</template>
            </span>
            <span v-if="room.building">•</span>
            <RoomTypeBadge :type="room.room_type" size="xs" />
            <span>•</span>
            <RoomCapacityBadge :capacity="room.capacity" />
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/rooms">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <Button
          v-if="can('rooms.change_status')"
          variant="outline"
          size="sm"
          @click="emit('change-status')"
        >
          <RefreshCw class="w-3.5 h-3.5 text-slate-500" />
          <span>Ubah Status</span>
        </Button>

        <router-link v-if="can('rooms.update')" :to="`/rooms/${room.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit Ruangan</span>
          </Button>
        </router-link>

        <Button
          v-if="can('rooms.delete')"
          variant="ghost"
          size="sm"
          class="text-rose-600 hover:bg-rose-50"
          @click="emit('delete')"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  </div>
</template>
