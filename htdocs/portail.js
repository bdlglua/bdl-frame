"use strict";

const byId = (id) => document.getElementById(id);
const atelier = location.pathname.replace(/\/$/, "") === "/atelier";
const statusNames = {
  recue: "Reçue",
  "en-cours": "En cours",
  "devis-propose": "Devis proposé",
  cloturee: "Clôturée",
};
const localDate = new Intl.DateTimeFormat("fr-FR", { dateStyle: "long", timeStyle: "short" });
const feedback = byId("portal-feedback");
const threadFeedback = byId("thread-feedback");
const message = byId("message-body");
let selected = "";
let trackingKey = "";
let after = "";
let loadingList = false;
let threadVersion = 0;
let outgoing = null;

function node(tag, className, value) {
  const item = document.createElement(tag);
  item.className = className;
  item.textContent = value;
  return item;
}

async function api(path, method = "GET", data, key = "") {
  const headers = { Accept: "application/json" };
  if (data) headers["Content-Type"] = "application/json";
  if (key) headers["X-BDL-Tracking"] = key;
  const url = new URL(path, location.origin);
  url.searchParams.set("route", url.pathname.slice("/api/".length));
  url.pathname = "/api.php";
  const response = await fetch(url, { method, headers, body: data ? JSON.stringify(data) : undefined, cache: "no-store" });
  if (!(response.headers.get("content-type") || "").includes("application/json")) {
    throw new Error("Reconnectez-vous au site puis réessayez.");
  }
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.erreur || "Cette opération est momentanément indisponible.");
  return payload;
}

function readKey(link) {
  const value = link.trim();
  if (/^[0-9a-f]{48}$/.test(value)) return value;
  try {
    const url = new URL(value, location.origin);
    if (url.origin === location.origin && /^\/suivi\/?$/.test(url.pathname) && /^#[0-9a-f]{48}$/.test(url.hash)) {
      return url.hash.slice(1);
    }
  } catch {
    return "";
  }
  return "";
}

function endpoint() {
  return atelier ? "/api/atelier/demandes/" + selected : "/api/suivi";
}

function markSelected() {
  byId("inbox-list").querySelectorAll("button").forEach((item) => {
    if (item.dataset.id === selected) item.setAttribute("aria-current", "true");
    else item.removeAttribute("aria-current");
  });
}

function updateListBadge(status) {
  const item = Array.from(byId("inbox-list").children)
    .find((entry) => entry.firstElementChild.dataset.id === selected);
  const pill = item?.querySelector(".ticket-status");
  if (pill) {
    pill.textContent = statusNames[status];
    pill.dataset.status = status;
  }
}

async function updateInbox(reset) {
  if (loadingList) return;
  loadingList = true;
  feedback.textContent = "Chargement des demandes…";
  try {
    const cursor = reset ? "" : after;
    const data = await api("/api/atelier/demandes" + (cursor ? "?apres=" + encodeURIComponent(cursor) : ""));
    const list = byId("inbox-list");
    if (reset) list.replaceChildren();
    byId("inbox-empty").hidden = list.childElementCount > 0 || data.demandes.length > 0;
    for (const ticket of data.demandes) {
      const row = node("li", "", "");
      const button = node("button", "inbox-entry", "");
      button.type = "button";
      button.dataset.id = ticket.id;
      const title = node("strong", "", ticket.nom);
      const label = node("span", "entry-meta", ticket.service + " · " + ticket.reference);
      const status = node("span", "ticket-status", statusNames[ticket.statut]);
      status.dataset.status = ticket.statut;
      button.append(title, label, status);
      button.addEventListener("click", async () => {
        if (selected === ticket.id) return;
        selected = ticket.id;
        markSelected();
        byId("thread").hidden = true;
        byId("empty-thread").hidden = false;
        message.value = "";
        outgoing = null;
        threadFeedback.textContent = "";
        await openThread();
      });
      row.append(button);
      list.append(row);
    }
    markSelected();
    after = data.apres;
    byId("inbox-next").hidden = !after;
    feedback.textContent = "";
  } catch (problem) {
    feedback.textContent = problem.message;
  } finally {
    loadingList = false;
  }
}

function renderThread(data) {
  const ticket = data.demande;
  byId("thread-ref").textContent = ticket.reference;
  byId("thread-title").textContent = ticket.service;
  byId("thread-meta").textContent = ticket.nom + " · " + localDate.format(ticket.cree_le);
  const badge = byId("thread-status");
  badge.textContent = statusNames[ticket.statut];
  badge.dataset.status = ticket.statut;
  byId("update-status").value = ticket.statut;
  byId("thread-status-form").hidden = !atelier;
  byId("initial-description").textContent = ticket.description;
  byId("thread-empty").hidden = data.messages.length > 0;
  byId("message-list").replaceChildren(...data.messages.map((entry) => {
    const fromStudio = entry.auteur === "studio";
    const item = node("li", fromStudio ? "message from-studio" : "message", "");
    const time = node("time", "", localDate.format(entry.cree_le));
    time.dateTime = new Date(entry.cree_le).toISOString();
    item.append(
      node("strong", "", fromStudio ? "BDL Frame" : ticket.nom),
      time,
      node("p", "", entry.contenu),
    );
    return item;
  }));
  const closed = ticket.statut === "cloturee";
  byId("thread-closed").hidden = !closed;
  byId("thread-reply").hidden = closed;
  byId("thread").hidden = false;
  byId("empty-thread").hidden = true;
  byId("portal-layout").hidden = false;
  if (atelier) updateListBadge(ticket.statut);
}

