(async function loadSavedTheme() {
    const root = document.documentElement;
    const scriptUrl = document.currentScript
        ? document.currentScript.src
        : window.location.href;

    const preferencesUrl = new URL("../php/settings.php?api=1", scriptUrl);

    try {
        const response = await fetch(preferencesUrl, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });

        if (!response.ok) {
            return;
        }

        const preferences = await response.json();

        const allowedPalettes = [
            "default",
            "colors",
            "colors2",
            "color3",
            "color4",
            "color5"
        ];

        const palette = allowedPalettes.includes(preferences.palette)
            ? preferences.palette
            : "default";

        const theme = preferences.darkMode ? "dark" : "light";
        const font = preferences.font === "poppins" ? "poppins" : "system";

        root.dataset.palette = palette;
        root.dataset.theme = theme;
        root.dataset.font = font;
    } catch (error) {
        // If preferences cannot load, the page keeps its normal default style.
    }
})();