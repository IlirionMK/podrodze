<script setup>
import { useI18n } from "vue-i18n"
import { Shield, Trash2, Mail, Clock, Info } from "lucide-vue-next"

const { t, te } = useI18n({ useScope: "global" })

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const lastUpdated = "2026-01-22"
const contactEmail = "podrodzetest@gmail.com"

const pill =
    "inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/15 px-3 py-1 text-xs text-white/85 backdrop-blur"

const card =
    "rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl p-5 sm:p-6 text-white"

const lightCard =
    "rounded-2xl border border-gray-200 bg-white shadow-sm p-6 hover:shadow-md transition"
</script>

<template>
  <div class="w-full overflow-hidden">
    <!-- Hero / Header -->
    <section class="relative">
      <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-700"></div>

      <div class="absolute inset-0 opacity-45">
        <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute top-24 -right-24 h-96 w-96 rounded-full bg-white/15 blur-3xl"></div>
      </div>

      <div class="relative max-w-6xl mx-auto px-4 py-14 sm:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
          <div class="lg:col-span-7 text-white">
            <Transition
                appear
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
            >
              <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur">
                  <Shield class="h-4 w-4" />
                  {{ tr("legal.data_deletion.badge", "Your privacy and data control") }}
                </div>

                <h1 class="mt-5 text-3xl sm:text-5xl font-bold leading-tight drop-shadow">
                  {{ tr("legal.data_deletion.title", "User Data Deletion Instructions") }}
                </h1>

                <p class="mt-4 text-base sm:text-lg text-white/90 max-w-2xl">
                  {{ tr("legal.data_deletion.subtitle", "Learn how to request deletion of your personal data associated with the PoDrodze application.") }}
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                  <span :class="pill">
                    <Trash2 class="h-4 w-4" />
                    {{ tr("legal.data_deletion.pill_1", "Delete request") }}
                  </span>
                  <span :class="pill">
                    <Mail class="h-4 w-4" />
                    {{ tr("legal.data_deletion.pill_2", "Email verification") }}
                  </span>
                  <span :class="pill">
                    <Clock class="h-4 w-4" />
                    {{ tr("legal.data_deletion.pill_3", "Up to 30 days") }}
                  </span>
                </div>

                <p class="mt-6 text-sm text-white/85 max-w-2xl">
                  {{ tr("legal.data_deletion.student_note", "This is a student project developed for an academic course. The service is provided for educational purposes.") }}
                </p>
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
              <div :class="card">
                <div class="text-sm font-semibold">
                  {{ tr("legal.data_deletion.quick_title", "Quick steps") }}
                </div>

                <div class="mt-4 space-y-3">
                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <Mail class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">
                          {{ tr("legal.data_deletion.step_1_title", "Send an email request") }}
                        </div>
                        <div class="text-sm text-white/80">
                          {{ tr("legal.data_deletion.step_1_desc", "Email us with the subject line provided below.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <Info class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">
                          {{ tr("legal.data_deletion.step_2_title", "Include account details") }}
                        </div>
                        <div class="text-sm text-white/80">
                          {{ tr("legal.data_deletion.step_2_desc", "Provide your PoDrodze email or other identifier so we can locate your account.") }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                        <Trash2 class="h-5 w-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold">
                          {{ tr("legal.data_deletion.step_3_title", "We confirm deletion") }}
                        </div>
                        <div class="text-sm text-white/80">
                          {{ tr("legal.data_deletion.step_3_desc", "We will confirm completion via email once the request is processed.") }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-5 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/80">
                  {{ tr("legal.data_deletion.footer_note", "If you used Facebook Login, your request can reference the same email used for Facebook.") }}
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <!-- Content -->
    <section class="max-w-6xl mx-auto px-4 py-12 sm:py-14">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-8 space-y-6">
          <div :class="lightCard">
            <div class="flex items-start gap-3">
              <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
                <Shield class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">
                  {{ tr("legal.data_deletion.section_what_title", "What data can be deleted") }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                  {{ tr("legal.data_deletion.section_what_desc", "We can delete data associated with your PoDrodze account, including provider identifiers (e.g., Facebook Login) and app content linked to your account.") }}
                </p>

                <ul class="mt-4 list-disc pl-5 text-sm text-gray-700 space-y-2">
                  <li>{{ tr("legal.data_deletion.what_1", "Account details (name, email, linked provider identifiers).") }}</li>
                  <li>{{ tr("legal.data_deletion.what_2", "Content created in the app that is linked to your account (e.g., trips, places, votes).") }}</li>
                  <li>{{ tr("legal.data_deletion.what_3", "Technical identifiers needed to handle authentication records, where applicable.") }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div :class="lightCard">
            <div class="flex items-start gap-3">
              <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
                <Mail class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">
                  {{ tr("legal.data_deletion.section_how_title", "How to request deletion") }}
                </h2>

                <div class="mt-3 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                  <div class="text-sm text-gray-700">
                    <div class="font-semibold text-gray-900">
                      {{ tr("legal.data_deletion.email_label", "Email") }}
                    </div>
                    <a class="underline break-all" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>

                    <div class="mt-3 font-semibold text-gray-900">
                      {{ tr("legal.data_deletion.subject_label", "Subject") }}
                    </div>
                    <div class="text-sm text-gray-700">
                      {{ tr("legal.data_deletion.subject_value", "PoDrodze – Data Deletion Request") }}
                    </div>

                    <div class="mt-3 font-semibold text-gray-900">
                      {{ tr("legal.data_deletion.include_label", "Include in your message") }}
                    </div>
                    <ul class="mt-2 list-disc pl-5 text-sm text-gray-700 space-y-1">
                      <li>{{ tr("legal.data_deletion.include_1", "Your email used in PoDrodze (or your Facebook email if you used Facebook Login).") }}</li>
                      <li>{{ tr("legal.data_deletion.include_2", "Your name or username used in the app (if available).") }}</li>
                      <li>{{ tr("legal.data_deletion.include_3", "Any additional details that help us identify the account (optional).") }}</li>
                    </ul>
                  </div>
                </div>

                <p class="mt-4 text-sm text-gray-600">
                  {{ tr("legal.data_deletion.manual_note", "Requests are verified to prevent unauthorized deletions. We may ask for additional confirmation if needed.") }}
                </p>
              </div>
            </div>
          </div>

          <div :class="lightCard">
            <div class="flex items-start gap-3">
              <div class="h-11 w-11 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow-lg">
                <Clock class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">
                  {{ tr("legal.data_deletion.section_time_title", "Processing time") }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                  {{ tr("legal.data_deletion.section_time_desc", "We process deletion requests as soon as possible, typically within 30 days, and confirm completion by email.") }}
                </p>
                <p class="mt-4 text-xs text-gray-500">
                  {{ tr("legal.data_deletion.last_updated", "Last updated") }}: {{ lastUpdated }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <aside class="lg:col-span-4">
          <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
            <h3 class="text-base font-semibold text-gray-900">
              {{ tr("legal.data_deletion.aside_title", "Notes") }}
            </h3>

            <div class="mt-4 space-y-3 text-sm text-gray-700">
              <div class="flex items-start gap-3">
                <Info class="h-5 w-5 mt-0.5" />
                <p>
                  {{ tr("legal.data_deletion.aside_1", "If you signed in via Facebook, you can reference the same email used for Facebook Login.") }}
                </p>
              </div>

              <div class="flex items-start gap-3">
                <Shield class="h-5 w-5 mt-0.5" />
                <p>
                  {{ tr("legal.data_deletion.aside_2", "This page exists to meet platform requirements and to inform users how to request data deletion.") }}
                </p>
              </div>

              <div class="flex items-start gap-3">
                <Trash2 class="h-5 w-5 mt-0.5" />
                <p>
                  {{ tr("legal.data_deletion.aside_3", "You may also request deletion of the entire account and related app data.") }}
                </p>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>
