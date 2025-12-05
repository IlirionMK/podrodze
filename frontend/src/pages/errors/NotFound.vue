<template>
  <div class="min-h-screen flex items-center justify-center p-6 bg-[#0d1117] text-white">
    <div
        class="p-10 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl text-center max-w-md w-full"
    >
      <h1 class="text-4xl font-bold mb-4">404</h1>
      <p class="text-lg opacity-80 mb-6">Page not found</p>

      <router-link
          to="/"
          class="px-6 py-2 rounded-lg bg-white/20 hover:bg-white/30 transition font-medium"
      >
        Go Home
      </router-link>
    </div>
  </div>
</template>
