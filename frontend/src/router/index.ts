import { createRouter, createWebHistory } from 'vue-router'

import AdminLayout from '@/layouts/AdminLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import LoginView from '@/views/LoginView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView
    },
    {
      path: '/',
      component: AdminLayout,
      children: [
        {
          path: '',
          redirect: '/dashboard'
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView
        },
        {
          path: 'knowledge/documents',
          name: 'knowledge-documents',
          component: PlaceholderView,
          meta: { title: '知识中心' }
        },
        {
          path: 'rag/chat',
          name: 'rag-chat',
          component: PlaceholderView,
          meta: { title: 'AI 问答' }
        },
        {
          path: 'customers',
          name: 'customers',
          component: PlaceholderView,
          meta: { title: '客户中心' }
        },
        {
          path: 'rules',
          name: 'rules',
          component: PlaceholderView,
          meta: { title: '规则中心' }
        },
        {
          path: 'plans',
          name: 'plans',
          component: PlaceholderView,
          meta: { title: '方案中心' }
        },
        {
          path: 'cases',
          name: 'cases',
          component: PlaceholderView,
          meta: { title: '案例中心' }
        }
      ]
    }
  ]
})
