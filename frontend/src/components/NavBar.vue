<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useInventoryStore } from '../stores/inventory'
import { num } from '../api'

const auth = useAuthStore()
const inv = useInventoryStore()
const router = useRouter()
const route = useRoute()
const campana = ref(false)
const menu = ref(false)
let timer

const links = computed(() =>
  auth.esAdmin
    ? [['/admin', 'Panel'], ['/admin/cortes', 'Cortes de caja'], ['/admin/asignar', 'Asignar turno'], ['/admin/insumos', 'Insumos y compras'], ['/admin/recetas', 'Recetas'], ['/admin/sabores', 'Sabores y precios'], ['/admin/vendedores', 'Vendedores']]
    : [['/vendedor', 'Vender'], ['/vendedor/historial', 'Mis ventas'], ['/vendedor/inventario', 'Mi inventario'], ['/vendedor/corte', 'Corte de caja']]
)
const titulo = computed(() => links.value.find(([to]) => to === route.path)?.[1] ?? 'Gomiboing')

async function salir() {
  menu.value = false
  await auth.logout()
  router.push('/login')
}

const alEscape = (e) => { if (e.key === 'Escape') { menu.value = false; campana.value = false } }
watch(() => route.path, () => { menu.value = false; campana.value = false })

onMounted(() => {
  window.addEventListener('keydown', alEscape)
  if (auth.esAdmin) {
    inv.fetchAlertas()
    timer = setInterval(inv.fetchAlertas, 30000)
  }
})
onBeforeUnmount(() => { clearInterval(timer); window.removeEventListener('keydown', alEscape) })
</script>

<template>
  <header class="bg-uva text-white sticky top-0 z-30">
    <div class="mx-auto max-w-6xl px-2 sm:px-5 py-2 flex items-center gap-1">
      <button class="p-2.5 rounded-lg hover:bg-white/10" @click="menu = true" aria-label="Abrir menú" :aria-expanded="menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <span class="font-display text-xl font-extrabold tracking-tight flex-1 truncate">{{ titulo }}</span>

      <div class="relative" v-if="auth.esAdmin">
        <button class="relative p-2.5 rounded-lg hover:bg-white/10" @click="campana = !campana" :aria-expanded="campana" aria-label="Alertas de stock">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span v-if="inv.alertas.length" class="absolute top-0.5 right-0.5 bg-chamoy text-white text-xs font-bold rounded-full min-w-5 h-5 grid place-items-center px-1">{{ inv.alertas.length }}</span>
        </button>
        <div v-if="campana" class="absolute right-0 mt-2 w-72 max-w-[92vw] bg-white text-tinta rounded-xl shadow-xl border border-uva/15 z-20 p-3">
          <p class="font-display font-bold mb-2">Por resurtir</p>
          <p v-if="!inv.alertas.length" class="text-sm text-tinta/70">Todos los insumos están por encima de su mínimo.</p>
          <ul v-else class="space-y-1.5 max-h-72 overflow-auto">
            <li v-for="a in inv.alertas" :key="a.id" class="flex justify-between gap-2 text-sm bg-chamoy-claro rounded-lg px-2.5 py-1.5">
              <span>{{ a.nombre }}</span>
              <span class="font-bold text-chamoy whitespace-nowrap">{{ num(a.stock_almacen) }} / {{ num(a.stock_minimo) }} {{ a.unidad_medida }}</span>
            </li>
          </ul>
          <router-link to="/admin/insumos" class="btn-ghost w-full mt-3 text-sm">Registrar compra</router-link>
        </div>
      </div>
    </div>
  </header>

  <!-- Menú lateral -->
  <Transition name="menu">
    <div v-if="menu" class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="Menú principal">
      <div class="absolute inset-0 bg-tinta/50" @click="menu = false"></div>
      <nav class="absolute left-0 top-0 h-full w-72 max-w-[85vw] bg-white text-tinta flex flex-col shadow-2xl panel">
        <div class="bg-uva text-white px-4 py-4">
          <p class="font-display text-2xl font-extrabold">Gomiboing</p>
          <p class="text-sm text-white/80">{{ auth.user.name }} · {{ auth.esAdmin ? 'Administrador' : 'Vendedor' }}</p>
        </div>
        <ul class="flex-1 overflow-y-auto py-2">
          <li v-for="[to, texto] in links" :key="to">
            <router-link :to="to" class="block px-4 py-3.5 text-lg font-bold hover:bg-uva-claro" exact-active-class="bg-uva-claro text-uva">{{ texto }}</router-link>
          </li>
        </ul>
        <button class="m-3 btn-ghost" @click="salir">Cerrar sesión</button>
      </nav>
    </div>
  </Transition>
</template>

<style scoped>
.menu-enter-active, .menu-leave-active { transition: opacity .18s ease; }
.menu-enter-active .panel, .menu-leave-active .panel { transition: transform .18s ease; }
.menu-enter-from, .menu-leave-to { opacity: 0; }
.menu-enter-from .panel, .menu-leave-to .panel { transform: translateX(-100%); }
</style>
