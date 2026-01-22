<script setup>
import { computed, ref } from "vue"
import { useI18n } from "vue-i18n"
import { Shield, Database, Lock, Settings, Cookie, Mail, BadgeInfo } from "lucide-vue-next"

const { t, te } = useI18n({ useScope: "global" })

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const lastUpdated = "2026-01-22"
const contactEmail = "podrodzetest@gmail.com"

const pillBase =
    "inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold border transition"
const pillOnDark = "bg-white/10 border-white/15 text-white/90 hover:bg-white/15"

const cardDark =
    "rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl p-5 sm:p-6 text-white"
const cardLight =
    "rounded-2xl border border-gray-200 bg-white shadow-sm p-6"

const btnTab =
    "inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold border transition"
const btnTabIdle = "border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnTabActive =
    "border-transparent text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow"

const sections = [
  {
    id: "collect",
    icon: Database,
    title: () => tr("legal.privacy.section_data_title", "Data we collect"),
    lead: () => tr("legal.privacy.section_data_desc", "We collect only the data necessary to provide app functionality."),
    bullets: () => [
      tr("legal.privacy.data_1", "Account information."),
      tr("legal.privacy.data_2", "App content."),
      tr("legal.privacy.data_3", "Technical data."),
    ],
  },
  {
    id: "use",
    icon: Settings,
    title: () => tr("legal.privacy.section_use_title", "How we use data"),
    lead: () => tr("legal.privacy.section_use_desc", "We use data to provide authentication and core features."),
    bullets: () => [
      tr("legal.privacy.use_1", "To authenticate users."),
      tr("legal.privacy.use_2", "To enable trip planning features."),
      tr("legal.privacy.use_3", "To improve reliability and security."),
      tr("legal.privacy.no_sell", "We do not sell personal data."),
    ],
  },
  {
    id: "cookies",
    icon: Cookie,
    title: () => tr("legal.privacy.section_cookies_title", "Cookies and local storage"),
    lead: () => tr("legal.privacy.section_cookies_desc", "We use minimal storage required to keep you signed in."),
    bullets: () => [
      tr("legal.privacy.cookies_1", "Authentication tokens."),
      tr("legal.privacy.cookies_2", "Optional preferences."),
      tr("legal.privacy.no_sell", "We do not sell personal data."),
    ],
  },
  {
    id: "rights",
    icon: Lock,
    title: () => tr("legal.privacy.section_rights_title", "Your rights and choices"),
    lead: () => tr("legal.privacy.section_rights_desc", "You can access, update, and delete your data."),
    bullets: () => [
      tr("legal.privacy.rights_1", "Access and update your profile data."),
      tr("legal.privacy.rights_2", "Change password and manage sessions."),
      tr("legal.privacy.rights_3", "Request deletion or delete the account."),
      tr("legal.privacy.deletion_note", "See Data Deletion Instructions."),
    ],
  },
]

const active = ref(sections[0].id)
const activeSection = computed(() => sections.find((s) => s.id === active.value) || sections[0])

function setActive(id) {
  active.value = id
}
</script>

<template>
  <div class="w-full overflow-hidden">
    <!-- Hero -->
    <section class="relative">
      <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-700"></div>

      <div class="absolute inset-0 opacity-45">
        <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute top-24 -right-24 h-96 w-96 rounded-full bg-white/15 blur-3xl"></div>
      </div>

      <div class="relative max-w-6xl mx-auto px-4 py-14 sm:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
          <!-- Left -->
          <div class="lg:col-span-7 text-white">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur">
              <Shield class="h-4 w-4 shrink-0" />
              {{ tr("legal.privacy.badge", "Privacy & transparency") }}
            </div>

            <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
              {{ tr("legal.privacy.title", "Privacy Policy") }}
            </h1>

            <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
              {{ tr("legal.privacy.subtitle", "This document explains what data we collect, why we collect it, and how you can control it in PoDrodze.") }}
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
              <span :class="[pillBase, pillOnDark]">
                <Database class="h-4 w-4 shrink-0" />
                {{ tr("legal.privacy.pill_1", "Data protection") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <Lock class="h-4 w-4 shrink-0" />
                {{ tr("legal.privacy.pill_2", "Minimal collection") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <BadgeInfo class="h-4 w-4 shrink-0" />
                {{ tr("legal.privacy.pill_3", "Web application") }}
              </span>
            </div>
          </div>

          <!-- Right Quick Summary -->
          <div class="lg:col-span-5">
            <div :class="cardDark">
              <div class="text-sm font-semibold">
                {{ tr("legal.privacy.quick_title", "Quick summary") }}
              </div>

              <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center">
                      <Database class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.privacy.quick_1_title", "What we collect") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.privacy.quick_1_desc", "Account data and app content linked to your account.") }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center">
                      <Settings class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.privacy.quick_2_title", "How it is used") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.privacy.quick_2_desc", "To provide authentication and core features like trip planning.") }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center">
                      <Lock class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.privacy.quick_3_title", "Your control") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.privacy.quick_3_desc", "You can update profile data, change password, and request deletion.") }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-2 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80">
                  {{ tr("legal.privacy.footer_note", "For questions, contact us by email using the address below.") }}
                  <div class="mt-2 flex items-center gap-2">
                    <Mail class="h-4 w-4 shrink-0" />
                    <a class="underline break-all" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /Right -->
        </div>
      </div>
    </section>

    <!-- Content -->
    <section class="max-w-6xl mx-auto px-4 py-12 sm:py-14">
      <div :class="cardLight">
        <div class="flex items-start gap-3">
          <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
            <Shield class="h-5 w-5 shrink-0" />
          </div>

          <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">
              {{ tr("legal.privacy.details_title", "Details") }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("legal.privacy.details_subtitle", "Select a section to view more information.") }}
            </p>
          </div>
        </div>

        <!-- Tabs -->
        <div class="mt-5 flex flex-wrap gap-2">
          <button
              v-for="s in sections"
              :key="s.id"
              type="button"
              :class="[btnTab, active === s.id ? btnTabActive : btnTabIdle]"
              @click="setActive(s.id)"
          >
            <component :is="s.icon" class="h-4 w-4 shrink-0" />
            {{ s.title() }}
          </button>
        </div>

        <!-- Animated content -->
        <Transition
            mode="out-in"
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
          <div :key="active" class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-5 sm:p-6">
            <div class="flex items-start gap-3">
              <div class="h-10 w-10 shrink-0 rounded-2xl bg-white flex items-center justify-center border border-gray-200 text-gray-900">
                <component :is="activeSection.icon" class="h-5 w-5 shrink-0" />
              </div>

              <div class="min-w-0">
                <div class="text-base font-semibold text-gray-900">
                  {{ activeSection.title() }}
                </div>
                <div class="mt-1 text-sm text-gray-700">
                  {{ activeSection.lead() }}
                </div>

                <ul class="mt-4 list-disc pl-5 text-sm text-gray-700 space-y-2">
                  <li v-for="(b, idx) in activeSection.bullets()" :key="idx">
                    {{ b }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </Transition>

        <div class="mt-6 text-xs text-gray-500 flex flex-wrap gap-2 items-center">
          <span class="inline-flex items-center gap-2">
            {{ tr("legal.privacy.last_updated", "Last updated") }}:
            <span class="font-semibold text-gray-700">{{ lastUpdated }}</span>
          </span>
        </div>
      </div>
    </section>
  </div>
</template>
