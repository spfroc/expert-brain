<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">知识中心</h1>
        <p class="text-slate-500 mt-1">维护知识库、标签和知识文档，支持手工录入、文件上传和链接导入。</p>
      </div>
      <div class="flex gap-2">
        <el-button @click="openTagDialog">新增标签</el-button>
        <el-button type="primary" @click="openBaseDialog()">新增知识库</el-button>
        <el-button type="success" @click="openDocumentDialog()">新增文档</el-button>
        <el-button type="warning" @click="openUrlImportDialog">导入链接</el-button>
      </div>
    </div>

    <el-alert
      title="文件上传和链接导入会创建入库任务；任务执行完成后，可以在文档行内查看切片结果。"
      type="info"
      show-icon
      :closable="false"
    />

    <el-row :gutter="16">
      <el-col :span="8">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-semibold">知识库</span>
              <el-button link type="primary" @click="loadBases">刷新</el-button>
            </div>
          </template>

          <el-table :data="bases" v-loading="loadingBases" @row-click="selectBase" highlight-current-row>
            <el-table-column prop="name" label="名称" min-width="160" />
            <el-table-column prop="industry" label="行业" width="100" />
            <el-table-column label="状态" width="90">
              <template #default="scope">
                <el-tag>{{ scope.row.status ?? 'active' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90">
              <template #default="scope">
                <el-button link type="primary" @click.stop="openBaseDialog(scope.row)">编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :span="16">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-semibold">文档列表</span>
              <div class="flex gap-2">
                <el-input v-model="documentKeyword" placeholder="搜索标题/内容" clearable style="width: 220px" @keyup.enter="loadDocuments" />
                <el-button @click="loadDocuments">搜索</el-button>
              </div>
            </div>
          </template>

          <el-table :data="documents" v-loading="loadingDocuments">
            <el-table-column prop="title" label="标题" min-width="220" />
            <el-table-column prop="source_type" label="来源" width="110" />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="scope">
                <el-tag>{{ scope.row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="version" label="版本" width="80" />
            <el-table-column label="操作" width="370">
              <template #default="scope">
                <el-button link type="primary" @click="openDocumentDialog(scope.row)">编辑</el-button>
                <el-button link type="info" @click="openUploadDialog(scope.row)">上传文件</el-button>
                <el-button link type="primary" @click="openChunksDialog(scope.row)">切片</el-button>
                <el-button link type="success" @click="publishDocument(scope.row.id)">发布</el-button>
                <el-button link type="warning" @click="archiveDocument(scope.row.id)">归档</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="baseDialogVisible" :title="editingBase?.id ? '编辑知识库' : '新增知识库'" width="520px">
      <el-form label-position="top">
        <el-form-item label="名称">
          <el-input v-model="baseForm.name" />
        </el-form-item>
        <el-form-item label="行业">
          <el-input v-model="baseForm.industry" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="baseForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="baseDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingBase" @click="saveBase">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="tagDialogVisible" title="新增标签" width="420px">
      <el-form label-position="top">
        <el-form-item label="标签名">
          <el-input v-model="tagForm.name" />
        </el-form-item>
        <el-form-item label="类型">
          <el-input v-model="tagForm.tag_type" placeholder="platform / policy / experience" />
        </el-form-item>
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
        <el-form-item label="标题">
          <el-input v-model="documentForm.title" />
        </el-form-item>
        <el-form-item label="摘要">
          <el-input v-model="documentForm.summary" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="正文">
          <el-input v-model="documentForm.content" type="textarea" :rows="8" />
        </el-form-item>
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
          <el-col :span="8">
            <el-form-item label="版本">
              <el-input v-model="documentForm.version" />
            </el-form-item>
          </el-col>
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
        <el-form-item label="来源 URL">
          <el-input v-model="documentForm.source_url" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="documentDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingDocument" @click="saveDocument">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="uploadDialogVisible" title="上传文件并创建入库任务" width="560px">
      <el-alert
        title="支持先上传 docx、pdf、xlsx、txt、md 等文件。当前阶段会保存原始文件并创建解析任务，解析任务将在 AI Service 中执行。"
        type="info"
        show-icon
        :closable="false"
        class="mb-4"
      />
      <el-upload
        drag
        :auto-upload="false"
        :limit="1"
        :on-change="handleFileChange"
        :on-remove="handleFileRemove"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">拖拽文件到这里，或点击选择文件</div>
        <template #tip>
          <div class="el-upload__tip">单个文件最大 50MB。</div>
        </template>
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
        <el-form-item label="标题">
          <el-input v-model="urlForm.title" placeholder="可为空，系统先使用 URL 作为标题" />
        </el-form-item>
        <el-form-item label="链接 URL">
          <el-input v-model="urlForm.url" placeholder="https://..." />
        </el-form-item>
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

    <el-dialog v-model="chunksDialogVisible" :title="`文档切片：${chunkDocument?.title ?? ''}`" width="900px">
      <div class="flex items-center gap-2 mb-4">
        <el-input v-model="chunkKeyword" placeholder="搜索切片内容" clearable style="width: 260px" @keyup.enter="loadChunks" />
        <el-button @click="loadChunks">搜索</el-button>
      </div>
      <el-table :data="chunks" v-loading="loadingChunks" height="460">
        <el-table-column prop="chunk_index" label="#" width="70" />
        <el-table-column prop="token_count" label="Token" width="90" />
        <el-table-column label="内容" min-width="560">
          <template #default="scope">
            <div class="whitespace-pre-wrap text-sm leading-6">{{ scope.row.content }}</div>
          </template>
        </el-table-column>
      </el-table>
      <div class="flex justify-end mt-4">
        <el-pagination
          background
          layout="prev, pager, next, total"
          :total="chunkPagination.total"
          :page-size="chunkPagination.per_page"
          :current-page="chunkPagination.current_page"
          @current-change="handleChunkPageChange"
        />
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, type UploadFile } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'
import {
  archiveKnowledgeDocument,
  createKnowledgeBase,
  createKnowledgeDocument,
  createKnowledgeTag,
  importKnowledgeDocumentUrl,
  listDocumentChunks,
  listKnowledgeBases,
  listKnowledgeDocuments,
  publishKnowledgeDocument,
  updateKnowledgeBase,
  updateKnowledgeDocument,
  uploadKnowledgeDocumentFile
} from '@/api/knowledge'
import type { DocumentChunk, KnowledgeBase, KnowledgeDocument, PaginationMeta } from '@/types/knowledge'

const bases = ref<KnowledgeBase[]>([])
const documents = ref<KnowledgeDocument[]>([])
const selectedBaseId = ref<number | null>(null)
const documentKeyword = ref('')

const loadingBases = ref(false)
const loadingDocuments = ref(false)
const loadingChunks = ref(false)
const savingBase = ref(false)
const savingTag = ref(false)
const savingDocument = ref(false)
const uploadingFile = ref(false)
const importingUrl = ref(false)

const baseDialogVisible = ref(false)
const tagDialogVisible = ref(false)
const documentDialogVisible = ref(false)
const uploadDialogVisible = ref(false)
const urlDialogVisible = ref(false)
const chunksDialogVisible = ref(false)

const editingBase = ref<KnowledgeBase | null>(null)
const editingDocument = ref<KnowledgeDocument | null>(null)
const uploadingDocument = ref<KnowledgeDocument | null>(null)
const chunkDocument = ref<KnowledgeDocument | null>(null)
const selectedUploadFile = ref<File | null>(null)
const chunks = ref<DocumentChunk[]>([])
const chunkKeyword = ref('')

const chunkPagination = reactive<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  path: '',
  per_page: 10,
  to: null,
  total: 0
})

const baseForm = reactive({ name: '', industry: '', description: '', status: 'active' })
const tagForm = reactive({ name: '', tag_type: '' })
const documentForm = reactive({
  knowledge_base_id: null as number | null,
  title: '',
  summary: '',
  content: '',
  source_type: 'manual',
  source_url: '',
  version: '1.0',
  status: 'draft'
})
const urlForm = reactive({
  knowledge_base_id: null as number | null,
  title: '',
  url: '',
  source_type: 'url' as 'url' | 'policy' | 'platform_doc' | 'notice'
})

function resetBaseForm(): void {
  editingBase.value = null
  Object.assign(baseForm, { name: '', industry: '', description: '', status: 'active' })
}

function resetDocumentForm(): void {
  editingDocument.value = null
  Object.assign(documentForm, {
    knowledge_base_id: selectedBaseId.value,
    title: '',
    summary: '',
    content: '',
    source_type: 'manual',
    source_url: '',
    version: '1.0',
    status: 'draft'
  })
}

async function loadBases(): Promise<void> {
  loadingBases.value = true
  try {
    const response = await listKnowledgeBases({ per_page: 100 })
    bases.value = response.data
    if (!selectedBaseId.value && bases.value.length > 0) {
      selectedBaseId.value = bases.value[0].id
    }
  } finally {
    loadingBases.value = false
  }
}

async function loadDocuments(): Promise<void> {
  loadingDocuments.value = true
  try {
    const response = await listKnowledgeDocuments({
      per_page: 50,
      knowledge_base_id: selectedBaseId.value ?? undefined,
      keyword: documentKeyword.value || undefined
    })
    documents.value = response.data
  } finally {
    loadingDocuments.value = false
  }
}

async function selectBase(row: KnowledgeBase): Promise<void> {
  selectedBaseId.value = row.id
  await loadDocuments()
}

function openBaseDialog(row?: KnowledgeBase): void {
  resetBaseForm()
  if (row) {
    editingBase.value = row
    Object.assign(baseForm, {
      name: row.name,
      industry: row.industry ?? '',
      description: row.description ?? '',
      status: row.status ?? 'active'
    })
  }
  baseDialogVisible.value = true
}

async function saveBase(): Promise<void> {
  savingBase.value = true
  try {
    if (editingBase.value) {
      await updateKnowledgeBase(editingBase.value.id, baseForm)
    } else {
      await createKnowledgeBase(baseForm)
    }
    ElMessage.success('知识库已保存')
    baseDialogVisible.value = false
    await loadBases()
  } finally {
    savingBase.value = false
  }
}

function openTagDialog(): void {
  Object.assign(tagForm, { name: '', tag_type: '' })
  tagDialogVisible.value = true
}

async function saveTag(): Promise<void> {
  savingTag.value = true
  try {
    await createKnowledgeTag(tagForm)
    ElMessage.success('标签已保存')
    tagDialogVisible.value = false
  } finally {
    savingTag.value = false
  }
}

function openDocumentDialog(row?: KnowledgeDocument): void {
  resetDocumentForm()
  if (row) {
    editingDocument.value = row
    Object.assign(documentForm, {
      knowledge_base_id: row.knowledge_base_id,
      title: row.title,
      summary: row.summary ?? '',
      content: row.content ?? '',
      source_type: row.source_type ?? 'manual',
      source_url: row.source_url ?? '',
      version: row.version ?? '1.0',
      status: row.status ?? 'draft'
    })
  }
  documentDialogVisible.value = true
}

async function saveDocument(): Promise<void> {
  if (!documentForm.knowledge_base_id) {
    ElMessage.warning('请先选择知识库')
    return
  }
  savingDocument.value = true
  try {
    if (editingDocument.value) {
      await updateKnowledgeDocument(editingDocument.value.id, documentForm)
    } else {
      await createKnowledgeDocument(documentForm)
    }
    ElMessage.success('文档已保存')
    documentDialogVisible.value = false
    await loadDocuments()
  } finally {
    savingDocument.value = false
  }
}

function openUploadDialog(row: KnowledgeDocument): void {
  uploadingDocument.value = row
  selectedUploadFile.value = null
  uploadDialogVisible.value = true
}

function handleFileChange(uploadFile: UploadFile): void {
  selectedUploadFile.value = uploadFile.raw ?? null
}

function handleFileRemove(): void {
  selectedUploadFile.value = null
}

async function submitFileUpload(): Promise<void> {
  if (!uploadingDocument.value) {
    ElMessage.warning('请先选择文档')
    return
  }
  if (!selectedUploadFile.value) {
    ElMessage.warning('请选择文件')
    return
  }

  uploadingFile.value = true
  try {
    await uploadKnowledgeDocumentFile(uploadingDocument.value.id, selectedUploadFile.value)
    ElMessage.success('文件已上传，解析任务已创建')
    uploadDialogVisible.value = false
  } finally {
    uploadingFile.value = false
  }
}

function openUrlImportDialog(): void {
  Object.assign(urlForm, {
    knowledge_base_id: selectedBaseId.value,
    title: '',
    url: '',
    source_type: 'url'
  })
  urlDialogVisible.value = true
}

async function submitUrlImport(): Promise<void> {
  if (!urlForm.knowledge_base_id) {
    ElMessage.warning('请先选择知识库')
    return
  }
  if (!urlForm.url) {
    ElMessage.warning('请输入链接')
    return
  }

  importingUrl.value = true
  try {
    await importKnowledgeDocumentUrl(urlForm)
    ElMessage.success('链接已导入，抓取任务已创建')
    urlDialogVisible.value = false
    await loadDocuments()
  } finally {
    importingUrl.value = false
  }
}

async function openChunksDialog(row: KnowledgeDocument): Promise<void> {
  chunkDocument.value = row
  chunkKeyword.value = ''
  chunkPagination.current_page = 1
  chunksDialogVisible.value = true
  await loadChunks()
}

async function loadChunks(): Promise<void> {
  if (!chunkDocument.value) return

  loadingChunks.value = true
  try {
    const response = await listDocumentChunks(chunkDocument.value.id, {
      keyword: chunkKeyword.value || undefined,
      page: chunkPagination.current_page,
      per_page: chunkPagination.per_page
    })
    chunks.value = response.data
    Object.assign(chunkPagination, response.meta)
  } finally {
    loadingChunks.value = false
  }
}

function handleChunkPageChange(page: number): void {
  chunkPagination.current_page = page
  void loadChunks()
}

async function publishDocument(id: number): Promise<void> {
  await publishKnowledgeDocument(id)
  ElMessage.success('文档已发布')
  await loadDocuments()
}

async function archiveDocument(id: number): Promise<void> {
  await archiveKnowledgeDocument(id)
  ElMessage.success('文档已归档')
  await loadDocuments()
}

onMounted(async () => {
  await loadBases()
  await loadDocuments()
})
</script>
