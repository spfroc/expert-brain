<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">知识中心</h1>
        <p class="text-slate-500 mt-1">维护知识库、标签和知识文档，重点关注文档是否已经切片、向量化、可检索。</p>
      </div>
      <div class="flex gap-2">
        <el-button @click="openTagDialog">新增标签</el-button>
        <el-button type="primary" @click="openBaseDialog()">新增知识库</el-button>
        <el-button type="success" @click="openDocumentDialog()">新增文档</el-button>
        <el-button type="warning" @click="openUrlImportDialog">导入链接</el-button>
        <el-button type="danger" plain @click="openBatchUrlImportDialog">批量导入链接</el-button>
      </div>
    </div>

    <el-alert
      title="文档能否被问答检索，主要取决于：是否有来源内容、是否已生成切片、当前 embedding 模型是否完成向量化。"
      type="info"
      show-icon
      :closable="false"
    />

    <el-row :gutter="16">
      <el-col :span="7">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-semibold">知识库</span>
              <el-button link type="primary" @click="loadBases">刷新</el-button>
            </div>
          </template>

          <el-table :data="bases" v-loading="loadingBases" @row-click="selectBase" highlight-current-row>
            <el-table-column prop="name" label="名称" min-width="160" />
            <el-table-column prop="industry" label="行业" width="90" />
            <el-table-column label="状态" width="90">
              <template #default="scope"><el-tag>{{ scope.row.status ?? 'active' }}</el-tag></template>
            </el-table-column>
            <el-table-column label="操作" width="80">
              <template #default="scope"><el-button link type="primary" @click.stop="openBaseDialog(scope.row)">编辑</el-button></template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :span="17">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between gap-3">
              <div>
                <span class="font-semibold">文档列表</span>
                <span v-if="selectedBaseName" class="ml-2 text-xs text-slate-400">当前知识库：{{ selectedBaseName }}</span>
              </div>
              <div class="flex gap-2">
                <el-input v-model="documentKeyword" placeholder="搜索标题/内容" clearable style="width: 220px" @keyup.enter="loadDocuments" />
                <el-button @click="loadDocuments">搜索</el-button>
              </div>
            </div>
          </template>

          <el-table :data="documents" v-loading="loadingDocuments" row-key="id">
            <el-table-column label="文档" min-width="260">
              <template #default="scope">
                <div class="font-medium text-slate-900">{{ scope.row.title }}</div>
                <div class="mt-1 flex flex-wrap gap-1 text-xs text-slate-400">
                  <span>{{ scope.row.source_type }}</span>
                  <span>v{{ scope.row.version }}</span>
                  <span>业务状态：{{ scope.row.status }}</span>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="入库状态" width="145">
              <template #default="scope">
                <el-tooltip :content="scope.row.diagnostic_message || '暂无诊断信息'" placement="top">
                  <el-tag :type="diagnosticTagType(scope.row.search_status_type)" effect="plain">
                    {{ scope.row.search_status_label || '未知' }}
                  </el-tag>
                </el-tooltip>
                <div class="mt-1 text-xs text-slate-400">{{ scope.row.next_action || '-' }}</div>
              </template>
            </el-table-column>

            <el-table-column label="文件/切片/向量" width="155">
              <template #default="scope">
                <div class="text-xs leading-6 text-slate-600">
                  <div>文件：{{ scope.row.files_count ?? 0 }}</div>
                  <div>切片：{{ scope.row.chunks_count ?? 0 }}</div>
                  <div>向量：{{ embeddingCount(scope.row) }}</div>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="最近任务" width="150">
              <template #default="scope">
                <template v-if="scope.row.latest_job">
                  <el-tag :type="jobTagType(scope.row.latest_job.status)" effect="plain">
                    {{ jobLabel(scope.row.latest_job.status) }}
                  </el-tag>
                  <div class="mt-1 text-xs text-slate-400">{{ scope.row.latest_job.job_type }}</div>
                </template>
                <span v-else class="text-xs text-slate-400">无任务</span>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="420" fixed="right">
              <template #default="scope">
                <div class="flex flex-wrap gap-x-1">
                  <el-button link type="primary" @click="openDocumentDialog(scope.row)">编辑</el-button>
                  <el-button link type="info" @click="openUploadDialog(scope.row)">上传</el-button>
                  <el-button link type="primary" @click="openChunksDialog(scope.row)">切片</el-button>
                  <el-button link type="primary" :loading="chunkingDocumentId === scope.row.id" @click="generateChunks(scope.row.id)">生成切片</el-button>
                  <el-button link type="danger" :loading="embeddingDocumentId === scope.row.id" @click="embedDocument(scope.row.id)">向量化</el-button>
                  <el-button link type="danger" :loading="indexingDocumentId === scope.row.id" @click="indexDocument(scope.row.id)">一键入库</el-button>
                  <el-button link type="success" @click="publishDocument(scope.row.id)">发布</el-button>
                  <el-button link type="warning" @click="archiveDocument(scope.row.id)">归档</el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="baseDialogVisible" :title="editingBase?.id ? '编辑知识库' : '新增知识库'" width="520px">
      <el-form label-position="top">
        <el-form-item label="名称"><el-input v-model="baseForm.name" /></el-form-item>
        <el-form-item label="行业"><el-input v-model="baseForm.industry" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="baseForm.description" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="baseDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingBase" @click="saveBase">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="tagDialogVisible" title="新增标签" width="420px">
      <el-form label-position="top">
        <el-form-item label="标签名"><el-input v-model="tagForm.name" /></el-form-item>
        <el-form-item label="类型"><el-input v-model="tagForm.tag_type" placeholder="platform / policy / experience" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="tagDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingTag" @click="saveTag">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="documentDialogVisible" :title="editingDocument?.id ? '编辑文档' : '新增文档'" width="760px">
      <el-form label-position="top">
        <el-form-item label="所属知识库">
          <el-select v-model="documentForm.knowledge_base_id" placeholder="请选择知识库" class="w-full">
            <el-option v-for="base in bases" :key="base.id" :label="base.name" :value="base.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题"><el-input v-model="documentForm.title" /></el-form-item>
        <el-form-item label="摘要"><el-input v-model="documentForm.summary" type="textarea" :rows="2" /></el-form-item>
        <el-form-item label="正文"><el-input v-model="documentForm.content" type="textarea" :rows="8" /></el-form-item>
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="来源类型">
              <el-select v-model="documentForm.source_type" class="w-full">
                <el-option label="手工录入" value="manual" />
                <el-option label="政策法规" value="policy" />
                <el-option label="平台文档" value="platform_doc" />
                <el-option label="通知公告" value="notice" />
                <el-option label="业务经验" value="faq" />
                <el-option label="文件" value="file" />
                <el-option label="链接" value="url" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8"><el-form-item label="版本"><el-input v-model="documentForm.version" /></el-form-item></el-col>
          <el-col :span="8">
            <el-form-item label="状态">
              <el-select v-model="documentForm.status" class="w-full">
                <el-option label="草稿" value="draft" />
                <el-option label="已发布" value="published" />
                <el-option label="已归档" value="archived" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="来源 URL"><el-input v-model="documentForm.source_url" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="documentDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingDocument" @click="saveDocument">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="uploadDialogVisible" title="上传文件并创建入库任务" width="560px">
      <el-alert title="上传后会创建解析任务，并自动交给队列执行。" type="info" show-icon :closable="false" class="mb-4" />
      <el-upload drag :auto-upload="false" :limit="1" :on-change="handleFileChange" :on-remove="handleFileRemove">
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">拖拽文件到这里，或点击选择文件</div>
        <template #tip><div class="el-upload__tip">单个文件最大 50MB。</div></template>
      </el-upload>
      <template #footer>
        <el-button @click="uploadDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadingFile" @click="submitFileUpload">上传</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="urlDialogVisible" title="导入链接并创建入库任务" width="640px">
      <el-form label-position="top">
        <el-form-item label="所属知识库">
          <el-select v-model="urlForm.knowledge_base_id" placeholder="请选择知识库" class="w-full">
            <el-option v-for="base in bases" :key="base.id" :label="base.name" :value="base.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题"><el-input v-model="urlForm.title" placeholder="可为空，系统先使用 URL 作为标题" /></el-form-item>
        <el-form-item label="链接 URL"><el-input v-model="urlForm.url" placeholder="https://..." /></el-form-item>
        <el-form-item label="来源类型">
          <el-select v-model="urlForm.source_type" class="w-full">
            <el-option label="普通链接" value="url" />
            <el-option label="政策法规" value="policy" />
            <el-option label="平台文档" value="platform_doc" />
            <el-option label="通知公告" value="notice" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="urlDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="importingUrl" @click="submitUrlImport">导入</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="batchUrlDialogVisible" title="批量导入链接" width="760px">
      <el-alert title="一行一个 URL。提交后会批量创建文档和入库任务，默认自动排队抓取、切片、向量化。" type="info" show-icon :closable="false" class="mb-4" />
      <el-form label-position="top">
        <el-form-item label="所属知识库">
          <el-select v-model="batchUrlForm.knowledge_base_id" placeholder="请选择知识库" class="w-full">
            <el-option v-for="base in bases" :key="base.id" :label="base.name" :value="base.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="链接列表">
          <el-input v-model="batchUrlForm.raw_urls" type="textarea" :rows="10" placeholder="https://example.com/a.html&#10;https://example.com/b.html" />
          <div class="mt-1 text-xs text-slate-400">已识别 {{ batchUrlCount }} 个 URL</div>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="来源类型">
              <el-select v-model="batchUrlForm.source_type" class="w-full">
                <el-option label="普通链接" value="url" />
                <el-option label="政策法规" value="policy" />
                <el-option label="平台文档" value="platform_doc" />
                <el-option label="通知公告" value="notice" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="16">
            <el-form-item label="导入选项">
              <div class="flex flex-wrap gap-4">
                <el-checkbox v-model="batchUrlForm.deduplicate">跳过已存在链接</el-checkbox>
                <el-checkbox v-model="batchUrlForm.auto_process">自动抓取切片</el-checkbox>
                <el-checkbox v-model="batchUrlForm.auto_embed">自动向量化</el-checkbox>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div v-if="batchImportResult" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
        <div class="font-medium text-slate-800">导入结果：创建 {{ batchImportResult.created_count }} 个，跳过 {{ batchImportResult.skipped_count }} 个</div>
        <div class="mt-2 max-h-40 overflow-y-auto text-xs leading-6">
          <div v-for="item in batchImportResult.items" :key="item.url" class="truncate">
            <el-tag size="small" :type="item.status === 'created' ? 'success' : 'info'" effect="plain">{{ item.status }}</el-tag>
            <span class="ml-2">{{ item.url }}</span>
            <span v-if="item.reason" class="ml-2 text-slate-400">{{ item.reason }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="batchUrlDialogVisible = false">关闭</el-button>
        <el-button type="primary" :loading="importingUrls" @click="submitBatchUrlImport">批量导入</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="chunksDialogVisible" :title="`文档切片：${chunkDocument?.title ?? ''}`" width="900px">
      <div class="flex items-center gap-2 mb-4">
        <el-input v-model="chunkKeyword" placeholder="搜索切片内容" clearable style="width: 260px" @keyup.enter="loadChunks" />
        <el-button @click="loadChunks">搜索</el-button>
      </div>
      <el-table :data="chunks" v-loading="loadingChunks" height="460">
        <el-table-column prop="chunk_index" label="#" width="70" />
        <el-table-column prop="token_count" label="Token" width="90" />
        <el-table-column label="内容" min-width="560">
          <template #default="scope"><div class="whitespace-pre-wrap text-sm leading-6">{{ scope.row.content }}</div></template>
        </el-table-column>
      </el-table>
      <div class="flex justify-end mt-4">
        <el-pagination background layout="prev, pager, next, total" :total="chunkPagination.total" :page-size="chunkPagination.per_page" :current-page="chunkPagination.current_page" @current-change="handleChunkPageChange" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, type UploadFile } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'
