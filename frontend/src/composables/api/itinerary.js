import api from "@/composables/api/api.js"

export function fetchTripItinerary(tripId) {
    return api.get(`/trips/${tripId}/itinerary/generate`)
}

export function fetchTripItineraryFull(tripId) {
    return api.post(`/trips/${tripId}/itinerary/generate-full`)
}
