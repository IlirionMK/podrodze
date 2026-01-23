<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"
import { X, Pin, Trash2, MapPin, Star } from "lucide-vue-next"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tripPlace: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  maxWidthClass: { type: String, default: "max-w-lg" },
  closeOnBackdrop: { type: Boolean, default: true },

  rating: { type: [Number, null], default: null },
  isStart: { type: Boolean, default: false },
})

const emit = defineEmits([
  "update:modelValue",
  "rate",
  "toggle-fixed",
  "remove",
  "set-start",
  "open",
  "close",
])

const { t, te } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const place = computed(() => props.tripPlace?.place || props.tripPlace || null)

const title = computed(() => place.value?.name || "—")
const category = computed(() => place.value?.category_slug || "—")

const ratingValue = computed(() => {
  const fromProp = props.rating
  if (fromProp != null) return clampRating(fromProp)

  const v =
      props.tripPlace?.rating ??
      props.tripPlace?.user_rating ??
      props.tripPlace?.meta?.rating ??
      props.tripPlace?.meta?.user_rating ??
      props.tripPlace?.score ??
      null

  return v == null ? null : clampRating(v)
})

const votesCount = computed(() => {
  const v =
      props.tripPlace?.votes_count ??
      props.tripPlace?.votes ??
      props.tripPlace?.meta?.votes ??
      props.tripPlace?.meta?.votes_count ??
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

const isStartValue = computed(() => {
  if (props.isStart) return true
  const v = props.tripPlace?.is_start ?? props.tripPlace?.meta?.is_start ?? false
  return Boolean(v)
})

function clampRating(v) {
  const n = Number(v)
  if (!Number.isFinite(n)) return null
  const r = Math.round(n)
  if (r < 1) return 1
  if (r > 5) return 5
  return r
}

function close() {
  if (props.busy) return
  emit("update:modelValue", false)
  emit("close")
}

function onBackdropClick() {
  if (!props.closeOnBackdrop) return
  close()
}

function setRating(v) {
  if (props.busy) return
  const n = Number(v)
  if (!Number.isFinite(n) || n < 1 || n > 5) return
  emit("rate", n)
}

function toggleFixed() {
  if (props.busy) return
  emit("toggle-fixed")
}

function remove() {
  if (props.busy) return
  emit("remove")
}

function setStart() {
  if (props.busy) return
  emit("set-start")
}

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnGlass =
    btnBase + " bg-white/10 border border-white/15 hover:bg-white/15 text-white"
const btnDanger =
    btnBase + " bg-red-500/15 border border-red-400/30 hover:bg-red-500/20 text-red-100"

const pillBase = "inline-flex items-center rounded-full border px-3 py-1 text-xs"

function starClass(active) {
  return active ? "text-yellow-300" : "text-white/40"
}

function starFill(active) {
  return active ? "currentColor" : "none"
}
</script>

<template>
  <Teleport to="body">
    <Transition
        appear
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
      <div
          v-if="modelValue"
          class="fixed inset-0 z-50 flex items-center justify-center px-4"
          role="dialog"
          aria-modal="true"
      >
        <button class="absolute inset-0 bg-black/60" @click="onBackdropClick" aria-label="Close" />

        <div
            class="relative w-full rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden"
            :class="maxWidthClass"
        >
          <div class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h2 class="text-xl font-semibold drop-shadow truncate">
                  {{ title }}
                </h2>

                <p class="mt-1 text-sm text-white/70">
                  {{ tr("trip.place.modal.subtitle", "Rate, pin, or manage this place for the trip.") }}
                </p>
              </div>

              <button
                  type="button"
                  class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50 flex items-center justify-center"
                  @click="close"
                  :disabled="busy"
                  aria-label="Close"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
              <span :class="[pillBase, 'border-white/15 bg-black/20 text-white/90']">
                {{ tr("trip.place.modal.category", "Category") }}:
                <span class="ml-1 font-semibold">{{ category }}</span>
              </span>

              <span
                  v-if="votesCount !== null"
                  :class="[pillBase, 'border-white/15 bg-black/20 text-white/90']"
              >
                {{ tr("trip.place.votes", "Votes") }}:
                <span class="ml-1 font-semibold">{{ votesCount }}</span>
              </span>

              <span
                  v-if="isFixed"
                  :class="[pillBase, 'border-blue-300/30 bg-blue-500/15 text-blue-100']"
              >
                <Pin class="h-3.5 w-3.5 mr-1" />
                {{ tr("trip.place.modal.fixed", "Fixed") }}
              </span>

              <span
                  v-if="isStartValue"
                  :class="[pillBase, 'border-emerald-300/30 bg-emerald-500/15 text-emerald-100']"
              >
                <MapPin class="h-3.5 w-3.5 mr-1" />
                {{ tr("trip.place.modal.start", "Start") }}
              </span>
            </div>

            <div class="mt-6 rounded-2xl border border-white/10 bg-black/20 p-4">
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-medium text-white/90">
                  {{ tr("trip.place.modal.rating", "Your rating") }}
                </div>
                <div v-if="ratingValue != null" class="text-xs text-white/60">
                  {{ tr("trip.place.modal.rating_value", "Selected") }}:
                  <span class="font-semibold">{{ ratingValue }}</span>/5
                </div>
              </div>

              <div class="mt-3 grid grid-cols-5 gap-2">
                <button
                    v-for="i in 5"
                    :key="i"
                    type="button"
                    class="h-11 w-full rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition flex items-center justify-center disabled:opacity-50"
                    :disabled="busy"
                    :aria-label="tr('trip.place.modal.rate', 'Rate') + ' ' + i"
                    @click="setRating(i)"
                >
                  <Star
                      class="h-5 w-5"
                      :class="starClass((ratingValue ?? 0) >= i)"
                      :fill="starFill((ratingValue ?? 0) >= i)"
                  />
                </button>
              </div>

              <div class="mt-3 text-xs text-white/60">
                {{ tr("trip.place.modal.rating_hint", "Tap a star to vote 1–5. This is stored per trip place.") }}
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button type="button" :class="btnGlass" :disabled="busy" @click="setStart">
                <MapPin class="h-4 w-4" />
                {{
                  isStartValue
                      ? tr("trip.place.actions.start_selected", "Start selected")
                      : tr("trip.place.actions.set_start", "Set as start")
                }}
              </button>

              <button type="button" :class="btnGlass" :disabled="busy" @click="toggleFixed">
                <Pin class="h-4 w-4" />
                {{ isFixed ? tr("trip.place.actions.unfix", "Unfix") : tr("trip.place.actions.fix", "Fix") }}
              </button>
            </div>

            <div class="mt-3">
              <button type="button" :class="btnDanger + ' w-full'" :disabled="busy" @click="remove">
                <Trash2 class="h-4 w-4" />
                {{ tr("actions.remove", "Remove") }}
              </button>
            </div>

            <div class="mt-5 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/70">
              {{ tr("trip.place.modal.hint", "Fixed places are prioritized in the itinerary. Start point affects route generation.") }}
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
