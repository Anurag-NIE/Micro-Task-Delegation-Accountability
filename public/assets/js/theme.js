(function () {
  function getPreferredTheme() {
    const stored = localStorage.getItem("theme");
    if (stored === "light" || stored === "dark") return stored;

    const prefersDark =
      window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches;
    return prefersDark ? "dark" : "light";
  }

  function setTheme(theme) {
    document.documentElement.setAttribute("data-bs-theme", theme);
    try {
      localStorage.setItem("theme", theme);
    } catch (e) {}

    // Update any toggle buttons
    document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
      // Show the *target* theme icon (what clicking will switch to)
      const nextTheme = theme === "dark" ? "light" : "dark";
      // Use non-emoji symbols to avoid font/emoji differences
      const icon = nextTheme === "dark" ? "☾" : "☀";
      btn.textContent = icon;
      btn.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
      btn.setAttribute(
        "aria-label",
        nextTheme === "dark" ? "Switch to dark mode" : "Switch to light mode"
      );
      btn.setAttribute(
        "title",
        nextTheme === "dark" ? "Dark mode" : "Light mode"
      );
    });
  }

  function toggleTheme() {
    const current =
      document.documentElement.getAttribute("data-bs-theme") || "light";
    setTheme(current === "dark" ? "light" : "dark");
  }

  function init() {
    // Ensure theme is applied (in case inline pre-script wasn't included)
    setTheme(getPreferredTheme());

    document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
      btn.addEventListener("click", toggleTheme);
    });
  }

  window.Theme = { init, setTheme, toggleTheme, getPreferredTheme };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
