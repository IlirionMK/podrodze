import api from "@/composables/api/api"

export function fetchUserTrips() {
    return api.get("/trips")
}

export function fetchTrip(id) {
    return api.get(`/trips/${id}`)
}

export function createTrip(payload) {
    return api.post("/trips", payload)
}