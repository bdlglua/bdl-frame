<?php
declare(strict_types=1);

define('BDL_FRAME', true);
require __DIR__ . '/private/bootstrap.php';
require __DIR__ . '/private/schema.php';
require __DIR__ . '/private/clef-installation.php';

page_headers(true);
if (is_file(CONFIG_PATH)) {
    header('Location: /atelier/', true, 303);
    exit;
}
if (!secure_request()) {
    http_response_code(403);
    exit('Ouvrez bdl-frame.fr en HTTPS pour poursuivre l’installation.');
}

$title = 'Configurer les devis';
$notice = 'Saisis les identifiants MySQL de ton compte d’hébergement et la clef fournie avec le site. L’activation crée les tables de devis et verrouille cette page.';
$error = '';
$values = ['db_host' => '', 'db_name' => '', 'db_user' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        $values[$key] = field($_POST, $key, $key === 'db_host' ? 180 : 64);
    }
    $setupKey = field($_POST, 'setup_key', 64);
    $dbPassword = $_POST['db_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    $validDb = preg_match('/^[a-z0-9][a-z0-9.-]{0,179}$/iD', $values['db_host'])
        && preg_match('/^\w{1,64}$/D', $values['db_name'])
        && preg_match('/^\w{1,64}$/D', $values['db_user'])
        && is_string($dbPassword) && $dbPassword !== '' && strlen($dbPassword) <= 240;
    $validPassword = is_string($password) && strlen($password) >= 14 && strlen($password) <= 72
        && !str_contains($password, "\0") && $password === $confirmation;

    if (!is_csrf_valid() || !hash_equals(BDL_SETUP_KEY_HASH, hash('sha256', $setupKey))) {
        http_response_code(403);
        $error = 'Vérifie la clef d’installation dans le guide et réessaie.';
    } elseif (!$validDb) {
        http_response_code(400);
        $error = 'Vérifie les identifiants MySQL affichés dans InfinityFree.';
    } elseif (!$validPassword) {
        http_response_code(400);
        $error = 'Choisis un mot de passe d’au moins 14 caractères et saisis-le deux fois.';
    } else {
        $lock = null;
        $tempConfig = null;
        try {
            $lock = fopen(__DIR__ . '/private/install.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX)) {
                throw new RuntimeException('Configuration indisponible.');
            }
            clearstatcache(true, CONFIG_PATH);
            if (!is_file(CONFIG_PATH)) {
                $config = $values + ['db_password' => $dbPassword, 'admin_hash' => password_hash($password, PASSWORD_DEFAULT)];
                $db = db_connect($config);
                create_schema($db);
                $tempConfig = CONFIG_PATH . '.' . bin2hex(random_bytes(12)) . '.php';
                $source = "<?php\nif (!defined('BDL_FRAME')) { http_response_code(404); exit; }\nreturn "
                    . var_export($config, true) . ";\n";
                if (file_put_contents($tempConfig, $source, LOCK_EX) !== strlen($source)
                    || !rename($tempConfig, CONFIG_PATH)) {
                    throw new RuntimeException('Impossible d’écrire la configuration.');
                }
                $tempConfig = null;
            }
        } catch (PDOException $failure) {
            http_response_code(400);
            $error = 'La base MySQL est inaccessible. Vérifie le serveur, le nom complet et les identifiants dans InfinityFree.';
        } catch (Throwable $failure) {
            http_response_code(503);
            $error = 'L’installation ne peut pas enregistrer la configuration. Vérifie les droits du dossier private et réessaie.';
        } finally {
            if ($tempConfig !== null && is_file($tempConfig)) {
                unlink($tempConfig);
            }
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
        if ($error === '') {
            header('Location: /atelier/?configuration=ok', true, 303);
            exit;
        }
    }
}

$formView = __DIR__ . '/private/form-install.php';
require __DIR__ . '/private/form-page.php';
