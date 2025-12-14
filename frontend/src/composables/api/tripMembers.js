import api from "./api"

export function fetchTripMembers(tripId) {
    return api.get(`/trips/${tripId}/members`)
}
