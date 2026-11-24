<?php if (!defined('BDL_FRAME')) { http_response_code(404); exit; } ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#111015">
  <title>Suivi des demandes — BDL Frame</title>
  <link rel="icon" type="image/png" href="/assets/forme.png">
  <script src="/theme.js"></script>\n  <link rel="stylesheet" href="/styles.css">
  <script src="/portail.js" defer></script>
</head>
<body>
  <a class="skip" href="#contenu">Aller au contenu</a>
  <header class="site-header">
    <div class="shell header-layout">
      <a class="wordmark" href="/" aria-label="BDL Frame, accueil"><span class="star" aria-hidden="true">✳</span> BDL<span class="brand-studio">Frame</span></a>
      <nav class="site-nav" aria-label="Navigation principale"><a href="/#templates">Templates</a><a href="/#services">Services</a></nav>
      <div class="header-tools">
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Activer le thème clair" aria-pressed="true"><span class="theme-toggle-icon" aria-hidden="true">☀</span><span class="theme-toggle-label">Thème clair</span></button>
        <a class="button button-outline header-action" href="/">Retour au site</a>
      </div>
    </div>
  </header>

  <main class="shell portal" id="contenu">
    <div class="section-head">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span> Création sur mesure</p>
        <h1 id="portal-title">Suivre ma demande<span class="period">.</span></h1>
        <p id="portal-intro">Consultez l’avancement de votre projet et échangez avec le studio.</p>
      </div>
    </div>

    <p class="portal-feedback" id="portal-feedback" role="status">Chargement…</p>
    <a class="button button-dark" id="atelier-login" href="/atelier/" hidden>Se connecter à l’atelier</a>

    <form class="portal-card portal-access" id="portal-access" hidden>
      <label for="tracking-link">Lien privé de votre demande</label>
      <input id="tracking-link" required autocomplete="off" spellcheck="false" maxlength="400" placeholder="Collez votre lien de suivi">
      <button class="button button-dark" type="submit">Ouvrir la demande</button>
    </form>

    <div class="portal-layout" id="portal-layout" hidden>
      <aside class="portal-card inbox" id="inbox" aria-label="Boîte de réception" hidden>
        <div class="inbox-head"><h2>Demandes</h2><button class="text-link" type="button" id="inbox-refresh">Actualiser</button></div>
        <?php if ($atelier): ?>
          <form method="post" class="logout-form" action="/atelier/">
            <input type="hidden" name="csrf" value="<?= html(csrf_token()) ?>">
            <button type="submit" class="text-link" name="action" value="logout">Se déconnecter</button>
          </form>
        <?php endif; ?>
        <p id="inbox-empty" hidden>Aucune demande pour le moment.</p>
        <ul class="inbox-list" id="inbox-list"></ul>
        <button class="button button-outline" id="inbox-next" type="button" hidden>Charger la suite</button>
      </aside>

      <section class="portal-card thread" id="thread" aria-labelledby="thread-title" hidden>
        <div class="thread-head">
          <div>
            <p class="eyebrow" id="thread-ref"></p>
            <h2 id="thread-title"></h2>
            <p class="thread-meta" id="thread-meta"></p>
          </div>
          <div class="thread-actions">
            <span class="ticket-status" id="thread-status"></span>
            <button type="button" class="text-link" id="thread-refresh">Actualiser</button>
          </div>
        </div>

        <form class="thread-status-form" id="thread-status-form" hidden>
          <label for="update-status">Modifier le statut</label>
          <div>
            <select id="update-status">
              <option value="recue">Reçue</option>
              <option value="en-cours">En cours</option>
              <option value="devis-propose">Devis proposé</option>
              <option value="cloturee">Clôturée</option>
            </select>
            <button class="button button-outline" type="submit">Enregistrer</button>
          </div>
        </form>

        <div class="initial-request">
          <h3>Votre projet</h3>
          <p id="initial-description"></p>
        </div>

        <div class="conversation">
          <h3>Conversation</h3>
          <p id="thread-empty">Aucun message pour le moment.</p>
          <ol class="message-list" id="message-list"></ol>
        </div>
        <p id="thread-closed" hidden>Cette demande est clôturée.</p>
        <form id="thread-reply" class="thread-reply">
          <label for="message-body">Nouveau message</label>
          <textarea id="message-body" rows="3" maxlength="2500" required placeholder="Écrivez votre message…"></textarea>
          <button class="button button-dark" type="submit">Envoyer le message</button>
        </form>
        <p class="portal-feedback" id="thread-feedback" role="status"></p>
      </section>
      <div class="portal-card empty-thread" id="empty-thread" hidden>Sélectionnez une demande pour ouvrir la conversation.</div>
    </div>
  </main>

  <footer class="shell site-footer">
    <a class="wordmark" href="/" aria-label="BDL Frame, accueil"><span class="star" aria-hidden="true">✳</span> BDL<span class="brand-studio">Frame</span></a>
    <p>Templates & création sur mesure</p>
    <nav aria-label="Pied de page"><a href="/#templates">Boutique</a><a href="/#contact">Demander un devis</a></nav>
  </footer>
</body>
</html>
