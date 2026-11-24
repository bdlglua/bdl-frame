"use strict";

const byId = (id) => document.getElementById(id);
const catalog = byId("products");
const filters = byId("filters");
const productDialog = byId("product-dialog");
const currency = new Intl.NumberFormat("fr-FR", {
  style: "currency",
  currency: "EUR",
  maximumFractionDigits: 2,
  minimumFractionDigits: 0,
});

function element(tag, className, value) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (value !== undefined) node.textContent = value;
  return node;
}

function checkoutUrl(product) {
  if (!product.vente) return "";
  try {
    const url = new URL(product.vente);
    if (url.protocol === "https:") return url.href;
  } catch {
    return "";
  }
  return "";
}

function artwork(product) {
  const formatClass = product.categorie === "Organisation" ? " art-workbook" : "";
  const art = element("div", "product-art" + formatClass);
  const picture = element("img", "product-preview-image");
  picture.src = "/assets/templates/" + encodeURIComponent(product.id) + ".webp";
  picture.alt = "Aperçu du modèle " + product.nom;
  picture.width = 840;
  picture.height = 520;
  picture.loading = "lazy";
  art.append(picture);
  return art;
}

fetch("/api.php?route=atelier%2Fsession", { headers: { Accept: "application/json" } })
  .then((response) => response.ok ? response.json() : null)
  .then((result) => {
    byId("worker-admin").hidden = result?.studio !== true;
  })
  .catch(() => {});

function showProduct(product) {
  const url = checkoutUrl(product);
  byId("product-visual").replaceChildren(artwork(product));
  byId("product-format").textContent = product.format;
  byId("product-title").textContent = product.nom;
  byId("product-description").textContent = product.description;
  byId("product-features").replaceChildren(
    ...product.contenu.map((feature) => element("li", "", feature)),
  );
  byId("product-price").textContent = currency.format(product.prix);
  const availability = byId("product-availability");
  availability.textContent = url ? "Achat sur la page du produit" : "Tarif indicatif · vente à venir";
  const link = byId("buy-link");
  link.hidden = !url;
  if (url) link.href = url;
  else link.removeAttribute("href");
  productDialog.showModal();
  productDialog.scrollTop = 0;
}

const cards = boutique.produits.map((product) => {
  const card = element("article", "product-card");
  const thumb = element("div", "product-thumb");
  thumb.append(artwork(product));

  const info = element("div", "product-info");
  const heading = element("div", "product-heading");
  heading.append(
    element("h3", "", product.nom),
    element("span", "price", currency.format(product.prix)),
  );
  const details = element("button", "text-link", "Voir le template");
  details.type = "button";
  details.setAttribute("aria-label", "Voir le template " + product.nom);
  details.setAttribute("aria-haspopup", "dialog");
  details.addEventListener("click", () => showProduct(product));
  info.append(
    element("p", "product-format", product.format),
    heading,
    element("p", "", product.description),
    details,
  );
  card.append(thumb, info);
  catalog.append(card);
  return { card, category: product.categorie };
});

const categories = [...new Set(cards.map(({ category }) => category))];
const filterButtons = [["Tous", null], ...categories.map((category) => [category, category])]
  .map(([label, category]) => {
    const button = element("button", "filter", label);
    button.type = "button";
    button.setAttribute("aria-pressed", String(category === null));
    button.addEventListener("click", () => {
      filterButtons.forEach((filter) => filter.setAttribute("aria-pressed", String(filter === button)));
      let count = 0;
      cards.forEach((entry) => {
        entry.card.hidden = category !== null && entry.category !== category;
        if (!entry.card.hidden) count += 1;
      });
      byId("catalog-status").textContent = count > 1 ? count + " templates affichés" : count + " template affiché";
    });
    return button;
  });
filters.append(...filterButtons);

document.querySelectorAll("[data-service]").forEach((link) => {
  link.addEventListener("click", () => {
    byId("project-service").value = link.dataset.service;
  });
});

const form = byId("project-form");
const projectDialog = byId("project-dialog");
const tracking = byId("confirmation-link");
const copyStatus = byId("copy-status");
const submitFeedback = byId("submit-feedback");
let draft = null;

function trackingKey() {
  return Array.from(crypto.getRandomValues(new Uint8Array(24)), byte => byte.toString(16).padStart(2, "0")).join("");
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const nom = data.get("nom").trim();
  const service = data.get("service");
  const description = data.get("description").trim();
  if (!nom || description.length < 10) {
    submitFeedback.textContent = "Indiquez votre nom et décrivez votre projet en au moins 10 caractères.";
    return;
  }
  const signature = JSON.stringify([nom, service, description]);
  if (!draft || draft.signature !== signature) draft = { signature, cle: trackingKey() };
  const button = event.submitter;
  button.disabled = true;
  submitFeedback.textContent = "Envoi de la demande…";
  try {
    const response = await fetch("/api.php?route=demandes", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ nom, service, description, cle: draft.cle }),
    });
    if (!(response.headers.get("content-type") || "").includes("application/json")) {
      throw new Error("Reconnectez-vous au site puis réessayez.");
    }
    const result = await response.json();
    if (!response.ok) throw new Error(result.erreur || "Votre demande n’a pas pu être enregistrée.");
    const link = new URL("/suivi/#" + draft.cle, location.origin);
    byId("open-project").href = link.href;
    byId("project-reference").textContent = "Référence " + result.reference;
    tracking.value = link.href;
    copyStatus.textContent = "";
    submitFeedback.textContent = "Votre demande a bien été enregistrée.";
    form.reset();
    draft = null;
    projectDialog.showModal();
  } catch (problem) {
    submitFeedback.textContent = problem.message;
  } finally {
    button.disabled = false;
  }
});

byId("copy-project").addEventListener("click", async () => {
  try {
    await navigator.clipboard.writeText(tracking.value);
    copyStatus.textContent = "Lien copié.";
  } catch {
    tracking.focus();
    tracking.select();
    copyStatus.textContent = "Lien sélectionné. Copiez-le depuis votre appareil.";
  }
});
