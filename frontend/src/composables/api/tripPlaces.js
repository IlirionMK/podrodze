import api from "./api"

export function fetchTripPlaces(tripId) {
    return api.get(`/trips/${tripId}/places`)
}

export function createTripPlace(tripId, payload) {
    return api.post(`/trips/${tripId}/places`, payload)
}
