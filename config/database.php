<?php
/**
 * HEPTA Compta Web - Configuration & Connexion Base de Données
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'compta_hepta');
define('DB_USER', 'root');
define('DB_PASS', ''); // Par défaut sous WampServer, le mot de passe root est vide
define('DB_CHARSET', 'utf8mb4');

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}