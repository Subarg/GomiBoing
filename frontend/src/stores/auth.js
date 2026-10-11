import { defineStore } from 'pinia'
import api from '../api'

export const useAuthStore = defineStore('auth', {
  state: () => ({ token: localStorage.getItem('token'), user: null }),
  getters: {
    esAdmin: (s) => s.user?.rol === 'admin',
  },
  actions: {
    async login(email, password) {
      const { data } = await api.post('/login', { email, password })
      this.token = data.token
      this.user = data.user
      localStorage.setItem('token', data.token)
      localStorage.setItem('rol', data.user.rol)
    },
    async fetchMe() {
      try {
        const { data } = await api.get('/me')
        this.user = data
        localStorage.setItem('rol', data.rol)
      } catch {
        this.clear()
      }
    },
    async logout() {
      try { await api.post('/logout') } catch { /* el token ya no es válido */ }
      this.clear()
    },
    clear() {
      this.token = null
      this.user = null
      localStorage.removeItem('token')
      localStorage.removeItem('rol')
    },
  },
})
