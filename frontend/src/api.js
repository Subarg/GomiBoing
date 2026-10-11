import axios from 'axios'

const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api' })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers.Accept = 'application/json'
  return config
})

api.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error.response?.status === 401 && !error.config.url.includes('/login')) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

/** Extrae el mensaje legible de un error de la API (incluye errores de validación 422). */
export function mensajeError(e) {
  const d = e.response?.data
  if (e.response?.status === 404 && /could not be found/i.test(d?.message || '')) {
    return 'El servidor no tiene la versión más reciente del backend (falta una ruta). Actualiza el backend y ejecuta "php artisan migrate" y "php artisan optimize:clear".'
  }
  if (d?.errors) return Object.values(d.errors).flat().join(' ')
  return d?.message || 'No se pudo completar la acción. Revisa tu conexión.'
}

export const money = (n) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0)

export const num = (n) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(n) || 0)

export default api
