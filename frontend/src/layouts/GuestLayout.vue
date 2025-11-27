<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <!-- Navbar для гостей -->
    <header class="p-4 border-b bg-white">
      <h1 class="text-xl font-bold">PoDrodze — Guest</h1>
    </header>

    <!-- Контент страниц (login/register) -->
    <main class="flex-1 flex items-center justify-center p-6">
      <router-view />
    </main>
  </div>
</template>
