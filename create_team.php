<?php
require 'config.php';

// Function to generate a 10-character alphanumeric key
function get_key() {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
}

// Check if we have received a valid POST request with members
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON-Daten aus dem Request-Body einlesen
    $data = json_decode(file_get_contents('php://input'), true);

    // Überprüfen, ob die Daten korrekt sind
    $members = isset($data['members']) ? $data['members'] : null;
    $lang = isset($data['lang']) ? $data['lang'] : 'en';

    if (!$members || !is_array($members) || count($members) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid members list.']);
        exit();
    }

    // Create a new connection
    $mysqli = new mysqli(_MYSQL_HOST, _MYSQL_USER, _MYSQL_PWD, _MYSQL_DB, _MYSQL_PORT);

    // Check connection
    if ($mysqli->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit();
    }

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Generate a unique team key
        $team_key = get_key();

        // Prepare and execute the query to insert a new team
        $stmt = $mysqli->prepare("INSERT INTO team_tbl (`key`, `lang`) VALUES (?, ?)");
        $stmt->bind_param('ss', $team_key, $lang);
        if (!$stmt->execute()) {
            throw new Exception('Error inserting team: ' . $stmt->error);
        }
        $team_id = $stmt->insert_id;
        $stmt->close();

        // Insert each member into member_tbl with the generated team_id
        $stmt = $mysqli->prepare("INSERT INTO member_tbl (`key`, team_id, name) VALUES (?, ?, ?)");

        foreach ($members as $member) {
            $member_key = get_key();
            $stmt->bind_param('sis', $member_key, $team_id, $member);

            // Prüfen, ob das Einfügen erfolgreich war
            if (!$stmt->execute()) {
                throw new Exception('Error inserting member: ' . $stmt->error);
            }
        }
        $stmt->close();

        // Commit the transaction
        $mysqli->commit();

        // Return success response
        echo json_encode(['status' => 'success', 'team_id' => $team_key]);

    } catch (Exception $e) {
        // Rollback the transaction on error
        $mysqli->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    // Close the connection
    $mysqli->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
