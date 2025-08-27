<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Must be logged in
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
$description= trim($_POST['description'] ?? '');
$price_raw  = trim($_POST['price'] ?? '');
$location   = trim($_POST['location'] ?? ''); // optional
$image_name = null;

// -------- Basic validation --------
if ($title === '' || $description === '' || $price_raw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields']);
    exit;
}

// make price numeric (allow "BDT25.00", "25", "25.5")
$price_num = preg_replace('/[^0-9.\-]/', '', $price_raw);
if ($price_num === '' || !is_numeric($price_num)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid price']);
    exit;
}
$price = (float)$price_num;
if ($price < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Price cannot be negative']);
    exit;
}

// -------- Helpers --------
function safe_filename(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

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
    $base  = safe_filename(pathinfo($file['name'], PATHINFO_FILENAME));
    $name  = time() . '_' . mt_rand(1000,9999) . '_' . $base . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return $name; // store only filename
    }
    return null;
}

// optional image
if (!empty($_FILES['image']['name'])) {
    $saved = store_uploaded_image($_FILES['image']);
    if ($saved) $image_name = $saved;
}

// detect if marketplace.location column exists
$has_location = false;
try {
    $colRes = $conn->query("SHOW COLUMNS FROM marketplace LIKE 'location'");
    if ($colRes && $colRes->num_rows > 0) $has_location = true;
} catch (Throwable $e) {
    $has_location = false; // safe default
}

// -------- Insert into DB --------
try {
    if ($has_location) {
        $stmt = $conn->prepare(
            "INSERT INTO marketplace (user_id, title, price, description, image, location, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("isdsss", $user_id, $title, $price, $description, $image_name, $location);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO marketplace (user_id, title, price, description, image, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("isdss", $user_id, $title, $price, $description, $image_name);
    }
    $stmt->execute();
    $item_id = $stmt->insert_id ?? $conn->insert_id;
    $stmt->close();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

// -------- Fetch seller (for email/contact) --------
try {
    $u = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $u->bind_param("i", $user_id);
    $u->execute();
    $seller = $u->get_result()->fetch_assoc();
    $u->close();
} catch (Throwable $e) {
    $seller = ['fullname' => 'Seller', 'email' => ''];
}

// -------- Build product card HTML (matches marketplace.php) --------
$title_html = htmlspecialchars($title);
$desc_html  = nl2br(htmlspecialchars($description));
$price_txt  = 'BDT' . number_format($price, 2);
$img_src    = $image_name ? 'uploads/' . htmlspecialchars($image_name) : 'images/product1.jpg';
$seller_nm  = htmlspecialchars($seller['fullname'] ?? 'Seller');
$seller_em  = htmlspecialchars($seller['email'] ?? '');
$loc_html   = $location !== '' ? '<p class="location" style="color:#40916c;font-size:0.97em;">📍 ' . htmlspecialchars($location) . '</p>' : '';

$contact_btn = $seller_em
    ? '<a href="mailto:' . $seller_em . '?subject=Regarding%20your%20listing%20on%20TreeTalk" class="btn">Contact</a>'
    : '<a href="#" class="btn">View Details</a>';

$product_html = '
<div class="product-card" data-id="'. (int)$item_id .'">
    <img src="'. $img_src .'" alt="'. $title_html .'">
    <div class="product-info">
        <h3>'. $title_html .'</h3>
        <p class="price">'. $price_txt .'</p>
        <p class="seller">From: '. $seller_nm .'</p>
        '. $loc_html .'
        <p class="desc" style="margin-top:6px;">'. $desc_html .'</p>
        '. $contact_btn .'
    </div>
</div>';

echo json_encode(['status' => 'success', 'product_html' => $product_html]);
