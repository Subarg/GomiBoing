<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { mensajeError } from '../api'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const cargando = ref(false)

async function entrar() {
  error.value = ''
  cargando.value = true
  try {
    await auth.login(email.value, password.value)
    router.push(auth.esAdmin ? '/admin' : '/vendedor')
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <div class="min-h-[80vh] grid place-items-center">
    <div class="w-full max-w-sm">
      <h1 class="text-5xl font-extrabold text-uva text-center mb-1">Gomiboing</h1>
      <p class="text-center text-tinta/70 mb-6">Entra para vender o administrar tu turno.</p>
      <form class="card space-y-4" @submit.prevent="entrar">
        <div>
          <label class="label" for="email">Correo</label>
          <input id="email" v-model="email" type="email" class="input" autocomplete="username" required />
        </div>
        <div>
          <label class="label" for="pass">Contraseña</label>
          <input id="pass" v-model="password" type="password" class="input" autocomplete="current-password" required />
        </div>
        <p v-if="error" class="text-chamoy text-sm font-bold" role="alert">{{ error }}</p>
        <button class="btn-primary w-full" :disabled="cargando">{{ cargando ? 'Entrando…' : 'Entrar' }}</button>
      </form>
    </div>
  </div>
</template>
