import { computed, type ComputedRef } from 'vue'
import type { ExportOption } from '@/components/data-display/ExportMenu.vue'
import { integratorService } from '@/services/api/integrator'

interface FeederExportConfig {
  /**
   * Parameter yang diteruskan ke backend — biasanya filter yang sedang aktif di
   * halaman (mis. `{ semester_id: semesterId.value }`). Parameter di luar daftar
   * putih dataset diabaikan server, jadi aman mengirim objek apa adanya.
   */
  params?: () => Record<string, unknown>
  /**
   * Keterangan singkat di bawah pilihan menu, mis. "seluruh mahasiswa" atau
   * "sesuai filter halaman (semester + prodi)".
   */
  note?: string
}

/**
 * Tombol "Export as…" untuk data pelaporan PDDikti / Neo Feeder di halaman data
 * SIAKAD (mahasiswa, dosen, mata kuliah, kurikulum, kelas, KRS, nilai, lulusan, ...).
 *
 * Contoh pemakaian di halaman:
 *
 * ```ts
 * const { exportOptions } = useFeederExport('students', 'Data mahasiswa', {
 *   params: () => ({ study_program_id: studyProgramId.value || undefined, search: search.value }),
 *   note: 'sesuai filter halaman',
 * })
 * ```
 *
 * ```html
 * <ExportMenu :options="exportOptions" />
 * ```
 *
 * Tiga pilihan tersedia: CSV siap dibuka di Excel, CSV dengan nama kolom mentah
 * (`nim`, `sks`, ...) untuk dicocokkan dengan berkas yang ditarik feeder, dan JSON
 * untuk diarsipkan/diolah skrip. Hak akses mengikuti permission halaman asalnya dan
 * setiap unduhan tercatat di audit log.
 */
export function useFeederExport(
  dataset: string,
  label: string,
  config: FeederExportConfig = {}
): { exportOptions: ComputedRef<ExportOption[]> } {
  const params = (): Record<string, unknown> => {
    const resolved = config.params?.() ?? {}

    // Buang nilai kosong supaya backend melihat "tanpa filter", bukan string kosong.
    return Object.fromEntries(
      Object.entries(resolved).filter(([, value]) => value !== undefined && value !== null && value !== '')
    )
  }

  const note = config.note ?? 'seluruh data'

  const exportOptions = computed<ExportOption[]>(() => [
    {
      label: `${label} — CSV (Excel)`,
      description: `${note} · judul kolom bahasa Indonesia`,
      run: () => integratorService.exportDataset(dataset, params(), 'csv'),
    },
    {
      label: `${label} — CSV kolom feeder`,
      description: `${note} · nama kolom mentah (nim, sks, nilai_angka, …)`,
      run: () => integratorService.exportDataset(dataset, params(), 'csv', true),
    },
    {
      label: `${label} — JSON`,
      description: `${note} · untuk arsip atau diolah skrip`,
      run: () => integratorService.exportDataset(dataset, params(), 'json'),
    },
  ])

  return { exportOptions }
}
