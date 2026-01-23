import { ref, computed } from "vue"
import api from "@/composables/api/api"

const token = ref(localStorage.getItem("token"))
const user = ref(localStorage.getItem("user") ? JSON.parse(localStorage.getItem("user")) : null)

export function useAuth() {
    function setAuth(authUser, authToken) {
        token.value = authToken
        user.value = authUser
        localStorage.setItem("token", authToken)
        localStorage.setItem("user", JSON.stringify(authUser))
    }

    function clearAuth() {
        token.value = null
        user.value = null
        localStorage.removeItem("token")
        localStorage.removeItem("user")
        localStorage.removeItem("intended")
    }

    async function logout(router) {
        try {
            await api.post("/logout")
        } catch (e) {
        } finally {
            clearAuth()
            router?.replace({ name: "guest.home" })
        }
    }

    function forceLogout(router) {
        clearAuth()
        router?.replace({ name: "guest.home" })
    }

    return {
        token,
        user,
        setAuth,
        clearAuth,
        logout,
        forceLogout,
        isAuthenticated: computed(() => !!token.value),
        isAdmin: computed(() => user.value?.role === "admin"),
    }
}
