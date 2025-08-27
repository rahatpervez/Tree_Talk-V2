<?php
// create_event.php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// only logged-in users can create
if (!is_logged_in()) {
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Login required',
        'redirect' => 'auth.php'
    ]);
    exit;
}

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$title      = trim($_POST['title'] ?? '');
$event_date = trim($_POST['event_date'] ?? ''); // expected YYYY-MM-DD
$location   = trim($_POST['location'] ?? '');
$description= trim($_POST['description'] ?? '');
$category   = trim($_POST['category'] ?? '');
$image_name = null;

// basic validation
if ($title === '' || $event_date === '' || $location === '' || $description === '' || $category === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields']);
    exit;
}

// (optional) light date sanity: must be a YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event date']);
    exit;
}

// --- Image upload (optional) with MIME validation ---
function store_uploaded_image(array $file): ?string {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    // allow-list MIME types
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) return null;            // invalid type
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) return null; // >3MB

    $ext   = $allowed[$mime];
    $base  = pathinfo($file['name'], PATHINFO_FILENAME);
    $base  = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
    $name  = time() . '_' . mt_rand(1000, 9999) . '_' . $base . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return $name; // store only filename
    }
    return null;
}

if (!empty($_FILES['image']['name'])) {
    $saved = store_uploaded_image($_FILES['image']);
    if ($saved) {
        $image_name = $saved;
    } // invalid/too big হলে ছবি বাদ, ইভেন্ট তবু তৈরি হবে
}

// insert into DB
try {
    $stmt = $conn->prepare(
        "INSERT INTO events (user_id, title, event_date, location, category, description, image, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("issssss", $user_id, $title, $event_date, $location, $category, $description, $image_name);
    $stmt->execute();
    $event_id = $stmt->insert_id ?? $conn->insert_id;
    $stmt->close();
} catch (Throwable $e) {
    $msg = DEV_MODE ? ('Database error: ' . $e->getMessage()) : 'Database error';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// get user info for rendering (your schema uses fullname)
try {
    $u = $conn->prepare("SELECT fullname, profile_pic FROM users WHERE id = ?");
    $u->bind_param("i", $user_id);
    $u->execute();
    $user_info = $u->get_result()->fetch_assoc();
    $u->close();
} catch (Throwable $e) {
    $user_info = ['fullname' => 'User', 'profile_pic' => null];
}

// build HTML for the new event (must match events.php card structure)
$img_src     = $image_name ? 'uploads/' . htmlspecialchars($image_name) : 'images/event-placeholder.jpg';
$profile_src = !empty($user_info['profile_pic']) ? 'uploads/' . htmlspecialchars($user_info['profile_pic']) : 'images/default-profile.png';
$safe_title  = htmlspecialchars($title);
$safe_desc   = nl2br(htmlspecialchars($description));
$safe_loc    = htmlspecialchars($location);
$safe_cat    = htmlspecialchars($category);
$author      = htmlspecialchars($user_info['fullname'] ?? 'User');
$created_at  = date('Y-m-d H:i:s');

$event_html = '
<div class="event-card" data-event-id="'. (int)$event_id .'">
  <div class="inner">
    <img class="event-image" src="'. $img_src .'" alt="Event image">
    <div class="event-meta">
      <div class="author-row">
        <img src="'. $profile_src .'" alt="Author">
        <div>
          <div style="font-weight:700;color:#184d27;">'. $author .'</div>
          <div style="font-size:0.95rem;color:#6b7f6d;">'. date("F j, Y", strtotime($event_date)) .'</div>
        </div>
        <div style="margin-left:10px;">
          <span style="display:inline-block;background:#e6f4ea;color:#2d6a4f;padding:4px 10px;border-radius:8px;font-weight:600;">'. $safe_cat .'</span>
        </div>
      </div>
      <h3 class="title">'. $safe_title .'</h3>
      <p class="desc">'. $safe_desc .'</p>
      <div style="display:flex;gap:12px;align-items:center; margin-top:10px;">
        <div style="color:#40916c;">📍 '. $safe_loc .'</div>
        <div style="color:#6c757d;">'. $created_at .'</div>
      </div>
      <div class="event-actions">
        <button class="btn interested-btn" data-event-id="'. (int)$event_id .'">
          Interested (<span class="interested-count">0</span>)
        </button>
      </div>
    </div>
  </div>
</div>';

echo json_encode(['status' => 'success', 'event_html' => $event_html]);
