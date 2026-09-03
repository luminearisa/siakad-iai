import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CurriculumStatusBadge from '../pages/curriculum/components/CurriculumStatusBadge.vue'
import CurriculumHeader from '../pages/curriculum/components/CurriculumHeader.vue'
import CurriculumOverviewTab from '../pages/curriculum/tabs/CurriculumOverviewTab.vue'
import CurriculumStructureTab from '../pages/curriculum/tabs/CurriculumStructureTab.vue'
import CurriculumSubjectTable from '../pages/curriculum/components/CurriculumSubjectTable.vue'
import type { Curriculum, CurriculumSemester } from '../types/curriculum'

const mockCurriculum: Curriculum = {
  id: 1,
  study_program_id: 1,
  code: 'KUR-PAI-2024',
  name: 'Kurikulum OBE PAI 2024',
  version: '2024',
  description: 'Kurikulum berbasis Outcome-Based Education (OBE) untuk program studi S1 Pendidikan Agama Islam.',
  start_year: 2024,
  end_year: 2028,
  status: 'active',
  effective_date: '2024-09-01',
  expiry_date: '2028-08-31',
  total_credits: 144,
  study_program: {
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
  semesters: [
    {
      id: 1,
      curriculum_id: 1,
      semester_number: 1,
      name: 'Semester 1 (Gasal)',
      recommended_credits: 20,
      total_credits: 20,
      subjects: [
        {
          id: 1,
          curriculum_semester_id: 1,
          course_id: 1,
          is_mandatory: true,
          effective_credits: 3,
          minimum_grade: 'C',
          notes: 'Mata Kuliah Wajib',
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
        },
      ],
    },
  ],
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Curriculum Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('CurriculumStatusBadge', () => {
    it('renders draft status correctly', () => {
      const wrapper = mount(CurriculumStatusBadge, {
        props: { status: 'draft' },
      })
      expect(wrapper.text()).toContain('Draft')
    })

    it('renders active status correctly', () => {
      const wrapper = mount(CurriculumStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Aktif Berlaku')
    })

    it('renders archived status correctly', () => {
      const wrapper = mount(CurriculumStatusBadge, {
        props: { status: 'archived' },
      })
      expect(wrapper.text()).toContain('Diarsipkan')
    })
  })

  describe('CurriculumHeader', () => {
    it('renders curriculum header code, name, prodi, and total credits', () => {
      const wrapper = mount(CurriculumHeader, {
        props: { curriculum: mockCurriculum },
      })

      expect(wrapper.text()).toContain('KUR-PAI-2024')
      expect(wrapper.text()).toContain('Kurikulum OBE PAI 2024')
      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
      expect(wrapper.text()).toContain('144 SKS Kurikulum')
    })
  })

  describe('CurriculumOverviewTab', () => {
    it('renders curriculum information and validity period', () => {
      const wrapper = mount(CurriculumOverviewTab, {
        props: { curriculum: mockCurriculum },
      })

      expect(wrapper.text()).toContain('KUR-PAI-2024')
      expect(wrapper.text()).toContain('Kurikulum OBE PAI 2024')
      expect(wrapper.text()).toContain('2024 s/d 2028')
      expect(wrapper.text()).toContain('Fakultas Tarbiyah dan Ilmu Keguruan')
      expect(wrapper.text()).toContain('Outcome-Based Education')
    })
  })

  describe('CurriculumStructureTab', () => {
    it('renders semester list cards and counts', () => {
      const wrapper = mount(CurriculumStructureTab, {
        props: { curriculum: mockCurriculum },
      })

      expect(wrapper.text()).toContain('Semester 1 (Gasal)')
      expect(wrapper.text()).toContain('1 Mata Kuliah')
      expect(wrapper.text()).toContain('Target: 20 SKS')
    })
  })

  describe('CurriculumSubjectTable', () => {
    it('renders subject rows in semester table', () => {
      const semester: CurriculumSemester = mockCurriculum.semesters![0]
      const wrapper = mount(CurriculumSubjectTable, {
        props: { semester, isReadOnly: false },
      })

      expect(wrapper.text()).toContain('PAI-101')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('3 SKS')
      expect(wrapper.text()).toContain('Wajib')
      expect(wrapper.text()).toContain('C')
    })
  })
})
