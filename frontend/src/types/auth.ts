export interface CurrentUser {
  id: number
  name: string
  email: string
  status: string
  roles: string[]
  permissions: string[]
  last_login_at?: string | null
  created_at?: string | null
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message: string
  errors: unknown
}

export interface SessionPayload {
  access_token: string
  token_type: 'Bearer'
  user: CurrentUser
}
