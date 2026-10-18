import { defineStore } from 'pinia'
import api from '../api'

const guardarUsuario = (u) => localStorage.setItem('user', JSON.stringify(u))
const usuarioGuardado = () => {
  try { return JSON.parse(localStorage.getItem('user')) } catch { return null }
}

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
      guardarUsuario(data.user)
    },
    /**
     * Recupera al usuario. La sesión solo se cierra si el servidor responde 401;
     * ante cortes de red o un servidor "dormido" se conserva el usuario guardado.
     */
    async fetchMe() {
      try {
        const { data } = await api.get('/me')
        this.user = data
        localStorage.setItem('rol', data.rol)
        guardarUsuario(data)
      } catch (e) {
        if (e.response?.status === 401) this.clear()
        else this.user = usuarioGuardado()
      }
    },
    async logout() {
      try { await api.post('/logout') } catch { /* el token ya no es válido o no hay red */ }
      this.clear()
    },
    clear() {
      this.token = null
      this.user = null
      ;['token', 'rol', 'user'].forEach((k) => localStorage.removeItem(k))
    },
  },
})
