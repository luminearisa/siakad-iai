import { Comment, Fragment, Text } from 'vue'

export interface SelectOption {
  label: string
  value: string | number | null
  disabled?: boolean
}

/**
 * Ambil teks dari sebuah vnode secara rekursif.
 * Dipakai untuk membaca label <option> yang isinya bisa berupa interpolasi,
 * <template v-if>, atau gabungan keduanya.
 */
export function vnodeText(node: unknown): string {
  if (node === null || node === undefined || node === false) return ''
  if (typeof node === 'string' || typeof node === 'number') return String(node)
  if (Array.isArray(node)) return node.map(vnodeText).join('')

  const vnode = node as { type?: unknown; children?: unknown }
  if (vnode.type === Comment) return ''
  if (vnode.type === Fragment || vnode.type === Text) return vnodeText(vnode.children)
  if (typeof vnode.children === 'string') return vnode.children
  if (Array.isArray(vnode.children)) return vnode.children.map(vnodeText).join('')
  return ''
}

/**
 * Baca daftar opsi dari vnode anak <Select>.
 *
 * Banyak halaman menulis opsi sebagai anak <option> (bukan lewat prop `:options`).
 * Slot dirender di scope induk, sehingga `v-for` pada <option> sudah diekspansi
 * menjadi satu vnode per baris sebelum sampai ke sini — inilah yang membuat daftar
 * panjang ikut terdeteksi tanpa mengubah setiap halaman.
 */
export function readSlotOptions(raw: unknown[] | undefined | null): SelectOption[] {
  if (!Array.isArray(raw) || raw.length === 0) return []

  const out: SelectOption[] = []

  const visit = (nodes: unknown[]) => {
    for (const node of nodes) {
      if (node === null || node === undefined || node === false) continue
      if (typeof node === 'string' || typeof node === 'number') continue
      if (Array.isArray(node)) {
        visit(node)
        continue
      }

      const vnode = node as { type?: unknown; props?: Record<string, any>; children?: unknown }
      if (vnode.type === Fragment) {
        visit(Array.isArray(vnode.children) ? vnode.children : [])
        continue
      }
      if (vnode.type === Comment || vnode.type === Text) continue

      if (typeof vnode.type === 'string' && vnode.type.toLowerCase() === 'option') {
        // Bedakan "tidak punya prop value" (-> '') dengan ":value=\"null\"" (-> null).
        // Halaman memakai <option :value="null"> sebagai placeholder, dan nilainya
        // harus tetap null agar tidak mengubah tipe field form.
        const hasValue = !!vnode.props && 'value' in vnode.props
        out.push({
          value: (hasValue ? vnode.props!.value : '') as string | number | null,
          label: vnodeText(vnode.children).replace(/\s+/g, ' ').trim(),
          disabled: !!vnode.props?.disabled,
        })
        continue
      }

      // <optgroup> dan pembungkus lain: telusuri isinya.
      if (Array.isArray(vnode.children)) visit(vnode.children)
    }
  }

  visit(raw)
  return out
}
