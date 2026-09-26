<script setup lang="ts">
import { CalendarClock, GraduationCap, ShieldCheck, Users } from 'lucide-vue-next'
import ToastContainer from '@/components/feedback/ToastContainer.vue'

const highlights = [
  {
    icon: GraduationCap,
    title: 'Kurikulum & KRS',
    desc: 'Pengisian kartu rencana studi dengan validasi otomatis.',
  },
  {
    icon: CalendarClock,
    title: 'Jadwal & Presensi',
    desc: 'Penjadwalan kelas dan rekap kehadiran realtime.',
  },
  {
    icon: Users,
    title: 'Multi Peran',
    desc: 'Satu portal untuk mahasiswa, dosen, dan administrasi.',
  },
]
</script>

<template>
  <div
    class="relative flex min-h-dvh flex-col bg-gradient-to-br from-[#052e16] via-[#062317] to-[#04140d] selection:bg-brand-500 selection:text-white lg:grid lg:grid-cols-2 xl:grid-cols-[11fr_9fr]"
  >
    <!-- Decorative backdrop -->
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.055)_1px,transparent_0)] [background-size:22px_22px]"
    />
    <div
      class="pointer-events-none absolute -left-24 -top-32 h-96 w-96 rounded-full bg-brand-500/15 blur-3xl"
    />
    <div
      class="pointer-events-none absolute -bottom-40 -right-24 h-[26rem] w-[26rem] rounded-full bg-gold-500/10 blur-3xl lg:hidden"
    />

    <!-- Branding panel (desktop only) -->
    <aside class="relative z-10 hidden flex-col justify-between p-10 lg:flex xl:p-14">
      <div class="flex items-center gap-3">
        <div
          class="flex h-11 w-11 items-center justify-center rounded-lg border border-white/20 bg-white p-1.5 shadow-md"
        >
          <img
            src="/images/logo.webp"
            alt="Logo IAI Al-Irsyad Jakarta"
            class="h-full w-auto object-contain"
          />
        </div>
        <div>
          <p class="text-sm font-bold uppercase tracking-tight text-white">Siakad Terpadu</p>
          <p class="text-2xs font-medium text-emerald-200/80">IAI Al-Irsyad Jakarta</p>
        </div>
      </div>

      <div class="max-w-md py-10">
        <span
          class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-2.5 py-1 text-2xs font-semibold text-emerald-200"
        >
          <ShieldCheck class="h-3 w-3" />
          Portal Resmi Akademik
        </span>

        <h1 class="mt-5 text-2xl font-black leading-tight tracking-tight text-white">
          Sistem Informasi Akademik
          <span class="block text-emerald-300">Terpadu &amp; Terintegrasi</span>
        </h1>

        <div class="mt-4 h-0.5 w-12 rounded-full bg-gold-500" />

        <p class="mt-4 text-sm leading-relaxed text-emerald-100/70">
          Satu pintu untuk mengelola kurikulum, jadwal, presensi, dan KRS bagi seluruh civitas
          akademika.
        </p>

        <ul class="mt-8 space-y-3.5">
          <li v-for="item in highlights" :key="item.title" class="flex items-start gap-3">
            <span
              class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-emerald-400/20 bg-emerald-400/10 text-emerald-300"
            >
              <component :is="item.icon" class="h-3.5 w-3.5" />
            </span>
            <span class="text-xs leading-relaxed">
              <span class="font-semibold text-white">{{ item.title }}</span>
              <span class="block text-emerald-100/55">{{ item.desc }}</span>
            </span>
          </li>
        </ul>
      </div>

      <p class="text-2xs text-emerald-100/35">
        &copy; 2026 Institut Agama Islam Al-Irsyad Jakarta
      </p>
    </aside>

    <!-- Form panel -->
    <main
      class="relative z-10 flex flex-col justify-center px-4 py-8 sm:px-8 lg:bg-slate-50 lg:px-10 xl:px-16"
    >
      <div class="mx-auto w-full max-w-[26rem]">
        <!-- Compact brand row (mobile / tablet) -->
        <div class="mb-6 flex items-center justify-center gap-3 lg:hidden">
          <div
            class="flex h-11 w-11 items-center justify-center rounded-lg border border-emerald-100 bg-white p-1.5 shadow-md"
          >
            <img
              src="/images/logo.webp"
              alt="Logo IAI Al-Irsyad Jakarta"
              class="h-full w-auto object-contain"
            />
          </div>
          <div>
            <p class="text-sm font-bold uppercase tracking-tight text-white">Siakad Terpadu</p>
            <p class="text-2xs font-medium text-emerald-300/80">IAI Al-Irsyad Jakarta</p>
          </div>
        </div>

        <div
          class="rounded-xl border border-white/10 bg-white p-6 shadow-modal sm:p-7 lg:border-slate-200 lg:shadow-elevated"
        >
          <router-view />
        </div>

        <p class="mt-6 text-center text-2xs tracking-wide text-emerald-200/50 lg:text-slate-400">
          Sistem Informasi Akademik &bull; &copy; 2026 IAI Al-Irsyad Jakarta
        </p>
      </div>
    </main>

    <ToastContainer />
  </div>
</template>
