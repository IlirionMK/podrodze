let loadingPromise = null

export function loadGoogleMaps(apiKey) {
    if (window.google?.maps) {
        return Promise.resolve(window.google)
    }

    if (loadingPromise) {
        return loadingPromise
    }

    loadingPromise = new Promise((resolve, reject) => {
        window.__initGoogleMaps = async () => {
            try {
                await google.maps.importLibrary("maps")
                await google.maps.importLibrary("marker")
                resolve(window.google)
            } catch (e) {
                reject(e)
            }
        }

        const script = document.createElement("script")
        script.src =
            `https://maps.googleapis.com/maps/api/js` +
            `?key=${encodeURIComponent(apiKey)}` +
            `&v=weekly` +
            `&libraries=places` +
            `&loading=async` +
            `&callback=__initGoogleMaps`

        script.async = true
        script.defer = true
        script.onerror = reject

        document.head.appendChild(script)
    })

    return loadingPromise
}
