<template>
  <div class="space-y-5">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">RAG 调试台</span>
            <span v-if="elapsedMs" class="text-xs text-slate-400">最近一次耗时 {{ elapsedMs }} ms</span>
          </div>
          <h1 class="mt-3 text-2xl font-semibold text-slate-900">AI 问答</h1>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
            当前页面用于验证“问题 → 向量化 → 召回切片”的链路。先看召回是否正确，再接入大模型生成正式回答。
          </p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center text-xs text-slate-500">
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold text-slate-900">{{ bases.length }}</div>
            <div>知识库</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold text-slate-900">{{ topK }}</div>
            <div>召回数</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold" :class="bestScoreClass">{{ bestScoreText }}</div>
            <div>最高分</div>
          </div>
        </div>
      </div>
    </div>

    <el-alert
      title="当前回答草稿不是大模型生成，而是直接基于召回切片整理。若召回结果不相关，应优先检查知识库是否切片、是否向量化、是否选错知识库。"
      type="info"
      show-icon
      :closable="false"
    />

    <el-alert
      v-if="errorMessage"
      :title="errorMessage"
      type="error"
      show-icon
      class="mb-4"
      @close="errorMessage = ''"
    />

    <el-card shadow="never" class="border-slate-200">
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold text-slate-900">提问与检索范围</div>
            <div class="mt-1 text-xs text-slate-400">Ctrl + Enter 可快速检索</div>
          </div>
          <el-button text type="primary" @click="fillExample">填入示例</el-button>
        </div>
      </template>

      <el-form label-position="top">
        <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
          <el-form-item label="知识库">
            <el-select v-model="selectedBaseId" clearable filterable placeholder="全部知识库" class="w-full">
              <el-option v-for="base in bases" :key="base.id" :label="base.name" :value="base.id" />
            </el-select>
            <div class="mt-2 text-xs text-slate-400">
              不选择知识库时会全库检索，容易召回无关内容。测试法规问题时建议选择法规库。
            </div>
          </el-form-item>

          <el-form-item label="问题">
            <el-input
              v-model="query"
              type="textarea"
              :rows="4"
              maxlength="500"
              show-word-limit
              placeholder="例如：无线电爱好者平时使用无线电设备应注意哪些法律风险？"
              @keyup.ctrl.enter="runSearch"
            />
          </el-form-item>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <el-input-number v-model="topK" :min="1" :max="10" />
          <span class="text-sm text-slate-500">召回数量</span>
          <el-button type="primary" :loading="loading" @click="runSearch">开始检索</el-button>
          <el-button :disabled="loading" @click="clearSearch">清空</el-button>
          <el-tag v-if="selectedBaseName" type="success" effect="plain">当前：{{ selectedBaseName }}</el-tag>
          <el-tag v-else type="warning" effect="plain">全库检索</el-tag>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <el-tag
            v-for="item in questionExamples"
            :key="item"
            class="cursor-pointer"
            effect="plain"
            @click="query = item"
          >
            {{ item }}
          </el-tag>
        </div>
      </el-form>
    </el-card>

    <el-card v-if="searched" shadow="never" class="border-slate-200">
      <template #header>
        <div class="flex items-center justify-between">
          <div class="font-semibold text-slate-900">回答草稿</div>
          <el-tag v-if="results.length > 0" :type="isLowConfidence ? 'warning' : 'success'" effect="plain">
            {{ isLowConfidence ? '低可信召回' : '可用召回' }}
          </el-tag>
          <el-tag v-else-if="diagnostics" :type="diagnosticTagType" effect="plain">{{ diagnostics.status }}</el-tag>
        </div>
      </template>

      <el-alert
        v-if="results.length > 0 && isLowConfidence"
        title="召回分数偏低，结果可能不相关。建议选择正确知识库，或检查该知识库是否完成切片与当前模型向量化。"
        type="warning"
        show-icon
        :closable="false"
        class="mb-4"
      />

      <div v-if="results.length > 0" class="space-y-3">
        <p class="text-sm text-slate-600">根据当前知识库召回结果，可以先这样回答：</p>
        <div class="whitespace-pre-wrap rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-7 text-slate-700">{{ answerDraft }}</div>
      </div>
      <div v-else-if="diagnostics" class="space-y-4">
        <el-alert
          :title="diagnostics.reason"
          :description="diagnostics.next_action"
          :type="diagnosticTagType"
          show-icon
          :closable="false"
        />
        <div class="grid gap-3 md:grid-cols-5">
          <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
            <div class="text-lg font-semibold text-slate-900">{{ diagnostics.documents_count }}</div>
            <div class="text-xs text-slate-500">文档</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
            <div class="text-lg font-semibold text-slate-900">{{ diagnostics.chunks_count }}</div>
            <div class="text-xs text-slate-500">切片</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
            <div class="text-lg font-semibold text-slate-900">{{ diagnostics.effective_embeddings_count }}</div>
            <div class="text-xs text-slate-500">有效向量</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
            <div class="truncate text-sm font-semibold text-slate-900">{{ diagnostics.knowledge_base_name || '全部' }}</div>
            <div class="text-xs text-slate-500">知识库</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
            <div class="truncate text-sm font-semibold text-slate-900">{{ diagnostics.active_embedding_model_key || 'legacy' }}</div>
            <div class="text-xs text-slate-500">模型</div>
          </div>
        </div>
      </div>
      <el-empty v-else description="没有检索到结果">
        <div class="max-w-xl text-left text-sm leading-7 text-slate-500">
          <div>可以按这个顺序排查：</div>
          <div>1. 该知识库是否有文档；2. 文档是否已经解析切片；3. 当前 embedding 模型是否完成向量化；4. 是否选择了错误的知识库。</div>
        </div>
      </el-empty>
    </el-card>

    <el-card v-if="results.length > 0" shadow="never" class="border-slate-200">
      <template #header>
        <div class="flex items-center justify-between">
          <div class="font-semibold text-slate-900">召回结果</div>
          <div class="text-xs text-slate-400">按综合分排序，score 越高越相关</div>
        </div>
      </template>

      <div class="space-y-4">
        <div v-for="item in results" :key="item.chunk_id" class="rounded-xl border border-slate-200 bg-white p-4 transition hover:shadow-sm">
          <div class="flex flex-col gap-2 border-b border-slate-100 pb-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div class="font-semibold text-slate-900">{{ item.document_title }}</div>
              <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-400">
                <span>chunk #{{ item.chunk_index }}</span>
                <span>模型：{{ item.model_key ?? 'legacy' }}</span>
                <span v-if="item.source_type">来源：{{ item.source_type }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
              <el-tag :type="scoreTagType(item.score)" effect="plain">score {{ item.score.toFixed(4) }}</el-tag>
              <el-tag effect="plain">distance {{ item.distance.toFixed(4) }}</el-tag>
            </div>
          </div>
          <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-700">{{ item.content }}</div>
          <div v-if="item.source_url" class="mt-3 break-all rounded bg-slate-50 px-3 py-2 text-xs text-slate-400">{{ item.source_url }}</div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listKnowledgeBases, searchRag, type RagSearchDiagnostics, type RagSearchResult } from '@/api/knowledge'
