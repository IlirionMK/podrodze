<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"
import { X, ThumbsUp, ThumbsDown, Pin, Trash2 } from "lucide-vue-next"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tripPlace: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  maxWidthClass: { type: String, default: "max-w-md" },
  closeOnBackdrop: { type: Boolean, default: true },
})

const emit = defineEmits([
  "update:modelValue",
  "like",
  "dislike",
  "toggle-fixed",
  "remove",
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
  if (props.busy) return
  emit("update:modelValue", false)
  emit("close")
}

function onBackdropClick() {
  if (!props.closeOnBackdrop) return
  close()
}

function like() {
  if (props.busy) return
  emit("like")
}

function dislike() {
  if (props.busy) return
  emit("dislike")
}

function toggleFixed() {
  if (props.busy) return
  emit("toggle-fixed")
}

function remove() {
  if (props.busy) return
  emit("remove")
}

const btnBase =
    "w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnGlass =
    btnBase + " bg-white/10 border border-white/15 hover:bg-white/15 text-white"
const btnDanger =
    btnBase + " bg-red-500/15 border border-red-400/30 hover:bg-red-500/20 text-red-100"

const pillBase =
    "inline-flex items-center rounded-full border px-3 py-1 text-xs"
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
            class="relative w-full rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white"
            :class="maxWidthClass"
        >
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h2 class="text-xl font-semibold drop-shadow truncate">
                  {{ title }}
                </h2>

                <p class="mt-1 text-sm text-white/70">
                  {{ tr("trip.place.modal.subtitle", "Manage votes and pin this place for the trip.") }}
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
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button type="button" :class="btnGlass" :disabled="busy" @click="like">
                <ThumbsUp class="h-4 w-4" />
                {{ tr("trip.place.actions.like", "Like") }}
              </button>

              <button type="button" :class="btnGlass" :disabled="busy" @click="dislike">
                <ThumbsDown class="h-4 w-4" />
                {{ tr("trip.place.actions.dislike", "Dislike") }}
              </button>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3">
              <button type="button" :class="btnGlass" :disabled="busy" @click="toggleFixed">
                <Pin class="h-4 w-4" />
                {{ isFixed ? tr("trip.place.actions.unfix", "Unfix") : tr("trip.place.actions.fix", "Fix") }}
              </button>

              <button type="button" :class="btnDanger" :disabled="busy" @click="remove">
                <Trash2 class="h-4 w-4" />
                {{ tr("actions.remove", "Remove") }}
              </button>
            </div>

            <div class="mt-5 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-xs text-white/70">
              {{ tr("trip.place.modal.hint", "Like = score 5, Dislike = score 1. This can be extended to a 1–5 rating later.") }}
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
