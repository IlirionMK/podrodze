import api from "@/composables/api/api.js"

export function fetchTripPlaces(tripId) {
    return api.get(`/trips/${tripId}/places`)
}

export function createTripPlace(tripId, payload) {
    return api.post(`/trips/${tripId}/places`, payload)
}

export function voteTripPlace(tripId, placeId) {
    return api.post(`/trips/${tripId}/places/${placeId}/vote`)
}

export function updateTripPlace(tripId, placeId, payload) {
    return api.patch(`/trips/${tripId}/places/${placeId}`, payload)
}

export function deleteTripPlace(tripId, placeId) {
    return api.delete(`/trips/${tripId}/places/${placeId}`)
}