import {
  archiveKnowledgeDocument,
  chunkKnowledgeDocument,
  createKnowledgeBase,
  createKnowledgeDocument,
  createKnowledgeTag,
  embedKnowledgeDocument,
  importKnowledgeDocumentUrl,
  importKnowledgeDocumentUrls,
  indexKnowledgeDocument,
  listDocumentChunks,
  listKnowledgeBases,
  listKnowledgeDocuments,
  publishKnowledgeDocument,
  updateKnowledgeBase,
  updateKnowledgeDocument,
  uploadKnowledgeDocumentFile,
  type ImportUrlsResult
} from '@/api/knowledge'
import type { DocumentChunk, KnowledgeBase, KnowledgeDocument, PaginationMeta } from '@/types/knowledge'

const bases = ref<KnowledgeBase[]>([])
const documents = ref<KnowledgeDocument[]>([])
const selectedBaseId = ref<number | null>(null)
const documentKeyword = ref('')
const chunks = ref<DocumentChunk[]>([])
const chunkKeyword = ref('')

const loadingBases = ref(false)
const loadingDocuments = ref(false)
const loadingChunks = ref(false)
const savingBase = ref(false)
const savingTag = ref(false)
const savingDocument = ref(false)
const uploadingFile = ref(false)
const importingUrl = ref(false)
const importingUrls = ref(false)
const chunkingDocumentId = ref<number | null>(null)
const embeddingDocumentId = ref<number | null>(null)
const indexingDocumentId = ref<number | null>(null)

