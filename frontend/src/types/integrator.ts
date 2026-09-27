/**
 * Types for the Integrator module (API clients, keys, access log).
 */

export type ApiKeyStatus = 'active' | 'revoked' | 'expired' | 'client_inactive'

export interface IntegratorScope {
  value: string
  label: string
  sensitive: boolean
}

export interface ApiClient {
  id: number
  name: string
  slug: string
  description?: string | null
  contact_email?: string | null
  is_active: boolean
  allowed_ips: string[]
  rate_limit_per_minute: number
  last_used_at?: string | null
  created_at?: string | null
  updated_at?: string | null
  creator?: { id: number; name: string } | null
  keys_count?: number
  active_keys_count?: number
  keys?: ApiKey[]
}

export interface ApiKeyScopeDetail {
  value: string
  label: string
  sensitive: boolean
}

export interface ApiKey {
  id: number
  api_client_id: number
  name: string
  key_prefix: string
  scopes: string[]
  scope_details?: ApiKeyScopeDetail[]
  status: ApiKeyStatus
  status_label: string
  expires_at?: string | null
  revoked_at?: string | null
  revoked_reason?: string | null
  last_used_at?: string | null
  last_used_ip?: string | null
  request_count: number
  created_at?: string | null
  client?: {
    id: number
    name: string
    slug: string
    is_active: boolean
  } | null
}

/** Response of the issue/rotate endpoint: the only place a plaintext token appears. */
export interface IssuedApiKey {
  key: ApiKey
  plain_key: string
  warning: string
  revoked_key?: ApiKey
  usage?: {
    header: string
    example: string
  }
}

export interface ApiKeyScopeCatalog {
  scopes: IntegratorScope[]
  groups: Record<string, string[]>
  defaults: string[]
  statuses: Record<string, string>
}

export interface ApiClientPayload {
  name: string
  slug?: string | null
  description?: string | null
  contact_email?: string | null
  is_active?: boolean
  allowed_ips?: string[] | null
  rate_limit_per_minute?: number
}

export interface ApiRequestLog {
  id: number
  method: string
  path: string
  query?: Record<string, unknown> | null
  status_code: number
  is_successful: boolean
  duration_ms: number
  ip_address?: string | null
  user_agent?: string | null
  error_message?: string | null
  created_at?: string | null
  client?: { id: number; name: string; slug: string } | null
  key?: { id: number; name: string; key_prefix: string } | null
}

export interface IntegratorLogStats {
  window_days: number
  totals: {
    requests: number
    successful: number
    client_errors: number
    server_errors: number
    success_rate: number
    avg_duration_ms: number
  }
  top_paths: Array<{ path: string; hits: number }>
  per_client: Array<{ client: string | null; slug: string | null; hits: number; successful: number }>
  recent_errors: Array<{
    id: number
    path: string
    status_code: number
    error_message?: string | null
    client?: string | null
    created_at?: string | null
  }>
}

/**
 * Satu dataset pelaporan yang bisa diunduh dari halaman data SIAKAD
 * (dipakai oleh tombol "Export as…" di halaman mahasiswa, kelas, KRS, dst.).
 */
export interface DatasetExportItem {
  /** Kunci dataset di backend, mis. `students`. */
  key: string
  /** Nama dataset, mis. "Data Mahasiswa". */
  label: string
  /** Permission halaman asal yang dipakai backend untuk memfilter katalog. */
  permission: string
  /** Jumlah kolom pada berkas unduhan. */
  columns: number
  /** Parameter wajib (mis. `semester_id` untuk AKM). */
  requires: string[]
  /** Parameter filter yang diteruskan dari halaman. */
  filters: string[]
}
