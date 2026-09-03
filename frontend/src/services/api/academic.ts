import { apiClient } from './client'
import type { ApiResponse, QueryParams } from '@/types/api'
import type { AcademicYear, Faculty, Institution, Semester, StudyProgram, StudyProgramSetting } from '@/types/academic'

export const academicService = {
  // Institutions
  getInstitutions(params?: QueryParams): Promise<ApiResponse<Institution[]>> {
    return apiClient.get<Institution[]>('/academic/institutions', params)
  },
  getInstitution(id: number): Promise<ApiResponse<Institution>> {
    return apiClient.get<Institution>(`/academic/institutions/${id}`)
  },
  createInstitution(payload: Partial<Institution>): Promise<ApiResponse<Institution>> {
    return apiClient.post<Institution>('/academic/institutions', payload)
  },
  updateInstitution(id: number, payload: Partial<Institution>): Promise<ApiResponse<Institution>> {
    return apiClient.put<Institution>(`/academic/institutions/${id}`, payload)
  },
  deleteInstitution(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/academic/institutions/${id}`)
  },

  // Faculties
  getFaculties(params?: QueryParams): Promise<ApiResponse<Faculty[]>> {
    return apiClient.get<Faculty[]>('/academic/faculties', params)
  },
  getFaculty(id: number): Promise<ApiResponse<Faculty>> {
    return apiClient.get<Faculty>(`/academic/faculties/${id}`)
  },
  createFaculty(payload: Partial<Faculty>): Promise<ApiResponse<Faculty>> {
    return apiClient.post<Faculty>('/academic/faculties', payload)
  },
  updateFaculty(id: number, payload: Partial<Faculty>): Promise<ApiResponse<Faculty>> {
    return apiClient.put<Faculty>(`/academic/faculties/${id}`, payload)
  },
  deleteFaculty(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/academic/faculties/${id}`)
  },

  // Study Programs
  getStudyPrograms(params?: QueryParams): Promise<ApiResponse<StudyProgram[]>> {
    return apiClient.get<StudyProgram[]>('/academic/study-programs', params)
  },
  getStudyProgram(id: number): Promise<ApiResponse<StudyProgram>> {
    return apiClient.get<StudyProgram>(`/academic/study-programs/${id}`)
  },
  createStudyProgram(payload: Partial<StudyProgram>): Promise<ApiResponse<StudyProgram>> {
    return apiClient.post<StudyProgram>('/academic/study-programs', payload)
  },
  updateStudyProgram(id: number, payload: Partial<StudyProgram>): Promise<ApiResponse<StudyProgram>> {
    return apiClient.put<StudyProgram>(`/academic/study-programs/${id}`, payload)
  },
  updateStudyProgramSetting(id: number, payload: Partial<StudyProgramSetting>): Promise<ApiResponse<StudyProgram>> {
    return apiClient.put<StudyProgram>(`/academic/study-programs/${id}/settings`, payload)
  },
  deleteStudyProgram(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/academic/study-programs/${id}`)
  },

  // Academic Years
  getAcademicYears(params?: QueryParams): Promise<ApiResponse<AcademicYear[]>> {
    return apiClient.get<AcademicYear[]>('/academic/academic-years', params)
  },
  getAcademicYear(id: number): Promise<ApiResponse<AcademicYear>> {
    return apiClient.get<AcademicYear>(`/academic/academic-years/${id}`)
  },
  createAcademicYear(payload: Partial<AcademicYear>): Promise<ApiResponse<AcademicYear>> {
    return apiClient.post<AcademicYear>('/academic/academic-years', payload)
  },
  updateAcademicYear(id: number, payload: Partial<AcademicYear>): Promise<ApiResponse<AcademicYear>> {
    return apiClient.put<AcademicYear>(`/academic/academic-years/${id}`, payload)
  },
  setActiveAcademicYear(id: number): Promise<ApiResponse<AcademicYear>> {
    return apiClient.put<AcademicYear>(`/academic/academic-years/${id}/set-active`)
  },
  deleteAcademicYear(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/academic/academic-years/${id}`)
  },

  // Semesters / Periode Akademik
  getSemesters(params?: QueryParams): Promise<ApiResponse<Semester[]>> {
    return apiClient.get<Semester[]>('/academic/semesters', params)
  },
  getSemester(id: number): Promise<ApiResponse<Semester>> {
    return apiClient.get<Semester>(`/academic/semesters/${id}`)
  },
  createSemester(payload: Partial<Semester>): Promise<ApiResponse<Semester>> {
    return apiClient.post<Semester>('/academic/semesters', payload)
  },
  updateSemester(id: number, payload: Partial<Semester>): Promise<ApiResponse<Semester>> {
    return apiClient.put<Semester>(`/academic/semesters/${id}`, payload)
  },
  setActiveSemester(id: number): Promise<ApiResponse<Semester>> {
    return apiClient.put<Semester>(`/academic/semesters/${id}/set-active`)
  },
  deleteSemester(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/academic/semesters/${id}`)
  },
}
