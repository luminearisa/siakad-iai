import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  AcademicSnapshot,
  CompletionEvaluation,
  EligibilityResult,
  MbkmActivityLog,
  MbkmActivityPlan,
  MbkmAdminDashboard,
  MbkmApplication,
  MbkmAssessment,
  MbkmAssessmentComponent,
  MbkmAttendance,
  MbkmAttendanceSummary,
  MbkmCatalogItem,
  MbkmCompletion,
  MbkmCooperation,
  MbkmDocument,
  MbkmExtensionRequest,
  MbkmFinalScore,
  MbkmIssue,
  MbkmLearningAgreement,
  MbkmLecturerDashboard,
  MbkmLogbookSummary,
  MbkmNotification,
  MbkmParticipant,
  MbkmPartner,
  MbkmPlacement,
  MbkmProgram,
  MbkmProgramLocation,
  MbkmProgramRequirement,
  MbkmProgramType,
  MbkmRecognition,
  MbkmRecognitionSummary,
  MbkmReport,
  MbkmStatusHistory,
  MbkmStudentDashboard,
  MbkmSupervisor,
  MbkmWithdrawalRequest,
} from '@/types/mbkm'

/**
 * MBKM service.
 *
 * Every endpoint mirrors `modules/MBKM/Routes/api.php` 1:1. Ownership and
 * permission checks live on the server; this layer never sends a student_id /
 * participant_id to prove identity.
 */