const baseDialogVisible = ref(false)
const tagDialogVisible = ref(false)
const documentDialogVisible = ref(false)
const uploadDialogVisible = ref(false)
const urlDialogVisible = ref(false)
const batchUrlDialogVisible = ref(false)
const chunksDialogVisible = ref(false)

const editingBase = ref<KnowledgeBase | null>(null)
const editingDocument = ref<KnowledgeDocument | null>(null)
const uploadingDocument = ref<KnowledgeDocument | null>(null)
const chunkDocument = ref<KnowledgeDocument | null>(null)
const selectedUploadFile = ref<File | null>(null)
const batchImportResult = ref<ImportUrlsResult | null>(null)

const selectedBaseName = computed(() => bases.value.find((base) => base.id === selectedBaseId.value)?.name ?? '')
const batchUrlCount = computed(() => extractUrls(batchUrlForm.raw_urls).length)

const chunkPagination = reactive<PaginationMeta>({ current_page: 1, from: null, last_page: 1, path: '', per_page: 10, to: null, total: 0 })
const baseForm = reactive({ name: '', industry: '', description: '', status: 'active' })
const tagForm = reactive({ name: '', tag_type: '' })
const documentForm = reactive({ knowledge_base_id: null as number | null, title: '', summary: '', content: '', source_type: 'manual', source_url: '', version: '1.0', status: 'draft' })
const urlForm = reactive({ knowledge_base_id: null as number | null, title: '', url: '', source_type: 'url' as 'url' | 'policy' | 'platform_doc' | 'notice' })
const batchUrlForm = reactive({ knowledge_base_id: null as number | null, raw_urls: '', source_type: 'url' as 'url' | 'policy' | 'platform_doc' | 'notice', auto_process: true, auto_embed: true, deduplicate: true })

