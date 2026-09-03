export const PERMISSIONS = {
  // Students
  STUDENTS_VIEW: 'students.view',
  STUDENTS_CREATE: 'students.create',
  STUDENTS_UPDATE: 'students.update',
  STUDENTS_DELETE: 'students.delete',

  // Lecturers
  LECTURERS_VIEW: 'lecturers.view',
  LECTURERS_CREATE: 'lecturers.create',
  LECTURERS_UPDATE: 'lecturers.update',
  LECTURERS_DELETE: 'lecturers.delete',

  // Academic
  FACULTIES_VIEW: 'faculties.view',
  STUDY_PROGRAMS_VIEW: 'study_programs.view',
  ACADEMIC_YEARS_VIEW: 'academic_years.view',
  SEMESTERS_VIEW: 'semesters.view',

  // Course & Curriculum
  COURSES_VIEW: 'courses.view',
  CURRICULA_VIEW: 'curricula.view',

  // Class & Schedule
  CLASSES_VIEW: 'classes.view',
  SCHEDULES_VIEW: 'schedules.view',
  ROOMS_VIEW: 'rooms.view',

  // Enrollment & Advising
  ENROLLMENTS_VIEW: 'enrollments.view',
  ENROLLMENTS_CREATE: 'enrollments.create',
  ENROLLMENTS_APPROVE: 'enrollments.approve',
  ADVISING_VIEW: 'advising.view',
} as const
