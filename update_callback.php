<?php
require 'config.php';

// Sicheres Auslesen der GET-Parameter
$key = substr(filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING), 0, 80);
$working = filter_input(INPUT_GET, 'working', FILTER_VALIDATE_INT);

// Überprüfen, ob der key und working-Parameter gesetzt sind
if (!$key || $working === null) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid key or working parameter']);
    exit();
}

// Verbindung zur Datenbank herstellen
$mysqli = new mysqli(_MYSQL_HOST, _MYSQL_USER, _MYSQL_PWD, _MYSQL_DB, _MYSQL_PORT);

// Überprüfen der Datenbankverbindung
if ($mysqli->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

try {
    // Wenn working=1, setze last_callback auf NOW(), andernfalls auf NULL
    if ($working == 1) {
        $stmt = $mysqli->prepare("UPDATE member_tbl SET last_callback = NOW() WHERE `key` = ?");
    } else {
        $stmt = $mysqli->prepare("UPDATE member_tbl SET last_callback = NULL WHERE `key` = ?");
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $mysqli->close();
}
