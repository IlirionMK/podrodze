<template>
  <div class="min-h-screen flex flex-col items-center justify-center text-center p-8">
    <h1 class="text-5xl font-bold text-red-600 mb-4">403</h1>
    <p class="text-xl text-gray-700 mb-6">
      {{ $t("errors.403_message") }}
    </p>

    <router-link
        :to="{ name: 'home' }"
        class="px-6 py-3 bg-blue-600 text-white rounded-lg shadow"
    >
      {{ $t("errors.back_home") }}
    </router-link>
  </div>
</template>
