import { createRouter, createWebHistory } from "vue-router"

import GuestLayout from "../layouts/GuestLayout.vue"
import UserLayout from "../layouts/UserLayout.vue"
import AdminLayout from "../layouts/AdminLayout.vue"

import HomePage from "../pages/Home.vue"
import AuthLoginPage from "../pages/auth/Login.vue"
import AuthRegisterPage from "../pages/auth/Register.vue"

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: "/",
            component: GuestLayout,
            meta: { guest: true },
            children: [
                { path: "", name: "guest.home", component: HomePage },

                { path: "login", name: "auth.login", component: AuthLoginPage },
                { path: "register", name: "auth.register", component: AuthRegisterPage },

                {
                    path: "auth/google",
                    name: "auth.google",
                    component: () => import("../pages/auth/GoogleCallback.vue"),
                    meta: { guest: true },
                },
                {
                    path: "auth/facebook/callback",
                    name: "auth.facebook",
                    component: () => import("../pages/auth/FacebookCallback.vue"),
                    meta: { guest: true },
                },
                {
                    path: "auth/verify-email",
                    name: "auth.verify",
                    component: () => import("../pages/auth/VerifyEmail.vue"),
                    meta: { public: true },
                },

                {
                    path: "data-deletion",
                    name: "legal.data_deletion",
                    component: () => import("../pages/terms/Data_Deletion.vue"),
                    meta: { public: true },
                },
                {
                    path: "privacy",
                    name: "legal.privacy",
                    component: () => import("../pages/terms/Privacy.vue"),
                    meta: { public: true },
                },
                {
                    path: "terms",
                    name: "legal.terms",
                    component: () => import("../pages/terms/Terms.vue"),
                    meta: { public: true },
                },
            ],
        },

        {
            path: "/app",
            component: UserLayout,
            meta: { auth: true },
            children: [
                { path: "", redirect: { name: "app.home" } },
                { path: "home", name: "app.home", component: HomePage },

                { path: "trips", name: "app.trips", component: () => import("../pages/app/trips/TripsList.vue") },
                { path: "trips/create", name: "app.trips.create", component: () => import("../pages/app/trips/TripCreate.vue") },
                { path: "trips/:id", name: "app.trips.show", component: () => import("../pages/app/trips/TripDetail.vue"), props: true },
                { path: "profile", name: "app.profile", component: () => import("../pages/app/Profile.vue") },
            ],
        },

        {
            path: "/admin",
            component: AdminLayout,
            meta: { admin: true },
            children: [
                { path: "", name: "admin.dashboard", component: () => import("../pages/admin/Dashboard.vue") },
                { path: "users", name: "admin.users", component: () => import("../pages/admin/Users.vue") },
                { path: "trips", name: "admin.trips", component: () => import("../pages/admin/Trips.vue") },
                { path: "places", name: "admin.places", component: () => import("../pages/admin/Places.vue") },
                { path: "settings", name: "admin.settings", component: () => import("../pages/admin/Settings.vue") },
            ],
        },

        { path: "/403", name: "error.403", component: () => import("../pages/errors/Forbidden.vue") },
        { path: "/:pathMatch(.*)*", name: "error.404", component: () => import("../pages/errors/NotFound.vue") },
    ],
})

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("token")
    const isAuthenticated = !!token

    let user = null
    try {
        user = JSON.parse(localStorage.getItem("user") || "null")
    } catch {
        user = null
    }
    const isAdmin = user?.role === "admin"

    if (to.meta.public) return next()

    if (to.meta.guest && isAuthenticated) {
        if (isAdmin) return next({ name: "admin.dashboard" })
        return next({ name: "app.home" })
    }

    if (to.meta.auth && !isAuthenticated) {
        localStorage.setItem("intended", to.fullPath)
        return next({ name: "auth.login" })
    }

    if (to.meta.admin) {
        if (!isAuthenticated) {
            localStorage.setItem("intended", to.fullPath)
            return next({ name: "auth.login" })
        }
        if (!isAdmin) return next({ name: "error.403" })
    }

    next()
})

export default router