import type { KnowledgeBase } from '@/types/knowledge'

const bases = ref<KnowledgeBase[]>([])
const selectedBaseId = ref<number | null>(null)
const query = ref('')
const topK = ref(5)
const loading = ref(false)
const searched = ref(false)
const results = ref<RagSearchResult[]>([])
const diagnostics = ref<RagSearchDiagnostics | null>(null)
const elapsedMs = ref<number | null>(null)
const errorMessage = ref('')

const questionExamples = [
  '无线电爱好者平时使用无线电设备应注意哪些法律风险？',
  '政府采购供应商参加采购活动需要具备哪些条件？',
  '路由中添加路径参数示例。'
]

const selectedBaseName = computed(() => {
  return bases.value.find((base) => base.id === selectedBaseId.value)?.name ?? ''
})

const bestScore = computed(() => results.value.length > 0 ? Math.max(...results.value.map((item) => item.score)) : null)
const bestScoreText = computed(() => bestScore.value === null ? '-' : bestScore.value.toFixed(3))
const isLowConfidence = computed(() => results.value.length > 0 && (bestScore.value ?? 0) < 0.35)
const bestScoreClass = computed(() => {
  if (bestScore.value === null) return 'text-slate-900'
  return bestScore.value >= 0.35 ? 'text-emerald-600' : 'text-amber-600'
})
const diagnosticTagType = computed((): 'success' | 'warning' | 'danger' | 'info' => {
  if (!diagnostics.value) return 'info'
  if (diagnostics.value.status === 'low_similarity') return 'warning'
  if (diagnostics.value.status === 'no_documents' || diagnostics.value.status === 'no_chunks' || diagnostics.value.status === 'no_embeddings') return 'warning'
  return 'info'
})

const answerDraft = computed(() => {
  if (results.value.length === 0) return ''

  const lines = results.value.slice(0, 3).map((item, index) => {
    return `${index + 1}. 【${item.document_title}】\n${item.content}`
  })

  return [
    `问题：${query.value}`,
    '',
    '参考知识：',
    ...lines,
    '',
    '说明：以上内容来自当前知识库召回切片。若召回分数偏低或内容明显不相关，不应直接作为正式答案。'
  ].join('\n')
})

async function loadBases(): Promise<void> {
  const response = await listKnowledgeBases({ per_page: 100 })
  bases.value = response.data
}

function fillExample(): void {
  query.value = questionExamples[0]
}

function clearSearch(): void {
  query.value = ''
  results.value = []
  diagnostics.value = null
  elapsedMs.value = null
  searched.value = false
  errorMessage.value = ''
}

async function runSearch(): Promise<void> {
  if (!query.value.trim()) {
    ElMessage.warning('请输入问题')
    return
  }

  loading.value = true
  errorMessage.value = ''
  elapsedMs.value = null
  diagnostics.value = null
  try {
    const response = await searchRag(query.value, selectedBaseId.value, topK.value)
    results.value = response.results
    diagnostics.value = response.diagnostics ?? null
    elapsedMs.value = response.elapsed_ms ?? null
    searched.value = true
  } catch (error) {
    searched.value = true
    results.value = []
    if (axios.isAxiosError(error)) {
      diagnostics.value = error.response?.data?.data?.diagnostics ?? null
      if (error.code === 'ECONNABORTED') {
        errorMessage.value = 'RAG 检索请求超时。可能是 embedding 服务加载模型较慢、向量检索过慢，或目标模型向量覆盖不足。'
      } else if (error.response?.data?.errors?.rag?.[0]) {
        errorMessage.value = error.response.data.errors.rag[0]
      } else if (error.response?.data?.message) {
        errorMessage.value = error.response.data.message
      } else {
        errorMessage.value = error.message
      }
    } else {
      errorMessage.value = 'RAG 检索失败'
    }
  } finally {
    loading.value = false
  }
}

function scoreTagType(score: number): 'success' | 'warning' | 'danger' | 'info' {
  if (score >= 0.45) return 'success'
  if (score >= 0.35) return 'warning'
  return 'danger'
}

onMounted(loadBases)
</script>
