<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">工作台</h1>
      <p class="text-slate-500 mt-1">ExpertBrain Phase 0 工程骨架</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <el-card>
        <div class="text-slate-500">知识库</div>
        <div class="text-3xl font-bold mt-2">0</div>
      </el-card>
      <el-card>
        <div class="text-slate-500">文档</div>
        <div class="text-3xl font-bold mt-2">0</div>
      </el-card>
      <el-card>
        <div class="text-slate-500">客户</div>
        <div class="text-3xl font-bold mt-2">0</div>
      </el-card>
      <el-card>
        <div class="text-slate-500">方案</div>
        <div class="text-3xl font-bold mt-2">0</div>
      </el-card>
    </div>

    <el-alert
      title="下一步：完成 Issue #2 初始化 Laravel Backend，并接入 docker-compose。"
      type="info"
      show-icon
      :closable="false"
    />
  </div>
</template>
