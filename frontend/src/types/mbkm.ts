/**
 * MBKM (Merdeka Belajar Kampus Merdeka) types.
 *
 * Field names mirror the MBKM module schema exactly. All statuses are
 * configurable strings on the backend (enum-backed), so they are typed as
 * unions here but always rendered through the label/color maps below rather
 * than hardcoded conditions.
 */

// ---------------------------------------------------------------------------
// Status unions
// ---------------------------------------------------------------------------

export type ProgramStatus =
  | 'draft'
  | 'published'
  | 'registration_open'
  | 'registration_closed'
  | 'selection'
  | 'ongoing'
  | 'completed'
  | 'cancelled'
  | 'archived'

export type ApplicationStatus =
  | 'draft'
  | 'submitted'
  | 'verified'
  | 'rejected'
  | 'selected'
  | 'not_selected'
  | 'withdrawn'

export type ParticipantStatus =
  | 'assigned'
  | 'ongoing'
  | 'completed'
  | 'failed'
  | 'withdrawn'
  | 'terminated'
  | 'cancelled'

export type DocumentStatus = 'uploaded' | 'verified' | 'rejected' | 'expired'

export type LearningAgreementStatus = 'draft' | 'submitted' | 'reviewed' | 'approved' | 'locked'

export type ActivityPlanStatus = 'planned' | 'in_progress' | 'completed' | 'cancelled'

export type LogbookStatus = 'draft' | 'submitted' | 'approved' | 'revision_required' | 'rejected'

export type MbkmAttendanceStatus = 'present' | 'late' | 'permission' | 'sick' | 'absent'

export type IssueSeverity = 'low' | 'medium' | 'high' | 'critical'

export type IssueStatus = 'open' | 'in_progress' | 'resolved' | 'closed'

export type RecognitionStatus = 'draft' | 'submitted' | 'reviewed' | 'approved' | 'locked' | 'rejected'

export type RecognitionType = 'course_conversion' | 'credit_weight' | 'activity_conversion'

export type SupervisorRole = 'internal' | 'co_supervisor' | 'field'

export type CompletionStatus = 'pending' | 'incomplete' | 'complete' | 'verified'

export type WithdrawalType = 'withdrawal' | 'cancellation' | 'termination'

export type ApprovalDecision = 'approve' | 'reject' | 'revision'

export type LocationMode = 'on_campus' | 'off_campus' | 'domestic' | 'overseas' | 'remote' | 'hybrid'

export type OrganizerType = 'study_program' | 'faculty' | 'university' | 'external' | 'national_program'

/** WHO grades a component. Mirrors the backend `AssessorType` enum. */
export type AssessorType =
  | 'internal_supervisor'
  | 'co_supervisor'
  | 'field_supervisor'
  | 'partner'
  | 'self'
  | 'committee'

/** WHAT a component measures. Mirrors the backend `AssessmentComponentType` enum. */
export type ComponentType =
  | 'performance'
  | 'logbook'
  | 'final_project'
  | 'partner'
  | 'presentation'
  | 'report'
  | 'self'
  | 'other'

// ---------------------------------------------------------------------------
// Master data
// ---------------------------------------------------------------------------

export interface MbkmProgramType {
  id: number
  code: string
  name: string
  description?: string | null
  is_active: boolean
  sort_order: number
  created_at?: string
  updated_at?: string
}

export interface MbkmPartner {
  id: number
  code: string
  name: string
  type: string
  address?: string | null
  city?: string | null
  province?: string | null
  country: string
  phone?: string | null
  email?: string | null
  website?: string | null
  contact_person_name?: string | null
  contact_person_position?: string | null
  contact_person_email?: string | null
  contact_person_phone?: string | null
  status: string
  notes?: string | null
  cooperations?: MbkmCooperation[]
  created_at?: string
  updated_at?: string
}

export interface MbkmCooperation {
  id: number
  partner_id: number
  program_id?: number | null
  type: string
  number?: string | null
  title: string
  start_date?: string | null
  end_date?: string | null
  status: string
  notes?: string | null
  partner?: MbkmPartner
  program?: MbkmProgram
  created_at?: string
  updated_at?: string
}

export interface MbkmProgramLocation {
  id: number
  program_id: number
  name: string
  location_mode: LocationMode
  address?: string | null
  city?: string | null
  province?: string | null
  country: string
  is_remote: boolean
  latitude?: number | null
  longitude?: number | null
  notes?: string | null
}

