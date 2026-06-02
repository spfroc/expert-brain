import axios from 'axios'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1',
  timeout: 30000
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
