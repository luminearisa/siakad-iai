/**
 * Verifikasi pembacaan opsi dari slot (<Select> dengan anak <option>).
 * Tidak butuh browser / vitest / Vite — cukup Node + vnode asli dari Vue.
 *
 * Jalankan: node --experimental-strip-types verify-slot-options.mts
 */
import { h, Fragment, Comment, Text } from 'vue'
import { readSlotOptions, vnodeText } from './src/components/ui/slotOptions.ts'

const results: { name: string; ok: boolean; detail?: string }[] = []
function check(name: string, ok: boolean, detail?: string) {
  results.push({ name, ok, detail })
}

// 1. satu <option> statis
{
  const opts = readSlotOptions([h('option', { value: '' }, '-- Pilih Mahasiswa --')])
  check('1 opsi statis terbaca', opts.length === 1, JSON.stringify(opts))
  check('  value & label benar', opts[0]?.value === '' && opts[0]?.label === '-- Pilih Mahasiswa --', JSON.stringify(opts[0]))
}

// 2. hasil ekspansi v-for (array vnode) + placeholder
{
  const items = Array.from({ length: 30 }, (_, i) => ({ id: i + 1, name: `Mahasiswa ${i + 1}` }))
  const nodes = [
    h('option', { value: '' }, '-- Pilih Mahasiswa --'),
    ...items.map((it) => h('option', { value: it.id }, it.name)),
  ]
  const opts = readSlotOptions(nodes)
  check('30 item + placeholder = 31 opsi', opts.length === 31, `dapat ${opts.length}`)
  check('  id numerik dipertahankan', opts[1]?.value === 1 && typeof opts[1]?.value === 'number', JSON.stringify(opts[1]))
  check('  label item terakhir benar', opts[30]?.label === 'Mahasiswa 30', JSON.stringify(opts[30]))
}

// 3. label dengan interpolasi + <template v-if> (pola yang dipakai halaman dosen)
{
  const opts = readSlotOptions([
    h('option', { value: 7 }, ['Dr. Ahmad ', h(Fragment, null, [', M.Kom'])]),
  ])
  check('label gabungan teks + Fragment terbaca', opts[0]?.label === 'Dr. Ahmad , M.Kom', JSON.stringify(opts[0]))
}

// 4. v-for yang dibungkus Fragment
{
  const inner = Array.from({ length: 12 }, (_, i) => h('option', { value: i + 1 }, `Kelas ${i + 1}`))
  const opts = readSlotOptions([h(Fragment, null, inner)])
  check('opsi di dalam Fragment terbaca', opts.length === 12, `dapat ${opts.length}`)
}

// 5. optgroup ditelusuri
{
  const opts = readSlotOptions([
    h('optgroup', { label: 'Ganjil' }, [
      h('option', { value: 1 }, 'Semester 1'),
      h('option', { value: 2 }, 'Semester 2'),
    ]),
  ])
  check('opsi di dalam optgroup terbaca', opts.length === 2, JSON.stringify(opts))
}

// 6. komentar / null / false diabaikan
{
  const opts = readSlotOptions([h(Comment, null, 'catatan'), null, false, h('option', { value: 1 }, 'A')])
  check('komentar & nilai kosong diabaikan', opts.length === 1 && opts[0]?.label === 'A', JSON.stringify(opts))
}

// 7. opsi disabled terbaca
{
  const opts = readSlotOptions([h('option', { value: 5, disabled: true }, 'Terisi')])
  check('flag disabled terbaca', opts[0]?.disabled === true, JSON.stringify(opts[0]))
}

// 8. nilai null / tanpa value -> '' (perilaku placeholder)
{
  const opts = readSlotOptions([h('option', { value: null }, 'Pilih Periode'), h('option', null, 'Tanpa value')])
  check('value null dipertahankan sebagai null', opts[0]?.value === null, JSON.stringify(opts[0]))
  check('tanpa value jadi string kosong', opts[1]?.value === '', JSON.stringify(opts[1]))
}

// 9. daftar kosong
{
  check('input kosong -> []', readSlotOptions([]).length === 0)
  check('input undefined -> []', readSlotOptions(undefined).length === 0)
  check('input null -> []', readSlotOptions(null).length === 0)
}

// 10. spasi berlebih dirapikan
{
  const opts = readSlotOptions([h('option', { value: 1 }, ['  Mata   Kuliah ', 'Agama  '])])
  check('spasi berlebih dirapikan', opts[0]?.label === 'Mata Kuliah Agama', JSON.stringify(opts[0]))
}

// 11. vnodeText langsung
{
  check('vnodeText pada teks mentah', vnodeText('halo') === 'halo')
  check('vnodeText pada Text vnode', vnodeText(h(Text, null, 'teks')) === 'teks')
  check('vnodeText pada Comment', vnodeText(h(Comment, null, 'x')) === '')
}

let failed = 0
for (const r of results) {
  if (!r.ok) failed++
  console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${r.name}${r.ok || !r.detail ? '' : `\n        detail: ${r.detail}`}`)
}
console.log(`\n${results.length - failed}/${results.length} passed`)
process.exit(failed === 0 ? 0 : 1)