export interface MbkmProgramRequirement {
  id: number
  program_id: number
  type: string
  code?: string | null
  name: string
  description?: string | null
  is_mandatory: boolean
  is_document: boolean
  rule?: Record<string, unknown> | null
  sort_order: number
}

export interface MbkmDocument {
  id: number
  documentable_type: string
  documentable_id: number
  category: string
  title?: string | null
  original_name: string
  file_path: string
  mime_type?: string | null
  size: number
  status: DocumentStatus
  uploaded_by?: number | null
  verified_by?: number | null
  verified_at?: string | null
  rejection_reason?: string | null
  expires_at?: string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface MbkmProgram {
  id: number
  program_type_id: number
  code: string
  name: string
  description?: string | null
  organizer_type: OrganizerType
  organizer_name?: string | null
  faculty_id?: number | null
  study_program_id?: number | null
  semester_id?: number | null
  registration_start_date?: string | null
  registration_end_date?: string | null
  start_date?: string | null
  end_date?: string | null
  quota?: number | null
  target_degree_levels?: string[] | null
  target_study_program_ids?: number[] | null
  target_admission_years?: number[] | null
  min_semester?: number | null
  max_semester?: number | null
  min_gpa?: number | string | null
  min_credits?: number | null
  max_recognized_credits?: number | null
  participation_limit?: number | null
  required_passed_course_ids?: number[] | null
  location_mode: LocationMode
  requires_documents: boolean
  requires_learning_agreement: boolean
  requires_attendance: boolean
  requires_logbook: boolean
  logbook_period: string
  requires_assessment: boolean
  requires_final_report: boolean
  requires_recognition: boolean
  allow_public_participant_count: boolean
  min_attendance_percentage?: number | string | null
  status: ProgramStatus
  requirements_text?: string | null
  notes?: string | null
  created_by?: number | null
  // computed / relations
  quota_used?: number
  applications_count?: number
  participants_count?: number
  program_type?: MbkmProgramType
  faculty?: { id: number; name: string } | null
  study_program?: { id: number; name: string } | null
  semester?: { id: number; name: string; academic_year?: { id: number; name: string } } | null
  locations?: MbkmProgramLocation[]
  requirements?: MbkmProgramRequirement[]
  selection_criteria?: MbkmSelectionCriteria[]
  assessment_components?: MbkmAssessmentComponent[]
  created_at?: string
  updated_at?: string
}

export interface MbkmSelectionCriteria {
  id: number
  program_id: number
  name: string
  description?: string | null
  weight: number | string
  max_score: number | string
  sort_order: number
  is_active: boolean
}

export interface MbkmSelectionScore {
  id: number
  application_id: number
  criteria_id: number
  reviewer_id: number
  score: number | string
  notes?: string | null
  scored_at?: string | null
  criteria?: MbkmSelectionCriteria
  reviewer?: { id: number; name: string }
}

// ---------------------------------------------------------------------------
// Eligibility (explainable)
// ---------------------------------------------------------------------------

export interface EligibilityCheck {
  code: string
  label: string
  passed: boolean
  message: string
  type?: string
}

export interface EligibilityResult {
  is_eligible: boolean
  reasons: string[]
  checks: EligibilityCheck[]
  snapshot?: AcademicSnapshot
  quota?: {
    quota: number | null
    used: number
    available: number | null
  }
}

export interface AcademicSnapshot {
  total_credits_passed: number
  cumulative_gpa: number
  last_semester_gpa?: number
  current_semester: number
  max_credits_next?: number
  [key: string]: unknown
}

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------

/**
 * One row of the student-facing catalog. The backend annotates every program
 * with the student's own eligibility so the UI can explain the status instead
 * of offering a button that only fails on submit.
 */
export interface MbkmCatalogItem {
  program: MbkmProgram
  is_eligible: boolean
  eligibility_reasons: string[]
  eligibility_checks: EligibilityCheck[]
  quota: {
    quota: number | null
    used: number
    available: number | null
  }
  participant_count: number | null
  registration_open: boolean
  has_active_application: boolean
}

export interface MbkmApplication {
  id: number
  registration_number: string
  program_id: number
  student_id: number
  motivation_statement?: string | null
  notes?: string | null
  status: ApplicationStatus
  submitted_at?: string | null
  verified_at?: string | null
  verified_by?: number | null
  verification_notes?: string | null
  selection_score?: number | string | null
  selection_rank?: number | null
  decided_at?: string | null
  decided_by?: number | null
  decision_notes?: string | null
  withdrawn_at?: string | null
  withdrawal_reason?: string | null
  academic_snapshot?: AcademicSnapshot | null
  program?: MbkmProgram
  student?: {
    id: number
    student_number: string
    full_name: string
    study_program_id?: number
    admission_year?: number
    study_program?: { id: number; name: string }
  }
  documents?: MbkmDocument[]
  scores?: MbkmSelectionScore[]
  eligibility?: EligibilityResult
  participant?: MbkmParticipant | null
  created_at?: string
  updated_at?: string
}

// ---------------------------------------------------------------------------
// Participant + execution
// ---------------------------------------------------------------------------

export interface MbkmParticipant {
  id: number
  participant_number: string
  program_id: number
  application_id?: number | null
  student_id: number
  start_date?: string | null
  end_date?: string | null
  original_end_date?: string | null
  status: ParticipantStatus
  final_score?: number | string | null
  letter_grade?: string | null
  grade_point?: number | string | null
  recognized_credits: number
  score_finalized_at?: string | null
  score_finalized_by?: number | null
  completed_at?: string | null
  terminated_at?: string | null
  termination_reason?: string | null
  notes?: string | null
  assigned_by?: number | null
  program?: MbkmProgram
  student?: MbkmApplication['student']
  application?: MbkmApplication
  placement?: MbkmPlacement | null
  supervisors?: MbkmSupervisor[]
  learning_agreement?: MbkmLearningAgreement | null
  activity_plans?: MbkmActivityPlan[]
  attendances?: MbkmAttendance[]
  assessments?: MbkmAssessment[]
  recognitions?: MbkmRecognition[]
  completion?: MbkmCompletion | null
  documents?: MbkmDocument[]
  created_at?: string
  updated_at?: string
}

export interface MbkmPlacement {
  id: number
  participant_id: number
  partner_id?: number | null
  location_id?: number | null
  division?: string | null
  position?: string | null
  batch?: string | null
  field_supervisor_name?: string | null
  field_supervisor_position?: string | null
  field_supervisor_email?: string | null
  field_supervisor_phone?: string | null
  field_supervisor_organization?: string | null
  start_date?: string | null
  end_date?: string | null
  status: string
  notes?: string | null
  partner?: MbkmPartner
  location?: MbkmProgramLocation
}

export interface MbkmSupervisor {
  id: number
  participant_id: number
  lecturer_id?: number | null
  role: SupervisorRole
  external_name?: string | null
  external_position?: string | null
  external_email?: string | null
  external_phone?: string | null
  external_organization?: string | null
  assigned_at?: string | null
  status: string
  notes?: string | null
  lecturer?: { id: number; nidn: string; full_name: string }
}

export interface MbkmLearningAgreementItem {
  id: number
  learning_agreement_id: number
  activity_title: string
  activity_description?: string | null
  course_id?: number | null
  curriculum_subject_id?: number | null
  credits: number
  target_grade?: string | null
  sort_order: number
  notes?: string | null
  course?: { id: number; code: string; name: string; credits: number }
}

export interface MbkmLearningAgreement {
  id: number
  participant_id: number
  title: string
  period_start?: string | null
  period_end?: string | null
  status: LearningAgreementStatus
  submitted_at?: string | null
  reviewed_at?: string | null
  reviewed_by?: number | null
  review_notes?: string | null
  approved_at?: string | null
  approved_by?: number | null
  locked_at?: string | null
  notes?: string | null
  items?: MbkmLearningAgreementItem[]
  total_credits?: number
}

export interface MbkmActivityPlan {
  id: number
  participant_id: number
  title: string
  description?: string | null
  target_output?: string | null
  planned_start_date?: string | null
  planned_end_date?: string | null
  planned_hours?: number | string | null
  location?: string | null
  status: ActivityPlanStatus
  notes?: string | null
  logs_count?: number
}

export interface MbkmActivityLog {
  id: number
  participant_id: number
  activity_plan_id?: number | null
  log_date: string
  period_label?: string | null
  activity: string
  description?: string | null
  duration_hours?: number | string | null
  output?: string | null
  location?: string | null
  status: LogbookStatus
  submitted_at?: string | null
  reviewed_at?: string | null
  reviewed_by?: number | null
  review_notes?: string | null
  revision_count: number
  locked_at?: string | null
  participant?: MbkmParticipant
  activity_plan?: MbkmActivityPlan | null
  reviewer?: { id: number; name: string } | null
  documents?: MbkmDocument[]
  created_at?: string
  updated_at?: string
}

export interface MbkmLogbookSummary {
  total: number
  approved: number
  submitted: number
  revision_required: number
  draft: number
  rejected?: number
  total_hours: number
  progress_percentage: number
}

export interface MbkmAttendance {
  id: number
  participant_id: number
  attendance_date: string
  status: MbkmAttendanceStatus
  check_in_time?: string | null
  check_out_time?: string | null
  duration_hours?: number | string | null
  notes?: string | null
  recorded_by?: number | null
}

export interface MbkmAttendanceSummary {
  total_days: number
  present: number
  late: number
  permission: number
  sick: number
  absent: number
  total_hours: number
  attendance_percentage: number
  minimum_percentage?: number | null
  meets_minimum?: boolean
}

export interface MbkmIssue {
  id: number
  participant_id: number
  category: string
  severity: IssueSeverity
  title: string
  description?: string | null
  status: IssueStatus
  reported_by?: number | null
  assigned_to?: number | null
  resolution?: string | null
  resolved_at?: string | null
  resolved_by?: number | null
  participant?: MbkmParticipant
  created_at?: string
  updated_at?: string
}

export interface MbkmAssessmentComponent {
  id: number
  program_id: number
  code: string
  name: string
  type: string
  weight: number | string
  max_score: number | string
  assessor_type: AssessorType
  description?: string | null
  sort_order: number
  is_active: boolean
}

export interface MbkmAssessment {
  id: number
  participant_id: number
  component_id: number
  assessor_type: AssessorType
  assessor_user_id?: number | null
  assessor_lecturer_id?: number | null
  assessor_name?: string | null
  score: number | string
  max_score: number | string
  feedback?: string | null
  recommendation?: string | null
  status: string
  assessed_at?: string | null
  component?: MbkmAssessmentComponent
  assessor?: { id: number; name: string } | null
}

/**
 * One row of the weighted assessment breakdown, as returned by
 * `MbkmAssessmentService::computeFinalScore()`.
 *
 * Note the key names are backend-native (`component_name`, `assessors`) — they
 * are NOT `name` / `is_scored`.
 */
export interface MbkmFinalScoreBreakdown {
  component_id: number
  component_code: string
  component_name: string
  type: string
  assessor_type: string
  weight: number
  average_score: number | null
  normalized_score: number | null
  weighted_score: number
  /** How many assessors have recorded a score for this component. 0 = unscored. */
  assessors: number
  feedbacks?: string[]
}

export interface MbkmFinalScore {
  final_score: number
  breakdown: MbkmFinalScoreBreakdown[]
  is_complete: boolean
  total_weight: number
}

export interface MbkmRecognition {
  id: number
  participant_id: number
  program_id: number
  activity_log_id?: number | null
  source_label?: string | null
  course_id?: number | null
  curriculum_id?: number | null
  curriculum_subject_id?: number | null
  credits: number
  recognition_type: RecognitionType
  score?: number | string | null
  letter_grade?: string | null
  grade_point?: number | string | null
  semester_id?: number | null
  status: RecognitionStatus
  submitted_at?: string | null
  reviewed_at?: string | null
  reviewed_by?: number | null
  review_notes?: string | null
  approved_at?: string | null
  approved_by?: number | null
  locked_at?: string | null
  academic_enrollment_id?: number | null
  academic_enrollment_item_id?: number | null
  academic_class_id?: number | null
  sync_status?: string
  sync_message?: string | null
  synced_at?: string | null
  notes?: string | null
  course?: { id: number; code: string; name: string; credits: number }
  curriculum?: { id: number; name: string }
  semester?: { id: number; name: string }
  participant?: MbkmParticipant
}

export interface MbkmRecognitionSummary {
  total: number
  recognized_credits: number
  pending: number
  approved: number
  draft: number
  max_recognized_credits?: number | null
  remaining_credits?: number | null
}

export interface CompletionRequirement {
  code: string
  label: string
  satisfied: boolean
  detail: string
}

export interface MbkmCompletion {
  id: number
  participant_id: number
  status: CompletionStatus
  requirements_snapshot?: CompletionRequirement[] | null
  unmet_requirements?: string[] | null
  checked_at?: string | null
  checked_by?: number | null
  verified_at?: string | null
  verified_by?: number | null
  completed_at?: string | null
  notes?: string | null
  certificate_document_id?: number | null
  certificate?: MbkmDocument | null
  certificate_document?: MbkmDocument | null
}

export interface CompletionEvaluation {
  is_complete: boolean
  status: CompletionStatus
  requirements: CompletionRequirement[]
  unmet: string[]
  completion?: MbkmCompletion | null
}

export interface MbkmWithdrawalRequest {
  id: number
  participant_id: number
  type: WithdrawalType
  reason: string
  effective_date?: string | null
  document_id?: number | null
  status: string
  requested_by?: number | null
  decided_at?: string | null
  decided_by?: number | null
  decision_notes?: string | null
  participant?: MbkmParticipant
  created_at?: string
}

export interface MbkmExtensionRequest {
  id: number
  participant_id: number
  old_end_date?: string | null
  new_end_date: string
  reason: string
  status: string
  requested_by?: number | null
  decided_at?: string | null
  decided_by?: number | null
  decision_notes?: string | null
  participant?: MbkmParticipant
  created_at?: string
}

export interface MbkmStatusHistory {
  id: number
  entity_type: string
  entity_id: number
  action: string
  from_status?: string | null
  to_status?: string | null
  actor_id?: number | null
  notes?: string | null
  meta?: Record<string, unknown> | null
  actor?: { id: number; name: string } | null
  created_at?: string
}

// ---------------------------------------------------------------------------
// Dashboards
// ---------------------------------------------------------------------------

export interface MbkmStudentDashboard {
  role: 'mahasiswa'
  applications?: MbkmApplication[]
  participants?: MbkmParticipant[]
  counters?: Record<string, number>
  active_participant?: MbkmParticipant | null
  logbook_summary?: MbkmLogbookSummary
  attendance_summary?: MbkmAttendanceSummary
  recognitions?: MbkmRecognition[]
  recognition_summary?: MbkmRecognitionSummary
  academic_result?: Record<string, unknown> | null
  completion?: CompletionEvaluation | null
}

export interface MbkmLecturerDashboard {
  role: 'dosen'
  participants?: MbkmParticipant[]
  counters?: Record<string, number>
  pending_logbooks?: MbkmActivityLog[]
  pending_assessments?: MbkmParticipant[]
  pending_recognitions?: MbkmRecognition[]
  issues?: MbkmIssue[]
}

export interface MbkmAdminDashboard {
  role: 'admin'
  counters?: Record<string, number>
  active_programs?: MbkmProgram[]
  pending_applications?: MbkmApplication[]
  active_participants?: MbkmParticipant[]
  pending_logbooks?: MbkmActivityLog[]
  pending_recognitions?: MbkmRecognition[]
  pending_completions?: MbkmParticipant[]
  participants_by_study_program?: Array<{ id: number; name: string; total: number }>
  participants_by_partner?: Array<{ id: number; name: string; total: number }>
  action_items?: Array<{ code: string; label: string; total: number; severity?: string }>
}

export interface MbkmReport {
  type: string
  generated_at: string
  rows: Array<Record<string, unknown>>
  summary?: MbkmAdminDashboard
}

export const REPORT_LABELS: Record<string, string> = {
  programs: 'Daftar Program MBKM',
  applicants: 'Daftar Pendaftar',
  participants: 'Daftar Peserta',
  participants_by_study_program: 'Peserta per Program Studi',
  participants_by_partner: 'Peserta per Mitra',
  participants_by_period: 'Peserta per Periode',
  participant_progress: 'Progress Peserta',
  attendance: 'Laporan Presensi',
  logbook: 'Laporan Logbook',
  assessment: 'Laporan Penilaian',
  recognition: 'Laporan Rekognisi',
  recognized_credits: 'Laporan SKS yang Diakui',
  completion: 'Laporan Penyelesaian',
  summary: 'Dashboard Ringkasan MBKM',
}

// ---------------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------------

export interface MbkmNotification {
  id: string
  type?: string
  module?: string
  event?: string
  title?: string
  message?: string
  payload?: Record<string, unknown>
  read_at?: string | null
  created_at?: string
}

// ---------------------------------------------------------------------------
// Display maps — every status is rendered through these, never hardcoded
// ---------------------------------------------------------------------------

export const PROGRAM_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  published: 'Diterbitkan',
  registration_open: 'Pendaftaran Dibuka',
  registration_closed: 'Pendaftaran Ditutup',
  selection: 'Seleksi',
  ongoing: 'Berlangsung',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  archived: 'Diarsipkan',
}

