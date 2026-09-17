<script setup lang="ts">
import { Info } from 'lucide-vue-next'

interface Props {
  /** What the page is limited to, e.g. "kelas perkuliahan" or "jadwal perkuliahan". */
  subject: string
}

defineProps<Props>()
</script>

<template>
  <div class="flex items-start gap-2 p-3 bg-brand-50 border border-brand-200 rounded-lg text-xs text-brand-900">
    <Info class="w-4 h-4 shrink-0 mt-0.5 text-brand-700" />
    <p class="leading-relaxed">
      Anda masuk sebagai <strong>Dosen</strong>, sehingga halaman ini hanya menampilkan
      <strong>{{ subject }}</strong> yang Anda ampu. Hubungi Admin Akademik bila ada data yang tidak sesuai.
    </p>
  </div>
</template>
