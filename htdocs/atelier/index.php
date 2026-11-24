<?php
declare(strict_types=1);

define('BDL_FRAME', true);
require __DIR__ . '/../private/bootstrap.php';

page_headers(true);
if (!secure_request()) {
    http_response_code(403);
    exit('Ouvrez bdl-frame.fr en HTTPS pour accéder à l’atelier.');
}

$config = site_config();
$signedIn = studio_session($config);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config !== null) {
    $action = $_POST['action'] ?? '';
    if (!is_csrf_valid()) {
        http_response_code(403);
        $error = 'Actualise cette page puis réessaie.';
    } elseif ($action === 'logout' && $signedIn) {
        end_studio_session();
        header('Location: /atelier/', true, 303);
        exit;
    } elseif ($action === 'login' && !$signedIn) {
        $password = $_POST['password'] ?? '';
        if (is_string($password) && strlen($password) <= 72) {
            try {
                if (!consume_limit(db_connect($config), 'login', 900, 12)) {
                    http_response_code(429);
                    $error = 'Trop de tentatives. Réessaie dans quelques minutes.';
                } elseif (password_verify($password, $config['admin_hash'])) {
                    start_studio_session($config);
                    header('Location: /atelier/', true, 303);
                    exit;
                }
            } catch (Throwable $failure) {
                http_response_code(503);
                $error = 'Connexion momentanément indisponible.';
            }
        }
        if ($error === '') {
            http_response_code(401);
            $error = 'Le mot de passe saisi est incorrect.';
        }
    }
}
if ($signedIn && $error === '') {
    $atelier = true;
    require __DIR__ . '/../private/portail.php';
    exit;
}

$title = 'Connexion à l’atelier';
$notice = $config === null
    ? 'Active la base MySQL pour recevoir les demandes de devis.'
    : 'Connecte-toi pour consulter les demandes, répondre et mettre à jour leur statut.';
if (($_GET['configuration'] ?? '') === 'ok' && $config !== null) {
    $notice = 'Les demandes de devis sont activées. Connecte-toi avec le mot de passe que tu viens de créer.';
}
$formView = __DIR__ . '/../private/form-login.php';
require __DIR__ . '/../private/form-page.php';
