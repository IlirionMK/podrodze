import api from "@/composables/api/api"

export const fetchGoogleMapsKey = () => {
    return api.get("/google/maps-key")
}
