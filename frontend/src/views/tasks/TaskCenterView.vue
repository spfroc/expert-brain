<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">任务中心</h1>
        <p class="text-slate-500 mt-1">查看文档解析、链接抓取、重新索引等异步任务。</p>
      </div>
      <div class="flex gap-2">
        <el-select v-model="filters.status" clearable placeholder="任务状态" style="width: 150px" @change="loadJobs">
          <el-option label="待处理" value="pending" />
          <el-option label="处理中" value="processing" />
          <el-option label="已完成" value="completed" />
          <el-option label="失败" value="failed" />
        </el-select>
        <el-select v-model="filters.job_type" clearable placeholder="任务类型" style="width: 160px" @change="loadJobs">
          <el-option label="文件解析" value="file_parse" />
          <el-option label="链接抓取" value="url_fetch" />
        </el-select>
        <el-button @click="loadJobs">刷新</el-button>
      </div>
    </div>

    <el-card>
      <el-table :data="jobs" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="job_type" label="类型" width="120">
          <template #default="scope">
            <el-tag>{{ jobTypeText(scope.row.job_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="110">
          <template #default="scope">
            <el-tag :type="statusTagType(scope.row.status)">{{ statusText(scope.row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="180">
          <template #default="scope">
            <el-progress :percentage="scope.row.progress ?? 0" />
          </template>
        </el-table-column>
        <el-table-column prop="knowledge_document_id" label="文档ID" width="100" />
        <el-table-column prop="document_file_id" label="文件ID" width="100" />
        <el-table-column prop="source_url" label="来源 URL" min-width="240" show-overflow-tooltip />
        <el-table-column prop="error_message" label="错误信息" min-width="220" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="190" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="scope">
            <el-button
              link
              type="primary"
              :disabled="!canProcess(scope.row.status)"
              :loading="processingJobId === scope.row.id"
              @click="processJob(scope.row.id)"
            >
              执行
            </el-button>
            <el-button link @click="openDetail(scope.row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-end mt-4">
        <el-pagination
          background
          layout="prev, pager, next, total"
          :total="pagination.total"
          :page-size="pagination.per_page"
          :current-page="pagination.current_page"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="detailVisible" title="任务详情" width="760px">
      <el-descriptions v-if="selectedJob" :column="2" border>
        <el-descriptions-item label="任务ID">{{ selectedJob.id }}</el-descriptions-item>
        <el-descriptions-item label="任务类型">{{ jobTypeText(selectedJob.job_type) }}</el-descriptions-item>
        <el-descriptions-item label="状态">{{ statusText(selectedJob.status) }}</el-descriptions-item>
        <el-descriptions-item label="进度">{{ selectedJob.progress }}%</el-descriptions-item>
        <el-descriptions-item label="文档ID">{{ selectedJob.knowledge_document_id }}</el-descriptions-item>
        <el-descriptions-item label="文件ID">{{ selectedJob.document_file_id ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="开始时间">{{ selectedJob.started_at ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="完成时间">{{ selectedJob.finished_at ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="来源URL" :span="2">{{ selectedJob.source_url ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="错误信息" :span="2">{{ selectedJob.error_message ?? '-' }}</el-descriptions-item>
      </el-descriptions>
      <pre v-if="selectedJob" class="mt-4 bg-slate-900 text-slate-100 p-4 rounded overflow-auto text-xs">{{ JSON.stringify(selectedJob.metadata ?? {}, null, 2) }}</pre>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listDocumentIngestionJobs, processDocumentIngestionJob } from '@/api/knowledge'
import type { DocumentIngestionJob, PaginationMeta } from '@/types/knowledge'

const jobs = ref<DocumentIngestionJob[]>([])
const loading = ref(false)
const processingJobId = ref<number | null>(null)
const detailVisible = ref(false)
const selectedJob = ref<DocumentIngestionJob | null>(null)

const filters = reactive({
  status: '',
  job_type: '',
  page: 1
})

const pagination = reactive<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  path: '',
  per_page: 20,
  to: null,
  total: 0
})

async function loadJobs(): Promise<void> {
  loading.value = true
  try {
    const response = await listDocumentIngestionJobs({
      status: filters.status || undefined,
      job_type: filters.job_type || undefined,
      per_page: pagination.per_page,
      page: filters.page
    })
    jobs.value = response.data
    Object.assign(pagination, response.meta)
  } finally {
    loading.value = false
  }
}

function handlePageChange(page: number): void {
  filters.page = page
  void loadJobs()
}

function canProcess(status: string): boolean {
  return ['pending', 'failed'].includes(status)
}

async function processJob(id: number): Promise<void> {
  processingJobId.value = id
  try {
    await processDocumentIngestionJob(id)
    ElMessage.success('任务已执行')
    await loadJobs()
  } finally {
    processingJobId.value = null
  }
}

function openDetail(job: DocumentIngestionJob): void {
  selectedJob.value = job
  detailVisible.value = true
}

function jobTypeText(type: string): string {
  const map: Record<string, string> = {
    file_parse: '文件解析',
    url_fetch: '链接抓取'
  }
  return map[type] ?? type
}

function statusText(status: string): string {
  const map: Record<string, string> = {
    pending: '待处理',
    processing: '处理中',
    completed: '已完成',
    failed: '失败'
  }
  return map[status] ?? status
}

function statusTagType(status: string): '' | 'success' | 'warning' | 'info' | 'danger' {
  const map: Record<string, '' | 'success' | 'warning' | 'info' | 'danger'> = {
    pending: 'info',
    processing: 'warning',
    completed: 'success',
    failed: 'danger'
  }
  return map[status] ?? ''
}

onMounted(loadJobs)
</script>
