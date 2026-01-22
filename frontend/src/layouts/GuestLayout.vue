<script setup>
import { computed } from "vue"
import { useRoute } from "vue-router"
import Header from "@/components/Header.vue"
import Footer from "@/components/Footer.vue"

const route = useRoute()

const hideChrome = computed(() =>
    ["auth.google", "auth.facebook"].includes(route.name)
)
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white">
    <Header v-if="!hideChrome" />

    <main class="flex-1">
      <router-view />
    </main>

    <Footer v-if="!hideChrome" />
  </div>
</template>
