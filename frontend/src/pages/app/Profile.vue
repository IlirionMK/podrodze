<script setup>
import { ref, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'


const { token, user } = useAuth()
const errorMessage = ref('')


onMounted(async () => {
  if (!token.value) {
    errorMessage.value = 'Brak tokenu — zaloguj się ponownie.'
    return
  }


  try {
    const res = await fetch(import.meta.env.VITE_API_URL + '/user', {
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + token.value
      }
    })


    const data = await res.json()


    if (!res.ok) {
      errorMessage.value = data.message || 'Błąd'
      return
    }


    user.value = data
  } catch (e) {
    errorMessage.value = 'Błąd połączenia.'
  }
})
</script>


<template>
  <div class="max-w-md w-full bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">Profil użytkownika</h1>


    <p v-if="errorMessage" class="text-red-600 mb-3">{{ errorMessage }}</p>


    <div v-if="user">
      <p><strong>ID:</strong> {{ user.id }}</p>
      <p><strong>Name:</strong> {{ user.name }}</p>
      <p><strong>Email:</strong> {{ user.email }}</p>
    </div>
  </div>
</template>
