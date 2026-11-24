"use strict";

(() => {
  const root = document.documentElement;
  const storageKey = "bdl-frame-theme";
  const media = window.matchMedia("(prefers-color-scheme: dark)");

  function readPreference() {
    try {
      const saved = localStorage.getItem(storageKey);
      return saved === "light" || saved === "dark" ? saved : null;
    } catch {
      return null;
    }
  }

  function applyTheme(theme) {
    root.dataset.theme = theme;
    const dark = theme === "dark";
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", dark ? "#15131a" : "#f8f7f4");

    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.setAttribute("aria-pressed", String(dark));
      button.setAttribute("aria-label", dark ? "Activer le thème clair" : "Activer le thème sombre");
      const icon = button.querySelector(".theme-toggle-icon");
      const label = button.querySelector(".theme-toggle-label");
      if (icon) icon.textContent = dark ? "☀" : "☾";
      if (label) label.textContent = dark ? "Thème clair" : "Thème sombre";
    });
  }

  const saved = readPreference();
  applyTheme(saved || (media.matches ? "dark" : "light"));

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.addEventListener("click", () => {
        const next = root.dataset.theme === "dark" ? "light" : "dark";
        try { localStorage.setItem(storageKey, next); } catch {}
        applyTheme(next);
      });
    });
  });

  media.addEventListener?.("change", (event) => {
    if (!readPreference()) applyTheme(event.matches ? "dark" : "light");
  });
})();
