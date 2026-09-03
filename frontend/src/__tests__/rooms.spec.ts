import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import RoomStatusBadge from '../pages/rooms/components/RoomStatusBadge.vue'
import RoomTypeBadge from '../pages/rooms/components/RoomTypeBadge.vue'
import RoomCapacityBadge from '../pages/rooms/components/RoomCapacityBadge.vue'
import RoomHeader from '../pages/rooms/components/RoomHeader.vue'
import type { Room } from '../types/room'

const mockRoom: Room = {
  id: 1,
  institution_id: 1,
  code: 'R.101',
  name: 'Ruang Kuliah Teori 101',
  building: 'Gedung Tarbiyah A',
  floor: 1,
  capacity: 45,
  room_type: 'classroom',
  status: 'active',
  institution: {
    id: 1,
    code: 'IAI-HQ',
    name: 'Institut Agama Islam Kampus Pusat',
    created_at: '2025-01-01T00:00:00Z',
  },
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Room Management Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('RoomStatusBadge', () => {
    it('renders active status badge correctly', () => {
      const wrapper = mount(RoomStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Tersedia (Aktif)')
    })

    it('renders maintenance status badge correctly', () => {
      const wrapper = mount(RoomStatusBadge, {
        props: { status: 'maintenance' },
      })
      expect(wrapper.text()).toContain('Pemeliharaan')
    })

    it('renders inactive status badge correctly', () => {
      const wrapper = mount(RoomStatusBadge, {
        props: { status: 'inactive' },
      })
      expect(wrapper.text()).toContain('Non-Aktif')
    })
  })

  describe('RoomTypeBadge', () => {
    it('renders classroom type badge correctly', () => {
      const wrapper = mount(RoomTypeBadge, {
        props: { type: 'classroom' },
      })
      expect(wrapper.text()).toContain('Ruang Kelas Teori')
    })

    it('renders laboratory type badge correctly', () => {
      const wrapper = mount(RoomTypeBadge, {
        props: { type: 'laboratory' },
      })
      expect(wrapper.text()).toContain('Laboratorium')
    })

    it('renders auditorium type badge correctly', () => {
      const wrapper = mount(RoomTypeBadge, {
        props: { type: 'auditorium' },
      })
      expect(wrapper.text()).toContain('Auditorium / Aula')
    })
  })

  describe('RoomCapacityBadge', () => {
    it('renders room seats capacity correctly', () => {
      const wrapper = mount(RoomCapacityBadge, {
        props: { capacity: 45 },
      })
      expect(wrapper.text()).toContain('45 Kursi')
    })
  })

  describe('RoomHeader', () => {
    it('renders room code, name, building location, and badges', () => {
      const wrapper = mount(RoomHeader, {
        props: { room: mockRoom },
        global: {
          stubs: {
            'router-link': true,
          },
        },
      })

      expect(wrapper.text()).toContain('R.101')
      expect(wrapper.text()).toContain('Ruang Kuliah Teori 101')
      expect(wrapper.text()).toContain('Gedung Tarbiyah A')
      expect(wrapper.text()).toContain('45 Kursi')
      expect(wrapper.text()).toContain('Tersedia (Aktif)')
    })
  })
})
