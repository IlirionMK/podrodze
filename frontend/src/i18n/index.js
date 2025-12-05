import { createI18n } from "vue-i18n"

import pl from "./pl.json"
import en from "./en.json"

const savedLang = localStorage.getItem("lang") || "pl"

export default createI18n({
    legacy: false,
    globalInjection: true,
    locale: savedLang,
    fallbackLocale: "en",
    messages: { pl, en }
})
