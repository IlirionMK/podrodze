<script setup>
import Header from "@/components/Header.vue"
import Footer from "@/components/Footer.vue"
import { useI18n } from "vue-i18n"

const { t, te } = useI18n({ useScope: "global" })
const tr = (key, fallback) => (te(key) ? t(key) : fallback)
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <Header />

    <!-- Admin banner -->
    <section
        class="mx-auto w-full max-w-7xl px-4 sm:px-6 mt-6"
        aria-label="Admin banner"
    >
      <div
          class="rounded-2xl bg-gradient-to-r from-black to-blue-900 text-white shadow-md hover:shadow-lg hover:brightness-105 transition-all"
      >
        <div class="px-6 py-10 text-center">
          <router-link to="/admin" class="inline-block">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold hover:text-blue-200 transition">
              {{ tr("app.admin.title", "Admin Panel") }}
            </h1>
          </router-link>

          <p class="mt-2 text-sm sm:text-base text-white/80">
            {{ tr("app.admin.subtitle", "Manage system and users") }}
          </p>
        </div>
      </div>
    </section>

    <!-- Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 py-6">
      <router-view />
    </main>

    <Footer />
  </div>
</template>
