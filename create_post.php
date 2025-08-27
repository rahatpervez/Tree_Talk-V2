<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Auth guard (AJAX-friendly)
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit;
}

$user_id     = (int) $_SESSION['user_id'];
$description = trim($_POST['description'] ?? '');
$image_name  = '';

// Basic validation
if ($description === '') {
    echo json_encode(['status' => 'error', 'message' => 'Description is required']);
    exit;
}

// ---------- Helpers ----------
function safe_filename(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

function store_uploaded_image(array $file): ?string {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null; // image optional
    }

    // MIME check (jpg/png/webp)
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return null; // invalid type -> ignore
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) { // 3MB limit
        return null; // too big -> ignore
    }

    $ext   = $allowed[$mime];
    $base  = pathinfo($file['name'], PATHINFO_FILENAME);
    $base  = safe_filename($base);
    $fname = time() . '_' . mt_rand(1000, 9999) . '_' . $base . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fname)) {
        return $fname; // store only filename (relative path later: uploads/<name>)
    }
    return null;
}
// -----------------------------

// Handle image upload (optional)
if (!empty($_FILES['image']['name'])) {
    $saved = store_uploaded_image($_FILES['image']);
    if ($saved) {
        $image_name = $saved;
    }
}

// Insert post
try {
    $stmt = $conn->prepare("INSERT INTO posts (user_id, description, image, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $description, $image_name);
    $stmt->execute();
    $post_id = $stmt->insert_id ?? $conn->insert_id;
    $stmt->close();
} catch (Throwable $e) {
    $msg = DEV_MODE ? 'DB error: ' . $e->getMessage() : 'Post could not be created';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Fetch user info (use fullname; your schema doesn’t have "username")
try {
    $stmtU = $conn->prepare("SELECT fullname, profile_pic, email FROM users WHERE id = ?");
    $stmtU->bind_param("i", $user_id);
    $stmtU->execute();
    $user = $stmtU->get_result()->fetch_assoc();
    $stmtU->close();
} catch (Throwable $e) {
    $user = ['fullname' => 'User', 'profile_pic' => null, 'email' => ''];
}

// Build HTML card (classes/structure unchanged)
$display_name   = $user['fullname'] ?? 'User';
$profile_pic    = $user['profile_pic'] ? 'uploads/' . htmlspecialchars($user['profile_pic']) : 'default.png';
$created_at     = date('Y-m-d H:i:s');
$desc_html      = nl2br(htmlspecialchars($description));
$image_html     = $image_name ? '<img src="uploads/' . htmlspecialchars($image_name) . '" style="max-width:100%;margin-top:10px;border-radius:6px;">' : '';
$email_subject  = rawurlencode('Check this post');
$email_body     = rawurlencode('See this post by ' . $display_name);

$post_html = '
<div class="post-card" data-post-id="' . (int)$post_id . '">
    <div class="post-header">
        <img src="' . $profile_pic . '" alt="User">
        <div>
            <div class="post-username">' . htmlspecialchars($display_name) . '</div>
            <span class="post-date">' . htmlspecialchars($created_at) . '</span>
        </div>
    </div>
    <div class="post-content">
        <p>' . $desc_html . '</p>' . $image_html . '
    </div>
    <div class="post-actions">
        <button class="action-btn like-btn">Like (0)</button>
        <button class="action-btn email-btn" onclick="window.location.href=\'mailto:?subject=' . $email_subject . '&body=' . $email_body . '\'">Email</button>
    </div>
</div>';

echo json_encode(['status' => 'success', 'post_html' => $post_html]);
