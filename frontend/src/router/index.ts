import { createRouter, createWebHistory } from 'vue-router'

import AdminLayout from '@/layouts/AdminLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import LoginView from '@/views/LoginView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import { useAuthStore } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true }
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: '/dashboard'
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView,
          meta: { title: '工作台', permission: 'dashboard.view' }
        },
        {
          path: 'knowledge/documents',
          name: 'knowledge-documents',
          component: PlaceholderView,
          meta: { title: '知识中心', permission: 'knowledge_document.view' }
        },
        {
          path: 'rag/chat',
          name: 'rag-chat',
          component: PlaceholderView,
          meta: { title: 'AI 问答', permission: 'rag.ask' }
        },
        {
          path: 'customers',
          name: 'customers',
          component: PlaceholderView,
          meta: { title: '客户中心', permission: 'customer.view' }
        },
        {
          path: 'rules',
          name: 'rules',
          component: PlaceholderView,
          meta: { title: '规则中心', permission: 'business_rule.view' }
        },
        {
          path: 'plans',
          name: 'plans',
          component: PlaceholderView,
          meta: { title: '方案中心', permission: 'plan.view' }
        },
        {
          path: 'cases',
          name: 'cases',
          component: PlaceholderView,
          meta: { title: '案例中心', permission: 'case.view' }
        }
      ]
    }
  ]
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.user) {
    await auth.loadCurrentUser()
  }

  const requiredPermission = to.meta.permission
  if (typeof requiredPermission === 'string' && !auth.hasPermission(requiredPermission)) {
    return { name: 'dashboard' }
  }

  return true
})
