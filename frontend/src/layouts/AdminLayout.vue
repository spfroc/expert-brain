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
        <el-menu-item index="/dashboard">工作台</el-menu-item>
        <el-menu-item index="/knowledge/documents">知识中心</el-menu-item>
        <el-menu-item index="/rag/chat">AI 问答</el-menu-item>
        <el-menu-item index="/customers">客户中心</el-menu-item>
        <el-menu-item index="/rules">规则中心</el-menu-item>
        <el-menu-item index="/plans">方案中心</el-menu-item>
        <el-menu-item index="/cases">案例中心</el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="bg-white border-b flex items-center justify-between px-6">
        <div class="text-slate-600">企业行业专家系统</div>
        <div class="text-sm text-slate-500">Phase 0 Scaffold</div>
      </el-header>
      <el-main class="bg-slate-100">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>
