<?php if (!defined('BDL_FRAME')) { http_response_code(404); exit; } ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html($title) ?> — BDL Frame</title>
  <link rel="icon" type="image/png" href="/assets/forme.png">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <header class="site-header">
    <div class="shell header-layout">
      <a class="wordmark" href="/" aria-label="BDL Frame, accueil"><span class="star" aria-hidden="true">✳</span> BDL<span class="brand-studio">Frame</span></a>
      <nav class="site-nav" aria-label="Navigation principale"><a href="/#templates">Templates</a><a href="/#services">Services</a></nav>
      <a class="button button-outline header-action" href="/">Retour au site</a>
    </div>
  </header>
  <main class="shell portal">
    <div class="section-head"><div>
      <p class="eyebrow"><span aria-hidden="true"></span> Espace studio</p>
      <h1><?= html($title) ?><span class="period">.</span></h1>
    </div></div>
    <div class="portal-card admin-card">
      <?php if ($notice !== ''): ?>
      <p class="login-note"><?= html($notice) ?></p>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <p class="admin-alert" role="alert"><?= html($error) ?></p>
      <?php endif; ?>
      <?php require $formView; ?>
    </div>
  </main>
</body>
</html>
