<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError } from '../../api'

const lista = ref([])
const form = ref({ name: '', email: '', password: '' })
const error = ref('')

const cargar = async () => { lista.value = (await api.get('/admin/vendedores')).data }

async function crear() {
  error.value = ''
  try {
    await api.post('/admin/vendedores', form.value)
    form.value = { name: '', email: '', password: '' }
    await cargar()
  } catch (e) { error.value = mensajeError(e) }
}

async function alternar(v) {
  try { await api.patch(`/admin/vendedores/${v.id}`, { activo: !v.activo }); await cargar() } catch (e) { error.value = mensajeError(e) }
}
onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Vendedores</h1>
  <div class="grid md:grid-cols-2 gap-5">
    <form class="card space-y-3" @submit.prevent="crear">
      <h2 class="text-xl font-bold">Nuevo vendedor</h2>
      <div><label class="label" for="v-n">Nombre</label><input id="v-n" v-model="form.name" class="input" required /></div>
      <div><label class="label" for="v-e">Correo</label><input id="v-e" v-model="form.email" type="email" class="input" required /></div>
      <div><label class="label" for="v-p">Contraseña (mínimo 6 caracteres)</label><input id="v-p" v-model="form.password" type="password" minlength="6" class="input" required /></div>
      <p v-if="error" class="text-chamoy font-bold text-sm" role="alert">{{ error }}</p>
      <button class="btn-primary">Crear vendedor</button>
    </form>

    <section class="card">
      <h2 class="text-xl font-bold mb-2">Equipo</h2>
      <p v-if="!lista.length" class="text-sm text-tinta/60">Aún no hay vendedores.</p>
      <ul class="divide-y divide-uva/10">
        <li v-for="v in lista" :key="v.id" class="py-2 flex items-center justify-between gap-2">
          <div><p class="font-bold" :class="{ 'opacity-50': !v.activo }">{{ v.name }}</p><p class="text-sm text-tinta/60">{{ v.email }}</p></div>
          <button class="btn-ghost !py-1 text-sm" @click="alternar(v)">{{ v.activo ? 'Desactivar' : 'Activar' }}</button>
        </li>
      </ul>
    </section>
  </div>
</template>
