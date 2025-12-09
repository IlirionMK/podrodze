<script setup>
import { ref, onMounted } from "vue"
import { useRoute } from "vue-router"
import { useI18n } from "vue-i18n"

import { fetchTrip } from "@/composables/api/trips.js"
import { fetchTripPlaces } from "@/composables/api/tripPlaces.js"
import { fetchTripMembers } from "@/composables/api/tripMembers.js"

import TripMap from "@/components/trips/TripMap.vue"

const route = useRoute()
const { t } = useI18n()

const trip = ref(null)
const members = ref([])
const places = ref([])
const loading = ref(true)
const errorMsg = ref("")

// UI
const activeTab = ref("overview")

async function loadData() {
  try {
    const tripRes = await fetchTrip(route.params.id)
    trip.value = tripRes.data.data

    const membersRes = await fetchTripMembers(route.params.id)
    members.value = membersRes.data.data

    await refreshPlaces()
  } catch (err) {
    errorMsg.value = err.response?.data?.message || t("errors.default")
  } finally {
    loading.value = false
  }
}

async function refreshPlaces() {
  const res = await fetchTripPlaces(route.params.id)
  places.value = res.data.data
}

onMounted(loadData)
</script>

<template>
  <div class="w-full">

    <!-- Loading -->
    <div v-if="loading" class="p-8 text-center text-gray-500">
      {{ t("loading") }}…
    </div>

    <!-- Error -->
    <div
        v-if="errorMsg && !loading"
        class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-xl max-w-xl mx-auto"
    >
      {{ errorMsg }}
    </div>

    <!-- Content -->
    <div v-if="trip">

      <!-- Banner -->
      <div class="relative h-56 md:h-72 w-full overflow-hidden">
        <img
            src="https://images.unsplash.com/photo-1528909514045-2fa4ac7a08ba"
            alt="Trip banner"
            class="w-full h-full object-cover"
        />

        <div class="absolute inset-0 bg-black/40"></div>

        <div class="absolute bottom-6 left-6 text-white drop-shadow">
          <h1 class="text-3xl md:text-4xl font-bold">
            {{ trip.name }}
          </h1>
          <p v-if="trip.description" class="text-lg opacity-90">
            {{ trip.description }}
          </p>
        </div>
      </div>

      <div class="max-w-6xl mx-auto px-4 -mt-10 relative z-10">

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.places") }}</div>
            <div class="text-2xl font-semibold">{{ places.length }}</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.activities") }}</div>
            <div class="text-2xl font-semibold">0</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.members") }}</div>
            <div class="text-2xl font-semibold">{{ members.length }}</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.other") }}</div>
            <div class="text-2xl font-semibold">—</div>
          </div>
        </div>

        <!-- Tabs -->
        <div
            class="flex gap-8 border-b pb-2 text-gray-600 mb-6 overflow-x-auto whitespace-nowrap"
        >
          <button
              class="pb-1 font-medium"
              :class="activeTab === 'overview'
              ? 'text-black border-b-2 border-black'
              : 'hover:text-black'"
              @click="activeTab = 'overview'"
          >
            {{ t("trip.tabs.overview") }}
          </button>

          <button
              class="pb-1"
              :class="activeTab === 'places'
              ? 'text-black border-b-2 border-black'
              : 'hover:text-black'"
              @click="activeTab = 'places'"
          >
            {{ t("trip.tabs.places") }}
          </button>

          <button
              class="pb-1"
              :class="activeTab === 'plan'
              ? 'text-black border-b-2 border-black'
              : 'hover:text-black'"
              @click="activeTab = 'plan'"
          >
            {{ t("trip.tabs.plan") }}
          </button>

          <button
              class="pb-1"
              :class="activeTab === 'team'
              ? 'text-black border-b-2 border-black'
              : 'hover:text-black'"
              @click="activeTab = 'team'"
          >
            {{ t("trip.tabs.team") }}
          </button>
        </div>

        <!-- Main Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

          <!-- LEFT column -->
          <div class="lg:col-span-2 flex flex-col gap-8">

            <!-- About -->
            <section class="bg-white p-6 rounded-xl border shadow-sm">
              <h2 class="text-xl font-semibold mb-4">
                {{ t("trip.about") }}
              </h2>

              <p class="text-gray-700 mb-4">
                {{ trip.description || t("trip.no_description") }}
              </p>

              <div class="flex items-center gap-6 text-sm">
                <div>
                  <div class="text-gray-500">{{ t("trip.start") }}</div>
                  <div class="font-medium">{{ trip.start_date }}</div>
                </div>

                <div>
                  <div class="text-gray-500">{{ t("trip.end") }}</div>
                  <div class="font-medium">{{ trip.end_date }}</div>
                </div>
              </div>
            </section>

            <!-- Places -->
            <section class="bg-white p-6 rounded-xl border shadow-sm">
              <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">
                  {{ t("trip.view.places") }}
                </h2>

                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                  {{ t("trip.view.add_place") }}
                </button>
              </div>

              <div class="flex flex-col gap-3">
                <div
                    v-for="pl in places"
                    :key="pl.id"
                    class="border rounded-lg p-4 flex items-center justify-between hover:bg-gray-50 transition"
                >
                  <div>
                    <h3 class="font-medium">{{ pl.name }}</h3>
                    <p class="text-gray-500 text-sm">{{ pl.category }}</p>
                  </div>

                  <button class="text-blue-600 hover:underline">
                    {{ t("trip.view.open") }}
                  </button>
                </div>
              </div>
            </section>

          </div>

          <!-- RIGHT column -->
          <div class="flex flex-col gap-8">

            <!-- Map -->
            <section class="bg-white p-6 rounded-xl border shadow-sm">
              <h2 class="text-xl font-semibold mb-4">
                {{ t("trip.view.map") }}
              </h2>

              <div class="w-full rounded-xl overflow-hidden border" style="min-height: 360px;">
                <TripMap
                    :trip="trip"
                    :places="places"
                    @places-changed="refreshPlaces"
                />
              </div>
            </section>

            <!-- Members -->
            <section class="bg-white p-6 rounded-xl border shadow-sm">
              <h2 class="text-xl font-semibold mb-4">
                {{ t("trip.view.members") }}
              </h2>

              <div class="flex flex-wrap gap-4">
                <div
                    v-for="m in members"
                    :key="m.id"
                    class="px-4 py-2 rounded-lg bg-gray-100 border"
                >
                  {{ m.name }}
                </div>

                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                  {{ t("trip.view.add_member") }}
                </button>
              </div>
            </section>

          </div>

        </div>
      </div>

    </div>
  </div>
</template>
