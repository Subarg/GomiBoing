<script setup>
import { useAuthStore } from './stores/auth'
import NavBar from './components/NavBar.vue'
const auth = useAuthStore()
</script>

<template>
  <NavBar v-if="auth.user" />
  <main class="mx-auto max-w-6xl px-3 sm:px-5 py-5">
    <router-view />
  </main>
</template>
