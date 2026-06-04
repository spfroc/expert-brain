import { http } from './http'
import type { DocumentChunk, DocumentIngestionJob, KnowledgeBase, KnowledgeDocument, KnowledgeTag, PaginatedResponse } from '@/types/knowledge'

export interface ListParams {
  keyword?: string
  status?: string
  job_type?: string
  knowledge_base_id?: number | string
  knowledge_document_id?: number | string
  page?: number
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

export interface ImportUrlPayload {
  knowledge_base_id: number | null
  title?: string | null
  url: string
  source_type?: 'url' | 'policy' | 'platform_doc' | 'notice'
}

export interface EmbedDocumentResult {
  embedded_count: number
  provider?: string | null
  model?: string | null
  dimension?: number | null
  message?: string | null
}

export interface ChunkDocumentResult {
  chunk_count: number
  parser?: string | null
  message?: string | null
}

export interface IndexDocumentResult {
  chunk: ChunkDocumentResult
  embedding: EmbedDocumentResult
}

export interface RagSearchResult {
  chunk_id: number
  knowledge_document_id: number
  chunk_index: number
  content: string
  token_count?: number | null
  metadata?: Record<string, unknown> | null
  document_title: string
  source_type?: string | null
  source_url?: string | null
  knowledge_base_id: number
  distance: number
  score: number
}

export interface RagSearchResponse {
  query: string
  results: RagSearchResult[]
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

export async function uploadKnowledgeDocumentFile(documentId: number, file: File): Promise<void> {
  const formData = new FormData()
  formData.append('file', file)
  await http.post(`/knowledge-documents/${documentId}/files`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export async function importKnowledgeDocumentUrl(payload: ImportUrlPayload): Promise<void> {
  await http.post('/knowledge-documents/import-url', payload)
}

export async function listDocumentIngestionJobs(params: ListParams = {}): Promise<PaginatedResponse<DocumentIngestionJob>> {
  const response = await http.get<PaginatedResponse<DocumentIngestionJob>>('/document-ingestion-jobs', { params })
  return response.data
}

export async function processDocumentIngestionJob(id: number): Promise<DocumentIngestionJob> {
  const response = await http.post<{ data: DocumentIngestionJob }>(`/document-ingestion-jobs/${id}/process`)
  return response.data.data
}

export async function listDocumentChunks(documentId: number, params: ListParams = {}): Promise<PaginatedResponse<DocumentChunk>> {
  const response = await http.get<PaginatedResponse<DocumentChunk>>(`/knowledge-documents/${documentId}/chunks`, { params })
  return response.data
}

export async function chunkKnowledgeDocument(documentId: number): Promise<ChunkDocumentResult> {
  const response = await http.post<{ data: ChunkDocumentResult }>(`/knowledge-documents/${documentId}/chunk`)
  return response.data.data
}

export async function embedKnowledgeDocument(documentId: number): Promise<EmbedDocumentResult> {
  const response = await http.post<{ data: EmbedDocumentResult }>(`/knowledge-documents/${documentId}/embed`)
  return response.data.data
}

export async function indexKnowledgeDocument(documentId: number): Promise<IndexDocumentResult> {
  const response = await http.post<{ data: IndexDocumentResult }>(`/knowledge-documents/${documentId}/index`)
  return response.data.data
}

export async function searchRag(query: string, knowledgeBaseId?: number | null, topK = 5): Promise<RagSearchResponse> {
  const response = await http.post<{ data: RagSearchResponse }>('/rag/search', {
    query,
    knowledge_base_id: knowledgeBaseId,
    top_k: topK
  })
  return response.data.data
}
