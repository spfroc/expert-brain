<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-100">
    <el-card class="w-[420px]">
      <template #header>
        <div class="text-xl font-semibold">ExpertBrain 登录</div>
      </template>

      <el-form label-position="top">
        <el-form-item label="邮箱">
          <el-input placeholder="admin@example.com" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input type="password" placeholder="请输入密码" show-password />
        </el-form-item>
        <el-button type="primary" class="w-full">登录</el-button>
      </el-form>
    </el-card>
  </div>
</template>
