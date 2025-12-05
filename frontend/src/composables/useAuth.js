import { ref, computed } from "vue"

const token = ref(localStorage.getItem("token") || null)
const user = ref(JSON.parse(localStorage.getItem("user")) || null)

export function useAuth() {

    function setToken(newToken) {
        token.value = newToken
        localStorage.setItem("token", newToken)
    }

    function setUser(newUser) {
        user.value = newUser
        localStorage.setItem("user", JSON.stringify(newUser))
    }

    function clearAuth() {
        token.value = null
        user.value = null
        localStorage.removeItem("token")
        localStorage.removeItem("user")
        localStorage.removeItem("intended")
    }

    function logout(router) {
        clearAuth()

        if (router) {
            router.push({ name: "guest.home" })
        }
    }

    return {
        token,
        user,
        setToken,
        setUser,
        clearAuth,
        logout,

        isAuthenticated: computed(() => !!token.value),
        isAdmin: computed(() => user.value?.role === "admin")
    }
}
