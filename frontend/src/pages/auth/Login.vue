<script setup>
const t = {
  title: "auth.login.title",
  email: "auth.email",
  password: "auth.password",
  button: "auth.login.submit",
}
</script>

<template>
  <div class="max-w-md w-full bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">{{ t.title }}</h1>

    <form class="space-y-4">
      <div>
        <label class="block text-sm mb-1">{{ t.email }}</label>
        <input
            type="email"
            class="w-full border rounded px-3 py-2"
        />
      </div>

      <div>
        <label class="block text-sm mb-1">{{ t.password }}</label>
        <input
            type="password"
            class="w-full border rounded px-3 py-2"
        />
      </div>

      <button
          type="submit"
          class="w-full bg-blue-600 text-white py-2 rounded"
      >
        {{ t.button }}
      </button>
    </form>
  </div>
</template>
