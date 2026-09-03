import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  YudisiumPeriod,
  YudisiumParticipant,
  YudisiumRequirement,
  EligibleStudentAudit,
} from '@/types/yudisium'

export const yudisiumService = {
  // Periods
  getPeriods(params?: any): Promise<ApiResponse<YudisiumPeriod[]>> {
    return apiClient.get<YudisiumPeriod[]>('/graduation/yudisium/periods', params)
  },
  createPeriod(data: Partial<YudisiumPeriod>): Promise<ApiResponse<YudisiumPeriod>> {
    return apiClient.post<YudisiumPeriod>('/graduation/yudisium/periods', data)
  },
  updatePeriod(id: number | string, data: Partial<YudisiumPeriod>): Promise<ApiResponse<YudisiumPeriod>> {
    return apiClient.put<YudisiumPeriod>(`/graduation/yudisium/periods/${id}`, data)
  },
  deletePeriod(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/graduation/yudisium/periods/${id}`)
  },

  // Eligible
  getEligibleStudents(params?: any): Promise<ApiResponse<EligibleStudentAudit[]>> {
    return apiClient.get<EligibleStudentAudit[]>('/graduation/yudisium/eligible', params)
  },
  registerEligibleStudents(periodId: number, studentIds: number[]): Promise<ApiResponse<any>> {
    return apiClient.post<any>('/graduation/yudisium/eligible/register', {
      yudisium_period_id: periodId,
      student_ids: studentIds,
    })
  },

  // Approvals
  getApprovals(params?: any): Promise<ApiResponse<YudisiumParticipant[]>> {
    return apiClient.get<YudisiumParticipant[]>('/graduation/yudisium/approvals', params)
  },
  updateApprovalStatus(participantId: number | string, status: string, notes?: string, rejectionReason?: string): Promise<ApiResponse<YudisiumParticipant>> {
    return apiClient.put<YudisiumParticipant>(
      `/graduation/yudisium/approvals/${participantId}/status`,
      { status, notes, rejection_reason: rejectionReason }
    )
  },
  finalizeParticipants(periodId?: number, participantIds?: number[]): Promise<ApiResponse<void>> {
    return apiClient.post<void>('/graduation/yudisium/approvals/finalize', {
      yudisium_period_id: periodId,
      participant_ids: participantIds,
    })
  },

  // Participants
  getParticipants(params?: any): Promise<ApiResponse<YudisiumParticipant[]>> {
    return apiClient.get<YudisiumParticipant[]>('/graduation/yudisium/participants', params)
  },
  getParticipant(id: number | string): Promise<ApiResponse<YudisiumParticipant>> {
    return apiClient.get<YudisiumParticipant>(`/graduation/yudisium/participants/${id}`)
  },
  inputSkBatch(data: { yudisium_period_id: number; study_program_id: number; sk_number: string; sk_date: string }): Promise<ApiResponse<void>> {
    return apiClient.post<void>('/graduation/yudisium/participants/input-sk-batch', data)
  },
  toggleCertificate(participantId: number | string, takenBy?: string): Promise<ApiResponse<YudisiumParticipant>> {
    return apiClient.patch<YudisiumParticipant>(
      `/graduation/yudisium/participants/${participantId}/toggle-certificate`,
      { taken_by: takenBy }
    )
  },

  // Requirements
  getRequirements(params?: any): Promise<ApiResponse<YudisiumRequirement[]>> {
    return apiClient.get<YudisiumRequirement[]>('/graduation/yudisium/requirements', params)
  },
  createRequirement(data: Partial<YudisiumRequirement>): Promise<ApiResponse<YudisiumRequirement>> {
    return apiClient.post<YudisiumRequirement>('/graduation/yudisium/requirements', data)
  },
  updateRequirement(id: number | string, data: Partial<YudisiumRequirement>): Promise<ApiResponse<YudisiumRequirement>> {
    return apiClient.put<YudisiumRequirement>(`/graduation/yudisium/requirements/${id}`, data)
  },
  deleteRequirement(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/graduation/yudisium/requirements/${id}`)
  },
}
