import { http } from './http'
import type { ApiResponse, CurrentUser, SessionPayload } from '@/types/auth'

export interface LoginInput {
  email: string
  password: string
}

export async function createSession(input: LoginInput): Promise<SessionPayload> {
  const response = await http.post<ApiResponse<SessionPayload>>('/session', input)
  return response.data.data
}

export async function fetchCurrentSession(): Promise<CurrentUser> {
  const response = await http.get<ApiResponse<CurrentUser>>('/session')
  return response.data.data
}

export async function deleteSession(): Promise<void> {
  await http.delete('/session')
}
