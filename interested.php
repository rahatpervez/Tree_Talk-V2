<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// must be logged in
if (!is_logged_in()) {
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Login required',
        'redirect' => 'auth.php'
    ]);
    exit;
}

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

if ($event_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event id']);
    exit;
}

try {
    // check existing interest
    $chk = $conn->prepare("SELECT id FROM interested WHERE event_id = ? AND user_id = ?");
    $chk->bind_param("ii", $event_id, $user_id);
    $chk->execute();
    $res = $chk->get_result();

    if ($res && $res->num_rows > 0) {
        // remove (toggle off)
        $row = $res->fetch_assoc();
        $chk->close();

        $del = $conn->prepare("DELETE FROM interested WHERE id = ?");
        $del->bind_param("i", $row['id']);
        $del->execute();
        $del->close();

        $user_interested = false;
    } else {
        $chk->close();

        // add (toggle on)
        $ins = $conn->prepare("INSERT INTO interested (event_id, user_id, created_at) VALUES (?, ?, NOW())");
        $ins->bind_param("ii", $event_id, $user_id);
        $ins->execute();
        $ins->close();

        $user_interested = true;
    }

    // updated count
    $countQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM interested WHERE event_id = ?");
    $countQ->bind_param("i", $event_id);
    $countQ->execute();
    $cntRes = $countQ->get_result();
    $count  = (int)($cntRes->fetch_assoc()['cnt'] ?? 0);
    $countQ->close();

    echo json_encode(['status' => 'success', 'count' => $count, 'user_interested' => $user_interested]);
} catch (Throwable $e) {
    $msg = DEV_MODE ? ('Error: ' . $e->getMessage()) : 'Server error';
    echo json_encode(['status' => 'error', 'message' => $msg]);
}