async function openThread() {
  const version = ++threadVersion;
  feedback.textContent = "Chargement de la demande…";
  try {
    const data = await api(endpoint(), "GET", undefined, trackingKey);
    if (version !== threadVersion) return;
    if (!atelier) byId("portal-access").hidden = true;
    renderThread(data);
    feedback.textContent = "";
  } catch (problem) {
    if (version !== threadVersion) return;
    feedback.textContent = problem.message;
    if (!atelier) byId("portal-access").hidden = false;
  }
}

function loadClient(key) {
  if (key !== trackingKey) {
    message.value = "";
    outgoing = null;
    threadFeedback.textContent = "";
    byId("portal-layout").hidden = true;
  }
  trackingKey = key;
  history.replaceState(null, "", "/suivi/#" + key);
  openThread();
}

byId("portal-access").addEventListener("submit", (event) => {
  event.preventDefault();
  const key = readKey(byId("tracking-link").value);
  if (!key) {
    feedback.textContent = "Collez le lien privé complet que vous avez reçu à l’envoi de votre demande.";
    return;
  }
  loadClient(key);
});

byId("inbox-refresh").addEventListener("click", () => updateInbox(true));
byId("inbox-next").addEventListener("click", () => updateInbox(false));
byId("thread-refresh").addEventListener("click", openThread);

byId("thread-reply").addEventListener("submit", async (event) => {
  event.preventDefault();
  const content = message.value.trim();
  if (!content) {
    threadFeedback.textContent = "Écrivez votre message avant de l’envoyer.";
    return;
  }
  const button = event.submitter;
  button.disabled = true;
  const request = endpoint();
  const key = trackingKey;
  if (!outgoing || outgoing.content !== content) outgoing = { content, nonce: crypto.randomUUID() };
  const draft = outgoing;
  threadFeedback.textContent = "Envoi du message…";
  try {
    await api(request + "/messages", "POST", { contenu: content, nonce: draft.nonce }, key);
    if (request !== endpoint() || key !== trackingKey) return;
    if (outgoing === draft) {
      if (message.value.trim() === content) message.value = "";
      outgoing = null;
    }
    await openThread();
    if (request === endpoint() && key === trackingKey) threadFeedback.textContent = "Message envoyé.";
  } catch (problem) {
    if (request === endpoint() && key === trackingKey) threadFeedback.textContent = problem.message;
  } finally {
    button.disabled = false;
  }
});

byId("thread-status-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const request = endpoint();
  const button = event.submitter;
  button.disabled = true;
  threadFeedback.textContent = "Enregistrement…";
  try {
    await api(request + "/statut", "PATCH", { statut: byId("update-status").value });
    if (request !== endpoint()) return;
    await openThread();
    if (request === endpoint()) threadFeedback.textContent = "Statut mis à jour.";
  } catch (problem) {
    if (request === endpoint()) threadFeedback.textContent = problem.message;
  } finally {
    button.disabled = false;
  }
});

async function start() {
  if (atelier) {
    document.title = "Boîte de réception — BDL Frame";
    byId("portal-title").replaceChildren(document.createTextNode("Boîte de réception"), node("span", "period", "."));
    byId("portal-intro").textContent = "Répondez aux demandes de création et suivez leur avancement.";
    byId("portal-layout").classList.add("with-inbox");
    try {
      const session = await api("/api/atelier/session");
      if (!session.studio) {
        feedback.textContent = "Connectez-vous à l’atelier pour accéder aux demandes.";
        byId("atelier-login").hidden = false;
        return;
      }
      byId("inbox").hidden = false;
      byId("empty-thread").hidden = false;
      byId("portal-layout").hidden = false;
      await updateInbox(true);
    } catch (problem) {
      feedback.textContent = problem.message;
    }
    return;
  }

  const key = readKey(location.hash.slice(1));
  if (key) {
    loadClient(key);
  } else {
    feedback.textContent = "";
    byId("portal-access").hidden = false;
  }
}

window.addEventListener("hashchange", () => {
  if (atelier) return;
  const key = readKey(location.hash.slice(1));
  if (key) loadClient(key);
  else {
    threadVersion += 1;
    trackingKey = "";
    message.value = "";
    outgoing = null;
    byId("portal-layout").hidden = true;
    byId("portal-access").hidden = false;
    feedback.textContent = "";
  }
});
start();
