import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import LecturerStatusBadge from '../pages/lecturers/components/LecturerStatusBadge.vue'
import LecturerOverviewTab from '../pages/lecturers/tabs/LecturerOverviewTab.vue'
import LecturerAcademicTab from '../pages/lecturers/tabs/LecturerAcademicTab.vue'
import LecturerEducationTab from '../pages/lecturers/tabs/LecturerEducationTab.vue'
import LecturerExpertiseTab from '../pages/lecturers/tabs/LecturerExpertiseTab.vue'
import type { Lecturer } from '../types/lecturer'

const mockLecturer: Lecturer = {
  id: 1,
  user_id: 2,
  homebase_study_program_id: 1,
  lecturer_number: 'DOS-001',
  nidn: '0012345601',
  nidk: null,
  nip: '198001012005011001',
  full_name: 'Dr. H. Muhammad Ilyas',
  academic_degree: 'M.Ag',
  gender: 'male',
  birth_place: 'Yogyakarta',
  birth_date: '1980-01-01',
  phone: '081234567899',
  email: 'm.ilyas@siakad.ac.id',
  address: 'Jl. Kaliurang KM 5, Yogyakarta',
  status: 'active',
  functional_position: 'Lektor Kepala',
  join_date: '2010-08-01',
  photo_path: null,
  notes: 'Dosen tetap program studi PAI',
  homebase_study_program: {
    id: 1,
    faculty_id: 1,
    code: 'PAI',
    name: 'Pendidikan Agama Islam',
    degree: 'S1',
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
  educations: [
    {
      id: 1,
      lecturer_id: 1,
      degree: 'S3',
      institution_name: 'UIN Sunan Kalijaga',
      major: 'Studi Islam',
      graduation_year: 2018,
    },
    {
      id: 2,
      lecturer_id: 1,
      degree: 'S2',
      institution_name: 'UIN Syarif Hidayatullah',
      major: 'Pendidikan Islam',
      graduation_year: 2008,
    },
  ],
  expertises: [
    {
      id: 1,
      lecturer_id: 1,
      name: 'Metodologi Pembelajaran PAI',
      description: 'Pengembangan kurikulum dan model instruksional inovatif',
    },
    {
      id: 2,
      lecturer_id: 1,
      name: 'Filsafat Pendidikan Islam',
      description: 'Studi kritis pemikiran tokoh pendidikan Islam klasik dan kontemporer',
    },
  ],
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Lecturer Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('LecturerStatusBadge', () => {
    it('renders active status with correct label', () => {
      const wrapper = mount(LecturerStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Aktif Mengajar')
    })

    it('renders inactive status with correct label', () => {
      const wrapper = mount(LecturerStatusBadge, {
        props: { status: 'inactive' },
      })
      expect(wrapper.text()).toContain('Non-Aktif')
    })

    it('renders retired status with correct label', () => {
      const wrapper = mount(LecturerStatusBadge, {
        props: { status: 'retired' },
      })
      expect(wrapper.text()).toContain('Purnatugas / Pensiun')
    })

    it('renders resigned status with correct label', () => {
      const wrapper = mount(LecturerStatusBadge, {
        props: { status: 'resigned' },
      })
      expect(wrapper.text()).toContain('Mengundurkan Diri')
    })

    it('renders deceased status with correct label', () => {
      const wrapper = mount(LecturerStatusBadge, {
        props: { status: 'deceased' },
      })
      expect(wrapper.text()).toContain('Wafat')
    })
  })

  describe('LecturerOverviewTab', () => {
    it('renders lecturer personal and contact information correctly', () => {
      const wrapper = mount(LecturerOverviewTab, {
        props: { lecturer: mockLecturer },
      })

      expect(wrapper.text()).toContain('Dr. H. Muhammad Ilyas')
      expect(wrapper.text()).toContain('M.Ag')
      expect(wrapper.text()).toContain('0012345601')
      expect(wrapper.text()).toContain('198001012005011001')
      expect(wrapper.text()).toContain('DOS-001')
      expect(wrapper.text()).toContain('Laki-Laki')
      expect(wrapper.text()).toContain('Yogyakarta')
      expect(wrapper.text()).toContain('m.ilyas@siakad.ac.id')
      expect(wrapper.text()).toContain('081234567899')
      expect(wrapper.text()).toContain('Jl. Kaliurang KM 5, Yogyakarta')
      expect(wrapper.text()).toContain('Dosen tetap program studi PAI')
    })
  })

  describe('LecturerAcademicTab', () => {
    it('renders homebase study program and functional rank', () => {
      const wrapper = mount(LecturerAcademicTab, {
        props: { lecturer: mockLecturer },
      })

      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
      expect(wrapper.text()).toContain('PAI')
      expect(wrapper.text()).toContain('Fakultas Tarbiyah dan Ilmu Keguruan')
      expect(wrapper.text()).toContain('Lektor Kepala')
    })
  })

  describe('LecturerEducationTab', () => {
    it('renders formal education history records', () => {
      const wrapper = mount(LecturerEducationTab, {
        props: { lecturer: mockLecturer },
      })

      expect(wrapper.text()).toContain('UIN Sunan Kalijaga')
      expect(wrapper.text()).toContain('S3')
      expect(wrapper.text()).toContain('Studi Islam')
      expect(wrapper.text()).toContain('Lulus 2018')
      expect(wrapper.text()).toContain('UIN Syarif Hidayatullah')
      expect(wrapper.text()).toContain('S2')
    })

    it('renders empty state when lecturer has no educations', () => {
      const emptyLecturer = { ...mockLecturer, educations: [] }
      const wrapper = mount(LecturerEducationTab, {
        props: { lecturer: emptyLecturer },
      })

      expect(wrapper.text()).toContain('Belum Ada Riwayat Pendidikan')
    })
  })

  describe('LecturerExpertiseTab', () => {
    it('renders expertise items and descriptions', () => {
      const wrapper = mount(LecturerExpertiseTab, {
        props: { lecturer: mockLecturer },
      })

      expect(wrapper.text()).toContain('Metodologi Pembelajaran PAI')
      expect(wrapper.text()).toContain('Pengembangan kurikulum dan model instruksional inovatif')
      expect(wrapper.text()).toContain('Filsafat Pendidikan Islam')
    })

    it('renders empty state when lecturer has no expertises', () => {
      const emptyLecturer = { ...mockLecturer, expertises: [] }
      const wrapper = mount(LecturerExpertiseTab, {
        props: { lecturer: emptyLecturer },
      })

      expect(wrapper.text()).toContain('Belum Ada Bidang Kepakaran')
    })
  })
})
