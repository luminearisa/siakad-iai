<script setup lang="ts">
import ToastContainer from '@/components/feedback/ToastContainer.vue'
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-[#052e16] via-[#062317] to-[#041a11] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 selection:bg-brand-500 selection:text-white relative overflow-hidden">
    <!-- Ambient glow -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-6 relative z-10">
      <div class="inline-flex items-center justify-center p-2.5 rounded-2xl bg-white shadow-md border border-emerald-100 mb-3">
        <img
          src="/images/logo.webp"
          alt="Logo IAI Al-Irsyad Jakarta"
          class="h-14 w-auto object-contain"
        />
      </div>
      <h2 class="text-xl font-black tracking-tight text-white uppercase">
        SIAKAD TERPADU
      </h2>
      <p class="mt-1 text-xs text-emerald-300 font-medium tracking-wide">
        Institut Agama Islam Al-Irsyad Jakarta
      </p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
      <div class="bg-white py-8 px-6 shadow-modal rounded-2xl sm:px-8 border border-emerald-950/10">
        <router-view />
      </div>

      <p class="text-center text-3xs text-emerald-200/60 mt-6 tracking-wide">
        Sistem Informasi Akademik &bull; &copy; 2026 Institut Agama Islam Al-Irsyad Jakarta
      </p>
    </div>

    <ToastContainer />
  </div>
</template>
