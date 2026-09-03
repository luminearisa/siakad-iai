import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import StudentStatusBadge from '../pages/students/components/StudentStatusBadge.vue'
import OverviewTab from '../pages/students/tabs/OverviewTab.vue'
import AcademicTab from '../pages/students/tabs/AcademicTab.vue'
import FamilyTab from '../pages/students/tabs/FamilyTab.vue'
import EducationTab from '../pages/students/tabs/EducationTab.vue'
import type { Student } from '../types/student'

const mockStudent: Student = {
  id: 1,
  user_id: 10,
  study_program_id: 1,
  student_number: '202501001',
  national_student_number: '0012345678',
  national_id: '3201012345670001',
  full_name: 'Ahmad Fauzi',
  nickname: 'Fauzi',
  gender: 'male',
  birth_place: 'Bandung',
  birth_date: '2004-05-15',
  religion: 'Islam',
  marital_status: 'Single',
  phone: '081234567890',
  email: 'ahmad.fauzi@student.siakad.ac.id',
  address: 'Jl. Sukajadi No. 123, Bandung',
  postal_code: '40162',
  status: 'active',
  admission_year: 2025,
  entry_date: '2025-09-01',
  graduation_date: null,
  photo_path: null,
  notes: 'Mahasiswa jalur beasiswa berprestasi',
  study_program: {
    id: 1,
    code: 'PAI',
    name: 'Pendidikan Agama Islam',
    degree: 'S1',
    faculty_id: 1,
    faculty: {
      id: 1,
      institution_id: 1,
      code: 'FTIK',
      name: 'Fakultas Tarbiyah dan Ilmu Keguruan',
      dean_name: 'Dr. H. Muhammad, M.Ag',
      created_at: '2025-01-01T00:00:00Z',
    },
    created_at: '2025-01-01T00:00:00Z',
  },
  families: [
    {
      id: 1,
      student_id: 1,
      relationship: 'father',
      full_name: 'Bambang Sutrisno',
      phone: '081398765432',
      occupation: 'Guru PNS',
      address: 'Bandung',
    },
  ],
  educations: [
    {
      id: 1,
      student_id: 1,
      institution_name: 'MAN 1 Kota Bandung',
      level: 'MA',
      major: 'Keagamaan',
      graduation_year: 2025,
      certificate_number: 'DN-01/MA/2025/001',
    },
  ],
  created_at: '2025-09-01T00:00:00Z',
  updated_at: '2025-09-01T00:00:00Z',
}

describe('Student Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('StudentStatusBadge', () => {
    it('renders active status with correct label', () => {
      const wrapper = mount(StudentStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Aktif')
    })

    it('renders prospective status with correct label', () => {
      const wrapper = mount(StudentStatusBadge, {
        props: { status: 'prospective' },
      })
      expect(wrapper.text()).toContain('Calon Mahasiswa')
    })

    it('renders leave status with correct label', () => {
      const wrapper = mount(StudentStatusBadge, {
        props: { status: 'leave' },
      })
      expect(wrapper.text()).toContain('Cuti')
    })

    it('renders graduated status with correct label', () => {
      const wrapper = mount(StudentStatusBadge, {
        props: { status: 'graduated' },
      })
      expect(wrapper.text()).toContain('Lulus')
    })
  })

  describe('OverviewTab', () => {
    it('renders student personal and contact information correctly', () => {
      const wrapper = mount(OverviewTab, {
        props: { student: mockStudent },
      })

      expect(wrapper.text()).toContain('202501001')
      expect(wrapper.text()).toContain('Ahmad Fauzi')
      expect(wrapper.text()).toContain('Fauzi')
      expect(wrapper.text()).toContain('Laki-Laki')
      expect(wrapper.text()).toContain('Bandung')
      expect(wrapper.text()).toContain('3201012345670001')
      expect(wrapper.text()).toContain('0012345678')
      expect(wrapper.text()).toContain('Islam')
      expect(wrapper.text()).toContain('ahmad.fauzi@student.siakad.ac.id')
      expect(wrapper.text()).toContain('081234567890')
      expect(wrapper.text()).toContain('Jl. Sukajadi No. 123, Bandung')
      expect(wrapper.text()).toContain('Mahasiswa jalur beasiswa berprestasi')
    })
  })

  describe('AcademicTab', () => {
    it('renders student study program and faculty details', () => {
      const wrapper = mount(AcademicTab, {
        props: { student: mockStudent },
      })

      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
      expect(wrapper.text()).toContain('PAI')
      expect(wrapper.text()).toContain('Fakultas Tarbiyah dan Ilmu Keguruan')
      expect(wrapper.text()).toContain('2025')
    })
  })

  describe('FamilyTab', () => {
    it('renders family members cards correctly', () => {
      const wrapper = mount(FamilyTab, {
        props: { student: mockStudent },
      })

      expect(wrapper.text()).toContain('Bambang Sutrisno')
      expect(wrapper.text()).toContain('Ayah Kandung')
      expect(wrapper.text()).toContain('081398765432')
      expect(wrapper.text()).toContain('Guru PNS')
    })

    it('renders empty state when student has no families', () => {
      const emptyStudent = { ...mockStudent, families: [] }
      const wrapper = mount(FamilyTab, {
        props: { student: emptyStudent },
      })

      expect(wrapper.text()).toContain('Belum Ada Data Keluarga')
    })
  })

  describe('EducationTab', () => {
    it('renders education history items correctly', () => {
      const wrapper = mount(EducationTab, {
        props: { student: mockStudent },
      })

      expect(wrapper.text()).toContain('MAN 1 Kota Bandung')
      expect(wrapper.text()).toContain('MA')
      expect(wrapper.text()).toContain('Keagamaan')
      expect(wrapper.text()).toContain('Lulus 2025')
      expect(wrapper.text()).toContain('DN-01/MA/2025/001')
    })

    it('renders empty state when student has no educations', () => {
      const emptyStudent = { ...mockStudent, educations: [] }
      const wrapper = mount(EducationTab, {
        props: { student: emptyStudent },
      })

      expect(wrapper.text()).toContain('Belum Ada Riwayat Pendidikan')
    })
  })
})
