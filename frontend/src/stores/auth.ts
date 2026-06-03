import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { createSession, deleteSession, fetchCurrentSession } from '@/api/session'
import type { CurrentUser } from '@/types/auth'

const TOKEN_KEY = 'expert_brain_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<CurrentUser | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))
  const permissions = computed(() => user.value?.permissions ?? [])

  function setToken(nextToken: string | null): void {
    token.value = nextToken
    if (nextToken) {
      localStorage.setItem(TOKEN_KEY, nextToken)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  }

  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    try {
      const payload = await createSession({ email, password })
      setToken(payload.access_token)
      user.value = payload.user
    } finally {
      loading.value = false
    }
  }

  async function loadCurrentUser(): Promise<void> {
    if (!token.value) return
    loading.value = true
    try {
      user.value = await fetchCurrentSession()
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) {
        await deleteSession()
      }
    } finally {
      user.value = null
      setToken(null)
    }
  }

  function hasPermission(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  return {
    token,
    user,
    loading,
    isAuthenticated,
    permissions,
    login,
    loadCurrentUser,
    logout,
    hasPermission
  }
})
