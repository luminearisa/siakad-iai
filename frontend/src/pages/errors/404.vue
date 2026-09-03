<script setup lang="ts">
import { FileQuestion, ArrowLeft } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
</script>

<template>
  <div class="text-center max-w-md mx-auto p-6">
    <div class="w-14 h-14 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-4 border border-slate-200">
      <FileQuestion class="w-7 h-7" />
    </div>

    <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-2">
      404 — Halaman Tidak Ditemukan
    </h1>

    <p class="text-xs text-slate-600 mb-6 leading-relaxed">
      Halaman atau resource yang Anda cari tidak dapat ditemukan atau telah dipindahkan.
    </p>

    <div class="flex items-center justify-center gap-3">
      <router-link to="/dashboard">
        <Button variant="primary" size="md">
          <ArrowLeft class="w-3.5 h-3.5" />
          <span>Kembali ke Dashboard</span>
        </Button>
      </router-link>
    </div>
  </div>
</template>
