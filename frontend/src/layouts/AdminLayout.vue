<script setup>
import { ref } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import { useAuth } from "@/composables/useAuth"

import LogoIcon from "@/components/icons/LogoIcon.vue"

const { t } = useI18n({ useScope: "global" })
const router = useRouter()
const { logout } = useAuth()

const sidebar = ref(false)

function handleLogout() {
  logout(router)
}
</script>

<template>
  <div class="flex min-h-screen bg-gray-900 text-gray-100">

    <!-- Desktop Sidebar -->
    <aside
      class="bg-gray-600 w-64 p-4 hidden md:flex flex-col gap-4 shadow-lg"
    >
      <h2 class="font-bold text-xl text-blue-400 mb-4 flex items-center gap-2">
        <LogoIcon class="w-6 h-6" />
        {{ t("app.admin.title") }}
      </h2>

      <router-link
        to="/admin/dashboard"
        class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
      >
        Dashboard
      </router-link>
      <router-link
        to="/admin/users"
        class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
      >
        Użytkownicy
      </router-link>
      <router-link
        to="/admin/trips"
        class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
      >
        Podróże
      </router-link>
      <router-link
        to="/admin/places"
        class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
      >
        Miejsca
      </router-link>
      <router-link
        to="/admin/settings"
        class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
      >
        Ustawienia
      </router-link>

      <button
        @click="handleLogout"
        class="mt-auto px-3 py-2 rounded bg-red-600 hover:bg-red-500 transition"
      >
        {{ t("auth.logout") }}
      </button>
    </aside>

    <!-- Mobile toggle button -->
    <button
      @click="sidebar = !sidebar"
      class="md:hidden absolute top-4 left-4 z-20 bg-gray-800 p-2 rounded text-gray-100 shadow"
    >
      ☰
    </button>

    <!-- Mobile Sidebar -->
    <transition
      enter-active-class="transition-transform duration-200 ease-linear"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200 ease-linear"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <aside
        v-if="sidebar"
        class="absolute left-0 top-0 bottom-0 bg-gray-800 w-56 p-4 flex flex-col gap-4 shadow-md z-30 md:hidden"
      >
        <h2 class="font-bold text-xl text-blue-200 mb-4 flex items-center gap-2">
          <LogoIcon class="w-6 h-6" />
          {{ t("app.admin.title") }}
        </h2>

        <router-link
          to="/admin/dashboard"
          class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
        >
          Dashboard
        </router-link>
        <router-link
          to="/admin/users"
          class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
        >
          Użytkownicy
        </router-link>
        <router-link
          to="/admin/trips"
          class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
        >
          Podróże
        </router-link>
        <router-link
          to="/admin/places"
          class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
        >
          Miejsca
        </router-link>
        <router-link
          to="/admin/settings"
          class="hover:bg-gray-700 px-3 py-2 rounded text-gray-100 transition"
        >
          Ustawienia
        </router-link>

        <button
          @click="handleLogout"
          class="mt-auto px-3 py-2 rounded bg-red-600 hover:bg-red-500 transition"
        >
          {{ t("auth.logout") }}
        </button>
      </aside>
    </transition>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">

      <!-- Admin Header -->
      <header class="w-full bg-gray-900 text-gray-100 shadow-sm border-b border-gray-700">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-3">
          <span class="text-xl font-bold text-blue-400">
            Panel Administratora
          </span>
          <div>
            <button
              @click="handleLogout"
              class="px-4 py-2 bg-red-600 rounded hover:bg-red-500 transition"
            >
              Wyloguj
            </button>
          </div>
        </div>
      </header>

      <main class="flex-1 p-6">
        <router-view :t="t" />
      </main>

    </div>

  </div>
</template>
