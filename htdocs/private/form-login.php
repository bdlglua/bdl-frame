<?php if (!defined('BDL_FRAME')) { http_response_code(404); exit; } ?>
<?php if ($config === null): ?>
  <a class="button button-dark" href="/installation.php">Configurer les devis</a>
<?php else: ?>
  <form class="admin-form" method="post" action="/atelier/">
    <input type="hidden" name="csrf" value="<?= html(csrf_token()) ?>">
    <label>Mot de passe de l’atelier
      <input name="password" type="password" maxlength="72" required autocomplete="current-password">
    </label>
    <button class="button button-dark" type="submit" name="action" value="login">Se connecter</button>
  </form>
<?php endif; ?>