function resetBaseForm(): void { editingBase.value = null; Object.assign(baseForm, { name: '', industry: '', description: '', status: 'active' }) }
function resetDocumentForm(): void { editingDocument.value = null; Object.assign(documentForm, { knowledge_base_id: selectedBaseId.value, title: '', summary: '', content: '', source_type: 'manual', source_url: '', version: '1.0', status: 'draft' }) }

async function loadBases(): Promise<void> {
  loadingBases.value = true
  try {
    const response = await listKnowledgeBases({ per_page: 100 })
    bases.value = response.data
    if (!selectedBaseId.value && bases.value.length > 0) selectedBaseId.value = bases.value[0].id
  } finally { loadingBases.value = false }
}

async function loadDocuments(): Promise<void> {
  loadingDocuments.value = true
  try {
    const response = await listKnowledgeDocuments({ per_page: 50, knowledge_base_id: selectedBaseId.value ?? undefined, keyword: documentKeyword.value || undefined })
    documents.value = response.data
  } finally { loadingDocuments.value = false }
}

async function selectBase(row: KnowledgeBase): Promise<void> { selectedBaseId.value = row.id; await loadDocuments() }

function openBaseDialog(row?: KnowledgeBase): void {
  resetBaseForm()
  if (row) { editingBase.value = row; Object.assign(baseForm, { name: row.name, industry: row.industry ?? '', description: row.description ?? '', status: row.status ?? 'active' }) }
  baseDialogVisible.value = true
}

