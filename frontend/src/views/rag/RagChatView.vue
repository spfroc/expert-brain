<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-2xl font-semibold">AI 问答</h1>
      <p class="text-slate-500 mt-1">当前阶段先测试 RAG 召回效果：输入问题后，系统返回最相关的知识切片。</p>
    </div>

    <el-alert
      title="当前还没有接入 LLM 生成回答，页面展示的是向量检索召回结果。检索结果稳定后，再接入回答生成。"
      type="info"
      show-icon
      :closable="false"
    />

    <el-card>
      <el-form label-position="top">
        <el-form-item label="知识库">
          <el-select v-model="selectedBaseId" clearable placeholder="全部知识库" style="width: 360px">
            <el-option v-for="base in bases" :key="base.id" :label="base.name" :value="base.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="问题">
          <el-input
            v-model="query"
            type="textarea"
            :rows="4"
            placeholder="例如：京东慧采适合什么类型的供应商？"
            @keyup.ctrl.enter="runSearch"
          />
        </el-form-item>
        <div class="flex items-center gap-3">
          <el-input-number v-model="topK" :min="1" :max="20" />
          <span class="text-sm text-slate-500">召回数量</span>
          <el-button type="primary" :loading="loading" @click="runSearch">检索</el-button>
        </div>
      </el-form>
    </el-card>

    <el-card v-if="results.length > 0">
      <template #header>
        <div class="font-semibold">召回结果</div>
      </template>

      <div class="space-y-4">
        <div v-for="item in results" :key="item.chunk_id" class="border rounded p-4 bg-white">
          <div class="flex items-center justify-between mb-2">
            <div class="font-semibold">{{ item.document_title }}</div>
            <div class="text-sm text-slate-500">
              chunk #{{ item.chunk_index }} / score {{ item.score.toFixed(4) }} / distance {{ item.distance.toFixed(4) }}
            </div>
          </div>
          <div class="whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ item.content }}</div>
          <div v-if="item.source_url" class="mt-2 text-xs text-slate-400 break-all">{{ item.source_url }}</div>
        </div>
      </div>
    </el-card>

    <el-empty v-else-if="searched" description="没有检索到结果，请确认文档已解析并完成向量化。" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listKnowledgeBases, searchRag } from '@/api/knowledge'
import type { KnowledgeBase, RagSearchResult } from '@/api/knowledge'

const bases = ref<KnowledgeBase[]>([])
const selectedBaseId = ref<number | null>(null)
const query = ref('')
const topK = ref(5)
const loading = ref(false)
const searched = ref(false)
const results = ref<RagSearchResult[]>([])

async function loadBases(): Promise<void> {
  const response = await listKnowledgeBases({ per_page: 100 })
  bases.value = response.data
}

async function runSearch(): Promise<void> {
  if (!query.value.trim()) {
    ElMessage.warning('请输入问题')
    return
  }

  loading.value = true
  try {
    const response = await searchRag(query.value, selectedBaseId.value, topK.value)
    results.value = response.results
    searched.value = true
  } finally {
    loading.value = false
  }
}

onMounted(loadBases)
</script>
