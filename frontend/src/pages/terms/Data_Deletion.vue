<script setup>
import { computed, ref } from "vue"
import { useI18n } from "vue-i18n"
import { Shield, Trash2, Mail, Clock, Info } from "lucide-vue-next"

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
const cardLight = "rounded-2xl border border-gray-200 bg-white shadow-sm p-6"

const btnTab =
    "inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold border transition"
const btnTabIdle = "border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnTabActive =
    "border-transparent text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow"

const sections = [
  {
    id: "what",
    icon: Shield,
    title: () =>
        tr("legal.data_deletion.section_what_title", "What data can be deleted"),
    lead: () =>
        tr(
            "legal.data_deletion.section_what_desc",
            "We can delete data associated with your PoDrodze account."
        ),
    bullets: () => [
      tr(
          "legal.data_deletion.what_1",
          "Account details (name, email, linked provider identifiers)."
      ),
      tr(
          "legal.data_deletion.what_2",
          "Content created in the app linked to your account (e.g., trips, places, votes)."
      ),
      tr(
          "legal.data_deletion.what_3",
          "Technical identifiers needed to handle authentication records, where applicable."
      ),
    ],
  },
  {
    id: "how",
    icon: Mail,
    title: () =>
        tr("legal.data_deletion.section_how_title", "How to request deletion"),
    lead: () =>
        tr(
            "legal.data_deletion.section_how_desc",
            "Send a request by email using the details below."
        ),
    bullets: () => [],
  },
  {
    id: "time",
    icon: Clock,
    title: () => tr("legal.data_deletion.section_time_title", "Processing time"),
    lead: () =>
        tr(
            "legal.data_deletion.section_time_desc",
            "We process deletion requests as soon as possible, typically within 30 days."
        ),
    bullets: () => [],
  },
]