export const PROGRAM_STATUS_VARIANTS: Record<string, string> = {
  draft: 'neutral',
  published: 'info',
  registration_open: 'success',
  registration_closed: 'warning',
  selection: 'warning',
  ongoing: 'primary',
  completed: 'success',
  cancelled: 'danger',
  archived: 'neutral',
}

export const APPLICATION_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Diajukan',
  verified: 'Terverifikasi',
  rejected: 'Ditolak',
  selected: 'Diterima',
  not_selected: 'Tidak Diterima',
  withdrawn: 'Dibatalkan',
}

export const APPLICATION_STATUS_VARIANTS: Record<string, string> = {
  draft: 'neutral',
  submitted: 'info',
  verified: 'primary',
  rejected: 'danger',
  selected: 'success',
  not_selected: 'danger',
  withdrawn: 'warning',
}

export const PARTICIPANT_STATUS_LABELS: Record<string, string> = {
  assigned: 'Ditetapkan',
  ongoing: 'Berjalan',
  completed: 'Selesai',
  failed: 'Gagal',
  withdrawn: 'Mengundurkan Diri',
  terminated: 'Dihentikan',
  cancelled: 'Dibatalkan',
}

export const PARTICIPANT_STATUS_VARIANTS: Record<string, string> = {
  assigned: 'info',
  ongoing: 'primary',
  completed: 'success',
  failed: 'danger',
  withdrawn: 'warning',
  terminated: 'danger',
  cancelled: 'neutral',
}

