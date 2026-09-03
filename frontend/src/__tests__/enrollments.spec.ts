import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import EnrollmentStatusBadge from '../pages/enrollments/components/EnrollmentStatusBadge.vue'
import EnrollmentSummary from '../pages/enrollments/components/EnrollmentSummary.vue'
import EnrollmentValidationAlert from '../pages/enrollments/components/EnrollmentValidationAlert.vue'
import ClassSelectionTable from '../pages/enrollments/components/ClassSelectionTable.vue'
import EnrollmentItemList from '../pages/enrollments/components/EnrollmentItemList.vue'
import EnrollmentHeader from '../pages/enrollments/components/EnrollmentHeader.vue'
import type { StudentEnrollment } from '../types/enrollment'
import type { AcademicClass } from '../types/class'

const mockEnrollment: StudentEnrollment = {
  id: 1,
  student_id: 1,
  semester_id: 1,
  status: 'draft',
  total_credits: 6,
  student: {
    id: 1,
    user_id: 1,
    student_number: '202401001',
    full_name: 'Ahmad Dahlan',
    gender: 'male',
    study_program_id: 1,
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
    study_program: {
      id: 1,
      faculty_id: 1,
      code: 'PAI',
      name: 'Pendidikan Agama Islam',
      degree: 'S1',
      created_at: '2025-01-01T00:00:00Z',
    },
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
  items: [
    {
      id: 101,
      enrollment_id: 1,
      class_id: 1,
      course_id: 1,
      credits: 3,
      status: 'enrolled',
      academic_class: {
        id: 1,
        semester_id: 1,
        course_id: 1,
        study_program_id: 1,
        code: 'PAI-101-A',
        name: 'Pengantar Studi Islam (A)',
        section: 'A',
        capacity: 40,
        enrolled_count: 28,
        status: 'open',
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
        lecturers: [
          {
            id: 1,
            user_id: 1,
            lecturer_number: 'DOS-001',
            nidn: '0012018501',
            full_name: 'Dr. H. Fauzi, M.Ag',
            gender: 'male',
            status: 'active',
            created_at: '2025-01-01T00:00:00Z',
            updated_at: '2025-01-01T00:00:00Z',
          },
        ],
        schedules: [
          {
            id: 1,
            class_id: 1,
            room_id: 1,
            day_of_week: 'monday',
            start_time: '08:00',
            end_time: '09:40',
            status: 'active',
            room: {
              id: 1,
              code: 'R.101',
              name: 'Ruang Teori 101',
              capacity: 45,
              room_type: 'classroom',
              status: 'active',
              created_at: '2025-01-01T00:00:00Z',
            },
            created_at: '2025-01-01T00:00:00Z',
          },
        ],
        created_at: '2025-01-01T00:00:00Z',
        updated_at: '2025-01-01T00:00:00Z',
      },
      created_at: '2025-01-01T00:00:00Z',
    },
    {
      id: 102,
      enrollment_id: 1,
      class_id: 2,
      course_id: 2,
      credits: 3,
      status: 'enrolled',
      academic_class: {
        id: 2,
        semester_id: 1,
        course_id: 2,
        study_program_id: 1,
        code: 'PAI-102-A',
        name: 'Ulumul Qur\'an (A)',
        section: 'A',
        capacity: 40,
        enrolled_count: 30,
        status: 'open',
        course: {
          id: 2,
          code: 'PAI-102',
          name: 'Ulumul Qur\'an',
          credits: 3,
          theory_credits: 3,
          practical_credits: 0,
          type: 'theory',
          category: 'MKK',
          status: 'active',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        created_at: '2025-01-01T00:00:00Z',
        updated_at: '2025-01-01T00:00:00Z',
      },
      created_at: '2025-01-01T00:00:00Z',
    },
  ],
  items_count: 2,
  created_at: '2025-01-01T00:00:00Z',
}

const mockAvailableClass: AcademicClass = {
  id: 3,
  semester_id: 1,
  course_id: 3,
  study_program_id: 1,
  code: 'PAI-103-A',
  name: 'Ulumul Hadits (A)',
  section: 'A',
  capacity: 35,
  enrolled_count: 15,
  status: 'open',
  course: {
    id: 3,
    code: 'PAI-103',
    name: 'Ulumul Hadits',
    credits: 3,
    theory_credits: 3,
    practical_credits: 0,
    type: 'theory',
    category: 'MKK',
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
  schedules: [
    {
      id: 3,
      class_id: 3,
      room_id: 2,
      day_of_week: 'wednesday',
      start_time: '10:00',
      end_time: '11:40',
      status: 'active',
      room: {
        id: 2,
        code: 'R.102',
        name: 'Ruang Teori 102',
        capacity: 40,
        room_type: 'classroom',
        status: 'active',
        created_at: '2025-01-01T00:00:00Z',
      },
      created_at: '2025-01-01T00:00:00Z',
    },
  ],
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Enrollment Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('EnrollmentStatusBadge', () => {
    it('renders draft status correctly', () => {
      const wrapper = mount(EnrollmentStatusBadge, {
        props: { status: 'draft' },
      })
      expect(wrapper.text()).toContain('Draft')
    })

    it('renders submitted status correctly', () => {
      const wrapper = mount(EnrollmentStatusBadge, {
        props: { status: 'submitted' },
      })
      expect(wrapper.text()).toContain('Submitted')
    })

    it('renders approved status correctly', () => {
      const wrapper = mount(EnrollmentStatusBadge, {
        props: { status: 'approved' },
      })
      expect(wrapper.text()).toContain('Approved')
    })

    it('renders locked status correctly', () => {
      const wrapper = mount(EnrollmentStatusBadge, {
        props: { status: 'locked' },
      })
      expect(wrapper.text()).toContain('Locked')
    })
  })

  describe('EnrollmentSummary', () => {
    it('calculates total credits, courses, and remaining SKS quota', () => {
      const wrapper = mount(EnrollmentSummary, {
        props: {
          enrollment: mockEnrollment,
          maxSks: 24,
        },
      })
      expect(wrapper.text()).toContain('2 Kelas')
      expect(wrapper.text()).toContain('6 SKS')
      expect(wrapper.text()).toContain('24 SKS')
      expect(wrapper.text()).toContain('18 SKS')
    })
  })

  describe('EnrollmentValidationAlert', () => {
    it('renders single and multiple validation errors properly', () => {
      const errors = [
        'Prasyarat mata kuliah belum terpenuhi: Dasar Pemrograman.',
        'Jadwal bentrok dengan PAI-101-A pada Senin (08:00 - 09:40).',
      ]
      const wrapper = mount(EnrollmentValidationAlert, {
        props: { errors },
      })
      expect(wrapper.text()).toContain('Validasi Akademik KRS Tidak Terpenuhi')
      expect(wrapper.text()).toContain('Prasyarat mata kuliah belum terpenuhi')
      expect(wrapper.text()).toContain('Jadwal bentrok dengan PAI-101-A')
    })
  })

  describe('EnrollmentHeader', () => {
    it('renders student name, NIM, study program, and semester', () => {
      const wrapper = mount(EnrollmentHeader, {
        props: { enrollment: mockEnrollment },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      expect(wrapper.text()).toContain('Ahmad Dahlan')
      expect(wrapper.text()).toContain('202401001')
      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
      expect(wrapper.text()).toContain('Semester Gasal 2024/2025')
      expect(wrapper.text()).toContain('6 SKS')
    })
  })

  describe('ClassSelectionTable', () => {
    it('renders available class with capacity and emits add-class when clicked', async () => {
      const wrapper = mount(ClassSelectionTable, {
        props: {
          classes: [mockAvailableClass],
          enrollment: mockEnrollment,
        },
      })
      expect(wrapper.text()).toContain('Ulumul Hadits')
      expect(wrapper.text()).toContain('PAI-103')
      expect(wrapper.text()).toContain('15 / 35')

      const addButton = wrapper.find('button')
      expect(addButton.exists()).toBe(true)
      await addButton.trigger('click')
      expect(wrapper.emitted('add-class')?.[0]).toEqual([mockAvailableClass])
    })
  })

  describe('EnrollmentItemList', () => {
    it('renders enrolled items with credits, section, and allows removal in draft', async () => {
      const wrapper = mount(EnrollmentItemList, {
        props: {
          enrollment: mockEnrollment,
          items: mockEnrollment.items || [],
        },
      })
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('Ulumul Qur\'an')
      expect(wrapper.text()).toContain('3')

      const removeButtons = wrapper.findAll('button')
      expect(removeButtons.length).toBeGreaterThan(0)
      await removeButtons[0].trigger('click')
      expect(wrapper.emitted('remove-item')?.[0]).toEqual([mockEnrollment.items![0]])
    })
  })
})
