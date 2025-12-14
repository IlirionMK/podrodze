<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  tripPlace: { type: Object, default: null },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits([
  "update:modelValue",
  "vote",
  "toggle-fixed",
  "remove",
])

const { t } = useI18n()

const place = computed(() => props.tripPlace?.place || props.tripPlace || null)

const title = computed(() => place.value?.name || "—")
const category = computed(() => place.value?.category_slug || "—")
const rating = computed(() => {
  const v = props.tripPlace?.rating ?? place.value?.rating
  return v == null ? null : Number(v)
})

const address = computed(() => props.tripPlace?.meta?.address || place.value?.meta?.address || null)

const votesCount = computed(() => {
  const v =
      props.tripPlace?.votes_count ??
      props.tripPlace?.votes ??
      props.tripPlace?.meta?.votes ??
      null
  return v == null ? null : Number(v)
})

const isFixed = computed(() => {
  const v =
      props.tripPlace?.is_fixed ??
      props.tripPlace?.fixed ??
      props.tripPlace?.is_mandatory ??
      false
  return Boolean(v)
})

function close() {
  emit("update:modelValue", false)
}

function onBackdrop(e) {
  if (e.target === e.currentTarget) close()
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-50" @mousedown="onBackdrop">
    <div class="absolute inset-0 bg-black/40"></div>

    <aside
        class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl border-l flex flex-col"
        role="dialog"
        aria-modal="true"
    >
      <header class="p-5 border-b">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-xl font-semibold truncate">{{ title }}</h3>
            <div class="mt-1 text-sm text-gray-600 flex flex-wrap gap-2">
              <span class="px-2 py-1 rounded-lg bg-gray-100 border">{{ category }}</span>
              <span v-if="rating !== null" class="px-2 py-1 rounded-lg bg-gray-100 border">
                ★ {{ rating.toFixed(1) }}
              </span>
              <span v-if="votesCount !== null" class="px-2 py-1 rounded-lg bg-gray-100 border">
                {{ t("trip.place.votes") || "Votes" }}: {{ votesCount }}
              </span>
              <span v-if="isFixed" class="px-2 py-1 rounded-lg bg-amber-100 border border-amber-200 text-amber-800">
                {{ t("trip.place.fixed") || "Fixed" }}
              </span>
            </div>
          </div>

          <button
              type="button"
              class="px-3 py-2 rounded-xl border hover:bg-gray-50 transition"
              @click="close"
          >
            {{ t("actions.close") || "Close" }}
          </button>
        </div>

        <p v-if="address" class="mt-3 text-sm text-gray-600 leading-relaxed">
          {{ address }}
        </p>
      </header>

      <div class="p-5 flex-1 overflow-auto">
        <div class="grid grid-cols-1 gap-3">
          <button
              type="button"
              class="w-full px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition disabled:opacity-60"
              :disabled="busy"
              @click="$emit('vote')"
          >
            {{ t("trip.place.vote") || "Vote" }}
          </button>

          <button
              type="button"
              class="w-full px-4 py-2.5 rounded-xl border hover:bg-gray-50 transition disabled:opacity-60"
              :disabled="busy"
              @click="$emit('toggle-fixed')"
          >
            {{ isFixed ? (t("trip.place.unfix") || "Unfix") : (t("trip.place.fix") || "Mark as fixed") }}
          </button>

          <button
              type="button"
              class="w-full px-4 py-2.5 rounded-xl border border-red-200 text-red-700 hover:bg-red-50 transition disabled:opacity-60"
              :disabled="busy"
              @click="$emit('remove')"
          >
            {{ t("actions.remove") || "Remove from trip" }}
          </button>
        </div>

        <div class="mt-6 p-4 rounded-xl border bg-gray-50 text-sm text-gray-700">
          {{ t("trip.place.details_hint") || "More details (opening hours, website, notes) can be added here later." }}
        </div>
      </div>
    </aside>
  </div>
</template>