export const DOCUMENT_STATUS_LABELS: Record<string, string> = {
  uploaded: 'Terunggah',
  verified: 'Terverifikasi',
  rejected: 'Ditolak',
  expired: 'Kadaluarsa',
}

export const DOCUMENT_STATUS_VARIANTS: Record<string, string> = {
  uploaded: 'info',
  verified: 'success',
  rejected: 'danger',
  expired: 'warning',
}

export const LEARNING_AGREEMENT_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Diajukan',
  reviewed: 'Ditinjau',
  approved: 'Disetujui',
  locked: 'Terkunci',
}

export const LEARNING_AGREEMENT_STATUS_VARIANTS: Record<string, string> = {
  draft: 'neutral',
  submitted: 'info',
  reviewed: 'primary',
  approved: 'success',
  locked: 'success',
}

export const LOGBOOK_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Menunggu Review',
  approved: 'Disetujui',
  revision_required: 'Perlu Revisi',
  rejected: 'Ditolak',
}

export const LOGBOOK_STATUS_VARIANTS: Record<string, string> = {
  draft: 'neutral',
  submitted: 'warning',
  approved: 'success',
  revision_required: 'warning',
  rejected: 'danger',
}

export const ATTENDANCE_STATUS_LABELS: Record<string, string> = {
  present: 'Hadir',
  late: 'Terlambat',
  permission: 'Izin',
  sick: 'Sakit',
  absent: 'Alfa',
}

