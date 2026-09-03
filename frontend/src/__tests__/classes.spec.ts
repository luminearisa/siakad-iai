import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ClassStatusBadge from '../pages/classes/components/ClassStatusBadge.vue'
import ClassCapacityBadge from '../pages/classes/components/ClassCapacityBadge.vue'
import ClassHeader from '../pages/classes/components/ClassHeader.vue'
import ClassOverviewTab from '../pages/classes/tabs/ClassOverviewTab.vue'
import ClassLecturerList from '../pages/classes/components/ClassLecturerList.vue'
import type { AcademicClass, ClassLecturer } from '../types/class'

const mockClass: AcademicClass = {
  id: 1,
  semester_id: 1,
  course_id: 1,
  study_program_id: 1,
  code: 'PAI-101-A',
  name: 'Kelas Pengantar Studi Islam (A)',
  section: 'A',
  capacity: 40,
  enrolled_count: 28,
  status: 'open',
  notes: 'Kelas tatap muka di Gedung A',
  course: {
    id: 1,
    code: 'PAI-101',
    name: 'Pengantar Studi Islam',
    credits: 3,
    theory_credits: 3,
    practical_credits: 0,
    type: 'theory',
    category: 'MKWU',
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
  semester: {
    id: 1,
    academic_year_id: 1,
    name: 'Semester Gasal 2024/2025',
    type: 'ganjil',
    start_date: '2024-09-01',
    end_date: '2025-01-31',
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
  },
  study_program: {
    id: 1,
    faculty_id: 1,
    code: 'PAI',
    name: 'Pendidikan Agama Islam',
    degree: 'S1',
    created_at: '2025-01-01T00:00:00Z',
  },
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

const mockClassLecturers: ClassLecturer[] = [
  {
    id: 1,
    class_id: 1,
    lecturer_id: 1,
    role: 'primary',
    lecturer: {
      id: 1,
      user_id: 1,
      lecturer_number: 'DOS-001',
      nidn: '0012018501',
      full_name: 'Dr. H. Ahmad Fauzi, M.Ag',
      gender: 'male',
      academic_degree: 'M.Ag, Ph.D',
      functional_position: 'Lektor Kepala',
      status: 'active',
      created_at: '2025-01-01T00:00:00Z',
      updated_at: '2025-01-01T00:00:00Z',
    },
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
]

describe('Academic Class Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('ClassStatusBadge', () => {
    it('renders draft status correctly', () => {
      const wrapper = mount(ClassStatusBadge, {
        props: { status: 'draft' },
      })
      expect(wrapper.text()).toContain('Draft')
    })

    it('renders open status correctly', () => {
      const wrapper = mount(ClassStatusBadge, {
        props: { status: 'open' },
      })
      expect(wrapper.text()).toContain('Buka (Open)')
    })

    it('renders closed status correctly', () => {
      const wrapper = mount(ClassStatusBadge, {
        props: { status: 'closed' },
      })
      expect(wrapper.text()).toContain('Ditutup (Closed)')
    })

    it('renders cancelled status correctly', () => {
      const wrapper = mount(ClassStatusBadge, {
        props: { status: 'cancelled' },
      })
      expect(wrapper.text()).toContain('Dibatalkan')
    })
  })

  describe('ClassCapacityBadge', () => {
    it('renders enrolled and remaining seats properly', () => {
      const wrapper = mount(ClassCapacityBadge, {
        props: { enrolled: 28, capacity: 40 },
      })
      expect(wrapper.text()).toContain('28 / 40')
      expect(wrapper.text()).toContain('Sisa 12')
    })

    it('renders full badge when capacity is reached', () => {
      const wrapper = mount(ClassCapacityBadge, {
        props: { enrolled: 40, capacity: 40 },
      })
      expect(wrapper.text()).toContain('40 / 40')
      expect(wrapper.text()).toContain('Penuh')
    })
  })

  describe('ClassHeader', () => {
    it('renders class section, course name, semester, and action buttons', () => {
      const wrapper = mount(ClassHeader, {
        props: { academicClass: mockClass },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })

      expect(wrapper.text()).toContain('Kelas A')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('Semester Gasal 2024/2025')
      expect(wrapper.text()).toContain('Buka (Open)')
    })
  })

  describe('ClassOverviewTab', () => {
    it('renders class metadata, capacity, and course details', () => {
      const wrapper = mount(ClassOverviewTab, {
        props: { academicClass: mockClass },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })

      expect(wrapper.text()).toContain('PAI-101-A')
      expect(wrapper.text()).toContain('Kelas A')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('3 SKS')
    })
  })

  describe('ClassLecturerList', () => {
    it('renders assigned lecturers and roles', () => {
      const wrapper = mount(ClassLecturerList, {
        props: {
          academicClass: mockClass,
          classLecturers: mockClassLecturers,
          isReadOnly: false,
        },
      })

      expect(wrapper.text()).toContain('Dr. H. Ahmad Fauzi, M.Ag')
      expect(wrapper.text()).toContain('Dosen Utama (Pengampu)')
      expect(wrapper.text()).toContain('NIDN: 0012018501')
    })
  })
})
