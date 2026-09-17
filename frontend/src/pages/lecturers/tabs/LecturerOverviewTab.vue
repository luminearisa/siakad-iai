<script setup lang="ts">
import { KeyRound, UserPlus, ShieldCheck, AlertCircle } from 'lucide-vue-next'
import type { Lecturer } from '@/types/lecturer'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import { formatDate } from '@/utils/format'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'create-account'): void
  (e: 'reset-password'): void
  (e: 'toggle-account-status'): void
}>()
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- 1. Biodata & Identitas -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Data Identitas Pribadi
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Nama Lengkap</dt>
          <dd class="col-span-2 font-bold text-slate-900">{{ lecturer.full_name }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Gelar Akademik</dt>
          <dd class="col-span-2 font-medium text-slate-800">{{ lecturer.academic_degree || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIDN</dt>
          <dd class="col-span-2 font-mono font-semibold text-slate-900">{{ lecturer.nidn || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIDK</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.nidk || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIP</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.nip || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Kode Dosen</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.lecturer_number || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Jenis Kelamin</dt>
          <dd class="col-span-2 text-slate-800 capitalize">{{ lecturer.gender === 'male' ? 'Laki-Laki' : 'Perempuan' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Tempat, Tgl Lahir</dt>
          <dd class="col-span-2 text-slate-800">
            {{ lecturer.birth_place || '-' }}, {{ formatDate(lecturer.birth_date) }}
          </dd>
        </div>
      </dl>
    </Card>

    <!-- 2. Kontak & Alamat -->
    <div class="space-y-5">
      <!-- Akun Pengguna & Portal Login -->
      <Card class="border-emerald-200/80">
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
              <KeyRound class="w-3.5 h-3.5 text-emerald-700" />
              Akun Pengguna & Portal Dosen
            </h3>
            <span
              v-if="lecturer.user"
              class="px-2 py-0.5 rounded-full text-3xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
            >
              Aktif Terdaftar
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-3xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"
            >
              Belum Punya Akun
            </span>
          </div>
        </template>

        <div v-if="lecturer.user" class="space-y-3 text-xs">
          <dl class="divide-y divide-slate-100">
            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Status Akun</dt>
              <dd class="col-span-2 flex items-center gap-1.5">
                <ShieldCheck class="w-4 h-4 text-emerald-600" />
                <span class="font-bold text-emerald-800">
                  {{ lecturer.user.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Email Login</dt>
              <dd class="col-span-2 font-mono font-medium text-slate-900">{{ lecturer.user.email }}</dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Role Sistem</dt>
              <dd class="col-span-2">
                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-medium text-3xs border border-slate-200">
                  Dosen
                </span>
              </dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Dibuat Pada</dt>
              <dd class="col-span-2 text-slate-700">{{ formatDate(lecturer.user.created_at) }}</dd>
            </div>
          </dl>

          <div class="pt-2 border-t border-slate-100 flex items-center justify-end gap-2">
            <Button
              variant="outline"
              size="sm"
              class="text-2xs h-7 border-slate-300 text-slate-700 hover:bg-slate-50"
              @click="emit('toggle-account-status')"
            >
              {{ lecturer.user.status === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
            </Button>
            <Button
              variant="primary"
              size="sm"
              class="text-2xs h-7 bg-emerald-700 hover:bg-emerald-800 text-white font-medium gap-1"
              @click="emit('reset-password')"
            >
              <KeyRound class="w-3 h-3" />
              Ganti Password
            </Button>
          </div>
        </div>

        <div v-else class="p-4 text-center rounded-lg bg-amber-50/50 border border-amber-200/80 text-xs space-y-2">
          <AlertCircle class="w-6 h-6 text-amber-600 mx-auto" />
          <p class="font-bold text-amber-900">Dosen ini belum memiliki akun login SIAKAD</p>
          <p class="text-2xs text-amber-800">
            Buat akun sekarang agar dosen dapat login ke portal untuk mengelola kelas, presensi, dan input nilai mahasiswa.
          </p>
          <Button
            variant="primary"
            size="sm"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-2xs gap-1 mt-1 shadow-2xs"
            @click="emit('create-account')"
          >
            <UserPlus class="w-3.5 h-3.5" />
            Buat Akun Portal Dosen
          </Button>
        </div>
      </Card>

      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Kontak & Alamat Domisili
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Email</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ lecturer.email || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">No. Telepon / HP</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ lecturer.phone || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Lengkap</dt>
            <dd class="col-span-2 text-slate-800 leading-relaxed">{{ lecturer.address || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Catatan Tambahan -->
      <Card v-if="lecturer.notes">
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Catatan Tambahan
          </h3>
        </template>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ lecturer.notes }}</p>
      </Card>
    </div>
  </div>
</template>
