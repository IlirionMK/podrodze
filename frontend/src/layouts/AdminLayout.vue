<script setup>
import { ref, onMounted, onUnmounted } from "vue"
import { useI18n } from "vue-i18n"
import LogoIcon from "@/components/icons/LogoIcon.vue"
import LanguageSwitcher from "@/components/LanguageSwitcher.vue"
import Header from "@/components/Header.vue"
import Footer from "@/components/Footer.vue"

const { t } = useI18n({ useScope: "global" })

const menuOpen = ref(false)
const menuRef = ref(null)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

function clickOutside(e) {
  if (menuRef.value && !menuRef.value.contains(e.target)) {
    menuOpen.value = false
  }
}

onMounted(() => document.addEventListener("click", clickOutside))
onUnmounted(() => document.removeEventListener("click", clickOutside))
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">

    <!-- ===== TOP HEADER ===== -->
    <header class="bg-white border-b shadow-sm">
      <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

        <!-- Logo → strona główna -->
        <router-link
          to="/app/home"
          class="flex items-center gap-3 font-semibold text-xl hover:opacity-90 transition"
        >
          <LogoIcon class="w-9 h-9 text-blue-600" />
          <span>PoDrodze</span>
        </router-link>

        <!-- PRAWA STRONA: język + menu -->
        <div class="flex items-center gap-4 relative" ref="menuRef">
          <LanguageSwitcher />

          <button
            @click="toggleMenu"
            class="w-10 h-10 rounded-full border flex items-center justify-center
                   hover:bg-gray-100 transition text-lg"
            aria-label="Admin menu"
          >
            ☰
          </button>

          <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div
              v-if="menuOpen"
              class="absolute right-0 top-12 w-52 bg-white border rounded-xl shadow-lg py-2 z-50"
            >
              <router-link to="/admin/users" class="menu-item">
                {{ t("app.admin.menu.users") }}
              </router-link>
              <router-link to="/admin/trips" class="menu-item">
                {{ t("app.admin.menu.trips") }}
              </router-link>
              <router-link to="/admin/places" class="menu-item">
                {{ t("app.admin.menu.places") }}
              </router-link>
              <router-link to="/admin/settings" class="menu-item">
                {{ t("app.admin.menu.settings") }}
              </router-link>

            </div>
          </transition>
        </div>
      </div>
    </header>

    <!-- ADMIN TITLE HEADER -->
<section class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600
                 text-white rounded-lg shadow-md hover:shadow-lg
                 hover:brightness-105 transition-all active:scale-[0.98]">
  <div class="max-w-7xl mx-auto px-6 py-10 text-center">
    <!-- Klikalny tytuł -> dashboard -->
    <router-link to="/admin">
      <h1 class="text-3xl md:text-4xl font-bold text-white-800
                 hover:text-blue-600 transition cursor-pointer">
        {{ t("app.admin.title") }}
      </h1>
    </router-link>
    <p class="mt-3 text-white-500 text-sm md:text-base">
      {{ t("app.admin.subtitle") }}
    </p>
  </div>
</section>



    <!-- ===== CONTENT ===== -->
    <main class="flex-1 max-w-7xl mx-auto w-full p-6">
      <router-view />
    </main>

    <!-- ===== FOOTER ===== -->
    <Footer/>

  </div>
</template>

<style scoped>
.menu-item {
  display: block;
  padding: 0.6rem 1rem;
  font-size: 0.875rem;
  color: #374151;
}
.menu-item:hover {
  background: #f3f4f6;
}
.footer-title {
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
  color: #e5e7eb;
  opacity: 0.8;
}
.footer-list li {
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: color 0.2s;
}
.footer-list li:hover {
  color: #e5e7eb;
}
</style>
