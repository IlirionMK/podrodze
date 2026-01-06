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
        // Guest routes
        {
            path: "/",
            component: GuestLayout,
            meta: { guest: true },
            children: [
                { path: "", name: "guest.home", component: HomePage },
                { path: "login", name: "auth.login", component: AuthLoginPage },
                { path: "register", name: "auth.register", component: AuthRegisterPage },

                // Google OAuth callback route
                {
                    path: "auth/google",
                    name: "auth.google",
                    component: () => import("../pages/auth/GoogleCallback.vue"),
                    meta: { guest: true },
                },

                // Facebook OAuth callback route (redirect_uri = http://localhost:5173/auth/facebook/callback)
                {
                    path: "auth/facebook/callback",
                    name: "auth.facebook",
                    component: () => import("../pages/auth/FacebookCallback.vue"),
                    meta: { guest: true },
                },

                // Email verification (SPA)
                {
                    path: "auth/verify-email",
                    name: "auth.verify",
                    component: () => import("../pages/auth/VerifyEmail.vue"),
                    meta: { guest: true },
                },
            ],
        },

        // Authenticated user routes
        {
            path: "/app",
            component: UserLayout,
            meta: { auth: true },
            children: [
                { path: "", redirect: { name: "app.home" } },
                { path: "home", name: "app.home", component: HomePage },

                // Trips module
                {
                    path: "trips",
                    name: "app.trips",
                    component: () => import("../pages/app/trips/TripsList.vue"),
                },
                {
                    path: "trips/create",
                    name: "app.trips.create",
                    component: () => import("../pages/app/trips/TripCreate.vue"),
                },
                {
                    path: "trips/:id",
                    name: "app.trips.show",
                    component: () => import("../pages/app/trips/TripDetail.vue"),
                    props: true,
                },

                {
                    path: "profile",
                    name: "app.profile",
                    component: () => import("../pages/app/Profile.vue"),
                },
            ],
        },

        // Admin routes
        {
            path: "/admin",
            component: AdminLayout,
            meta: { admin: true },
            children: [
                {
                    path: "",
                    name: "admin.dashboard",
                    component: () => import("../pages/admin/Dashboard.vue"),
                },
                {
                    path: "users",
                    name: "admin.users",
                    component: () => import("../pages/admin/Users.vue"),
                },
                {
                    path: "trips",
                    name: "admin.trips",
                    component: () => import("../pages/admin/Trips.vue"),
                },
                {
                    path: "places",
                    name: "admin.places",
                    component: () => import("../pages/admin/Places.vue"),
                },
                {
                    path: "settings",
                    name: "admin.settings",
                    component: () => import("../pages/admin/Settings.vue"),
                },
            ],
        },

        // Error pages
        {
            path: "/403",
            name: "error.403",
            component: () => import("../pages/errors/Forbidden.vue"),
        },
        {
            path: "/:pathMatch(.*)*",
            name: "error.404",
            component: () => import("../pages/errors/NotFound.vue"),
        },
    ],
})

// Global navigation guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("token")
    const isAuthenticated = !!token

    // Prefer user.role from stored user (more reliable than separate "role" key)
    const user = JSON.parse(localStorage.getItem("user") || "null")
    const isAdmin = user?.role === "admin"

    // Guest restrictions
    const allowWhenAuth = ["auth.verify", "auth.google", "auth.facebook"]

    if (to.meta.guest && isAuthenticated && !allowWhenAuth.includes(to.name)) {
        if (isAdmin) return next({ name: "admin.dashboard" })
        return next({ name: "app.home" })
    }

    // Auth protection
    if (to.meta.auth && !isAuthenticated) {
        localStorage.setItem("intended", to.fullPath)
        return next({ name: "auth.login" })
    }

    // Admin protection
    if (to.meta.admin) {
        if (!isAuthenticated) return next({ name: "auth.login" })
        if (!isAdmin) return next({ name: "error.403" })
    }

    next()
})

export default router
