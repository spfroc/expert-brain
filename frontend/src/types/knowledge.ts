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
  created_at?: string | null
  updated_at?: string | null
}
