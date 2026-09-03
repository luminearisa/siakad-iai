import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ScheduleStatusBadge from '../pages/schedules/components/ScheduleStatusBadge.vue'
import ScheduleTimeDisplay from '../pages/schedules/components/ScheduleTimeDisplay.vue'
import DaySelector from '../pages/schedules/components/DaySelector.vue'
import TimeRangePicker from '../pages/schedules/components/TimeRangePicker.vue'
import ScheduleConflictAlert from '../pages/schedules/components/ScheduleConflictAlert.vue'
import ScheduleHeader from '../pages/schedules/components/ScheduleHeader.vue'
import WeeklyScheduleView from '../pages/schedules/views/WeeklyScheduleView.vue'
import DailyScheduleView from '../pages/schedules/views/DailyScheduleView.vue'
import type { ClassSchedule } from '../types/schedule'

const mockSchedule: ClassSchedule = {
  id: 1,
  class_id: 1,
  room_id: 1,
  day_of_week: 'monday',
  start_time: '08:00',
  end_time: '09:40',
  status: 'active',
  notes: 'Pertemuan di Lab Komputer 1',
  room: {
    id: 1,
    code: 'R.101',
    name: 'Ruang Kuliah Teori 101',
    building: 'Gedung Tarbiyah A',
    floor: 1,
    capacity: 45,
    room_type: 'classroom',
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
  academic_class: {
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
    lecturers: [
      {
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
    ],
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  },
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
}

describe('Schedule Module Components', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('ScheduleStatusBadge', () => {
    it('renders active status badge correctly', () => {
      const wrapper = mount(ScheduleStatusBadge, {
        props: { status: 'active' },
      })
      expect(wrapper.text()).toContain('Aktif Berjalan')
    })

    it('renders cancelled status badge correctly', () => {
      const wrapper = mount(ScheduleStatusBadge, {
        props: { status: 'cancelled' },
      })
      expect(wrapper.text()).toContain('Dibatalkan')
    })
  })

  describe('ScheduleTimeDisplay', () => {
    it('formats day name and time range correctly', () => {
      const wrapper = mount(ScheduleTimeDisplay, {
        props: {
          dayOfWeek: 'monday',
          startTime: '08:00:00',
          endTime: '09:40:00',
        },
      })
      expect(wrapper.text()).toContain('Senin')
      expect(wrapper.text()).toContain('08:00 – 09:40')
    })
  })

  describe('DaySelector', () => {
    it('renders all standard days and responds to clicks', async () => {
      const wrapper = mount(DaySelector, {
        props: { modelValue: 'monday' },
      })
      expect(wrapper.text()).toContain('Senin')
      expect(wrapper.text()).toContain('Selasa')
      expect(wrapper.text()).toContain('Rabu')
      expect(wrapper.text()).toContain('Kamis')
      expect(wrapper.text()).toContain('Jumat')
      expect(wrapper.text()).toContain('Sabtu')

      const buttons = wrapper.findAll('button')
      await buttons[1].trigger('click') // Tuesday
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['tuesday'])
    })
  })

  describe('TimeRangePicker', () => {
    it('calculates duration in minutes and SKS equivalent', () => {
      const wrapper = mount(TimeRangePicker, {
        props: {
          startTime: '08:00',
          endTime: '09:40',
        },
      })
      expect(wrapper.text()).toContain('100 Menit')
      expect(wrapper.text()).toContain('2.0 SKS')
    })
  })

  describe('ScheduleConflictAlert', () => {
    it('renders conflict error messages properly', () => {
      const conflicts = [
        'Ruangan R.101 sudah digunakan oleh PAI-101-B pada Senin (08:00 - 09:40).',
        'Dosen Dr. H. Ahmad Fauzi memiliki jadwal bentrok pada waktu yang sama.',
      ]
      const wrapper = mount(ScheduleConflictAlert, {
        props: { conflicts },
      })
      expect(wrapper.text()).toContain('Peringatan Konflik Jadwal Perkuliahan')
      expect(wrapper.text()).toContain('Ruangan R.101 sudah digunakan')
      expect(wrapper.text()).toContain('Dr. H. Ahmad Fauzi memiliki jadwal bentrok')
    })
  })

  describe('ScheduleHeader', () => {
    it('renders schedule day, time, course, class, and room', () => {
      const wrapper = mount(ScheduleHeader, {
        props: { schedule: mockSchedule },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      expect(wrapper.text()).toContain('Senin')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('Kelas A')
      expect(wrapper.text()).toContain('Ruang Kuliah Teori 101')
      expect(wrapper.text()).toContain('Aktif Berjalan')
    })
  })

  describe('WeeklyScheduleView', () => {
    it('renders weekly timetable grid and puts schedule into correct day column', () => {
      const wrapper = mount(WeeklyScheduleView, {
        props: {
          schedules: [mockSchedule],
        },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      expect(wrapper.text()).toContain('Senin')
      expect(wrapper.text()).toContain('1 Kelas')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('R.101')
    })
  })

  describe('DailyScheduleView', () => {
    it('renders daily schedule list for selected day', () => {
      const wrapper = mount(DailyScheduleView, {
        props: {
          schedules: [mockSchedule],
        },
        global: {
          stubs: {
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })
      expect(wrapper.text()).toContain('08:00 – 09:40')
      expect(wrapper.text()).toContain('Pengantar Studi Islam')
      expect(wrapper.text()).toContain('R.101')
    })
  })
})
