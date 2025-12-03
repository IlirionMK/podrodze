<script setup>
import { ref } from "vue";


const isOpen = ref(false);


const t = {
  title: "app.user.title",
  dashboard: "app.user.dashboard",
  trips: "app.user.trips",
  profile: "app.user.profile",
};
</script>


<template>
  <div class="min-h-screen flex flex-col">
    <header class="p-4 border-b bg-white flex items-center justify-between">
      <h1 class="text-xl font-bold">
        {{ $t(t.title) }}
      </h1>


      <!-- Desktop menu -->
      <nav class="hidden md:flex gap-6 text-sm">
        <router-link to="/dashboard" class="hover:underline">
          {{ $t(t.dashboard) }}
        </router-link>


        <router-link to="/trips" class="hover:underline">
          {{ $t(t.trips) }}
        </router-link>


        <router-link to="/profile" class="hover:underline">
          {{ $t(t.profile) }}
        </router-link>
      </nav>


      <!-- Mobile toggle -->
      <button class="md:hidden" @click="isOpen = !isOpen">
        ☰
      </button>
    </header>


    <!-- Mobile menu -->
    <transition name="fade">
      <nav
          v-if="isOpen"
          class="md:hidden bg-white border-b flex flex-col p-4 gap-3 text-sm"
      >
        <router-link to="/dashboard" class="hover:underline">
          {{ $t(t.dashboard) }}
        </router-link>


        <router-link to="/trips" class="hover:underline">
          {{ $t(t.trips) }}
        </router-link>


        <router-link to="/profile" class="hover:underline">
          {{ $t(t.profile) }}
        </router-link>
      </nav>
    </transition>


    <main class="flex-1 p-4">
      <router-view />
    </main>
  </div>
</template>


<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>





