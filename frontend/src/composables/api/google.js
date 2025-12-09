import { api } from "./trips.js"

export const fetchGoogleMapsKey = () => {
    return api.get("/google/maps-key")
}