export const ATTENDANCE_STATUS_VARIANTS: Record<string, string> = {
  present: 'success',
  late: 'warning',
  permission: 'info',
  sick: 'info',
  absent: 'danger',
}

export const ISSUE_SEVERITY_LABELS: Record<string, string> = {
  low: 'Rendah',
  medium: 'Sedang',
  high: 'Tinggi',
  critical: 'Kritis',
}

export const ISSUE_SEVERITY_VARIANTS: Record<string, string> = {
  low: 'neutral',
  medium: 'info',
  high: 'warning',
  critical: 'danger',
}

export const ISSUE_STATUS_LABELS: Record<string, string> = {
  open: 'Terbuka',
  in_progress: 'Diproses',
  resolved: 'Selesai',
  closed: 'Ditutup',
}

export const ISSUE_STATUS_VARIANTS: Record<string, string> = {
  open: 'danger',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'neutral',
}

export const RECOGNITION_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  submitted: 'Diajukan',
  reviewed: 'Ditinjau',
  approved: 'Disetujui',
  locked: 'Terkunci',
  rejected: 'Ditolak',
}

export const RECOGNITION_STATUS_VARIANTS: Record<string, string> = {
  draft: 'neutral',
  submitted: 'info',
  reviewed: 'primary',
  approved: 'success',
  locked: 'success',
  rejected: 'danger',
}

