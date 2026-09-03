<script setup lang="ts">
import { KeyRound, UserPlus, ShieldCheck, AlertCircle } from 'lucide-vue-next'
import type { Student } from '@/types/student'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import { formatDate } from '@/utils/format'

interface Props {
  student: Student
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
          Data Identitas Diri
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIM</dt>
          <dd class="col-span-2 font-mono font-semibold text-slate-900">{{ student.student_number }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Nama Lengkap</dt>
          <dd class="col-span-2 font-medium text-slate-900">{{ student.full_name }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Nama Panggilan</dt>
          <dd class="col-span-2 text-slate-800">{{ student.nickname || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Jenis Kelamin</dt>
          <dd class="col-span-2 text-slate-800 capitalize">{{ student.gender === 'male' ? 'Laki-Laki' : 'Perempuan' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Tempat, Tgl Lahir</dt>
          <dd class="col-span-2 text-slate-800">
            {{ student.birth_place || '-' }}, {{ formatDate(student.birth_date) }}
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIK (KTP)</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ student.national_id || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NISN</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ student.national_student_number || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Agama</dt>
          <dd class="col-span-2 text-slate-800">{{ student.religion || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Status Pernikahan</dt>
          <dd class="col-span-2 text-slate-800">{{ student.marital_status || '-' }}</dd>
        </div>
      </dl>
    </Card>

    <!-- 2. Kontak & Akun Pengguna -->
    <div class="space-y-5">
      <!-- Akun Pengguna & Portal Login -->
      <Card class="border-emerald-200/80">
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
              <KeyRound class="w-3.5 h-3.5 text-emerald-700" />
              Akun Pengguna & Portal Mahasiswa
            </h3>
            <span
              v-if="student.user"
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

        <div v-if="student.user" class="space-y-3 text-xs">
          <dl class="divide-y divide-slate-100">
            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Status Akun</dt>
              <dd class="col-span-2 flex items-center gap-1.5">
                <ShieldCheck class="w-4 h-4 text-emerald-600" />
                <span class="font-bold text-emerald-800">
                  {{ student.user.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Email Login</dt>
              <dd class="col-span-2 font-mono font-medium text-slate-900">{{ student.user.email }}</dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Role Sistem</dt>
              <dd class="col-span-2">
                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-medium text-3xs border border-slate-200">
                  Mahasiswa
                </span>
              </dd>
            </div>

            <div class="py-2 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Dibuat Pada</dt>
              <dd class="col-span-2 text-slate-700">{{ formatDate(student.user.created_at) }}</dd>
            </div>
          </dl>

          <div class="pt-2 border-t border-slate-100 flex items-center justify-end gap-2">
            <Button
              variant="outline"
              size="sm"
              class="text-2xs h-7 border-slate-300 text-slate-700 hover:bg-slate-50"
              @click="emit('toggle-account-status')"
            >
              {{ student.user.status === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
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
          <p class="font-bold text-amber-900">Mahasiswa ini belum memiliki akun login SIAKAD</p>
          <p class="text-2xs text-amber-800">
            Buat akun sekarang agar mahasiswa dapat login ke portal mahasiswa untuk mengisi KRS, melihat jadwal kuliah, KHS, dan presensi.
          </p>
          <Button
            variant="primary"
            size="sm"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-2xs gap-1 mt-1 shadow-2xs"
            @click="emit('create-account')"
          >
            <UserPlus class="w-3.5 h-3.5" />
            Buat Akun Portal Mahasiswa
          </Button>
        </div>
      </Card>

      <!-- Kontak & Alamat -->
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Kontak & Alamat Domisili
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Email</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ student.email || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">No. Telepon / HP</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ student.phone || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Kode Pos</dt>
            <dd class="col-span-2 font-mono text-slate-800">{{ student.postal_code || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Lengkap</dt>
            <dd class="col-span-2 text-slate-800 leading-relaxed">{{ student.address || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Catatan Sistem -->
      <Card v-if="student.notes">
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Catatan Khusus
          </h3>
        </template>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ student.notes }}</p>
      </Card>
    </div>
  </div>
</template>
