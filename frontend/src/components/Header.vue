<script setup>
import { useAuth } from "@/composables/useAuth"
import { computed, ref, onMounted, onUnmounted } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"

import LogoIcon from "@/components/icons/LogoIcon.vue"
import LanguageSwitcher from "@/components/LanguageSwitcher.vue"

const { token, user, clearAuth } = useAuth()
const { t } = useI18n()
const router = useRouter()

const isLogged = computed(() => !!token.value)

const showMenu = ref(false)
const menuRef = ref(null)

function handleLogout() {
  clearAuth()
  router.push("/")
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
  <header class="w-full border-b bg-white/80 backdrop-blur">
    <div class="max-w-7xl mx-auto flex justify-between items-center p-4">

      <!-- Brand -->
      <router-link to="/" class="flex items-center gap-3 font-semibold text-lg">
        <LogoIcon class="w-8 h-8 text-blue-600" />
        <span>PoDrodze</span>
      </router-link>

      <div class="flex items-center gap-4">

        <LanguageSwitcher />

        <!-- Guest -->
        <template v-if="!isLogged">
          <router-link to="/login" class="text-sm hover:text-blue-600">
            {{ t("auth.login.title") }}
          </router-link>

          <router-link
              to="/register"
              class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded shadow hover:opacity-95"
          >
            {{ t("auth.register.title") }}
          </router-link>
        </template>

        <!-- Authenticated -->
        <template v-else>
          <div class="relative" ref="menuRef">
            <button
                @click="showMenu = !showMenu"
                class="flex items-center gap-3 px-3 py-1 rounded hover:bg-gray-100 transition"
            >
              <img
                  :src="`https://api.dicebear.com/7.x/thumbs/svg?seed=${user?.name || 'user'}`"
                  class="w-8 h-8 rounded-full"
              />

              <span class="text-sm">
                {{ t("header.hello") }},
                <strong>{{ user?.name || "User" }}</strong>
              </span>
            </button>

            <!-- Dropdown -->
            <div
                v-if="showMenu"
                class="absolute right-0 mt-2 w-40 bg-white border rounded shadow-md py-2"
            >
              <router-link
                  to="/app/profile"
                  class="block px-4 py-2 text-sm hover:bg-gray-100"
              >
                {{ t("header.profile") }}
              </router-link>

              <button
                  @click="handleLogout"
                  class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
              >
                {{ t("auth.logout") }}
              </button>
            </div>

          </div>
        </template>

      </div>
    </div>
  </header>
</template>
