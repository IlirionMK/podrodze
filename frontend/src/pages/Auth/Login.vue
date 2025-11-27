<script setup>
import { ref } from 'vue'

const email = ref('')
const password = ref('')

const submit = () => {
  console.log('Login attempt:', email.value)
}
</script>

<template>
  <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow">
    <h2 class="text-3xl font-bold mb-6 text-center">Logowanie</h2>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">Email</label>
        <input v-model="email" type="email" class="w-full border rounded px-3 py-2" />
      </div>

      <div>
        <label class="block text-sm font-medium">Hasło</label>
        <input v-model="password" type="password" class="w-full border rounded px-3 py-2" />
      </div>

      <button
          class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition"
      >
        Zaloguj
      </button>
    </form>
  </div>
</template>
