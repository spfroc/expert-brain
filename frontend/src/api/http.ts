import axios from 'axios'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  timeout: Number(import.meta.env.VITE_API_TIMEOUT_MS ?? 120000)
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('expert_brain_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('expert_brain_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)
