<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Guest -> send redirect hint for frontend
if (!is_logged_in()) {
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Login required',
        'redirect' => 'auth.php'
    ]);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

if ($post_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid post ID']);
    exit;
}

try {
    // Check if already liked
    $stmt = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        // Already liked -> remove (toggle off)
        $row = $res->fetch_assoc();
        $like_id = (int)$row['id'];
        $stmt->close();

        $stmtDel = $conn->prepare("DELETE FROM likes WHERE id = ?");
        $stmtDel->bind_param("i", $like_id);
        $stmtDel->execute();
        $stmtDel->close();
    } else {
        // Not liked -> insert
        $stmt->close();
        $stmtIns = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
        $stmtIns->bind_param("ii", $post_id, $user_id);
        $stmtIns->execute();
        $stmtIns->close();
    }

    // Updated like count
    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
    $stmtCnt->bind_param("i", $post_id);
    $stmtCnt->execute();
    $cntRes = $stmtCnt->get_result();
    $like_count = (int)($cntRes->fetch_assoc()['total'] ?? 0);
    $stmtCnt->close();

    echo json_encode(['status' => 'success', 'like_count' => $like_count]);
} catch (Throwable $e) {
    $msg = DEV_MODE ? ('Like failed: ' . $e->getMessage()) : 'Like failed';
    echo json_encode(['status' => 'error', 'message' => $msg]);
}
