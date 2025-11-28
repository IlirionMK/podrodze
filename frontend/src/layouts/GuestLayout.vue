<script setup>
const t = {
  guestTitle: "app.guest.title",
}
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <header class="p-4 border-b bg-white">
      <h1 class="text-xl font-bold">
        {{ t.guestTitle }}
      </h1>
    </header>

    <main class="flex-1 flex items-center justify-center p-6">
      <router-view />
    </main>
  </div>
</template>
