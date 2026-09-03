import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AttendanceStatusBadge from '@/pages/attendance/components/AttendanceStatusBadge.vue'
import TeachingMethodBadge from '@/pages/attendance/components/TeachingMethodBadge.vue'
import ExamEligibilityBadge from '@/pages/attendance/components/ExamEligibilityBadge.vue'
import { attendanceService } from '@/services/api/attendance'

vi.mock('@/services/api/attendance', () => ({
  attendanceService: {
    getSessions: vi.fn(),
    getSession: vi.fn(),
    createSession: vi.fn(),
    updateSession: vi.fn(),
    deleteSession: vi.fn(),
    openCheckIn: vi.fn(),
    closeSession: vi.fn(),
    getSessionStudents: vi.fn(),
    recordBatch: vi.fn(),
    selfCheckIn: vi.fn(),
    getMyAttendance: vi.fn(),
    getClassRecap: vi.fn(),
    getStudentRecap: vi.fn(),
  },
}))

describe('Attendance UI Components & Services', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('AttendanceStatusBadge.vue', () => {
    it('renders present (Hadir) badge correctly', () => {
      const wrapper = mount(AttendanceStatusBadge, {
        props: { status: 'present', showCode: true },
      })
      expect(wrapper.text()).toContain('Hadir')
      expect(wrapper.text()).toContain('[H]')
    })

    it('renders permit (Izin) badge correctly', () => {
      const wrapper = mount(AttendanceStatusBadge, {
        props: { status: 'permit', showCode: true },
      })
      expect(wrapper.text()).toContain('Izin')
      expect(wrapper.text()).toContain('[I]')
    })

    it('renders sick (Sakit) badge correctly', () => {
      const wrapper = mount(AttendanceStatusBadge, {
        props: { status: 'sick' },
      })
      expect(wrapper.text()).toContain('Sakit')
    })

    it('renders absent (Alpa) badge correctly', () => {
      const wrapper = mount(AttendanceStatusBadge, {
        props: { status: 'absent' },
      })
      expect(wrapper.text()).toContain('Alpa')
    })
  })

  describe('TeachingMethodBadge.vue', () => {
    it('renders offline method label', () => {
      const wrapper = mount(TeachingMethodBadge, {
        props: { method: 'offline' },
      })
      expect(wrapper.text()).toContain('Luring (Tatap Muka)')
    })

    it('renders online method label', () => {
      const wrapper = mount(TeachingMethodBadge, {
        props: { method: 'online' },
      })
      expect(wrapper.text()).toContain('Daring (Online)')
    })

    it('renders hybrid method label', () => {
      const wrapper = mount(TeachingMethodBadge, {
        props: { method: 'hybrid' },
      })
      expect(wrapper.text()).toContain('Hybrid')
    })
  })

  describe('ExamEligibilityBadge.vue', () => {
    it('renders eligible (Layak Ujian) badge when eligible is true', () => {
      const wrapper = mount(ExamEligibilityBadge, {
        props: { eligible: true, percentage: 87.5 },
      })
      expect(wrapper.text()).toContain('Layak Ujian (87.5%)')
    })

    it('renders not eligible badge when eligible is false', () => {
      const wrapper = mount(ExamEligibilityBadge, {
        props: { eligible: false, percentage: 62.5 },
      })
      expect(wrapper.text()).toContain('Tidak Layak (62.5%)')
    })
  })

  describe('attendanceService API client', () => {
    it('calls getMyAttendance', async () => {
      ;(attendanceService.getMyAttendance as any).mockResolvedValue({
        success: true,
        data: { summary: { overall_percentage: 85 } },
      })

      const res = await attendanceService.getMyAttendance()
      expect(attendanceService.getMyAttendance).toHaveBeenCalled()
      expect(res.data.summary.overall_percentage).toBe(85)
    })

    it('calls selfCheckIn with payload', async () => {
      ;(attendanceService.selfCheckIn as any).mockResolvedValue({
        success: true,
        message: 'Presensi mandiri berhasil!',
      })

      const res = await attendanceService.selfCheckIn({
        teaching_session_id: 1,
        check_in_code: 'HADIR1',
      })

      expect(attendanceService.selfCheckIn).toHaveBeenCalledWith({
        teaching_session_id: 1,
        check_in_code: 'HADIR1',
      })
      expect(res.success).toBe(true)
    })
  })
})
