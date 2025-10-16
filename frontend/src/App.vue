<script setup>
import { ref, onMounted } from 'vue'

const status = ref('loading...')

onMounted(async () => {
  try {
    const res = await fetch(`${import.meta.env.VITE_API_URL}/health`)
    const json = await res.json()
    status.value = json.status
  } catch (e) {
    status.value = 'error'
    console.error(e)
  }
})
</script>

<template>
  <main style="padding:1rem">
    <h1>API health: {{ status }}</h1>
  </main>
</template>
