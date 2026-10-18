<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from './stores/auth'
import api from './api'
import NavBar from './components/NavBar.vue'

const VERSION_ESPERADA = 4
const auth = useAuthStore()
const aviso = ref('')

// Avisa si el servidor corre una versión anterior del backend o le faltan migraciones
async function revisarBackend() {
  aviso.value = ''
  try {
    const { data } = await api.get('/estado')
    if (data.version < VERSION_ESPERADA) aviso.value = 'El backend es de una versión anterior.'
    else if (!data.ok) aviso.value = `Faltan migraciones en el backend (${data.migraciones_pendientes.join(', ')}). Ejecuta "php artisan migrate".`
  } catch (e) {
    if (e.response?.status === 404) aviso.value = 'El servidor tiene una versión anterior del backend (falta la ruta /estado).'
  }
}

watch(() => auth.user?.id, (id) => { if (id) revisarBackend() }, { immediate: true })
</script>

<template>
  <div v-if="aviso" class="bg-chamoy text-white text-sm px-3 py-2 text-center" role="alert">
    <b>Backend desactualizado.</b> {{ aviso }}
    {{ auth.esAdmin ? 'Copia los archivos de /backend, ejecuta ./actualizar-backend.sh y reinicia el servidor.' : 'Avisa al administrador.' }}
  </div>
  <NavBar v-if="auth.user" />
  <main class="mx-auto max-w-6xl px-3 sm:px-5 py-5">
    <router-view />
  </main>
</template>
