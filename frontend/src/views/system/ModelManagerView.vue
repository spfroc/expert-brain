<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">模型管理</h1>
        <p class="text-slate-500 mt-1">登记、检查和激活 embedding / reranker / LLM / OCR 模型。下载大模型仍建议在终端执行命令。</p>
      </div>
      <div class="flex gap-2">
        <el-button @click="loadModels">刷新</el-button>
        <el-button type="primary" :loading="installing" @click="installRecommended">初始化推荐模型</el-button>
      </div>
    </div>

    <el-alert
      title="当前版本的“激活模型”只更新系统注册表。embedding 实际运行模型仍由 ai-service 环境变量控制；切换 embedding 维度后需要重建向量。"
      type="warning"
      show-icon
      :closable="false"
    />

    <el-card>
      <div class="flex items-center gap-3 mb-4">
        <el-select v-model="filters.task_type" clearable placeholder="任务类型" style="width: 180px" @change="loadModels">
          <el-option label="Embedding" value="embedding" />
          <el-option label="Reranker" value="reranker" />
          <el-option label="LLM" value="llm" />
          <el-option label="OCR" value="ocr" />
        </el-select>
        <el-select v-model="filters.status" clearable placeholder="状态" style="width: 180px" @change="loadModels">
          <el-option label="registered" value="registered" />
          <el-option label="ready" value="ready" />
          <el-option label="failed" value="failed" />
          <el-option label="disabled" value="disabled" />
        </el-select>
      </div>

      <el-table :data="models" v-loading="loading" row-key="id">
        <el-table-column label="Active" width="90">
          <template #default="scope">
            <el-tag v-if="scope.row.is_active" type="success">启用</el-tag>
            <el-tag v-else type="info">否</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="220" />
        <el-table-column prop="task_type" label="任务" width="110" />
        <el-table-column prop="provider" label="Provider" width="160" />
        <el-table-column prop="status" label="状态" width="110">
          <template #default="scope">
            <el-tag :type="statusTagType(scope.row.status)">{{ scope.row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="dimension" label="维度" width="90" />
        <el-table-column prop="model_id" label="模型 ID" min-width="220" show-overflow-tooltip />
        <el-table-column prop="local_path" label="本地路径" min-width="220" show-overflow-tooltip />
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="scope">
            <el-button link type="primary" :loading="checkingId === scope.row.id" @click="checkModel(scope.row)">检查</el-button>
            <el-button link type="success" :loading="activatingId === scope.row.id" @click="activateModel(scope.row)">激活</el-button>
            <el-button link type="info" @click="showDownloadCommand(scope.row)">下载命令</el-button>
            <el-button link type="warning" @click="showEvents(scope.row)">事件</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="commandDialogVisible" title="模型下载命令" width="760px">
      <el-alert title="请在宿主机或模型目录对应环境中执行。Web 端暂不直接启动大模型下载任务。" type="info" show-icon :closable="false" class="mb-4" />
      <el-input v-model="currentCommand" type="textarea" :rows="5" readonly />
      <template #footer>
        <el-button @click="commandDialogVisible = false">关闭</el-button>
        <el-button type="primary" @click="copyCommand">复制命令</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="eventsDialogVisible" :title="`模型事件：${currentModel?.name ?? ''}`" width="860px">
      <el-table :data="events" v-loading="loadingEvents" height="420">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="event_type" label="事件" width="130" />
        <el-table-column prop="message" label="消息" min-width="300" show-overflow-tooltip />
        <el-table-column prop="created_at" label="时间" width="180" />
      </el-table>
    </el-dialog>

    <el-dialog v-model="checkDialogVisible" title="检查结果" width="760px">
      <pre class="bg-slate-900 text-slate-100 rounded p-4 text-sm overflow-auto max-h-[520px]">{{ checkResult }}</pre>
      <template #footer>
        <el-button type="primary" @click="checkDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import {
  activateAiModel,
  checkAiModel,
  installRecommendedAiModels,
  listAiModelEvents,
  listAiModels,
  type AiModel,
  type AiModelEvent
} from '@/api/aiModels'

const models = ref<AiModel[]>([])
const events = ref<AiModelEvent[]>([])
const currentModel = ref<AiModel | null>(null)
const currentCommand = ref('')
const checkResult = ref('')

const loading = ref(false)
const installing = ref(false)
const checkingId = ref<number | null>(null)
const activatingId = ref<number | null>(null)
const loadingEvents = ref(false)
const commandDialogVisible = ref(false)
const eventsDialogVisible = ref(false)
const checkDialogVisible = ref(false)

const filters = reactive({ task_type: '', status: '' })

async function loadModels(): Promise<void> {
  loading.value = true
  try {
    const response = await listAiModels({
      task_type: filters.task_type || undefined,
      status: filters.status || undefined,
      per_page: 100
    })
    models.value = response.data
  } finally {
    loading.value = false
  }
}

async function installRecommended(): Promise<void> {
  installing.value = true
  try {
    const result = await installRecommendedAiModels()
    ElMessage.success(`推荐模型已初始化：${result.installed_count} 个`)
    await loadModels()
  } finally {
    installing.value = false
  }
}

async function checkModel(model: AiModel): Promise<void> {
  checkingId.value = model.id
  try {
    const result = await checkAiModel(model.id)
    checkResult.value = JSON.stringify(result, null, 2)
    checkDialogVisible.value = true
    await loadModels()
  } finally {
    checkingId.value = null
  }
}

async function activateModel(model: AiModel): Promise<void> {
  activatingId.value = model.id
  try {
    await activateAiModel(model.id)
    ElMessage.success('模型已激活')
    await loadModels()
  } finally {
    activatingId.value = null
  }
}

function showDownloadCommand(model: AiModel): void {
  currentModel.value = model
  currentCommand.value = model.download_command || '该模型未配置下载命令。'
  commandDialogVisible.value = true
}

async function copyCommand(): Promise<void> {
  await navigator.clipboard.writeText(currentCommand.value)
  ElMessage.success('已复制')
}

async function showEvents(model: AiModel): Promise<void> {
  currentModel.value = model
  eventsDialogVisible.value = true
  loadingEvents.value = true
  try {
    events.value = await listAiModelEvents(model.id)
  } finally {
    loadingEvents.value = false
  }
}

function statusTagType(status: string): 'success' | 'warning' | 'danger' | 'info' | 'primary' {
  if (status === 'ready') return 'success'
  if (status === 'failed') return 'danger'
  if (status === 'downloading') return 'warning'
  if (status === 'registered') return 'info'
  return 'primary'
}

onMounted(loadModels)
</script>
