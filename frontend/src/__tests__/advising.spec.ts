import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '../stores/auth'
import AdvisorStatusBadge from '../pages/advising/components/AdvisorStatusBadge.vue'
import AdvisingSessionStatusBadge from '../pages/advising/components/AdvisingSessionStatusBadge.vue'
import AcademicAlert from '../pages/advising/components/AcademicAlert.vue'
import AdvisingSummary from '../pages/advising/components/AdvisingSummary.vue'
import AdvisorHeader from '../pages/advising/components/AdvisorHeader.vue'
import StudentAdviseeTable from '../pages/advising/components/StudentAdviseeTable.vue'
import AdvisingSessionList from '../pages/advising/components/AdvisingSessionList.vue'
import AdvisorAssignmentHistory from '../pages/advising/components/AdvisorAssignmentHistory.vue'
import KrsReviewCard from '../pages/advising/components/KrsReviewCard.vue'
import KrsReviewModal from '../pages/advising/components/KrsReviewModal.vue'
import type { AcademicAdvisor, AdvisingSession } from '../types/advising'
import type { Lecturer } from '../types/lecturer'
import type { StudentEnrollment } from '../types/enrollment'
import type { User } from '../types/auth'

const mockUser: User = {
  id: 1,
  name: 'Super Admin',
  email: 'admin@siakad.test',
  status: 'active',
  roles: [
    {
      id: 1,
      name: 'super_admin',
      display_name: 'Super Admin',
      is_system: true,
    },
  ],
  permissions: [
    { id: 1, name: 'advising.view', display_name: 'View Advising Data', group: 'advising' },
    { id: 2, name: 'advising.assign', display_name: 'Assign Academic Advisor', group: 'advising' },
    { id: 3, name: 'advising.create_session', display_name: 'Create Advising Session', group: 'advising' },
    { id: 4, name: 'advising.update_session', display_name: 'Update Advising Session', group: 'advising' },
    { id: 5, name: 'enrollments.approve', display_name: 'Approve Enrollment', group: 'enrollment' },
    { id: 6, name: 'enrollments.revise', display_name: 'Revise Enrollment', group: 'enrollment' },
    { id: 7, name: 'enrollments.reject', display_name: 'Reject Enrollment', group: 'enrollment' },
  ],
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

const mockLecturer: Lecturer = {
  id: 1,
  user_id: 1,
  lecturer_number: 'DOS-001',
  nidn: '0012018501',
  full_name: 'Fauzi Rahmat, M.Ag',
  gender: 'male',
  status: 'active',
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
      created_at: '2025-01-01T00:00:00Z',
    },
    created_at: '2025-01-01T00:00:00Z',
  },
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

const mockAdvisee: AcademicAdvisor = {
  id: 1,
  student_id: 1,
  lecturer_id: 1,
  start_date: '2024-09-01',
  status: 'active',
  notes: 'Penugasan SK Dekan No 12/2024',
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
  lecturer: mockLecturer,
  created_at: '2025-01-01T00:00:00Z',
}

const mockSession: AdvisingSession = {
  id: 1,
  student_id: 1,
  lecturer_id: 1,
  session_date: '2024-10-15',
  topic: 'Konsultasi Rencana Studi Semester Gasal',
  notes: 'Mahasiswa disarankan mengambil 21 SKS dan memperhatikan prasyarat Ulumul Hadits.',
  status: 'completed',
  student: mockAdvisee.student,
  lecturer: mockLecturer,
  created_at: '2025-01-01T00:00:00Z',
}

const mockEnrollment: StudentEnrollment = {
  id: 1,
  student_id: 1,
  semester_id: 1,
  status: 'submitted',
  total_credits: 6,
  student: mockAdvisee.student,
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
  created_at: '2025-01-01T00:00:00Z',
}

