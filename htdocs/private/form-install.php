<?php if (!defined('BDL_FRAME')) { http_response_code(404); exit; } ?>
<form class="admin-form" method="post" action="/installation.php">
  <input type="hidden" name="csrf" value="<?= html(csrf_token()) ?>">
  <label>Clef d’installation
    <input name="setup_key" type="password" maxlength="64" required autocomplete="off">
  </label>
  <label>Hôte MySQL
    <input name="db_host" value="<?= html($values['db_host']) ?>" maxlength="180" required autocomplete="off" placeholder="sql123.infinityfree.com">
  </label>
  <label>Nom complet de la base MySQL
    <input name="db_name" value="<?= html($values['db_name']) ?>" maxlength="64" required autocomplete="off" placeholder="if0_12345678_bdlframe">
  </label>
  <label>Utilisateur MySQL
    <input name="db_user" value="<?= html($values['db_user']) ?>" maxlength="64" required autocomplete="off" placeholder="if0_12345678">
  </label>
  <label>Mot de passe MySQL
    <input name="db_password" type="password" maxlength="240" required autocomplete="off">
  </label>
  <label>Choisis un mot de passe pour accéder à l’atelier
    <input name="password" type="password" minlength="14" maxlength="72" required autocomplete="new-password">
  </label>
  <label>Confirme ce mot de passe
    <input name="confirmation" type="password" minlength="14" maxlength="72" required autocomplete="new-password">
  </label>
  <button class="button button-dark" type="submit">Activer les devis</button>
</form>