export const RECOGNITION_TYPE_LABELS: Record<string, string> = {
  course_conversion: 'Konversi Mata Kuliah',
  credit_weight: 'Bobot SKS Kegiatan',
  activity_conversion: 'Konversi Aktivitas',
}

export const SUPERVISOR_ROLE_LABELS: Record<string, string> = {
  internal: 'Pembimbing Internal',
  co_supervisor: 'Pembimbing Pendamping',
  field: 'Pembimbing Lapangan',
}

export const COMPLETION_STATUS_LABELS: Record<string, string> = {
  pending: 'Menunggu',
  incomplete: 'Belum Lengkap',
  complete: 'Lengkap',
  verified: 'Terverifikasi',
}

export const COMPLETION_STATUS_VARIANTS: Record<string, string> = {
  pending: 'neutral',
  incomplete: 'warning',
  complete: 'info',
  verified: 'success',
}

export const WITHDRAWAL_TYPE_LABELS: Record<string, string> = {
  withdrawal: 'Pengunduran Diri',
  cancellation: 'Pembatalan',
  termination: 'Penghentian',
}

export const LOCATION_MODE_LABELS: Record<string, string> = {
  on_campus: 'Dalam Kampus',
  off_campus: 'Luar Kampus',
  domestic: 'Dalam Negeri',
  overseas: 'Luar Negeri',
  remote: 'Daring',
  hybrid: 'Hybrid',
}

