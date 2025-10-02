import axios from 'axios'
import { auth } from '../utils/auth'
import { message } from 'antd'

const API_BASE_URL = 'http://localhost:8000/api'

const api = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        'Content-Type': 'application/json',
    },
})

// Интерцептор для добавления токена к запросам
api.interceptors.request.use(
    (config) => {
        const token = auth.getToken()
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }
        return config
    },
    (error) => {
        return Promise.reject(error)
    }
)

// Интерцептор для обработки ошибок
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            auth.removeToken()
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

export const authAPI = {
    login: (credentials) => api.post('/login', credentials),
    register: (userData) => api.post('/register', userData),
}

export const tasksAPI = {
    getTasks: (params = {}) => api.get('/tasks', { params }),
    createTask: (taskData) => api.post('/tasks', taskData),
    updateTask: (id, taskData) => api.post(`/tasks/${id}`, taskData),
    deleteTask: (id) => api.delete(`/tasks/${id}`),
}

export default api