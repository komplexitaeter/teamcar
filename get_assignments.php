<?php
require 'config.php';

$t = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

$mysqli = new mysqli(_MYSQL_HOST, _MYSQL_USER, _MYSQL_PWD, _MYSQL_DB);

if ($mysqli->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

// Aggregiere die Rollen-Zuordnung und prüfe, ob das Mitglied sich selbst zugeordnet hat
$sql = "
    SELECT 
        a.role_key, 
        m.name, 
        COUNT(a.member_id) as count_assignments, 
        SUM(IF(a.from_member_id = a.member_id, 1, 0)) as self_assigned 
    FROM 
        assignment_tbl a 
    JOIN 
        member_tbl m ON a.member_id = m.id 
    WHERE 
        a.team_id = (SELECT id FROM team_tbl WHERE `key` = ?) 
    GROUP BY 
        a.role_key, m.name
    ORDER BY a.role_key, count_assignments DESC, self_assigned DESC, m.name;
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $t);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {
    $assignments[] = [
        'role_key' => $row['role_key'],
        'name' => $row['name'],
        'count' => $row['count_assignments'],
        'self_assigned' => (bool)$row['self_assigned']
    ];
}

$stmt->close();
$mysqli->close();

echo json_encode(['status' => 'success', 'assignments' => $assignments]);
