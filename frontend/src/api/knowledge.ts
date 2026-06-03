import { http } from './http'
import type { KnowledgeBase, KnowledgeDocument, KnowledgeTag, PaginatedResponse } from '@/types/knowledge'

export interface ListParams {
  keyword?: string
  status?: string
  knowledge_base_id?: number | string
  per_page?: number
}

export type KnowledgeBasePayload = Pick<KnowledgeBase, 'name' | 'description' | 'industry' | 'status'>
export type KnowledgeTagPayload = Pick<KnowledgeTag, 'name' | 'tag_type'>

export interface KnowledgeDocumentPayload {
  knowledge_base_id: number | null
  title: string
  summary?: string | null
  content?: string | null
  source_type?: string
  source_url?: string | null
  version?: string
  status?: string
  tag_ids?: number[]
}

export async function listKnowledgeBases(params: ListParams = {}): Promise<PaginatedResponse<KnowledgeBase>> {
  const response = await http.get<PaginatedResponse<KnowledgeBase>>('/knowledge-bases', { params })
  return response.data
}

export async function createKnowledgeBase(payload: KnowledgeBasePayload): Promise<KnowledgeBase> {
  const response = await http.post<{ data: KnowledgeBase }>('/knowledge-bases', payload)
  return response.data.data
}

export async function updateKnowledgeBase(id: number, payload: KnowledgeBasePayload): Promise<KnowledgeBase> {
  const response = await http.put<{ data: KnowledgeBase }>(`/knowledge-bases/${id}`, payload)
  return response.data.data
}

export async function deleteKnowledgeBase(id: number): Promise<void> {
  await http.delete(`/knowledge-bases/${id}`)
}

export async function listKnowledgeTags(params: ListParams = {}): Promise<PaginatedResponse<KnowledgeTag>> {
  const response = await http.get<PaginatedResponse<KnowledgeTag>>('/knowledge-tags', { params })
  return response.data
}

export async function createKnowledgeTag(payload: KnowledgeTagPayload): Promise<KnowledgeTag> {
  const response = await http.post<{ data: KnowledgeTag }>('/knowledge-tags', payload)
  return response.data.data
}

export async function listKnowledgeDocuments(params: ListParams = {}): Promise<PaginatedResponse<KnowledgeDocument>> {
  const response = await http.get<PaginatedResponse<KnowledgeDocument>>('/knowledge-documents', { params })
  return response.data
}

export async function createKnowledgeDocument(payload: KnowledgeDocumentPayload): Promise<KnowledgeDocument> {
  const response = await http.post<{ data: KnowledgeDocument }>('/knowledge-documents', payload)
  return response.data.data
}

export async function updateKnowledgeDocument(id: number, payload: KnowledgeDocumentPayload): Promise<KnowledgeDocument> {
  const response = await http.put<{ data: KnowledgeDocument }>(`/knowledge-documents/${id}`, payload)
  return response.data.data
}

export async function publishKnowledgeDocument(id: number): Promise<KnowledgeDocument> {
  const response = await http.post<{ data: KnowledgeDocument }>(`/knowledge-documents/${id}/publish`)
  return response.data.data
}

export async function archiveKnowledgeDocument(id: number): Promise<KnowledgeDocument> {
  const response = await http.post<{ data: KnowledgeDocument }>(`/knowledge-documents/${id}/archive`)
  return response.data.data
}
