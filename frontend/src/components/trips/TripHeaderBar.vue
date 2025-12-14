<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"

const props = defineProps({
  trip: { type: Object, required: true },
  stats: { type: Object, required: true },
  bannerImage: { type: String, required: true },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(["add-place", "invite-member", "open-preferences"])
const { t } = useI18n()

const dateRange = computed(() => {
  const s = props.formatDate(props.trip?.start_date)
  const e = props.formatDate(props.trip?.end_date)
  return `${s} — ${e}`
})
</script>

<template>
  <div class="relative h-56 md:h-80 w-full overflow-hidden">
    <img :src="bannerImage" alt="Trip banner" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-black/10"></div>

    <div class="absolute bottom-6 left-6 right-6 text-white drop-shadow">
      <div class="flex flex-col gap-3">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h1 class="text-3xl md:text-4xl font-bold leading-tight truncate">
              {{ trip.name }}
            </h1>
            <div class="text-sm opacity-90 flex flex-wrap gap-2 mt-2">
              <span class="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                {{ dateRange }}
              </span>
              <span class="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                {{ t("trip.stats.places") }}: <span class="font-semibold">{{ stats.places }}</span>
              </span>
              <span class="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                {{ t("trip.stats.members") }}: <span class="font-semibold">{{ stats.members }}</span>
              </span>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 shrink-0">
            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition"
                @click="$emit('add-place')"
            >
              {{ t("trip.view.add_place") }}
            </button>

            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-white/10 border border-white/20 hover:bg-white/15 transition"
                @click="$emit('invite-member')"
            >
              {{ t("trip.view.add_member") }}
            </button>

            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-white/10 border border-white/20 hover:bg-white/15 transition"
                @click="$emit('open-preferences')"
            >
              {{ t("trip.tabs.preferences") }}
            </button>
          </div>
        </div>

        <p v-if="trip.description" class="text-base md:text-lg opacity-90 max-w-3xl">
          {{ trip.description }}
        </p>
      </div>
    </div>
  </div>
</template>
