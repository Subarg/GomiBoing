import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/login', component: () => import('../views/Login.vue'), meta: { guest: true } },
  { path: '/', redirect: () => (localStorage.getItem('rol') === 'admin' ? '/admin' : '/vendedor') },

  { path: '/admin', component: () => import('../views/admin/Dashboard.vue'), meta: { rol: 'admin' } },
  { path: '/admin/insumos', component: () => import('../views/admin/Insumos.vue'), meta: { rol: 'admin' } },
  { path: '/admin/recetas', component: () => import('../views/admin/Recetas.vue'), meta: { rol: 'admin' } },
  { path: '/admin/sabores', component: () => import('../views/admin/Sabores.vue'), meta: { rol: 'admin' } },
  { path: '/admin/cortes', component: () => import('../views/admin/Cortes.vue'), meta: { rol: 'admin' } },
  { path: '/admin/asignar', component: () => import('../views/admin/Asignar.vue'), meta: { rol: 'admin' } },
  { path: '/admin/vendedores', component: () => import('../views/admin/Vendedores.vue'), meta: { rol: 'admin' } },

  { path: '/vendedor', component: () => import('../views/vendedor/POS.vue'), meta: { rol: 'vendedor' } },
  { path: '/vendedor/inventario', component: () => import('../views/vendedor/Inventario.vue'), meta: { rol: 'vendedor' } },
  { path: '/vendedor/historial', component: () => import('../views/vendedor/Historial.vue'), meta: { rol: 'vendedor' } },
  { path: '/vendedor/corte', component: () => import('../views/vendedor/Corte.vue'), meta: { rol: 'vendedor' } },

  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.token) return to.meta.guest ? true : '/login'
  if (!auth.user) await auth.fetchMe()
  if (!auth.user) return '/login'
  if (to.meta.guest) return auth.user.rol === 'admin' ? '/admin' : '/vendedor'
  if (to.meta.rol && to.meta.rol !== auth.user.rol) return auth.user.rol === 'admin' ? '/admin' : '/vendedor'
  return true
})

export default router
