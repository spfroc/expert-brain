<template>
  <el-card>
    <template #header>
      <div class="text-lg font-semibold">{{ title }}</div>
    </template>
    <el-empty description="该模块将在后续 Issue 中实现" />
  </el-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => String(route.meta.title ?? '模块占位'))
</script>
