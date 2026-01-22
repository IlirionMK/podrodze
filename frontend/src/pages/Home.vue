<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import {
  MapPin,
  Users,
  ThumbsUp,
  Sparkles,
  ArrowRight,
  CalendarDays,
  Route,
  SlidersHorizontal,
  PlusCircle,
  CheckCircle2,
} from "lucide-vue-next"

const { t } = useI18n({ useScope: "global" })
const router = useRouter()

const token = ref(localStorage.getItem("token"))
const isAuthenticated = computed(() => !!token.value)

function syncToken() {
  token.value = localStorage.getItem("token")
}

onMounted(() => {
  window.addEventListener("storage", syncToken)
  window.addEventListener("auth-change", syncToken)
})

onUnmounted(() => {
  window.removeEventListener("storage", syncToken)
  window.removeEventListener("auth-change", syncToken)
})

function go(name) {
  if (name) router.push({ name: String(name) })
}

const heroPills = [
  "home.pill_trips",
  "home.pill_places",
  "home.pill_team",
  "home.pill_preferences",
  "home.pill_plan",
]

const previewItems = [
  { icon: CalendarDays, title: "home.preview_1_title", desc: "home.preview_1_desc" },
  { icon: MapPin, title: "home.preview_2_title", desc: "home.preview_2_desc" },
  { icon: Users, title: "home.preview_3_title", desc: "home.preview_3_desc" },
  { icon: SlidersHorizontal, title: "home.preview_4_title", desc: "home.preview_4_desc" },
  { icon: Route, title: "home.preview_5_title", desc: "home.preview_5_desc" },
]

const howSteps = [
  { icon: PlusCircle, title: "home.how_1_title", desc: "home.how_1_desc" },
  { icon: MapPin, title: "home.how_2_title", desc: "home.how_2_desc" },
  { icon: CheckCircle2, title: "home.how_3_title", desc: "home.how_3_desc" },
]

const features = [
  { icon: MapPin, title: "home.feature_places_title", desc: "home.feature_places_desc" },
  { icon: Users, title: "home.feature_team_title", desc: "home.feature_team_desc" },
  { icon: ThumbsUp, title: "home.feature_decisions_title", desc: "home.feature_decisions_desc" },
]

const btnPrimary =
    "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold " +
    "bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg " +
    "hover:opacity-95 active:opacity-90 transition w-full sm:w-auto " +
    "focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer"

const btnSecondary =
    "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold " +
    "bg-white/15 text-white border border-white/25 backdrop-blur shadow-lg " +
    "hover:bg-white/20 active:bg-white/25 transition w-full sm:w-auto " +
    "focus:outline-none focus:ring-2 focus:ring-white/30 cursor-pointer"

const pillBase =
    "rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur select-none"

const previewCard =
    "rounded-xl border border-white/15 bg-white/10 px-4 py-3 " +
    "transition hover:bg-white/15 hover:-translate-y-0.5 active:translate-y-0 cursor-default"

const featureCard =
    "rounded-2xl border border-gray-200 bg-white shadow-sm p-6 transition " +
    "hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 cursor-default"

const iconBoxDark =
    "h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center"

const iconBoxLight =
    "h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 " +
    "text-white flex items-center justify-center shadow-lg"
</script>

<template>
  <div class="w-full overflow-hidden">
    <section class="relative">
      <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-700"></div>

      <div class="absolute inset-0 opacity-45">
        <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute top-24 -right-24 h-96 w-96 rounded-full bg-white/15 blur-3xl"></div>
      </div>

      <div class="relative max-w-6xl mx-auto px-4 py-16 sm:py-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
          <div class="lg:col-span-7 text-white">
            <Transition
                appear
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
            >
              <div>
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur"
                >
                  <Sparkles class="h-4 w-4 shrink-0" />
                  {{ t("home.badge") }}
                </div>

                <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
                  {{ t("home.hero_title") }}
                </h1>

                <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
                  {{ t("home.hero_subtitle") }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                  <button
                      v-if="isAuthenticated"
                      type="button"
                      :class="btnPrimary"
                      @click="go('app.trips')"
                  >
                    {{ t("home.cta_my_trips") }}
                    <ArrowRight class="h-4 w-4 shrink-0" />
                  </button>

                  <template v-else>
                    <button type="button" :class="btnPrimary" @click="go('auth.login')">
                      {{ t("home.cta_login") }}
                      <ArrowRight class="h-4 w-4 shrink-0" />
                    </button>

                    <button type="button" :class="btnSecondary" @click="go('auth.register')">
                      {{ t("home.cta_register") }}
                    </button>
                  </template>
                </div>

                <div class="mt-6 flex flex-wrap gap-2 text-xs text-white/80">
                  <span v-for="pill in heroPills" :key="pill" :class="pillBase">
                    {{ t(pill) }}
                  </span>
                </div>
              </div>
            </Transition>
          </div>

          <div class="lg:col-span-5">
            <Transition
                appear
                enter-active-class="transition duration-500 ease-out delay-100"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
            >
              <div
                  class="rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl shadow-2xl p-5 sm:p-6 text-white"
              >
                <div class="text-sm font-semibold">
                  {{ t("home.preview_title") }}
                </div>

                <div class="mt-4 space-y-3">
                  <div v-for="(item, idx) in previewItems" :key="idx" :class="previewCard">
                    <div class="flex items-center gap-3">
                      <div :class="iconBoxDark">
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ t(item.title) }}</div>
                        <div class="text-sm text-white/80">{{ t(item.desc) }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-5 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80">
                  {{ t("home.preview_footer") }}
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-10 sm:py-12">
      <div class="flex items-end justify-between gap-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-semibold text-gray-900">
            {{ t("home.how_title") }}
          </h2>
          <p class="mt-1 text-sm text-gray-600">
            {{ t("home.how_subtitle") }}
          </p>
        </div>

        <button
            v-if="isAuthenticated"
            type="button"
            class="hidden sm:inline-flex items-center gap-2 text-sm font-semibold text-gray-900 hover:text-gray-700 transition cursor-pointer"
            @click="go('app.trips')"
        >
          {{ t("home.how_cta") }}
          <ArrowRight class="h-4 w-4 shrink-0" />
        </button>
      </div>

      <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
        <div
            v-for="(step, idx) in howSteps"
            :key="idx"
            class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 transition hover:shadow-md hover:-translate-y-0.5 cursor-default"
        >
          <div class="flex items-start gap-4">
            <div :class="iconBoxLight">
              <component :is="step.icon" class="h-5 w-5 shrink-0" />
            </div>
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ t("home.how_step") }} {{ idx + 1 }}
              </div>
              <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ t(step.title) }}</h3>
              <p class="mt-1 text-sm text-gray-600">{{ t(step.desc) }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-10 sm:py-12">
      <Transition
          appear
          enter-active-class="transition duration-500 ease-out delay-150"
          enter-from-class="opacity-0 translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
      >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
          <div v-for="(feature, idx) in features" :key="idx" :class="featureCard">
            <div :class="iconBoxLight">
              <component :is="feature.icon" class="h-5 w-5 shrink-0" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">
              {{ t(feature.title) }}
            </h3>
            <p class="mt-1 text-sm text-gray-600">
              {{ t(feature.desc) }}
            </p>
          </div>
        </div>
      </Transition>
    </section>
  </div>
</template>