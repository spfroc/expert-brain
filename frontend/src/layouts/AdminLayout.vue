<template>
  <el-container class="min-h-screen">
    <el-aside width="240px" class="bg-slate-900 text-white">
      <div class="px-5 py-4 text-lg font-semibold border-b border-slate-700">
        ExpertBrain
      </div>
      <el-menu
        router
        background-color="#0f172a"
        text-color="#cbd5e1"
        active-text-color="#ffffff"
        class="border-r-0"
      >
        <el-menu-item v-for="item in visibleMenuItems" :key="item.path" :index="item.path">
          {{ item.label }}
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="bg-white border-b flex items-center justify-between px-6">
        <div class="text-slate-600">企业行业专家系统</div>
        <div class="flex items-center gap-3 text-sm text-slate-500">
          <span>{{ auth.user?.name ?? '未登录' }}</span>
          <el-button size="small" @click="handleLogout">退出</el-button>
        </div>
      </el-header>
      <el-main class="bg-slate-100">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const menuItems = [
  { path: '/dashboard', label: '工作台', permission: 'dashboard.view' },
  { path: '/knowledge/documents', label: '知识中心', permission: 'knowledge_document.view' },
  { path: '/rag/chat', label: 'AI 问答', permission: 'rag.ask' },
  { path: '/customers', label: '客户中心', permission: 'customer.view' },
  { path: '/rules', label: '规则中心', permission: 'business_rule.view' },
  { path: '/plans', label: '方案中心', permission: 'plan.view' },
  { path: '/cases', label: '案例中心', permission: 'case.view' }
]

const visibleMenuItems = computed(() => menuItems.filter((item) => auth.hasPermission(item.permission)))

async function handleLogout(): Promise<void> {
  await auth.logout()
  await router.push('/login')
}
</script>