export const ORGANIZER_TYPE_LABELS: Record<string, string> = {
  study_program: 'Program Studi',
  faculty: 'Fakultas',
  university: 'Universitas',
  external: 'Eksternal',
  national_program: 'Program Nasional',
}

/**
 * WHO grades a component. Mirrors the backend `AssessorType` enum — the list
 * must stay identical or the score-recording endpoint rejects the payload.
 */
export const ASSESSOR_TYPE_LABELS: Record<string, string> = {
  internal_supervisor: 'Pembimbing Internal',
  co_supervisor: 'Co-Pembimbing',
  field_supervisor: 'Pembimbing Lapangan',
  partner: 'Mitra',
  self: 'Penilaian Diri',
  committee: 'Tim Penguji',
}

/**
 * WHAT a component measures. Mirrors the backend `AssessmentComponentType`
 * enum. Distinct vocabulary from ASSESSOR_TYPE_LABELS — do not merge them.
 */
export const COMPONENT_TYPE_LABELS: Record<string, string> = {
  performance: 'Kinerja',
  logbook: 'Logbook',
  final_project: 'Proyek Akhir',
  partner: 'Penilaian Mitra',
  presentation: 'Presentasi',
  report: 'Laporan',
  self: 'Penilaian Diri',
  other: 'Lainnya',
}

export const LOGBOOK_PERIOD_LABELS: Record<string, string> = {
  daily: 'Harian',
  weekly: 'Mingguan',
  monthly: 'Bulanan',
  periodic: 'Periodik',
}

export const REQUIREMENT_TYPE_LABELS: Record<string, string> = {
  academic: 'Akademik',
  administrative: 'Administratif',
  document: 'Dokumen',
  custom: 'Kustom',
}

export const PARTNER_TYPE_LABELS: Record<string, string> = {
  company: 'Perusahaan',
  university: 'Perguruan Tinggi',
  school: 'Sekolah',
  government: 'Pemerintah',
  ngo: 'LSM / NGO',
  startup: 'Startup',
  other: 'Lainnya',
}

export const COOPERATION_TYPE_LABELS: Record<string, string> = {
  mou: 'MoU',
  moa: 'MoA',
  ia: 'IA',
  pks: 'PKS',
  other: 'Lainnya',
}

/** Options helper — turns a label map into `{ value, label }` pairs. */
export function optionsFrom(map: Record<string, string>): Array<{ value: string; label: string }> {
  return Object.entries(map).map(([value, label]) => ({ value, label }))
}

export function labelOf(map: Record<string, string>, value?: string | null): string {
  if (!value) return '-'
  return map[value] ?? value
}

export function variantOf(map: Record<string, string>, value?: string | null): string {
  if (!value) return 'neutral'
  return map[value] ?? 'neutral'
}