async function saveBase(): Promise<void> {
  savingBase.value = true
  try {
    if (editingBase.value) await updateKnowledgeBase(editingBase.value.id, baseForm)
    else await createKnowledgeBase(baseForm)
    ElMessage.success('知识库已保存')
    baseDialogVisible.value = false
    await loadBases()
  } finally { savingBase.value = false }
}

function openTagDialog(): void { Object.assign(tagForm, { name: '', tag_type: '' }); tagDialogVisible.value = true }
async function saveTag(): Promise<void> { savingTag.value = true; try { await createKnowledgeTag(tagForm); ElMessage.success('标签已保存'); tagDialogVisible.value = false } finally { savingTag.value = false } }

function openDocumentDialog(row?: KnowledgeDocument): void {
  resetDocumentForm()
  if (row) { editingDocument.value = row; Object.assign(documentForm, { knowledge_base_id: row.knowledge_base_id, title: row.title, summary: row.summary ?? '', content: row.content ?? '', source_type: row.source_type ?? 'manual', source_url: row.source_url ?? '', version: row.version ?? '1.0', status: row.status ?? 'draft' }) }
  documentDialogVisible.value = true
}

async function saveDocument(): Promise<void> {
  if (!documentForm.knowledge_base_id) { ElMessage.warning('请先选择知识库'); return }
  savingDocument.value = true
  try {
    if (editingDocument.value) await updateKnowledgeDocument(editingDocument.value.id, documentForm)
    else await createKnowledgeDocument(documentForm)
    ElMessage.success('文档已保存')
    documentDialogVisible.value = false
    await loadDocuments()
  } finally { savingDocument.value = false }
}

function openUploadDialog(row: KnowledgeDocument): void { uploadingDocument.value = row; selectedUploadFile.value = null; uploadDialogVisible.value = true }
function handleFileChange(uploadFile: UploadFile): void { selectedUploadFile.value = uploadFile.raw ?? null }
function handleFileRemove(): void { selectedUploadFile.value = null }

async function submitFileUpload(): Promise<void> {
  if (!uploadingDocument.value) { ElMessage.warning('请先选择文档'); return }
  if (!selectedUploadFile.value) { ElMessage.warning('请选择文件'); return }
  uploadingFile.value = true
  try { await uploadKnowledgeDocumentFile(uploadingDocument.value.id, selectedUploadFile.value); ElMessage.success('文件已上传，解析任务已创建'); uploadDialogVisible.value = false; await loadDocuments() } finally { uploadingFile.value = false }
}

function openUrlImportDialog(): void { Object.assign(urlForm, { knowledge_base_id: selectedBaseId.value, title: '', url: '', source_type: 'url' }); urlDialogVisible.value = true }
async function submitUrlImport(): Promise<void> {
  if (!urlForm.knowledge_base_id) { ElMessage.warning('请先选择知识库'); return }
  if (!urlForm.url) { ElMessage.warning('请输入链接'); return }
  importingUrl.value = true
  try { await importKnowledgeDocumentUrl(urlForm); ElMessage.success('链接已导入，抓取任务已创建'); urlDialogVisible.value = false; await loadDocuments() } finally { importingUrl.value = false }
}

