<script setup>
const t = {
  appName: "app.name",

  dashboard: "nav.dashboard",
  trips: "nav.trips",
  logout: "nav.logout",
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Top Navbar -->
    <header class="bg-white border-b">
      <div class="container mx-auto flex justify-between items-center py-4 px-4">

        <h1 class="text-xl font-semibold">
          {{ t.appName }}
        </h1>

        <nav class="flex items-center space-x-6">
          <RouterLink
              to="/app/dashboard"
              class="hover:text-indigo-600 transition"
          >
            {{ t.dashboard }}
          </RouterLink>

          <RouterLink
              to="/app/trips"
              class="hover:text-indigo-600 transition"
          >
            {{ t.trips }}
          </RouterLink>

          <button
              class="text-red-500 hover:text-red-600"
          >
            {{ t.logout }}
          </button>
        </nav>
      </div>
    </header>

    <!-- Page content -->
    <main class="flex-1 container mx-auto p-6">
      <router-view />
    </main>

  </div>
</template>
