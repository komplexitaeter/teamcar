<?php
require 'config.php';

// Sicheres Auslesen des GET-Parameters 't'
$t = substr(filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING), 0, 80);

// Überprüfen, ob der GET-Parameter 't' gesetzt ist
if (!$t) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid team key.']);
    exit();
}

// Verbindung zur Datenbank herstellen
$mysqli = new mysqli(_MYSQL_HOST, _MYSQL_USER, _MYSQL_PWD, _MYSQL_DB, _MYSQL_PORT);

// Überprüfen der Datenbankverbindung
if ($mysqli->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

try {
    // Abrufen der Sprachkennung des Teams
    $stmt = $mysqli->prepare("SELECT lang FROM team_tbl WHERE `key` = ?");
    $stmt->bind_param('s', $t);
    $stmt->execute();
    $stmt->bind_result($language_code);
    $stmt->fetch();
    $stmt->close();

    // Überprüfen, ob das Team gefunden wurde
    if (!$language_code) {
        echo json_encode(['status' => 'error', 'message' => 'Team not found.']);
        exit();
    }

    // Mitgliederinformationen abrufen und Status direkt in SQL bestimmen
    $stmt = $mysqli->prepare("
        SELECT 
            `key`, 
            name, 
            CASE 
                WHEN ready_flag = 1 THEN 2
                WHEN last_callback >= NOW() - INTERVAL 60 SECOND THEN 1
                ELSE 0
            END AS status
        FROM member_tbl 
        WHERE team_id = (SELECT id FROM team_tbl WHERE `key` = ?
                ) ORDER BY name
    ");
    $stmt->bind_param('s', $t);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'key' => $row['key'],
            'name' => $row['name'],
            'status' => $row['status']
        ];
    }

    $stmt->close();

    // JSON-Antwort erstellen
    $response = [
        'status' => 'success',
        'language_code' => $language_code,
        'members' => $members
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Fehlerbehandlung
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    // Schließen der Datenbankverbindung
    $mysqli->close();
}