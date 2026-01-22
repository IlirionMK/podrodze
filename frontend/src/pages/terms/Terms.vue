<script setup>
import { computed, ref } from "vue"
import { useI18n } from "vue-i18n"
import {
  FileText,
  Shield,
  Lock,
  AlertTriangle,
  Globe,
  Mail,
  Scale,
} from "lucide-vue-next"

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
const btnTabIdle =
    "border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnTabActive =
    "border-transparent text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow"

const sections = [
  {
    id: "scope",
    icon: Globe,
    title: () => tr("legal.terms.section_scope_title", "Scope"),
    lead: () => tr("legal.terms.section_scope_desc", "These terms apply to your use of the PoDrodze web application."),
    bullets: () => [],
  },
  {
    id: "accounts",
    icon: Shield,
    title: () => tr("legal.terms.section_accounts_title", "Accounts and authentication"),
    lead: () => "",
    bullets: () => [
      tr("legal.terms.accounts_1", "You are responsible for maintaining the confidentiality of your account credentials."),
      tr("legal.terms.accounts_2", "OAuth providers (e.g., Facebook, Google) may be used to authenticate you."),
      tr("legal.terms.accounts_3", "We may suspend access in cases of abuse or security concerns."),
    ],
  },
  {
    id: "content",
    icon: FileText,
    title: () => tr("legal.terms.section_content_title", "User content"),
    lead: () => "",
    bullets: () => [
      tr("legal.terms.content_1", "You retain responsibility for the content you create in the app (e.g., trips, places, notes)."),
      tr("legal.terms.content_2", "Do not submit unlawful or abusive content."),
      tr("legal.terms.content_3", "We may remove content that violates these terms or applicable rules."),
    ],
  },
  {
    id: "disclaimer",
    icon: AlertTriangle,
    title: () => tr("legal.terms.section_disclaimer_title", "Disclaimer"),
    lead: () => tr("legal.terms.section_disclaimer_desc", "PoDrodze is provided on an 'as-is' basis."),
    bullets: () => [
      tr("legal.terms.section_disclaimer_desc_2", "We do not guarantee uninterrupted availability or correctness of generated routes."),
    ],
  },
  {
    id: "contact",
    icon: Mail,
    title: () => tr("legal.terms.section_contact_title", "Contact"),
    lead: () => tr("legal.terms.contact_email_label", "Email"),
    bullets: () => [],
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
              <FileText class="h-4 w-4 shrink-0" />
              {{ tr("legal.terms.badge", "Terms of Service") }}
            </div>

            <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
              {{ tr("legal.terms.title", "Terms and Conditions") }}
            </h1>

            <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
              {{ tr("legal.terms.subtitle", "These terms govern your use of the PoDrodze web application.") }}
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
              <span :class="[pillBase, pillOnDark]">
                <Scale class="h-4 w-4 shrink-0" />
                {{ tr("legal.terms.pill_1", "Use rules") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <Shield class="h-4 w-4 shrink-0" />
                {{ tr("legal.terms.pill_2", "Account security") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <Lock class="h-4 w-4 shrink-0" />
                {{ tr("legal.terms.pill_3", "Core rules") }}
              </span>
            </div>
          </div>

          <!-- Right quick summary -->
          <div class="lg:col-span-5">
            <div :class="cardDark">
              <div class="text-sm font-semibold">
                {{ tr("legal.terms.quick_title", "Quick overview") }}
              </div>

              <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center">
                      <Shield class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.terms.quick_1_title", "Use responsibly") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.terms.quick_1_desc", "Do not misuse the app or attempt unauthorized access.") }}
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
                        {{ tr("legal.terms.quick_2_title", "Account security") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.terms.quick_2_desc", "Keep your credentials secure and report issues if needed.") }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center">
                      <AlertTriangle class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.terms.quick_3_title", "Disclaimer") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{ tr("legal.terms.quick_3_desc", "Provided as-is, without guarantees of availability or accuracy.") }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-2 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80">
                  {{ tr("legal.terms.footer_note", "If you disagree with these terms, please discontinue use of the application.") }}
                </div>
              </div>
            </div>
          </div>
          <!-- /Right -->
        </div>
      </div>
    </section>

    <!-- Details (tabs like Privacy) -->
    <section class="max-w-6xl mx-auto px-4 py-12 sm:py-14">
      <div :class="cardLight">
        <div class="flex items-start gap-3">
          <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
            <FileText class="h-5 w-5 shrink-0" />
          </div>

          <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">
              {{ tr("legal.terms.details_title", "Details") }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("legal.terms.details_subtitle", "Select a section to view more information.") }}
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

        <!-- Content -->
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

                <div v-if="activeSection.lead()" class="mt-1 text-sm text-gray-700">
                  {{ activeSection.lead() }}
                </div>

                <!-- Special contact section -->
                <div v-if="active === 'contact'" class="mt-4 rounded-2xl border border-gray-200 bg-white p-4">
                  <div class="text-sm text-gray-700">
                    <div class="font-semibold text-gray-900">
                      {{ tr("legal.terms.contact_email_label", "Email") }}
                    </div>
                    <a class="underline break-all" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>
                  </div>
                </div>

                <ul
                    v-else-if="activeSection.bullets()?.length"
                    class="mt-4 list-disc pl-5 text-sm text-gray-700 space-y-2"
                >
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
            {{ tr("legal.terms.last_updated", "Last updated") }}:
            <span class="font-semibold text-gray-700">{{ lastUpdated }}</span>
          </span>
        </div>
      </div>
    </section>
  </div>
</template>
