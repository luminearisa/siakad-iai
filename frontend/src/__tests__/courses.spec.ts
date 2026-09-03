import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CourseTypeBadge from '../pages/courses/components/CourseTypeBadge.vue'
import CourseHeader from '../pages/courses/components/CourseHeader.vue'
import CoursePrerequisiteList from '../pages/courses/components/CoursePrerequisiteList.vue'
import type { Course } from '../types/course'

const mockCourse: Course = {
  id: 1,
  code: 'INF-201',
  name: 'Struktur Data & Algoritma',
  short_name: 'Strukdat',
  description: 'Membahas representasi data linier, non-linier, graf, pohon biner, dan algoritma sorting.',
  credits: 3,
  theory_credits: 2,
  practical_credits: 1,
  type: 'mixed',
  category: 'MKWPS',
  status: 'active',
  prerequisites: [
    {
      id: 2,
      code: 'INF-101',
      name: 'Dasar Pemrograman',
      credits: 3,
      theory_credits: 2,
      practical_credits: 1,
      type: 'mixed',
      status: 'active',
      pivot: { minimum_grade: 'C' },
      created_at: '2025-01-01T00:00:00Z',
      updated_at: '2025-01-01T00:00:00Z',
    },
  ],
  dependents: [
    {
      id: 3,
      code: 'INF-301',
      name: 'Rekayasa Perangkat Lunak',
      credits: 3,
      theory_credits: 3,
      practical_credits: 0,
      type: 'theory',
      status: 'active',
      created_at: '2025-01-01T00:00:00Z',
      updated_at: '2025-01-01T00:00:00Z',
    },
  ],
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Course Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('CourseTypeBadge', () => {
    it('renders theory type badge correctly', () => {
      const wrapper = mount(CourseTypeBadge, {
        props: { type: 'theory' },
      })
      expect(wrapper.text()).toContain('Teori')
    })

    it('renders practical type badge correctly', () => {
      const wrapper = mount(CourseTypeBadge, {
        props: { type: 'practical' },
      })
      expect(wrapper.text()).toContain('Praktikum')
    })

    it('renders mixed type badge correctly', () => {
      const wrapper = mount(CourseTypeBadge, {
        props: { type: 'mixed' },
      })
      expect(wrapper.text()).toContain('Teori & Praktikum')
    })
  })

  describe('CourseHeader', () => {
    it('renders course code, name, credits, and status', () => {
      const wrapper = mount(CourseHeader, {
        props: { course: mockCourse },
      })

      expect(wrapper.text()).toContain('INF-201')
      expect(wrapper.text()).toContain('Struktur Data & Algoritma')
      expect(wrapper.text()).toContain('3 SKS')
      expect(wrapper.text()).toContain('Teori: 2 SKS')
      expect(wrapper.text()).toContain('Praktik: 1 SKS')
      expect(wrapper.text()).toContain('Aktif')
    })
  })

  describe('CoursePrerequisiteList', () => {
    it('renders prerequisite courses with minimum grade', () => {
      const wrapper = mount(CoursePrerequisiteList, {
        props: { course: mockCourse },
      })

      expect(wrapper.text()).toContain('INF-101')
      expect(wrapper.text()).toContain('Dasar Pemrograman')
      expect(wrapper.text()).toContain('Nilai Minimal: C')
    })

    it('renders dependent courses', () => {
      const wrapper = mount(CoursePrerequisiteList, {
        props: { course: mockCourse },
      })

      expect(wrapper.text()).toContain('INF-301')
      expect(wrapper.text()).toContain('Rekayasa Perangkat Lunak')
    })

    it('renders empty state when no prerequisites exist', () => {
      const emptyCourse = { ...mockCourse, prerequisites: [] }
      const wrapper = mount(CoursePrerequisiteList, {
        props: { course: emptyCourse },
      })

      expect(wrapper.text()).toContain('Tidak Ada Prasyarat')
    })
  })
})
