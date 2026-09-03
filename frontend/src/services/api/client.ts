import axios, { type AxiosError, type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios'
import type { ApiResponse } from '@/types/api'
import { tokenStorage } from '../storage/token'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1'

export interface ApiError {
  message: string
  status?: number
  errors?: Record<string, string[]>
  isNetworkError?: boolean
}

class ApiClient {
  private instance: AxiosInstance

  constructor() {
    this.instance = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      timeout: 15000,
    })

    this.setupInterceptors()
  }

  private setupInterceptors(): void {
    // Request Interceptor: Attach Sanctum Bearer Token
    this.instance.interceptors.request.use(
      (config) => {
        const token = tokenStorage.get()
        if (token && config.headers) {
          config.headers.Authorization = `Bearer ${token}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Response Interceptor: Normalize and Handle HTTP Errors
    this.instance.interceptors.response.use(
      (response: AxiosResponse) => response,
      (error: AxiosError<ApiResponse<unknown>>) => {
        const customError: ApiError = {
          message: 'Terjadi kesalahan pada sistem.',
          status: error.response?.status,
        }

        if (!error.response) {
          customError.message = 'Koneksi jaringan terputus atau server tidak dapat diakses.'
          customError.isNetworkError = true
          return Promise.reject(customError)
        }

        const { status, data } = error.response

        if (data && typeof data === 'object') {
          if (data.message) {
            customError.message = data.message
          }
          if (data.errors) {
            customError.errors = data.errors as Record<string, string[]>
          }
        }

        switch (status) {
          case 401:
            // Token expired or invalid -> clear storage and trigger event
            tokenStorage.remove()
            if (!window.location.pathname.startsWith('/auth/login')) {
              window.dispatchEvent(new CustomEvent('auth:unauthorized'))
            }
            break
          case 403:
            customError.message = customError.message || 'Anda tidak memiliki izin untuk mengakses resource ini.'
            break
          case 404:
            customError.message = customError.message || 'Resource yang diminta tidak ditemukan.'
            break
          case 422:
            customError.message = customError.message || 'Validasi data gagal. Silakan periksa formulir input.'
            break
          case 429:
            customError.message = 'Terlalu banyak permintaan. Silakan tunggu beberapa saat.'
            break
          case 500:
            customError.message = customError.message || 'Terjadi kesalahan internal pada server.'
            break
        }

        return Promise.reject(customError)
      }
    )
  }

  public async get<T>(url: string, params?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const res = await this.instance.get<ApiResponse<T>>(url, { params, ...config })
    return res.data
  }

  public async post<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const res = await this.instance.post<ApiResponse<T>>(url, data, config)
    return res.data
  }

  public async put<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const res = await this.instance.put<ApiResponse<T>>(url, data, config)
    return res.data
  }

  public async patch<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const res = await this.instance.patch<ApiResponse<T>>(url, data, config)
    return res.data
  }

  public async delete<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const res = await this.instance.delete<ApiResponse<T>>(url, config)
    return res.data
  }
}

export const apiClient = new ApiClient()