export const mbkmService = {
  // ------------------------------------------------------------------
  // Dashboards
  // ------------------------------------------------------------------
  dashboard(): Promise<ApiResponse<MbkmStudentDashboard | MbkmLecturerDashboard | MbkmAdminDashboard>> {
    return apiClient.get('/mbkm/dashboard')
  },
  studentDashboard(): Promise<ApiResponse<MbkmStudentDashboard>> {
    return apiClient.get('/mbkm/dashboard/student')
  },
  lecturerDashboard(params?: Record<string, unknown>): Promise<ApiResponse<MbkmLecturerDashboard>> {
    return apiClient.get('/mbkm/dashboard/lecturer', params)
  },
  adminDashboard(params?: Record<string, unknown>): Promise<ApiResponse<MbkmAdminDashboard>> {
    return apiClient.get('/mbkm/dashboard/admin', params)
  },

  // ------------------------------------------------------------------
  // Master: program types
  // ------------------------------------------------------------------
  getProgramTypes(params?: Record<string, unknown>): Promise<ApiResponse<MbkmProgramType[]>> {
    return apiClient.get('/mbkm/program-types', params)
  },
  createProgramType(data: Partial<MbkmProgramType>): Promise<ApiResponse<MbkmProgramType>> {
    return apiClient.post('/mbkm/program-types', data)
  },
  updateProgramType(id: number, data: Partial<MbkmProgramType>): Promise<ApiResponse<MbkmProgramType>> {
    return apiClient.put(`/mbkm/program-types/${id}`, data)
  },
  deleteProgramType(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/program-types/${id}`)
  },

  // ------------------------------------------------------------------
  // Master: partners + cooperations
  // ------------------------------------------------------------------
  getPartners(params?: Record<string, unknown>): Promise<ApiResponse<MbkmPartner[]>> {
    return apiClient.get('/mbkm/partners', params)
  },
  createPartner(data: Partial<MbkmPartner>): Promise<ApiResponse<MbkmPartner>> {
    return apiClient.post('/mbkm/partners', data)
  },
  updatePartner(id: number, data: Partial<MbkmPartner>): Promise<ApiResponse<MbkmPartner>> {
    return apiClient.put(`/mbkm/partners/${id}`, data)
  },
  deletePartner(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/partners/${id}`)
  },

  getCooperations(params?: Record<string, unknown>): Promise<ApiResponse<MbkmCooperation[]>> {
    return apiClient.get('/mbkm/cooperations', params)
  },
  createCooperation(data: Partial<MbkmCooperation>): Promise<ApiResponse<MbkmCooperation>> {
    return apiClient.post('/mbkm/cooperations', data)
  },
  updateCooperation(id: number, data: Partial<MbkmCooperation>): Promise<ApiResponse<MbkmCooperation>> {
    return apiClient.put(`/mbkm/cooperations/${id}`, data)
  },
  deleteCooperation(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/cooperations/${id}`)
  },

  // ------------------------------------------------------------------
  // Documents (single store)
  // ------------------------------------------------------------------
  getDocuments(params: {
    documentable_type?: string
    documentable_id?: number
    category?: string
    status?: string
  }): Promise<ApiResponse<MbkmDocument[]>> {
    return apiClient.get('/mbkm/documents', params)
  },
  uploadDocument(data: {
    documentable_type: string
    documentable_id: number
    category: string
    title?: string
    notes?: string
    expires_at?: string
    file: File
  }): Promise<ApiResponse<MbkmDocument>> {
    const form = new FormData()
    Object.entries(data).forEach(([key, value]) => {
      if (value !== undefined && value !== null) form.append(key, value as string | Blob)
    })
    return apiClient.post('/mbkm/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  verifyDocument(id: number, status: string, rejectionReason?: string): Promise<ApiResponse<MbkmDocument>> {
    return apiClient.put(`/mbkm/documents/${id}/verify`, {
      status,
      rejection_reason: rejectionReason,
    })
  },
  deleteDocument(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/documents/${id}`)
  },

  // ------------------------------------------------------------------
  // Programs
  // ------------------------------------------------------------------
  getPrograms(params?: Record<string, unknown>): Promise<ApiResponse<MbkmProgram[]>> {
    return apiClient.get('/mbkm/programs', params)
  },
  getProgram(id: number): Promise<ApiResponse<MbkmProgram>> {
    return apiClient.get(`/mbkm/programs/${id}`)
  },
  createProgram(data: Partial<MbkmProgram>): Promise<ApiResponse<MbkmProgram>> {
    return apiClient.post('/mbkm/programs', data)
  },
  updateProgram(id: number, data: Partial<MbkmProgram>): Promise<ApiResponse<MbkmProgram>> {
    return apiClient.put(`/mbkm/programs/${id}`, data)
  },
  deleteProgram(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/programs/${id}`)
  },
  transitionProgram(id: number, status: string, notes?: string): Promise<ApiResponse<MbkmProgram>> {
    return apiClient.post(`/mbkm/programs/${id}/transition`, { status, notes })
  },
  getProgramEligibility(id: number): Promise<ApiResponse<EligibilityResult>> {
    return apiClient.get(`/mbkm/programs/${id}/eligibility`)
  },
  storeProgramLocation(id: number, data: Partial<MbkmProgramLocation>): Promise<ApiResponse<MbkmProgramLocation>> {
    return apiClient.post(`/mbkm/programs/${id}/locations`, data)
  },
  deleteProgramLocation(id: number, locationId: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/programs/${id}/locations/${locationId}`)
  },
  storeProgramRequirement(id: number, data: Partial<MbkmProgramRequirement>): Promise<ApiResponse<MbkmProgramRequirement>> {
    return apiClient.post(`/mbkm/programs/${id}/requirements`, data)
  },
  deleteProgramRequirement(id: number, requirementId: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/programs/${id}/requirements/${requirementId}`)
  },
  syncSelectionCriteria(
    id: number,
    criteria: Array<{ name: string; weight: number; max_score?: number; description?: string }>
  ): Promise<ApiResponse<unknown>> {
    return apiClient.put(`/mbkm/programs/${id}/selection-criteria`, { criteria })
  },
  syncAssessmentComponents(
    id: number,
    components: Array<{
      code?: string
      name: string
      weight: number
      type?: string
      assessor_type?: string
      max_score?: number
      sort_order?: number
    }>
  ): Promise<ApiResponse<MbkmAssessmentComponent[]>> {
    return apiClient.put(`/mbkm/programs/${id}/assessment-components`, { components })
  },

  // Student catalog (only relevant programs, with explainable eligibility)
  getCatalog(params?: Record<string, unknown>): Promise<ApiResponse<MbkmCatalogItem[]>> {
    return apiClient.get('/mbkm/catalog', params)
  },

  // ------------------------------------------------------------------
  // Applications
  // ------------------------------------------------------------------
  getApplications(params?: Record<string, unknown>): Promise<ApiResponse<MbkmApplication[]>> {
    return apiClient.get('/mbkm/applications', params)
  },
  getApplication(id: number): Promise<
    ApiResponse<{ application: MbkmApplication; selection: unknown; history: MbkmStatusHistory[] }>
  > {
    return apiClient.get(`/mbkm/applications/${id}`)
  },
  createApplication(data: { program_id: number; motivation_statement?: string; notes?: string; student_id?: number }): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.post('/mbkm/applications', data)
  },
  updateApplication(id: number, data: { motivation_statement?: string; notes?: string }): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.put(`/mbkm/applications/${id}`, data)
  },
  submitApplication(id: number): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.post(`/mbkm/applications/${id}/submit`)
  },
  verifyApplication(id: number, decision: string, notes?: string): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.post(`/mbkm/applications/${id}/verify`, { decision, notes })
  },
  decideApplication(id: number, decision: string, notes?: string): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.post(`/mbkm/applications/${id}/decide`, { decision, notes })
  },
  scoreApplication(id: number, data: { criteria_id: number; score: number; notes?: string }): Promise<ApiResponse<unknown>> {
    return apiClient.post(`/mbkm/applications/${id}/score`, data)
  },
  withdrawApplication(id: number, reason: string): Promise<ApiResponse<MbkmApplication>> {
    return apiClient.post(`/mbkm/applications/${id}/withdraw`, { reason })
  },
  getApplicationHistory(id: number): Promise<ApiResponse<MbkmStatusHistory[]>> {
    return apiClient.get(`/mbkm/applications/${id}/history`)
  },

  // ------------------------------------------------------------------
  // Participants
  // ------------------------------------------------------------------
  getParticipants(params?: Record<string, unknown>): Promise<ApiResponse<MbkmParticipant[]>> {
    return apiClient.get('/mbkm/participants', params)
  },
  getParticipant(id: number): Promise<
    ApiResponse<{
      participant: MbkmParticipant
      attendance: MbkmAttendanceSummary
      logbook: MbkmLogbookSummary
      assessment: MbkmFinalScore
      recognition: MbkmRecognitionSummary
      completion: CompletionEvaluation
      history: MbkmStatusHistory[]
    }>
  > {
    return apiClient.get(`/mbkm/participants/${id}`)
  },
  assignParticipant(applicationId: number): Promise<ApiResponse<MbkmParticipant>> {
    return apiClient.post('/mbkm/participants/assign', { application_id: applicationId })
  },
  updateParticipant(id: number, data: Partial<MbkmParticipant>): Promise<ApiResponse<MbkmParticipant>> {
    return apiClient.put(`/mbkm/participants/${id}`, data)
  },
  startParticipant(id: number): Promise<ApiResponse<MbkmParticipant>> {
    return apiClient.post(`/mbkm/participants/${id}/start`)
  },
  /**
   * Finalise the MBKM final score. The backend refuses while assessment
   * components are still unscored; pass `force` to override explicitly.
   */
  finalizeParticipantScore(id: number, force = false): Promise<ApiResponse<MbkmParticipant>> {
    return apiClient.post(`/mbkm/participants/${id}/finalize-score`, { force })
  },
  getParticipantHistory(id: number): Promise<ApiResponse<MbkmStatusHistory[]>> {
    return apiClient.get(`/mbkm/participants/${id}/history`)
  },
  upsertPlacement(id: number, data: Partial<MbkmPlacement>): Promise<ApiResponse<MbkmPlacement>> {
    return apiClient.put(`/mbkm/participants/${id}/placement`, data)
  },
  storeSupervisor(id: number, data: Partial<MbkmSupervisor>): Promise<ApiResponse<MbkmSupervisor>> {
    return apiClient.post(`/mbkm/participants/${id}/supervisors`, data)
  },
  deleteSupervisor(id: number, supervisorId: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/participants/${id}/supervisors/${supervisorId}`)
  },

  // ------------------------------------------------------------------
  // Learning agreement
  // ------------------------------------------------------------------
  getLearningAgreement(participantId: number): Promise<
    ApiResponse<{ learning_agreement: MbkmLearningAgreement | null; total_credits: number; is_locked: boolean }>
  > {
    return apiClient.get(`/mbkm/participants/${participantId}/learning-agreement`)
  },
  upsertLearningAgreement(
    participantId: number,
    data: {
      title?: string
      period_start?: string
      period_end?: string
      notes?: string
      items?: Array<{
        activity_title: string
        activity_description?: string
        course_id?: number | null
        curriculum_subject_id?: number | null
        credits?: number
        target_grade?: string
        sort_order?: number
      }>
    }
  ): Promise<ApiResponse<MbkmLearningAgreement>> {
    return apiClient.put(`/mbkm/participants/${participantId}/learning-agreement`, data)
  },
  transitionLearningAgreement(participantId: number, status: string, notes?: string): Promise<ApiResponse<MbkmLearningAgreement>> {
    return apiClient.post(`/mbkm/participants/${participantId}/learning-agreement/transition`, { status, notes })
  },
  reopenLearningAgreement(participantId: number, reason: string): Promise<ApiResponse<MbkmLearningAgreement>> {
    return apiClient.post(`/mbkm/participants/${participantId}/learning-agreement/reopen`, { reason })
  },

  // ------------------------------------------------------------------
  // Activity plans
  // ------------------------------------------------------------------
  getActivityPlans(participantId: number, params?: Record<string, unknown>): Promise<ApiResponse<MbkmActivityPlan[]>> {
    return apiClient.get(`/mbkm/participants/${participantId}/activity-plans`, params)
  },
  createActivityPlan(participantId: number, data: Partial<MbkmActivityPlan>): Promise<ApiResponse<MbkmActivityPlan>> {
    return apiClient.post(`/mbkm/participants/${participantId}/activity-plans`, data)
  },
  updateActivityPlan(planId: number, data: Partial<MbkmActivityPlan>): Promise<ApiResponse<MbkmActivityPlan>> {
    return apiClient.put(`/mbkm/activity-plans/${planId}`, data)
  },
  deleteActivityPlan(planId: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/activity-plans/${planId}`)
  },

  // ------------------------------------------------------------------
  // Logbook
  // ------------------------------------------------------------------
  getAllLogbooks(params?: Record<string, unknown>): Promise<ApiResponse<MbkmActivityLog[]>> {
    return apiClient.get('/mbkm/logbooks', params)
  },
  getLogbooks(participantId: number, params?: Record<string, unknown>): Promise<ApiResponse<MbkmActivityLog[]>> {
    return apiClient.get(`/mbkm/participants/${participantId}/logbooks`, params)
  },
  createLogbook(participantId: number, data: Partial<MbkmActivityLog>): Promise<ApiResponse<MbkmActivityLog>> {
    return apiClient.post(`/mbkm/participants/${participantId}/logbooks`, data)
  },
  updateLogbook(id: number, data: Partial<MbkmActivityLog>): Promise<ApiResponse<MbkmActivityLog>> {
    return apiClient.put(`/mbkm/logbooks/${id}`, data)
  },
  submitLogbook(id: number): Promise<ApiResponse<MbkmActivityLog>> {
    return apiClient.post(`/mbkm/logbooks/${id}/submit`)
  },
  reviewLogbook(id: number, decision: string, notes?: string): Promise<ApiResponse<MbkmActivityLog>> {
    return apiClient.post(`/mbkm/logbooks/${id}/review`, { decision, notes })
  },
  deleteLogbook(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/logbooks/${id}`)
  },

  // ------------------------------------------------------------------
  // Attendance
  // ------------------------------------------------------------------
  getAttendances(participantId: number, params?: Record<string, unknown>): Promise<ApiResponse<MbkmAttendance[]>> {
    return apiClient.get(`/mbkm/participants/${participantId}/attendances`, params)
  },
  getAttendanceSummary(participantId: number): Promise<ApiResponse<MbkmAttendanceSummary>> {
    return apiClient.get(`/mbkm/participants/${participantId}/attendances/summary`)
  },
  createAttendance(participantId: number, data: Partial<MbkmAttendance>): Promise<ApiResponse<MbkmAttendance>> {
    return apiClient.post(`/mbkm/participants/${participantId}/attendances`, data)
  },
  createAttendanceBatch(
    participantId: number,
    rows: Array<Partial<MbkmAttendance>>
  ): Promise<ApiResponse<{ recorded: number; summary: MbkmAttendanceSummary }>> {
    return apiClient.post(`/mbkm/participants/${participantId}/attendances/batch`, { rows })
  },
  deleteAttendance(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/attendances/${id}`)
  },

  // ------------------------------------------------------------------
  // Issues
  // ------------------------------------------------------------------
  getIssues(params?: Record<string, unknown>): Promise<ApiResponse<MbkmIssue[]>> {
    return apiClient.get('/mbkm/issues', params)
  },
  createIssue(participantId: number, data: Partial<MbkmIssue>): Promise<ApiResponse<MbkmIssue>> {
    return apiClient.post(`/mbkm/participants/${participantId}/issues`, data)
  },
  updateIssue(id: number, data: Partial<MbkmIssue>): Promise<ApiResponse<MbkmIssue>> {
    return apiClient.put(`/mbkm/issues/${id}`, data)
  },
  deleteIssue(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/issues/${id}`)
  },

  // ------------------------------------------------------------------
  // Assessment
  // ------------------------------------------------------------------
  getAssessmentComponents(programId: number): Promise<ApiResponse<{ components: MbkmAssessmentComponent[]; total_weight: number }>> {
    return apiClient.get('/mbkm/assessment-components', { program_id: programId })
  },
  getAssessments(participantId: number): Promise<
    ApiResponse<{
      assessments: MbkmAssessment[]
      components: MbkmAssessmentComponent[]
      final: MbkmFinalScore
      participant_final_score: number | null
      letter_grade: string | null
      grade_point: number | null
    }>
  > {
    return apiClient.get(`/mbkm/participants/${participantId}/assessments`)
  },
  createAssessment(participantId: number, data: Partial<MbkmAssessment>): Promise<ApiResponse<MbkmAssessment>> {
    return apiClient.post(`/mbkm/participants/${participantId}/assessments`, data)
  },
  createPartnerAssessment(
    participantId: number,
    data: { component_id: number; assessor_name: string; score: number; max_score?: number; feedback?: string; recommendation?: string }
  ): Promise<ApiResponse<MbkmAssessment>> {
    return apiClient.post(`/mbkm/participants/${participantId}/assessments/partner`, data)
  },
  deleteAssessment(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/assessments/${id}`)
  },

  // ------------------------------------------------------------------
  // Recognition
  // ------------------------------------------------------------------
  getRecognitions(params?: Record<string, unknown>): Promise<ApiResponse<MbkmRecognition[]>> {
    return apiClient.get('/mbkm/recognitions', params)
  },
  getParticipantRecognitions(participantId: number): Promise<
    ApiResponse<{
      summary: MbkmRecognitionSummary
      recognitions: MbkmRecognition[]
      max_recognized_credits: number | null
      recognized_credits: number
      academic_result: unknown
    }>
  > {
    return apiClient.get(`/mbkm/participants/${participantId}/recognitions`)
  },
  getRecognition(id: number): Promise<ApiResponse<{ recognition: MbkmRecognition; history: MbkmStatusHistory[] }>> {
    return apiClient.get(`/mbkm/recognitions/${id}`)
  },
  createRecognition(participantId: number, data: Partial<MbkmRecognition>): Promise<ApiResponse<MbkmRecognition>> {
    return apiClient.post(`/mbkm/participants/${participantId}/recognitions`, data)
  },
  updateRecognition(id: number, data: Partial<MbkmRecognition>): Promise<ApiResponse<MbkmRecognition>> {
    return apiClient.put(`/mbkm/recognitions/${id}`, data)
  },
  transitionRecognition(id: number, status: string, notes?: string): Promise<ApiResponse<MbkmRecognition>> {
    return apiClient.post(`/mbkm/recognitions/${id}/transition`, { status, notes })
  },
  correctRecognition(id: number, reason: string): Promise<ApiResponse<MbkmRecognition>> {
    return apiClient.post(`/mbkm/recognitions/${id}/correct`, { reason })
  },
  deleteRecognition(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/mbkm/recognitions/${id}`)
  },

  // ------------------------------------------------------------------
  // Completion / withdrawal / extension
  // ------------------------------------------------------------------
  getCompletions(params?: Record<string, unknown>): Promise<ApiResponse<MbkmCompletion[]>> {
    return apiClient.get('/mbkm/completions', params)
  },
  evaluateCompletion(participantId: number): Promise<ApiResponse<CompletionEvaluation>> {
    return apiClient.get(`/mbkm/participants/${participantId}/completion`)
  },
  verifyCompletion(participantId: number, force = false, notes?: string): Promise<ApiResponse<MbkmCompletion>> {
    return apiClient.post(`/mbkm/participants/${participantId}/completion/verify`, { force, notes })
  },
  attachCertificate(
    participantId: number,
    file: File,
    category = 'certificate',
    title?: string
  ): Promise<ApiResponse<MbkmDocument>> {
    const form = new FormData()
    form.append('file', file)
    form.append('category', category)
    if (title) form.append('title', title)
    return apiClient.post(`/mbkm/participants/${participantId}/completion/certificate`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  getWithdrawals(params?: Record<string, unknown>): Promise<ApiResponse<MbkmWithdrawalRequest[]>> {
    return apiClient.get('/mbkm/withdrawals', params)
  },
  storeWithdrawal(
    participantId: number,
    data: { type: string; reason: string; effective_date?: string; document_id?: number }
  ): Promise<ApiResponse<MbkmWithdrawalRequest>> {
    return apiClient.post(`/mbkm/participants/${participantId}/withdrawals`, data)
  },
  decideWithdrawal(id: number, decision: 'approved' | 'rejected', notes?: string): Promise<ApiResponse<MbkmWithdrawalRequest>> {
    return apiClient.post(`/mbkm/withdrawals/${id}/decide`, { decision, notes })
  },

  getExtensions(params?: Record<string, unknown>): Promise<ApiResponse<MbkmExtensionRequest[]>> {
    return apiClient.get('/mbkm/extensions', params)
  },
  storeExtension(
    participantId: number,
    data: { new_end_date: string; reason: string }
  ): Promise<ApiResponse<MbkmExtensionRequest>> {
    return apiClient.post(`/mbkm/participants/${participantId}/extensions`, data)
  },
  decideExtension(id: number, decision: 'approved' | 'rejected', notes?: string): Promise<ApiResponse<MbkmExtensionRequest>> {
    return apiClient.post(`/mbkm/extensions/${id}/decide`, { decision, notes })
  },

  // ------------------------------------------------------------------
  // Reports
  // ------------------------------------------------------------------
  getReportTypes(): Promise<ApiResponse<string[]>> {
    return apiClient.get('/mbkm/reports')
  },
  getReport(type: string, params?: Record<string, unknown>): Promise<ApiResponse<MbkmReport>> {
    return apiClient.get(`/mbkm/reports/${type}`, params)
  },
  /**
   * Download a report as CSV. Uses the exact same filters as getReport so the
   * file matches what is on screen.
   */
  exportReport(type: string, params?: Record<string, unknown>): Promise<void> {
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')
    return apiClient.download(`/mbkm/reports/${type}/export`, params, `laporan-mbkm-${type}-${stamp}.csv`)
  },

  // ------------------------------------------------------------------
  // Notifications + history
  // ------------------------------------------------------------------
  getNotifications(params?: Record<string, unknown>): Promise<ApiResponse<MbkmNotification[]>> {
    return apiClient.get('/mbkm/notifications', params)
  },
  markNotificationRead(id: string): Promise<ApiResponse<void>> {
    return apiClient.post(`/mbkm/notifications/${id}/read`)
  },
  markAllNotificationsRead(): Promise<ApiResponse<void>> {
    return apiClient.post('/mbkm/notifications/read-all')
  },
  getHistory(params?: Record<string, unknown>): Promise<ApiResponse<MbkmStatusHistory[]>> {
    return apiClient.get('/mbkm/history', params)
  },
}

export type { AcademicSnapshot, EligibilityResult }
