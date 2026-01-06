import api from "@/composables/api/api"

export function fetchPreferences() {
    return api.get("/preferences")
}

// preferences: object { [slug]: score(0..2) }
export function updateMyPreferences(preferences) {
    return api.put("/users/me/preferences", { preferences })
}
