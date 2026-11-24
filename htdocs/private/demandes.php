<?php
declare(strict_types=1);

if (!defined('BDL_FRAME')) {
    http_response_code(404);
    exit;
}

const SERVICES = ['Site vitrine', 'Identité visuelle', 'Kit de contenu', 'Autre projet'];
const STATUTS = ['recue', 'en-cours', 'devis-propose', 'cloturee'];

function json_reply(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

function api_error(string $message, int $status): never
{
    json_reply(['erreur' => $message], $status);
}

function read_json(): array
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $origin = strtolower($_SERVER['HTTP_ORIGIN'] ?? '');
    $expectedScheme = getenv('BDL_FRAME_LOCAL') === '1' ? 'http' : 'https';
    if ($origin !== $expectedScheme . '://' . $host) {
        api_error('Origine non autorisée.', 403);
    }
    $contentType = explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0];
    if (strtolower(trim($contentType)) !== 'application/json') {
        api_error('Format de demande invalide.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16001);
    if (!is_string($raw) || strlen($raw) > 16000) {
        api_error('Votre message est trop long.', 413);
    }
    $decoded = json_decode($raw);
    if (!$decoded instanceof stdClass) {
        api_error('Ce message est illisible.', 400);
    }
    return (array) $decoded;
}

function reference(string $id): string
{
    return 'BDL-' . strtoupper(substr($id, 0, 10));
}

function now_ms(): int
{
    return (int) floor(microtime(true) * 1000);
}

function create_ticket(PDO $db): never
{
    $body = read_json();
    $key = field($body, 'cle', 48);
    $name = field($body, 'nom', 120);
    $service = field($body, 'service', 80);
    $description = field($body, 'description', 2500);
    if (!preg_match('/^[0-9a-f]{48}$/D', $key) || $name === '' || !in_array($service, SERVICES, true)
        || preg_match_all('/./us', $description) < 10) {
        api_error('Renseignez votre nom, la prestation et au moins 10 caractères sur votre projet.', 400);
    }

    $id = hash('sha256', $key);
    $existing = sql($db, 'SELECT nom, service, description FROM demandes WHERE id = ?', [$id])->fetch();
    if ($existing === false) {
        if (!consume_limit($db, 'devis', 3600, 12)) {
            api_error('Trop de demandes en peu de temps. Réessayez plus tard.', 429);
        }
        sql($db, 'INSERT INTO demandes (id, nom, service, description, cree_le) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id', [$id, $name, $service, $description, now_ms()]);
        $existing = sql($db, 'SELECT nom, service, description FROM demandes WHERE id = ?', [$id])->fetch();
    }

    if ($existing === false || $existing['nom'] !== $name || $existing['service'] !== $service
        || $existing['description'] !== $description) {
        api_error('Cette référence est déjà associée à une autre demande.', 409);
    }
    json_reply(['reference' => reference($id)], 201);
}

function list_tickets(PDO $db): never
{
    $cursor = $_GET['apres'] ?? '';
    if (!is_string($cursor)) {
        api_error('Cette page est indisponible.', 400);
    }
    $query = 'SELECT id, nom, service, statut, cree_le FROM demandes';
    $order = ' ORDER BY cree_le DESC, id DESC LIMIT 31';
    if ($cursor === '') {
        $rows = sql($db, $query . $order)->fetchAll();
    } elseif (preg_match('/^(\d{1,15}):([0-9a-f]{64})$/D', $cursor, $parts)) {
        $time = (int) $parts[1];
        $rows = sql($db, $query . ' WHERE cree_le < ? OR (cree_le = ? AND id < ?)' . $order,
            [$time, $time, $parts[2]])->fetchAll();
    } else {
        api_error('Cette page est indisponible.', 400);
    }

    $page = array_slice($rows, 0, 30);
    foreach ($page as &$row) {
        $row['reference'] = reference($row['id']);
        $row['cree_le'] = (int) $row['cree_le'];
    }
    unset($row);
    $last = end($page);
    json_reply([
        'demandes' => $page,
        'apres' => count($rows) > 30 && $last ? $last['cree_le'] . ':' . $last['id'] : '',
    ]);
}

function ticket_detail(PDO $db, string $id): never
{
    $ticket = sql($db, 'SELECT nom, service, description, statut, cree_le FROM demandes WHERE id = ?', [$id])->fetch();
    if ($ticket === false) {
        api_error('Cette demande est introuvable. Vérifiez le lien de suivi.', 404);
    }
    $ticket['cree_le'] = (int) $ticket['cree_le'];
    $ticket['reference'] = reference($id);
    $messages = sql($db, 'SELECT auteur, contenu, cree_le FROM messages WHERE demande_id = ? ORDER BY cree_le, id', [$id])->fetchAll();
    foreach ($messages as &$entry) {
        $entry['cree_le'] = (int) $entry['cree_le'];
    }
    unset($entry);
    json_reply(['demande' => $ticket, 'messages' => $messages]);
}

function add_message(PDO $db, string $id, bool $owner): never
{
    $body = read_json();
    $nonce = field($body, 'nonce', 36);
    $content = field($body, 'contenu', 2500);
    if (!preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D', $nonce) || $content === '') {
        api_error('Votre message est vide ou invalide.', 400);
    }
    if (!$owner && !consume_limit($db, 'message', 900, 45)) {
        api_error('Trop de messages en peu de temps. Réessayez plus tard.', 429);
    }
    $author = $owner ? 'studio' : 'client';
    $key = $id . ':' . $author . ':' . $nonce;
    $db->beginTransaction();
    try {
        $ticketStatus = sql($db, 'SELECT statut FROM demandes WHERE id = ? FOR UPDATE', [$id])->fetchColumn();
        $existing = sql($db, 'SELECT contenu FROM messages WHERE id = ?', [$key])->fetchColumn();
        if ($ticketStatus !== false && $ticketStatus !== 'cloturee' && $existing === false) {
            sql($db, 'INSERT INTO messages (id, demande_id, auteur, contenu, cree_le) VALUES (?, ?, ?, ?, ?)',
                [$key, $id, $author, $content, now_ms()]);
        }
        $db->commit();
    } catch (Throwable $failure) {
        $db->rollBack();
        throw $failure;
    }

    if ($existing !== false && $existing !== $content) {
        api_error('Ce message a déjà été enregistré sous un contenu différent.', 409);
    }
    if ($ticketStatus === false) {
        api_error('Cette demande est introuvable.', 404);
    }
    if ($existing === false && $ticketStatus === 'cloturee') {
        api_error('Cette demande est clôturée.', 409);
    }
    json_reply(['enregistre' => true], 201);
}

function set_status(PDO $db, string $id): never
{
    $status = field(read_json(), 'statut', 32);
    if (!in_array($status, STATUTS, true)) {
        api_error('Ce statut est invalide.', 400);
    }
    $changed = sql($db, 'UPDATE demandes SET statut = ? WHERE id = ?', [$status, $id])->rowCount();
    if ($changed === 0 && sql($db, 'SELECT id FROM demandes WHERE id = ?', [$id])->fetchColumn() === false) {
        api_error('Cette demande est introuvable.', 404);
    }
    json_reply(['statut' => $status]);
}
