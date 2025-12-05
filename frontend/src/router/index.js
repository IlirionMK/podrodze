import { createRouter, createWebHistory } from "vue-router"

// Layouts
import GuestLayout from "../layouts/GuestLayout.vue"
import UserLayout from "../layouts/UserLayout.vue"
import AdminLayout from "../layouts/AdminLayout.vue"

// Pages
import HomePage from "../pages/Home.vue"
import AuthLoginPage from "../pages/auth/Login.vue"
import AuthRegisterPage from "../pages/auth/Register.vue"

const router = createRouter({
    history: createWebHistory(),
    routes: [
        // -------------------------
        // GUEST
        // -------------------------
        {
            path: "/",
            component: GuestLayout,
            meta: { guest: true },
            children: [
                { path: "", name: "guest.home", component: HomePage },
                { path: "login", name: "auth.login", component: AuthLoginPage },
                { path: "register", name: "auth.register", component: AuthRegisterPage }
            ]
        },

        // -------------------------
        // AUTH USERS
        // -------------------------
        {
            path: "/app",
            component: UserLayout,
            meta: { auth: true },
            children: [
                { path: "", redirect: { name: "app.home" } },
                { path: "home", name: "app.home", component: HomePage },
                {
                    path: "profile",
                    name: "app.profile",
                    component: () => import("../pages/app/Profile.vue")
                }
            ]
        },

        // -------------------------
        // ADMIN
        // -------------------------
        {
            path: "/admin",
            component: AdminLayout,
            meta: { admin: true },
            children: [
                {
                    path: "",
                    name: "admin.dashboard",
                    component: () => import("../pages/admin/Dashboard.vue")
                }
            ]
        },

        // -------------------------
        // ERRORS
        // -------------------------
        {
            path: "/403",
            name: "error.403",
            component: () => import("../pages/errors/Forbidden.vue")
        },
        {
            path: "/:pathMatch(.*)*",
            name: "error.404",
            component: () => import("../pages/errors/NotFound.vue")
        }
    ]
})


// -------------------------------------
// GLOBAL GUARD
// -------------------------------------
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("token")
    const role = localStorage.getItem("role")
    const isAuthenticated = !!token
    const isAdmin = role === "admin"

    // Guest-only routes
    if (to.meta.guest && isAuthenticated) {
        if (isAdmin) return next({ name: "admin.dashboard" })
        return next({ name: "app.home" })
    }

    // Auth-only routes
    if (to.meta.auth && !isAuthenticated) {
        localStorage.setItem("intended", to.fullPath)
        return next({ name: "auth.login" })
    }

    // Admin-only routes
    if (to.meta.admin) {
        if (!isAuthenticated) return next({ name: "auth.login" })
        if (!isAdmin) return next({ name: "error.403" })
    }

    next()
})

export default router
