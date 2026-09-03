export interface Role {
  id: number
  name: string
  display_name: string
  description?: string
  is_system: boolean
}

export interface Permission {
  id: number
  name: string
  display_name: string
  group: string
  description?: string
}

export interface User {
  id: number
  name: string
  email: string
  status: 'active' | 'inactive' | 'suspended'
  roles: Role[]
  permissions: (Permission | string)[]
  student?: {
    id: number
    student_number: string
    full_name: string
  } | null
  lecturer?: {
    id: number
    nidn: string
    full_name: string
  } | null
  created_at: string
  updated_at: string
}

export interface LoginPayload {
  email: string
  password: string
  device_name?: string
}

export interface LoginResponseData {
  user: User
  token: string
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}
