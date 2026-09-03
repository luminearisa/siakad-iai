<script setup lang="ts">
import { ServerCrash, ArrowLeft, RefreshCw } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'

function refresh() {
  window.location.reload()
}
</script>

<template>
  <div class="text-center max-w-md mx-auto p-6">
    <div class="w-14 h-14 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4 border border-amber-200">
      <ServerCrash class="w-7 h-7" />
    </div>

    <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-2">
      500 — Gangguan Sistem
    </h1>

    <p class="text-xs text-slate-600 mb-6 leading-relaxed">
      Terjadi kendala pada server saat memproses permintaan Anda. Tim administrator sedang menindaklanjuti kendala ini.
    </p>

    <div class="flex items-center justify-center gap-3">
      <Button variant="outline" size="md" @click="refresh">
        <RefreshCw class="w-3.5 h-3.5" />
        <span>Muat Ulang</span>
      </Button>

      <router-link to="/dashboard">
        <Button variant="primary" size="md">
          <ArrowLeft class="w-3.5 h-3.5" />
          <span>Dashboard</span>
        </Button>
      </router-link>
    </div>
  </div>
</template>