const active = ref(sections[0].id)
const activeSection = computed(
    () => sections.find((s) => s.id === active.value) || sections[0]
)

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
            <div
                class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur"
            >
              <Trash2 class="h-4 w-4 shrink-0" />
              {{ tr("legal.data_deletion.badge", "Your privacy and data control") }}
            </div>

            <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
              {{ tr("legal.data_deletion.title", "User Data Deletion Instructions") }}
            </h1>

            <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
              {{
                tr(
                    "legal.data_deletion.subtitle",
                    "Learn how to request deletion of your personal data associated with the PoDrodze application."
                )
              }}
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
              <span :class="[pillBase, pillOnDark]">
                <Mail class="h-4 w-4 shrink-0" />
                {{ tr("legal.data_deletion.pill_1", "Email request") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <Info class="h-4 w-4 shrink-0" />
                {{ tr("legal.data_deletion.pill_2", "Account details") }}
              </span>
              <span :class="[pillBase, pillOnDark]">
                <Clock class="h-4 w-4 shrink-0" />
                {{ tr("legal.data_deletion.pill_3", "Up to 30 days") }}
              </span>
            </div>
          </div>

          <!-- Right quick steps -->
          <div class="lg:col-span-5">
            <div :class="cardDark">
              <div class="text-sm font-semibold">
                {{ tr("legal.data_deletion.quick_title", "Quick steps") }}
              </div>

              <div class="mt-4 space-y-3">
                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div
                        class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center"
                    >
                      <Mail class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.data_deletion.step_1_title", "Send an email request") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{
                          tr(
                              "legal.data_deletion.step_1_desc",
                              "Email us with the subject line provided below."
                          )
                        }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div
                        class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center"
                    >
                      <Info class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.data_deletion.step_2_title", "Include account details") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{
                          tr(
                              "legal.data_deletion.step_2_desc",
                              "Provide your PoDrodze email or other identifier so we can locate your account."
                          )
                        }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                  <div class="flex items-start gap-3">
                    <div
                        class="h-10 w-10 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center"
                    >
                      <Trash2 class="h-5 w-5 shrink-0" />
                    </div>
                    <div class="min-w-0">
                      <div class="font-semibold">
                        {{ tr("legal.data_deletion.step_3_title", "We confirm deletion") }}
                      </div>
                      <div class="text-sm text-white/80">
                        {{
                          tr(
                              "legal.data_deletion.step_3_desc",
                              "We will confirm completion via email once the request is processed."
                          )
                        }}
                      </div>
                    </div>
                  </div>
                </div>

                <div
                    class="mt-2 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80"
                >
                  {{
                    tr(
                        "legal.data_deletion.footer_note",
                        "If you used Facebook Login, your request can reference the same email used for Facebook."
                    )
                  }}
                </div>
              </div>
            </div>
          </div>
          <!-- /Right -->
        </div>
      </div>
    </section>

    <!-- Details (tabs) -->
    <section class="max-w-6xl mx-auto px-4 py-12 sm:py-14">
      <div :class="cardLight">
        <div class="flex items-start gap-3">
          <div
              class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg"
          >
            <Trash2 class="h-5 w-5 shrink-0" />
          </div>

          <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">
              {{ tr("legal.data_deletion.details_title", "Details") }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              {{ tr("legal.data_deletion.details_subtitle", "Select a section to view more information.") }}
            </p>
          </div>
        </div>

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
              <div
                  class="h-10 w-10 shrink-0 rounded-2xl bg-white flex items-center justify-center border border-gray-200 text-gray-900"
              >
                <component :is="activeSection.icon" class="h-5 w-5 shrink-0" />
              </div>

              <div class="min-w-0 flex-1">
                <div class="text-base font-semibold text-gray-900">
                  {{ activeSection.title() }}
                </div>

                <div class="mt-1 text-sm text-gray-700">
                  {{ activeSection.lead() }}
                </div>

                <!-- HOW section: flattened -->
                <div
                    v-if="active === 'how'"
                    class="mt-4 rounded-2xl border border-gray-200 overflow-hidden bg-white"
                >
                  <div class="divide-y divide-gray-200">
                    <div class="p-4 sm:p-5">
                      <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ tr("legal.data_deletion.email_label", "Email") }}
                      </div>
                      <a
                          class="mt-1 inline-flex items-center gap-2 underline break-all text-sm text-gray-900"
                          :href="`mailto:${contactEmail}`"
                      >
                        {{ contactEmail }}
                      </a>
                    </div>

                    <div class="p-4 sm:p-5">
                      <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ tr("legal.data_deletion.subject_label", "Subject") }}
                      </div>
                      <div class="mt-1 text-sm text-gray-900">
                        {{ tr("legal.data_deletion.subject_value", "PoDrodze – Data Deletion Request") }}
                      </div>
                    </div>

                    <div class="p-4 sm:p-5">
                      <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ tr("legal.data_deletion.include_label", "Include in your message") }}
                      </div>

                      <ul class="mt-3 space-y-2 text-sm text-gray-700">
                        <li class="flex gap-2">
                          <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                          <span>
                            {{
                              tr(
                                  "legal.data_deletion.include_1",
                                  "Your email used in PoDrodze (or your Facebook email if you used Facebook Login)."
                              )
                            }}
                          </span>
                        </li>

                        <li class="flex gap-2">
                          <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                          <span>
                            {{ tr("legal.data_deletion.include_2", "Your name or username used in the app (if available).") }}
                          </span>
                        </li>

                        <li class="flex gap-2">
                          <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                          <span>
                            {{
                              tr(
                                  "legal.data_deletion.include_3",
                                  "Any additional details that help us identify the account (optional)."
                              )
                            }}
                          </span>
                        </li>
                      </ul>

                      <p class="mt-4 text-sm text-gray-600">
                        {{
                          tr(
                              "legal.data_deletion.manual_note",
                              "Requests are verified to prevent unauthorized deletions. We may ask for additional confirmation if needed."
                          )
                        }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- WHAT section bullets -->
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

        <!-- Single "last updated" (only here) -->
        <div class="mt-6 text-xs text-gray-500 flex flex-wrap gap-2 items-center">
          <span class="inline-flex items-center gap-2">
            {{ tr("legal.data_deletion.last_updated", "Last updated") }}:
            <span class="font-semibold text-gray-700">{{ lastUpdated }}</span>
          </span>
        </div>
      </div>
    </section>
  </div>
</template>
