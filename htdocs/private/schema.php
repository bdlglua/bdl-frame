<?php
declare(strict_types=1);

if (!defined('BDL_FRAME')) {
    http_response_code(404);
    exit;
}

function create_schema(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS demandes (
        id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        nom VARCHAR(120) NOT NULL,
        service VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        statut VARCHAR(24) NOT NULL DEFAULT \'recue\',
        cree_le BIGINT UNSIGNED NOT NULL,
        INDEX bdl_demandes_creation (cree_le, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id VARCHAR(108) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        demande_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        auteur VARCHAR(12) NOT NULL,
        contenu TEXT NOT NULL,
        cree_le BIGINT UNSIGNED NOT NULL,
        INDEX bdl_messages_conversation (demande_id, cree_le, id),
        FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $db->exec('CREATE TABLE IF NOT EXISTS bdl_limits (
        bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        window_start INT UNSIGNED NOT NULL,
        count INT UNSIGNED NOT NULL,
        PRIMARY KEY (bucket_key, window_start),
        INDEX bdl_limit_expiry (window_start)
    ) ENGINE=InnoDB');
}
