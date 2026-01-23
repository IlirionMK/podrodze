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

function closeMenu() {
  showMenu.value = false
}

function toggleMenu() {
  showMenu.value = !showMenu.value
}

function handleLogout() {
  closeMenu()
  logout(router)
}

function clickOutside(e) {
  if (menuRef.value && !menuRef.value.contains(e.target)) {
    closeMenu()
  }
}

onMounted(() => document.addEventListener("click", clickOutside))
onUnmounted(() => document.removeEventListener("click", clickOutside))
</script>

<template>
  <header class="w-full border-b bg-white shadow-sm">
    <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-3 min-w-0">
      <router-link
          to="/"
          class="flex items-center gap-3 font-semibold text-xl hover:opacity-90 transition shrink-0"
      >
        <LogoIcon class="w-9 h-9 text-blue-600 shrink-0" />
        <span class="tracking-wide whitespace-nowrap">PoDrodze</span>
      </router-link>

      <div class="flex items-center gap-2 sm:gap-4 min-w-0">
        <div class="shrink-0">
          <LanguageSwitcher />
        </div>

        <template v-if="!isAuthenticated">
          <router-link
              to="/login"
              class="text-xs sm:text-sm text-gray-700 hover:text-blue-600 transition whitespace-nowrap"
          >
            {{ t("auth.login.title") }}
          </router-link>

          <router-link
              to="/register"
              class="px-3 sm:px-4 py-2 text-xs sm:text-sm whitespace-nowrap bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg shadow-md hover:shadow-lg hover:brightness-105 transition-all active:scale-[0.98]"
          >
            {{ t("auth.register.title") }}
          </router-link>
        </template>

        <template v-else>
          <div class="relative min-w-0" ref="menuRef">
            <button
                @click.stop="toggleMenu"
                class="flex items-center gap-3 px-3 py-1 rounded-lg hover:bg-gray-100 transition group min-w-0 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                type="button"
                aria-haspopup="menu"
                :aria-expanded="showMenu ? 'true' : 'false'"
            >
              <img
                  :src="`https://api.dicebear.com/7.x/thumbs/svg?seed=${user?.name}`"
                  class="w-9 h-9 rounded-full ring-1 ring-gray-200 shadow-sm group-hover:ring-blue-300 transition shrink-0"
                  alt=""
              />

              <span class="text-sm text-gray-700 hidden sm:inline min-w-0 truncate max-w-[14rem]">
                {{ t("header.hello") }},
                <strong>{{ user?.name }}</strong>
              </span>
            </button>

            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-120 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-1"
            >
              <div
                  v-if="showMenu"
                  class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-xl py-2 z-50"
                  role="menu"
              >
                <router-link
                    to="/app/profile"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition whitespace-nowrap"
                    role="menuitem"
                    @click="closeMenu"
                >
                  {{ t("header.profile") }}
                </router-link>

                <router-link
                    to="/app/trips"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition whitespace-nowrap"
                    role="menuitem"
                    @click="closeMenu"
                >
                  Trips
                </router-link>

                <button
                    @click="handleLogout"
                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition whitespace-nowrap"
                    type="button"
                    role="menuitem"
                >
                  {{ t("auth.logout") }}
                </button>
              </div>
            </transition>
          </div>
        </template>
      </div>
    </div>
  </header>
</template>