function openBatchUrlImportDialog(): void {
  Object.assign(batchUrlForm, { knowledge_base_id: selectedBaseId.value, raw_urls: '', source_type: 'url', auto_process: true, auto_embed: true, deduplicate: true })
  batchImportResult.value = null
  batchUrlDialogVisible.value = true
}

async function submitBatchUrlImport(): Promise<void> {
  if (!batchUrlForm.knowledge_base_id) { ElMessage.warning('请先选择知识库'); return }
  if (batchUrlCount.value === 0) { ElMessage.warning('请粘贴至少一个有效 URL'); return }
  importingUrls.value = true
  try {
    const result = await importKnowledgeDocumentUrls(batchUrlForm)
    batchImportResult.value = result
    ElMessage.success(`批量导入完成：创建 ${result.created_count} 个，跳过 ${result.skipped_count} 个`)
    await loadDocuments()
  } finally { importingUrls.value = false }
}

function extractUrls(text: string): string[] {
  const matches = text.match(/https?:\/\/[^\s,，;；"'<>]+/g) ?? []
  return Array.from(new Set(matches.map((url) => url.trim()).filter(Boolean)))
}

async function openChunksDialog(row: KnowledgeDocument): Promise<void> { chunkDocument.value = row; chunkKeyword.value = ''; chunkPagination.current_page = 1; chunksDialogVisible.value = true; await loadChunks() }
async function loadChunks(): Promise<void> {
  if (!chunkDocument.value) return
  loadingChunks.value = true
  try {
    const response = await listDocumentChunks(chunkDocument.value.id, { keyword: chunkKeyword.value || undefined, page: chunkPagination.current_page, per_page: chunkPagination.per_page })
    chunks.value = response.data
    Object.assign(chunkPagination, response.meta)
  } finally { loadingChunks.value = false }
}
function handleChunkPageChange(page: number): void { chunkPagination.current_page = page; void loadChunks() }

async function generateChunks(id: number): Promise<void> {
  chunkingDocumentId.value = id
  try { const result = await chunkKnowledgeDocument(id); ElMessage.success(`生成切片完成：${result.chunk_count} 个`); await loadDocuments() } finally { chunkingDocumentId.value = null }
}

async function embedDocument(id: number): Promise<void> {
  embeddingDocumentId.value = id
  try { const result = await embedKnowledgeDocument(id); ElMessage.success(`向量化完成：${result.embedded_count} 个切片，模型：${result.model ?? result.model_key ?? 'unknown'}`); await loadDocuments() } finally { embeddingDocumentId.value = null }
}

async function indexDocument(id: number): Promise<void> {
  indexingDocumentId.value = id
  try {
    const result = await indexKnowledgeDocument(id)
    ElMessage.success(`一键入库完成：切片 ${result.chunk.chunk_count} 个，向量 ${result.embedding.embedded_count} 个`)
    await loadDocuments()
  } finally { indexingDocumentId.value = null }
}

async function publishDocument(id: number): Promise<void> { await publishKnowledgeDocument(id); ElMessage.success('文档已发布'); await loadDocuments() }
async function archiveDocument(id: number): Promise<void> { await archiveKnowledgeDocument(id); ElMessage.success('文档已归档'); await loadDocuments() }

function embeddingCount(row: KnowledgeDocument): number {
  if (row.active_embedding_model_key) return row.active_model_embeddings_count ?? 0
  return row.legacy_embeddings_count ?? 0
}

function diagnosticTagType(type?: string): 'success' | 'warning' | 'danger' | 'info' {
  if (type === 'success' || type === 'warning' || type === 'danger' || type === 'info') return type
  return 'info'
}

function jobTagType(status?: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'completed' || status === 'success') return 'success'
  if (status === 'failed') return 'danger'
  if (status === 'running' || status === 'processing' || status === 'pending') return 'warning'
  return 'info'
}

function jobLabel(status?: string): string {
  const map: Record<string, string> = {
    pending: '待处理',
    running: '执行中',
    processing: '执行中',
    completed: '已完成',
    success: '已完成',
    failed: '失败'
  }
  return status ? (map[status] ?? status) : '未知'
}

onMounted(async () => { await loadBases(); await loadDocuments() })
</script>
