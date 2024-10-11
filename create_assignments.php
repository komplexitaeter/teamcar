<?php
require 'config.php'; // Deine Konfigurationsdatei mit den Datenbankeinstellungen

// Funktion, um den GET-Parameter sicher auszulesen
function get_param($key) {
    return substr(filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING), 0, 80);
}

// Sicherstellen, dass die Anfrage eine POST-Anfrage ist
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// JSON-Daten aus dem Request-Body einlesen
$data = json_decode(file_get_contents('php://input'), true);

// Überprüfen, ob die erforderlichen Parameter im JSON enthalten sind
if (!isset($data['team_key']) || !isset($data['from_key']) || !isset($data['assignments']) || !is_array($data['assignments'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing parameters.']);
    exit();
}

// Erstelle eine Verbindung zur Datenbank
$mysqli = new mysqli(_MYSQL_HOST, _MYSQL_USER, _MYSQL_PWD, _MYSQL_DB, _MYSQL_PORT);

// Überprüfe, ob die Datenbankverbindung erfolgreich war
if ($mysqli->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Starten der Transaktion
$mysqli->begin_transaction();

try {
    // Hole die Team-ID und die "from_member_id" anhand der übergebenen Schlüssel
    $stmt = $mysqli->prepare("SELECT id FROM team_tbl WHERE `key` = ?");
    $stmt->bind_param('s', $data['team_key']);
    $stmt->execute();
    $stmt->bind_result($team_id);
    $stmt->fetch();
    $stmt->close();

    // Sicherstellen, dass das Team existiert
    if (!$team_id) {
        throw new Exception('Invalid team key.');
    }

    // Hole die ID des Mitglieds, das die Zuweisung macht ("from_member_id")
    $stmt = $mysqli->prepare("SELECT id FROM member_tbl WHERE `key` = ?");
    $stmt->bind_param('s', $data['from_key']);
    $stmt->execute();
    $stmt->bind_result($from_member_id);
    $stmt->fetch();
    $stmt->close();

    // Sicherstellen, dass das Mitglied existiert
    if (!$from_member_id) {
        throw new Exception('Invalid from member key.');
    }

    // Bereite die SQL-Abfrage zum Einfügen in die Tabelle assignment_tbl vor
    $stmt = $mysqli->prepare("INSERT INTO assignment_tbl (team_id, from_member_id, member_id, role_key) VALUES (?, ?, ?, ?)");

    // Gehe die Zuweisungen (assignments) durch
    foreach ($data['assignments'] as $assignment) {
        $role_key = $assignment['role_key'];
        foreach ($assignment['assigned_members'] as $member) {
            // Hole die ID des zugewiesenen Mitglieds
            $stmt_member = $mysqli->prepare("SELECT id FROM member_tbl WHERE `key` = ?");
            $stmt_member->bind_param('s', $member['key']);
            $stmt_member->execute();
            $stmt_member->bind_result($member_id);
            $stmt_member->fetch();
            $stmt_member->close();

            // Sicherstellen, dass das zugewiesene Mitglied existiert
            if (!$member_id) {
                throw new Exception('Invalid member key: ' . $member['key']);
            }

            // Füge die Zuweisung in die Datenbank ein
            $stmt->bind_param('iiis', $team_id, $from_member_id, $member_id, $role_key);
            $stmt->execute();
        }
    }

    // Sobald die Assignments erfolgreich eingefügt wurden, setze das ready_flag auf true
    $stmt_ready = $mysqli->prepare("UPDATE member_tbl SET ready_flag = 1 WHERE `key` = ?");
    $stmt_ready->bind_param('s', $data['from_key']);
    $stmt_ready->execute();
    $stmt_ready->close();

    // Transaktion abschließen (commit)
    $mysqli->commit();
    $stmt->close();

    // Erfolgsmeldung zurückgeben
    echo json_encode(['status' => 'success', 'message' => 'Assignments created and ready_flag updated successfully.']);

} catch (Exception $e) {
    // Falls ein Fehler auftritt, die Transaktion zurücksetzen (rollback)
    $mysqli->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // Schließen der Verbindung zur Datenbank
    $mysqli->close();
}