<?php
require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<h1>Connexion à la base de données impossible</h1>';
            echo '<p>Vérifiez que MAMP est démarré et que la base <code>' . DB_NAME . '</code> a été importée via phpMyAdmin (fichier <code>database/rammss.sql</code>).</p>';
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }
    }
    return $pdo;
}
