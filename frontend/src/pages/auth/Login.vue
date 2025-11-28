<script setup>
import { ref } from 'vue'
import { useAuth } from '@/composables/useAuth'

const { setToken } = useAuth()

console.log('API_URL:', import.meta.env.VITE_API_URL)

const email = ref('')
const password = ref('')
const errorMessage = ref('')

const t = {
  title: "auth.login.title",
  email: "auth.email",
  password: "auth.password",
  button: "auth.login.submit",
}

async function onSubmit() {
  errorMessage.value = ''

  try {
    const res = await fetch(import.meta.env.VITE_API_URL + '/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: email.value,
        password: password.value,
      })
    })

    const data = await res.json()

    if (!res.ok) {
      errorMessage.value = data.message || 'Login failed'
      return
    }

    console.log('LOGIN SUCCESS:', data)
    setToken(data.token)
    alert('✔ Login OK')
    window.location.href = '/app/profile'

  } catch (e) {
    console.error(e)
    errorMessage.value = 'Network error'
  }
}
</script>

<template>
  <div class="max-w-md w-full bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">{{ t.title }}</h1>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <div>
        <label class="block text-sm mb-1">{{ t.email }}</label>
        <input
            v-model="email"
            type="email"
            class="w-full border rounded px-3 py-2"
        />
      </div>

      <div>
        <label class="block text-sm mb-1">{{ t.password }}</label>
        <input
            v-model="password"
            type="password"
            class="w-full border rounded px-3 py-2"
        />
      </div>

      <p v-if="errorMessage" class="text-red-600 text-sm">
        {{ errorMessage }}
      </p>

      <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded">
        {{ t.button }}
      </button>
    </form>
  </div>
</template>



