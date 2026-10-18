<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError } from '../api'

defineProps({ modelValue: { type: String, default: '' }, id: { type: String, default: 'proveedor' } })
const emit = defineEmits(['update:modelValue'])

const lista = ref([])
const abierto = ref(false)
const nuevo = ref('')
const error = ref('')

const cargar = async () => { lista.value = (await api.get('/admin/proveedores')).data }

async function agregar() {
  error.value = ''
  if (!nuevo.value.trim()) return
  try {
    const { data } = await api.post('/admin/proveedores', { nombre: nuevo.value })
    await cargar()
    emit('update:modelValue', data.nombre)
    nuevo.value = ''
    abierto.value = false
  } catch (e) { error.value = mensajeError(e) }
}

onMounted(cargar)
</script>

<template>
  <div>
    <label class="label" :for="id">Proveedor</label>
    <div class="flex gap-2">
      <select :id="id" :value="modelValue" class="input" required @change="emit('update:modelValue', $event.target.value)">
        <option value="" disabled>Elige proveedor</option>
        <option v-for="p in lista" :key="p.id" :value="p.nombre">{{ p.nombre }}</option>
      </select>
      <button type="button" class="btn-ghost shrink-0 !px-3.5" :aria-expanded="abierto" aria-label="Agregar proveedor nuevo" @click="abierto = !abierto">+</button>
    </div>
    <div v-if="abierto" class="flex gap-2 mt-2">
      <input v-model="nuevo" class="input" placeholder="Nombre del proveedor" aria-label="Nombre del proveedor nuevo" @keydown.enter.prevent="agregar" />
      <button type="button" class="btn-primary shrink-0" @click="agregar">Agregar</button>
    </div>
    <p v-if="error" class="text-chamoy text-sm font-bold mt-1" role="alert">{{ error }}</p>
  </div>
</template>
