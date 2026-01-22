<script setup>
import { useAuth } from "@/composables/useAuth"
import { ref, onMounted, onUnmounted } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"

import LogoIcon from "@/components/icons/LogoIcon.vue"
import LanguageSwitcher from "@/components/LanguageSwitcher.vue"

const { t } = useI18n({ useScope: "global" })
const router = useRouter()
const { isAuthenticated, user, logout } = useAuth()

const showMenu = ref(false)
const menuRef = ref(null)

function handleLogout() {
  logout(router)
}

function clickOutside(e) {
  if (menuRef.value && !menuRef.value.contains(e.target)) {
    showMenu.value = false
  }
}

onMounted(() => document.addEventListener("click", clickOutside))
onUnmounted(() => document.removeEventListener("click", clickOutside))
</script>

<template>
<header class="w-full border-b bg-white shadow-sm">
  <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-3">

    <!-- Logo -->
    <router-link
  :to="user?.role === 'admin' ? { name: 'admin.dashboard' } : '/app/home'"
  class="flex items-center gap-3 font-semibold text-xl hover:opacity-90 transition"
>
      <LogoIcon class="w-9 h-9 text-blue-600" />
      <span class="tracking-wide">PoDrodze</span>
    </router-link>

    <div class="flex items-center gap-4">

      <LanguageSwitcher />

      <!-- Guest -->
      <template v-if="!isAuthenticated">
        <router-link
          to="/login"
          class="text-sm text-gray-700 hover:text-blue-600 transition"
        >
          {{ t("auth.login.title") }}
        </router-link>

        <router-link
          to="/register"
          class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600
                 text-white rounded-lg shadow-md hover:shadow-lg
                 hover:brightness-105 transition-all active:scale-[0.98]"
        >
          {{ t("auth.register.title") }}
        </router-link>
      </template>

      <!-- Authenticated -->
      <template v-else>
        <div class="relative" ref="menuRef">

          <!-- user menu -->
          <button
            @click="showMenu = !showMenu"
            class="flex items-center gap-3 px-3 py-1 rounded-lg hover:bg-gray-100 transition group"
          >
            <img
              :src="`https://api.dicebear.com/7.x/thumbs/svg?seed=${user?.name}`"
              class="w-9 h-9 rounded-full ring-1 ring-gray-200 shadow-sm group-hover:ring-blue-300 transition"
            />
            <span class="text-sm text-gray-700 hidden sm:inline">
              {{ t("header.hello") }},
              <strong>{{ user?.name }}</strong>
            </span>
          </button>

          <transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div
              v-if="showMenu"
              class="absolute right-0 mt-2 w-48 bg-white border border-gray-200
                   rounded-lg shadow-xl py-2 z-50"
            >

              <!-- ADMIN -->
              <template v-if="user?.role === 'admin'">
                <button
                  @click="handleLogout"
                  class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                >
                  {{ t("auth.logout") }}
                </button>
              </template>

              <!-- USER -->
              <template v-else>
                <router-link
                  to="/app/profile"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition"
                >
                  {{ t("header.profile") }}
                </router-link>

                <router-link
                  to="/app/trips"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition"
                >
                  Trips
                </router-link>

                <button
                  @click="handleLogout"
                  class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                >
                  {{ t("auth.logout") }}
                </button>
              </template>

            </div>
          </transition>

        </div>
      </template>

    </div>
  </div>
</header>
</template>
