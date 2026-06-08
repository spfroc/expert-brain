export interface PaginationLinks {
  first?: string | null
  last?: string | null
  prev?: string | null
  next?: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  path: string
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  links: PaginationLinks
  meta: PaginationMeta
}

export interface KnowledgeBase {
  id: number
  name: string
  description?: string | null
  industry?: string | null
  status?: string | null
  created_by?: number | null
  created_at?: string | null
  updated_at?: string | null
}

export interface KnowledgeTag {
  id: number
  name: string
  tag_type?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface KnowledgeDocumentLatestJob {
  id: number
  job_type: string
  status: string
  progress: number
  error_message?: string | null
  created_at?: string | null
  started_at?: string | null
  finished_at?: string | null
}

export interface KnowledgeDocument {
  id: number
  knowledge_base_id: number
  category_id?: number | null
  title: string
  summary?: string | null
  content?: string | null
  source_type: string
  source_url?: string | null
  version: string
  status: string
  metadata?: Record<string, unknown> | null
  created_by?: number | null
  published_at?: string | null
  tags?: KnowledgeTag[]
  files_count?: number
  chunks_count?: number
  legacy_embeddings_count?: number
  active_model_embeddings_count?: number
  active_embedding_model_key?: string | null
  latest_job?: KnowledgeDocumentLatestJob | null
  search_status?: string
  search_status_label?: string
  search_status_type?: 'success' | 'warning' | 'danger' | 'info' | string
  next_action?: string
  diagnostic_message?: string
  created_at?: string | null
  updated_at?: string | null
}

export interface DocumentIngestionJob {
  id: number
  knowledge_document_id: number
  document_file_id?: number | null
  job_type: string
  status: string
  progress: number
  source_url?: string | null
  error_message?: string | null
  metadata?: Record<string, unknown> | null
  started_at?: string | null
  finished_at?: string | null
  created_by?: number | null
  created_at?: string | null
  updated_at?: string | null
}

export interface DocumentChunk {
  id: number
  knowledge_document_id: number
  document_file_id?: number | null
  chunk_index: number
  chunk_type: string
  title?: string | null
  content: string
  token_count?: number | null
  metadata?: Record<string, unknown> | null
  created_at?: string | null
  updated_at?: string | null
}
