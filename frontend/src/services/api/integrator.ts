import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  ApiClient,
  ApiClientPayload,
  ApiKey,
  ApiKeyScopeCatalog,
  ApiRequestLog,
  IntegratorLogStats,
  IssuedApiKey,
} from '@/types/integrator'

/**
 * Integrator module API: external systems that may pull SIAKAD data, the API keys
 * they authenticate with, and the audit log of every request they made.
 *
 * The plaintext token is returned only by `issueKey` / `rotateKey`; nothing else in
 * this service can read it back.
 */
export const integratorService = {
  // Clients -----------------------------------------------------------------
  listClients(params?: Record<string, unknown>): Promise<ApiResponse<ApiClient[]>> {
    return apiClient.get<ApiClient[]>('/integrator/clients', params)
  },
  getClient(id: number | string): Promise<ApiResponse<ApiClient>> {
    return apiClient.get<ApiClient>(`/integrator/clients/${id}`)
  },
  createClient(payload: ApiClientPayload): Promise<ApiResponse<ApiClient>> {
    return apiClient.post<ApiClient>('/integrator/clients', payload)
  },
  updateClient(id: number | string, payload: Partial<ApiClientPayload>): Promise<ApiResponse<ApiClient>> {
    return apiClient.put<ApiClient>(`/integrator/clients/${id}`, payload)
  },
  deleteClient(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/integrator/clients/${id}`)
  },

  // Keys --------------------------------------------------------------------
  scopes(): Promise<ApiResponse<ApiKeyScopeCatalog>> {
    return apiClient.get<ApiKeyScopeCatalog>('/integrator/scopes')
  },
  listKeys(params?: Record<string, unknown>): Promise<ApiResponse<ApiKey[]>> {
    return apiClient.get<ApiKey[]>('/integrator/keys', params)
  },
  listClientKeys(clientId: number | string, params?: Record<string, unknown>): Promise<ApiResponse<ApiKey[]>> {
    return apiClient.get<ApiKey[]>(`/integrator/clients/${clientId}/keys`, params)
  },
  issueKey(
    clientId: number | string,
    payload: { name: string; scopes: string[]; expires_at?: string | null }
  ): Promise<ApiResponse<IssuedApiKey>> {
    return apiClient.post<IssuedApiKey>(`/integrator/clients/${clientId}/keys`, payload)
  },
  rotateKey(keyId: number | string, payload?: { expires_at?: string | null; reason?: string }): Promise<ApiResponse<IssuedApiKey>> {
    return apiClient.post<IssuedApiKey>(`/integrator/keys/${keyId}/rotate`, payload ?? {})
  },
  revokeKey(keyId: number | string, reason?: string): Promise<ApiResponse<ApiKey>> {
    return apiClient.post<ApiKey>(`/integrator/keys/${keyId}/revoke`, { reason })
  },
  deleteKey(keyId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/integrator/keys/${keyId}`)
  },

  // Access log --------------------------------------------------------------
  logs(params?: Record<string, unknown>): Promise<ApiResponse<ApiRequestLog[]>> {
    return apiClient.get<ApiRequestLog[]>('/integrator/logs', params)
  },
  logStats(params?: { days?: number }): Promise<ApiResponse<IntegratorLogStats>> {
    return apiClient.get<IntegratorLogStats>('/integrator/logs/stats', params)
  },
}
