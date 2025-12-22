<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"
import { useRouter } from "vue-router"
import { MapPin, Users, ThumbsUp, Sparkles, ArrowRight, CalendarDays, Route, SlidersHorizontal } from "lucide-vue-next"

const { t, te } = useI18n({ useScope: "global" })
const router = useRouter()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const isAuthenticated = computed(() => Boolean(localStorage.getItem("token")))

function go(name) {
  router.push({ name })
}

const btnPrimary =
    "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold " +
    "bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg " +
    "hover:opacity-95 active:opacity-90 transition w-full sm:w-auto " +
    "focus:outline-none focus:ring-2 focus:ring-blue-500/30"

const btnSecondary =
    "inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold " +
    "bg-white/15 text-white border border-white/25 backdrop-blur shadow-lg " +
    "hover:bg-white/20 transition w-full sm:w-auto " +
    "focus:outline-none focus:ring-2 focus:ring-white/30"
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
                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur">
                  <Sparkles class="h-4 w-4" />
                  {{ tr("home.badge", "Planowanie wyjazdu zespołowo i bez chaosu") }}
                </div>

                <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
                  {{ tr("home.hero_title", "PoDrodze — zaplanuj podróż razem") }}
                </h1>

                <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
                  {{ tr("home.hero_subtitle", "Twórz podróże, dodawaj miejsca, zapraszaj znajomych i układaj plan dnia w jednym miejscu.") }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                  <button
                      v-if="isAuthenticated"
                      type="button"
                      :class="btnPrimary"
                      @click="go('app.trips')"
                  >
                    {{ tr("home.cta_my_trips", "Moje podróże") }}
                    <ArrowRight class="h-4 w-4" />
                  </button>

                  <template v-else>
                    <button type="button" :class="btnPrimary" @click="go('auth.login')">
                      {{ tr("home.cta_login", "Zaloguj się") }}
                      <ArrowRight class="h-4 w-4" />
                    </button>

                    <button type="button" :class="btnSecondary" @click="go('auth.register')">
                      {{ tr("home.cta_register", "Utwórz konto") }}
                    </button>
                  </template>
                </div>

                <div class="mt-6 flex flex-wrap gap-2 text-xs text-white/80">
                  <span class="rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur">
                    {{ tr("home.pill_trips", "Podróże") }}
                  </span>
                  <span class="rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur">
                    {{ tr("home.pill_places", "Miejsca") }}
                  </span>
                  <span class="rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur">
                    {{ tr("home.pill_team", "Zespół") }}
                  </span>
                  <span class="rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur">
                    {{ tr("home.pill_preferences", "Preferencje") }}
                  </span>
                  <span class="rounded-full bg-white/10 border border-white/15 px-3 py-1 backdrop-blur">
                    {{ tr("home.pill_plan", "Plan dnia") }}
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
              <div class="rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl shadow-2xl p-5 sm:p-6 text-white">
                <div class="text-sm font-semibold">
                  {{ tr("home.preview_title", "Co możesz zrobić") }}
                </div>

                <div class="mt-4 space-y-3">
                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <CalendarDays class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ tr("home.preview_1_title", "Utwórz podróż") }}</div>
                        <div class="text-sm text-white/80">
                          {{ tr("home.preview_1_desc", "Nazwa, daty i gotowe miejsce do planowania.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <MapPin class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ tr("home.preview_2_title", "Dodawaj miejsca i głosuj") }}</div>
                        <div class="text-sm text-white/80">
                          {{ tr("home.preview_2_desc", "Zbieraj propozycje i ustal priorytety razem.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <Users class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ tr("home.preview_3_title", "Zaproś uczestników") }}</div>
                        <div class="text-sm text-white/80">
                          {{ tr("home.preview_3_desc", "Owner/editor, zaproszenia i zarządzanie zespołem.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <SlidersHorizontal class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ tr("home.preview_4_title", "Ustaw preferencje") }}</div>
                        <div class="text-sm text-white/80">
                          {{ tr("home.preview_4_desc", "Dopasuj plan do stylu podróży grupy.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <Route class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">{{ tr("home.preview_5_title", "Zbuduj plan dnia") }}</div>
                        <div class="text-sm text-white/80">
                          {{ tr("home.preview_5_desc", "Porządek w aktywnościach i czytelny harmonogram.") }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-5 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80">
                  {{ tr("home.preview_footer", "Wszystko w jednym miejscu: miejsca, zespół, preferencje i plan.") }}
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-14 sm:py-16">
      <Transition
          appear
          enter-active-class="transition duration-500 ease-out delay-150"
          enter-from-class="opacity-0 translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
      >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
          <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 hover:shadow-md transition">
            <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
              <MapPin class="h-5 w-5" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">
              {{ tr("home.feature_plan", "Miejsca") }}
            </h3>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("home.feature_plan_desc", "Dodawaj miejsca, zarządzaj listą i wybieraj najlepsze opcje.") }}
            </p>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 hover:shadow-md transition">
            <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
              <Users class="h-5 w-5" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">
              {{ tr("home.feature_team", "Zespół") }}
            </h3>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("home.feature_team_desc", "Zaproszenia, role i praca zespołowa bez chaosu na czacie.") }}
            </p>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 hover:shadow-md transition">
            <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
              <ThumbsUp class="h-5 w-5" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">
              {{ tr("home.feature_vote", "Decyzje") }}
            </h3>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("home.feature_vote_desc", "Głosujcie na miejsca i łatwo ustalcie priorytety.") }}
            </p>
          </div>
        </div>
      </Transition>
    </section>
  </div>
</template>
