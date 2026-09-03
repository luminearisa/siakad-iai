<script setup lang="ts">
import { ref } from 'vue'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const ktmTitle = ref('KARTU TANDA MAHASISWA')
const campusName = ref('AMIK TARUNA PROBOLINGGO')
const showBarcode = ref(true)
const showQrCode = ref(true)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Pengaturan Kartu Tanda Mahasiswa (KTM)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Template dan konfigurasi pencetakan kartu tanda mahasiswa</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card class="p-5 border border-slate-200/80 shadow-2xs space-y-4 text-xs">
        <h3 class="font-bold text-slate-900 text-sm border-b pb-2">Layout & Format Cetak</h3>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Judul Header KTM</label>
          <Input v-model="ktmTitle" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nama Institusi</label>
          <Input v-model="campusName" />
        </div>
        <div class="flex items-center gap-4 pt-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="showBarcode" class="rounded text-brand-600 focus:ring-brand-500" />
            <span>Tampilkan Barcode NIM</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="showQrCode" class="rounded text-brand-600 focus:ring-brand-500" />
            <span>Tampilkan QR Validasi</span>
          </label>
        </div>
        <div class="pt-4">
          <Button variant="primary" size="sm" class="bg-brand-700 hover:bg-brand-800 text-white">
            Simpan Template
          </Button>
        </div>
      </Card>

      <Card class="p-5 border border-slate-200/80 shadow-2xs">
        <h3 class="font-bold text-slate-900 text-sm border-b pb-2 mb-4">Preview Kartu Tanda Mahasiswa</h3>
        <div class="w-full max-w-sm mx-auto aspect-[85.6/53.98] bg-gradient-to-br from-brand-900 via-brand-800 to-brand-950 rounded-xl p-4 text-white shadow-lg relative overflow-hidden flex flex-col justify-between">
          <div class="flex items-center justify-between border-b border-white/20 pb-2">
            <div>
              <div class="text-3xs font-semibold tracking-wider text-amber-300">{{ ktmTitle }}</div>
              <div class="text-xs font-bold">{{ campusName }}</div>
            </div>
          </div>
          <div class="flex items-center gap-3 my-auto">
            <div class="w-14 h-18 bg-white/20 border border-white/40 rounded flex items-center justify-center text-3xs">
              FOTO
            </div>
            <div class="text-3xs space-y-0.5">
              <div class="font-mono text-amber-200 font-bold">202601001</div>
              <div class="font-bold text-2xs">AHMAD FAUZI</div>
              <div class="text-white/80">Teknik Informatika (S1)</div>
            </div>
          </div>
          <div class="text-4xs text-white/60 text-right">
            SIAKAD PERGURUAN TINGGI
          </div>
        </div>
      </Card>
    </div>
  </PageContainer>
</template>
