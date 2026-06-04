import { http } from './http'

export interface AiModel {
  id: number
  name: string
  model_key: string
  task_type: 'embedding' | 'reranker' | 'llm' | 'ocr'
  provider: string
  model_id?: string | null
  local_path?: string | null
  dimension?: number | null
  device?: string | null
  status: string
  is_active: boolean
  description?: string | null
  download_command?: string | null
  error_message?: string | null
  metadata?: Record<string, unknown> | null
  last_checked_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AiModelEvent {
  id: number
  ai_model_id: number
  event_type: string
  message?: string | null
  metadata?: Record<string, unknown> | null
  created_at?: string | null
}

export interface LaravelPaginator<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface AiModelListResponse {
  success: boolean
  data: LaravelPaginator<AiModel>
  message: string
  errors: unknown
}

export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message: string
  errors: unknown
}

export interface ListAiModelParams {
  task_type?: string
  status?: string
  page?: number
  per_page?: number
}

export async function listAiModels(params: ListAiModelParams = {}): Promise<LaravelPaginator<AiModel>> {
  const response = await http.get<AiModelListResponse>('/ai-models', { params })
  return response.data.data
}

export async function installRecommendedAiModels(): Promise<{ installed_count: number }> {
  const response = await http.post<ApiEnvelope<{ installed_count: number }>>('/ai-models/install-recommended')
  return response.data.data
}

export async function checkAiModel(id: number): Promise<Record<string, unknown>> {
  const response = await http.post<ApiEnvelope<Record<string, unknown>>>(`/ai-models/${id}/check`)
  return response.data.data
}

export async function activateAiModel(id: number): Promise<AiModel> {
  const response = await http.post<ApiEnvelope<AiModel>>(`/ai-models/${id}/activate`)
  return response.data.data
}

export async function listAiModelEvents(id: number): Promise<AiModelEvent[]> {
  const response = await http.get<ApiEnvelope<AiModelEvent[]>>(`/ai-models/${id}/events`)
  return response.data.data
}
