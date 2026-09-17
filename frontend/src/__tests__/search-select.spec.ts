import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref, nextTick } from 'vue'
import Select from '@/components/ui/Select.vue'
import SearchSelect from '@/components/ui/SearchSelect.vue'

function makeOptions(count: number) {
  return Array.from({ length: count }, (_, i) => ({ label: `Opsi ${i + 1}`, value: i + 1 }))
}

/**
 * Halaman-halaman lama menulis opsi sebagai anak <option> (bukan prop :options).
 * Komponen ini harus tetap bisa membaca daftar tersebut agar tidak perlu
 * mengubah setiap halaman satu per satu.
 */
function mountWithSlotOptions(items: { id: number; name: string }[]) {
  const Parent = defineComponent({
    setup() {
      const value = ref<string | number | null>('')
      return () =>
        h(
          Select,
          {
            modelValue: value.value,
            'onUpdate:modelValue': (v: string | number | null) => {
              value.value = v
            },
          },
          () => [
            h('option', { value: '' }, '-- Pilih Mahasiswa --'),
            ...items.map((item) => h('option', { value: item.id }, item.name)),
          ],
        )
    },
  })
  return mount(Parent)
}

function click(el: Element | null) {
  el?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

function optionNodes(): Element[] {
  return Array.from(document.body.querySelectorAll('[role="option"]'))
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('Select — otomatis memakai search select untuk data banyak', () => {
  it('memakai <select> bawaan untuk daftar pendek', () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(3) },
    })

    expect(wrapper.find('select').exists()).toBe(true)
    expect(wrapper.findComponent(SearchSelect).exists()).toBe(false)
  })

  it('beralih ke search select saat jumlah opsi melewati ambang', () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(25) },
    })

    expect(wrapper.find('select').exists()).toBe(false)
    expect(wrapper.findComponent(SearchSelect).exists()).toBe(true)
  })

  it('menghormati ambang kustom lewat prop searchThreshold', () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(5), searchThreshold: 3 },
    })

    expect(wrapper.findComponent(SearchSelect).exists()).toBe(true)
  })

  it('searchable=false memaksa <select> bawaan walau opsi banyak', () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(40), searchable: false },
    })

    expect(wrapper.find('select').exists()).toBe(true)
    expect(wrapper.findComponent(SearchSelect).exists()).toBe(false)
  })

  it('membaca opsi dari slot dan mengaktifkan pencarian untuk daftar panjang', async () => {
    const items = Array.from({ length: 30 }, (_, i) => ({ id: i + 1, name: `Mahasiswa ${i + 1}` }))
    const wrapper = mountWithSlotOptions(items)

    expect(wrapper.find('select').exists()).toBe(false)
    expect(wrapper.findComponent(SearchSelect).exists()).toBe(true)

    await wrapper.find('[role="combobox"]').trigger('click')
    await nextTick()

    // 30 mahasiswa + 1 opsi placeholder yang ikut terbaca dari slot
    expect(optionNodes()).toHaveLength(31)
    const text = document.body.textContent ?? ''
    expect(text).toContain('Mahasiswa 1')
    expect(text).toContain('Mahasiswa 30')
    expect(text).toContain('-- Pilih Mahasiswa --')
  })

  it('daftar slot yang pendek tetap memakai <select> bawaan', () => {
    const wrapper = mountWithSlotOptions([
      { id: 1, name: 'Mahasiswa A' },
      { id: 2, name: 'Mahasiswa B' },
    ])

    expect(wrapper.find('select').exists()).toBe(true)
    expect(wrapper.findComponent(SearchSelect).exists()).toBe(false)
  })
})

describe('SearchSelect — pencarian & pemilihan', () => {
  it('memfilter opsi sesuai kata kunci dan mengirim nilai aslinya (bukan string)', async () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(30) },
    })

    await wrapper.find('[role="combobox"]').trigger('click')
    await nextTick()

    const input = document.body.querySelector<HTMLInputElement>('input[type="text"]')
    expect(input).toBeTruthy()

    input!.value = 'Opsi 12'
    input!.dispatchEvent(new Event('input'))
    await nextTick()

    expect(optionNodes()).toHaveLength(1)

    click(optionNodes()[0])
    await nextTick()

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toBe(12)
    expect(typeof emitted![0][0]).toBe('number')
  })

  it('menampilkan label opsi terpilih dan menyediakan tombol kosongkan', async () => {
    const wrapper = mount(Select, {
      props: { modelValue: 7, options: makeOptions(30) },
    })

    expect(wrapper.find('[role="combobox"]').text()).toContain('Opsi 7')

    const clearButton = wrapper.find('button[title="Kosongkan"]')
    expect(clearButton.exists()).toBe(true)

    await clearButton.trigger('click')
    await nextTick()

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toBeNull()
  })

  it('mencocokkan nilai string dengan id numerik pada opsi', () => {
    const wrapper = mount(Select, {
      props: { modelValue: '7', options: makeOptions(30) },
    })

    // modelValue "7" (string, mis. dari query URL) harus tetap tampil sebagai Opsi 7
    expect(wrapper.find('[role="combobox"]').text()).toContain('Opsi 7')
  })

  it('membuka daftar dengan keyboard (ArrowDown) dan memilih dengan Enter', async () => {
    const wrapper = mount(Select, {
      props: { modelValue: null, options: makeOptions(30) },
    })

    const trigger = wrapper.find('[role="combobox"]')
    await trigger.trigger('keydown', { key: 'ArrowDown' })
    await nextTick()

    expect(optionNodes().length).toBe(30)

    await trigger.trigger('keydown', { key: 'ArrowDown' })
    await trigger.trigger('keydown', { key: 'Enter' })
    await nextTick()

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toBe(2)
  })
})
