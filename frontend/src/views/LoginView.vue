<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-100">
    <el-card class="w-[420px]">
      <template #header>
        <div>
          <div class="text-xl font-semibold">ExpertBrain 登录</div>
          <div class="text-sm text-slate-500 mt-1">企业行业专家系统</div>
        </div>
      </template>

      <el-alert
        v-if="errorMessage"
        :title="errorMessage"
        type="error"
        show-icon
        class="mb-4"
      />

      <el-form label-position="top" @submit.prevent="handleLogin">
        <el-form-item label="邮箱">
          <el-input v-model="form.email" placeholder="admin@example.com" autocomplete="username" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
            autocomplete="current-password"
            show-password
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <el-button type="primary" class="w-full" :loading="auth.loading" @click="handleLogin">
          登录
        </el-button>
      </el-form>

      <div class="text-xs text-slate-400 mt-4">
        开发默认账号：admin@example.com / password
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  email: 'admin@example.com',
  password: 'password'
})

const errorMessage = ref('')

async function handleLogin(): Promise<void> {
  errorMessage.value = ''

  try {
    await auth.login(form.email, form.password)
    await router.push('/dashboard')
  } catch (error) {
    errorMessage.value = '登录失败，请检查邮箱和密码。'
  }
}
</script>