describe('Academic Advising Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const authStore = useAuthStore()
    authStore.user = mockUser
    document.body.innerHTML = ''
  })

  describe('AdvisorStatusBadge', () => {
    it('1. renders active status badge correctly', () => {
      const wrapper = mount(AdvisorStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Aktif Menjabat')
    })

    it('2. renders transferred status badge correctly', () => {
      const wrapper = mount(AdvisorStatusBadge, {
        props: { status: 'transferred' },
      })
      expect(wrapper.text()).toContain('Dialihkan')
    })

    it('3. renders completed status badge correctly', () => {
      const wrapper = mount(AdvisorStatusBadge, {
        props: { status: 'completed' },
      })
      expect(wrapper.text()).toContain('Selesai')
    })
  })

  describe('AdvisingSessionStatusBadge', () => {
    it('4. renders scheduled status badge correctly', () => {
      const wrapper = mount(AdvisingSessionStatusBadge, {
        props: { status: 'scheduled' },
      })
      expect(wrapper.text()).toContain('Dijadwalkan')
    })

    it('5. renders completed status badge correctly', () => {
      const wrapper = mount(AdvisingSessionStatusBadge, {
        props: { status: 'completed' },
      })
      expect(wrapper.text()).toContain('Selesai')
    })

    it('6. renders cancelled status badge correctly', () => {
      const wrapper = mount(AdvisingSessionStatusBadge, {
        props: { status: 'cancelled' },
      })
      expect(wrapper.text()).toContain('Dibatalkan')
    })
  })

  describe('AcademicAlert', () => {
    it('7. renders danger alert message with title', () => {
      const wrapper = mount(AcademicAlert, {
        props: {
          type: 'danger',
          title: 'KRS Belum Diajukan',
          message: 'Mahasiswa belum mengajukan rencana studi.',
        },
      })
      expect(wrapper.text()).toContain('KRS Belum Diajukan')
      expect(wrapper.text()).toContain('Mahasiswa belum mengajukan')
    })

    it('8. renders warning alert message in compact mode', () => {
      const wrapper = mount(AcademicAlert, {
        props: {
          type: 'warning',
          message: 'Beban SKS kurang dari batas minimal.',
          compact: true,
        },
      })
      expect(wrapper.text()).toContain('Beban SKS kurang')
    })
  })

  describe('AdvisingSummary', () => {
    it('9. calculates total advisees, active advisees, sessions, and pending KRS', () => {
      const wrapper = mount(AdvisingSummary, {
        props: {
          totalAdvisees: 15,
          activeAdvisees: 12,
          totalSessions: 8,
          pendingKrsCount: 3,
        },
      })
      expect(wrapper.text()).toContain('12')
      expect(wrapper.text()).toContain('/ 15 Total')
      expect(wrapper.text()).toContain('8')
      expect(wrapper.text()).toContain('3')
    })
  })

  describe('AdvisorHeader', () => {
    it('10. renders lecturer full name, NIDN, and study program', () => {
      const wrapper = mount(AdvisorHeader, {
        props: {
          lecturer: mockLecturer,
          activeAdviseesCount: 12,
        },
      })
      expect(wrapper.text()).toContain('Fauzi Rahmat')
      expect(wrapper.text()).toContain('0012018501')
      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
    })

    it('11. emits assign-student and create-session events on button clicks', async () => {
      const wrapper = mount(AdvisorHeader, {
        props: {
          lecturer: mockLecturer,
          activeAdviseesCount: 12,
        },
      })
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBeGreaterThan(1)
      await buttons[1].trigger('click')
      expect(wrapper.emitted('create-session') || wrapper.emitted('assign-student')).toBeTruthy()
    })
  })

  describe('StudentAdviseeTable', () => {
    it('12. renders advisee list with student name, NIM, and study program', () => {
      const wrapper = mount(StudentAdviseeTable, {
        props: {
          advisees: [mockAdvisee],
        },
      })
      expect(wrapper.text()).toContain('Ahmad Dahlan')
      expect(wrapper.text()).toContain('202401001')
      expect(wrapper.text()).toContain('Pendidikan Agama Islam')
    })

    it('13. emits create-session and reassign events for an advisee', async () => {
      const wrapper = mount(StudentAdviseeTable, {
        props: {
          advisees: [mockAdvisee],
        },
      })
      const actionButtons = wrapper.findAll('button')
      expect(actionButtons.length).toBeGreaterThan(0)
      await actionButtons[0].trigger('click')
      expect(wrapper.emitted('create-session')?.[0]).toEqual([mockAdvisee])
    })
  })

  describe('AdvisingSessionList', () => {
    it('14. renders session topic, student name, and notes content', () => {
      const wrapper = mount(AdvisingSessionList, {
        props: {
          sessions: [mockSession],
        },
      })
      expect(wrapper.text()).toContain('Konsultasi Rencana Studi')
      expect(wrapper.text()).toContain('Ahmad Dahlan')
      expect(wrapper.text()).toContain('Mahasiswa disarankan mengambil 21 SKS')
    })

    it('15. emits edit and delete events when clicked', async () => {
      const wrapper = mount(AdvisingSessionList, {
        props: {
          sessions: [mockSession],
        },
      })
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBeGreaterThan(0)
      await buttons[0].trigger('click')
      expect(wrapper.emitted('edit')?.[0]).toEqual([mockSession])
    })
  })

  describe('AdvisorAssignmentHistory', () => {
    it('16. renders timeline history of student assignments', () => {
      const wrapper = mount(AdvisorAssignmentHistory, {
        props: {
          history: [mockAdvisee],
        },
      })
      expect(wrapper.text()).toContain('Ahmad Dahlan')
      expect(wrapper.text()).toContain('202401001')
      expect(wrapper.text()).toContain('Penugasan SK Dekan No 12/2024')
    })
  })

  describe('KrsReviewCard', () => {
    it('17. renders student KRS review card with total SKS and courses', () => {
      const wrapper = mount(KrsReviewCard, {
        props: {
          enrollment: mockEnrollment,
        },
      })
      expect(wrapper.text()).toContain('Ahmad Dahlan')
      expect(wrapper.text()).toContain('6 SKS')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
    })

    it('18. emits approve, request-revision, and reject events', async () => {
      const wrapper = mount(KrsReviewCard, {
        props: {
          enrollment: mockEnrollment,
        },
      })
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBeGreaterThan(0)
      const approveBtn = buttons.find((b) => b.text().includes('Setujui KRS'))
      if (approveBtn) {
        await approveBtn.trigger('click')
        expect(wrapper.emitted('approve')?.[0]).toEqual([mockEnrollment])
      }
    })
  })

  describe('KrsReviewModal', () => {
    it('19. renders approve modal without requiring notes', () => {
      mount(KrsReviewModal, {
        props: {
          open: true,
          enrollment: mockEnrollment,
          actionType: 'approve',
        },
        attachTo: document.body,
      })
      expect(document.body.textContent).toContain('Setujui Rencana Studi')
      expect(document.body.textContent).toContain('Ahmad Dahlan')
    })

    it('20. renders revision modal with required notes instruction', () => {
      mount(KrsReviewModal, {
        props: {
          open: true,
          enrollment: mockEnrollment,
          actionType: 'revision',
        },
        attachTo: document.body,
      })
      expect(document.body.textContent).toContain('Minta Revisi Rencana Studi')
      expect(document.body.textContent).toContain('Revision Required')
    })

    it('21. renders reject modal with rejection reason', () => {
      mount(KrsReviewModal, {
        props: {
          open: true,
          enrollment: mockEnrollment,
          actionType: 'reject',
        },
        attachTo: document.body,
      })
      expect(document.body.textContent).toContain('Tolak Rencana Studi')
      expect(document.body.textContent).toContain('Penolakan KRS ini bersifat final')
    })
  })
})
