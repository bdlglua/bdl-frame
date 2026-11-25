<?php
declare(strict_types=1);

define('BDL_FRAME', true);
require __DIR__ . '/private/bootstrap.php';
require __DIR__ . '/private/demandes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");

$route = $_GET['route'] ?? '';
if (!is_string($route)) {
    api_error('Cette page est introuvable.', 404);
}
$method = $_SERVER['REQUEST_METHOD'];
$config = site_config();
$atelierRoute = str_starts_with($route, 'atelier/');
$owner = $atelierRoute && studio_session($config);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($route === 'atelier/session' && $method === 'GET') {
    json_reply(['studio' => $owner]);
}
if ($atelierRoute && !$owner) {
    api_error('Cet espace est réservé au studio.', 403);
}
if (!secure_request()) {
    api_error('Ouvrez la boutique en HTTPS pour poursuivre.', 403);
}
if ($config === null) {
    api_error('Le studio active les demandes de devis. Réessayez plus tard.', 503);
}

try {
    $db = db_connect($config);
    if ($route === 'demandes' && $method === 'POST') {
        create_ticket($db);
    }
    if ($route === 'atelier/demandes' && $method === 'GET') {
        list_tickets($db);
    }
    if ($route === 'suivi' || $route === 'suivi/messages') {
        $key = $_SERVER['HTTP_X_BDL_TRACKING'] ?? '';
        if (!preg_match('/^[0-9a-f]{48}$/D', $key)) {
            api_error('Indiquez le lien privé de votre demande.', 401);
        }
        $id = hash('sha256', $key);
        if ($route === 'suivi' && $method === 'GET') {
            ticket_detail($db, $id);
        }
        if ($route === 'suivi/messages' && $method === 'POST') {
            add_message($db, $id, false);
        }
    }
    if ($owner && preg_match('~^atelier/demandes/([0-9a-f]{64})(?:/(messages|statut))?$~D', $route, $parts)) {
        $id = $parts[1];
        $action = $parts[2] ?? '';
        if ($action === '' && $method === 'GET') {
            ticket_detail($db, $id);
        }
        if ($action === 'messages' && $method === 'POST') {
            add_message($db, $id, true);
        }
        if ($action === 'statut' && $method === 'PATCH') {
            set_status($db, $id);
        }
    }
    api_error('Cette page est introuvable.', 404);
} catch (Throwable $failure) {
    error_log('BDL Frame : erreur de traitement (' . get_class($failure) . ')');
    api_error('Le service est momentanément indisponible. Réessayez dans un instant.', 503);
}
