<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError } from '../../api'

const lista = ref([])
const form = ref({ name: '', email: '', password: '' })
const edicion = ref(null)
const visibles = ref(new Set())
const error = ref('')
const aviso = ref('')

const cargar = async () => { lista.value = (await api.get('/admin/vendedores')).data }
const ok = (m) => { error.value = ''; aviso.value = m }
const fallo = (e) => { aviso.value = ''; error.value = mensajeError(e) }

function alternarVista(id) {
  const s = new Set(visibles.value)
  s.has(id) ? s.delete(id) : s.add(id)
  visibles.value = s
}

async function crear() {
  try {
    await api.post('/admin/vendedores', form.value)
    form.value = { name: '', email: '', password: '' }
    ok('Vendedor creado.')
    await cargar()
  } catch (e) { fallo(e) }
}

const editar = (v) => { edicion.value = { id: v.id, name: v.name, email: v.email, password: '' }; error.value = ''; aviso.value = '' }

async function guardarEdicion() {
  const e = edicion.value
  const payload = { name: e.name, email: e.email }
  if (e.password) payload.password = e.password // vacío = no cambiar la contraseña
  try {
    await api.patch(`/admin/vendedores/${e.id}`, payload)
    edicion.value = null
    ok(payload.password ? 'Datos guardados. Se cerró su sesión para que entre con la contraseña nueva.' : 'Datos guardados.')
    await cargar()
  } catch (err) { fallo(err) }
}

async function alternar(v) {
  try { await api.patch(`/admin/vendedores/${v.id}`, { activo: !v.activo }); await cargar() } catch (e) { fallo(e) }
}

onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Vendedores</h1>
  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <p v-if="aviso" class="text-green-700 font-bold mb-3" role="status">{{ aviso }}</p>

  <div class="grid md:grid-cols-2 gap-5">
    <form class="card space-y-3 h-fit" @submit.prevent="crear">
      <h2 class="text-xl font-bold">Nuevo vendedor</h2>
      <div><label class="label" for="v-n">Nombre</label><input id="v-n" v-model="form.name" class="input" required /></div>
      <div><label class="label" for="v-e">Correo</label><input id="v-e" v-model="form.email" type="email" class="input" required /></div>
      <div><label class="label" for="v-p">Contraseña (mínimo 6 caracteres)</label><input id="v-p" v-model="form.password" minlength="6" class="input" autocomplete="off" required /></div>
      <button class="btn-primary">Crear vendedor</button>
    </form>

    <section class="card">
      <h2 class="text-xl font-bold mb-2">Equipo</h2>
      <p v-if="!lista.length" class="text-sm text-tinta/60">Aún no hay vendedores.</p>
      <ul class="divide-y divide-uva/10">
        <li v-for="v in lista" :key="v.id" class="py-3" :class="{ 'opacity-60': !v.activo }">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="font-bold">{{ v.name }}<span v-if="!v.activo" class="ml-2 text-xs text-chamoy">Inactivo</span></p>
              <p class="text-sm break-all">{{ v.email }}</p>
              <p class="text-sm mt-0.5">
                Contraseña:
                <template v-if="v.password">
                  <b class="font-mono">{{ visibles.has(v.id) ? v.password : '••••••••' }}</b>
                  <button class="ml-2 text-uva font-bold" @click="alternarVista(v.id)">{{ visibles.has(v.id) ? 'Ocultar' : 'Mostrar' }}</button>
                </template>
                <span v-else class="text-tinta/60">no disponible (asígnale una nueva con Editar)</span>
              </p>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
              <button class="btn-ghost !py-1 text-sm" @click="editar(v)">Editar</button>
              <button class="btn-ghost !py-1 text-sm" @click="alternar(v)">{{ v.activo ? 'Desactivar' : 'Activar' }}</button>
            </div>
          </div>
        </li>
      </ul>
      <p class="text-xs text-tinta/60 mt-3">Solo se pueden mostrar las contraseñas que asignaste desde este panel; se guardan cifradas. Usa una contraseña distinta a la que tus empleados usan en otros sitios.</p>
    </section>
  </div>

  <div v-if="edicion" class="fixed inset-0 bg-tinta/50 grid place-items-center p-3 z-50" role="dialog" aria-modal="true" aria-label="Editar vendedor">
    <form class="card w-full max-w-md space-y-3" @submit.prevent="guardarEdicion">
      <h2 class="text-xl font-bold">Editar vendedor</h2>
      <div><label class="label" for="e-n">Nombre</label><input id="e-n" v-model="edicion.name" class="input" required /></div>
      <div><label class="label" for="e-e">Correo</label><input id="e-e" v-model="edicion.email" type="email" class="input" required /></div>
      <div><label class="label" for="e-p">Contraseña nueva (déjala vacía para no cambiarla)</label><input id="e-p" v-model="edicion.password" minlength="6" class="input" autocomplete="off" /></div>
      <div class="flex justify-end gap-2">
        <button type="button" class="btn-ghost" @click="edicion = null">Cancelar</button>
        <button class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</template>
