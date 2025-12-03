<script setup>
import { ref } from "vue";


const sidebar = ref(false);


const t = {
  title: "app.admin.title",
  users: "app.admin.users",
  trips: "app.admin.trips",
  places: "app.admin.places",
  settings: "app.admin.settings",
};
</script>


<template>
  <div class="flex min-h-screen bg-gray-100">
    <!-- Sidebar -->
    <aside
        class="bg-white border-r w-64 p-4 hidden md:flex flex-col gap-4"
    >
      <h2 class="font-bold text-lg">
        {{ $t(t.title) }}
      </h2>


      <router-link to="/admin/users" class="hover:underline text-sm">
        {{ $t(t.users) }}
      </router-link>


      <router-link to="/admin/trips" class="hover:underline text-sm">
        {{ $t(t.trips) }}
      </router-link>


      <router-link to="/admin/places" class="hover:underline text-sm">
        {{ $t(t.places) }}
      </router-link>


      <router-link to="/admin/settings" class="hover:underline text-sm">
        {{ $t(t.settings) }}
      </router-link>
    </aside>


    <!-- Mobile sidebar toggle -->
    <button
        @click="sidebar = !sidebar"
        class="md:hidden absolute top-4 left-4 z-20 bg-white p-2 rounded shadow"
    >
      ☰
    </button>


    <!-- Mobile drawer -->
    <transition name="slide">
      <aside
          v-if="sidebar"
          class="absolute left-0 top-0 bottom-0 bg-white w-56 p-4 flex flex-col gap-4 shadow-md z-30 md:hidden"
      >
        <h2 class="font-bold text-lg">
          {{ $t(t.title) }}
        </h2>


        <router-link to="/admin/users" class="hover:underline text-sm">
          {{ $t(t.users) }}
        </router-link>


        <router-link to="/admin/trips" class="hover:underline text-sm">
          {{ $t(t.trips) }}
        </router-link>


        <router-link to="/admin/places" class="hover:underline text-sm">
          {{ $t(t.places) }}
        </router-link>


        <router-link to="/admin/settings" class="hover:underline text-sm">
          {{ $t(t.settings) }}
        </router-link>
      </aside>
    </transition>


    <!-- Content -->
    <main class="flex-1 p-6">
      <router-view />
    </main>
  </div>
</template>


<style>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}
</style>





