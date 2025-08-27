<?php
require_once __DIR__ . '/config.php';

// =================== Session Check ===================
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header("Location: auth.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// =================== Helpers ===================
function safe_filename(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

function store_uploaded_image(array $file): ?string {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    // allow-list mime
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) return null;
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) return null; // 3MB

    $ext   = $allowed[$mime];
    $base  = safe_filename(pathinfo($file['name'], PATHINFO_FILENAME));
    $fname = time() . '_' . mt_rand(1000,9999) . '_' . $base . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fname)) {
        return $fname; // DB-তে শুধু ফাইলনেম রাখি
    }
    return null;
}

// =================== Logout (your existing flow) ===================
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: auth.php");
    exit();
}

// =================== Fetch User Data ===================
try {
    $stmt = $conn->prepare("SELECT id, fullname, email, password, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {
    $user = null;
}

if (!$user) {
    // ইউজার না পেলে লগআউট করে দিই
    header("Location: auth.php");
    exit();
}

// =================== Update Profile ===================
if (isset($_POST['update_profile'])) {
    $new_name     = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    // fallback current values
    $img_name  = $user['profile_pic'];
    $pass_hash = $user['password'];

    // profile pic (optional)
    if (!empty($_FILES['profile_pic']['name'])) {
        $saved = store_uploaded_image($_FILES['profile_pic']);
        if ($saved) {
            $img_name = $saved;
        }
    }

    // password (optional)
    if ($new_password !== '') {
        $pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
    }

    if ($new_name === '') {
        $update_error = "Name cannot be empty.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, password = ?, profile_pic = ? WHERE id = ?");
            $stmt->bind_param("sssi", $new_name, $pass_hash, $img_name, $user_id);
            $stmt->execute();
            $stmt->close();

            // session update
            $_SESSION['fullname']    = $new_name;
            if ($img_name !== $user['profile_pic']) {
                $_SESSION['profile_pic'] = $img_name;
            }

            header("Location: profile.php");
            exit();
        } catch (Throwable $e) {
            $update_error = DEV_MODE ? ('Update failed: ' . $e->getMessage()) : 'Update failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | TreeTalk</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="container">
        <nav>
            <a href="index.php" class="logo">Tree<span>Talk</span></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="post.php">Posts</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="marketplace.php">Marketplace</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><a href="auth.php">Login/SignUp</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container">
    <section class="profile-section py-3">
        <h1 class="profile-title">Your Profile</h1>
        
        <div class="profile-container">
            <div class="profile-picture-container">
                <img src="uploads/<?php echo htmlspecialchars($user['profile_pic'] ?: 'default-pic.jpg'); ?>" alt="Profile Picture" class="profile-picture">
            </div>
            
            <div class="profile-info">
                <div class="profile-field">
                    <label>Username:</label>
                    <span class="profile-value"><?php echo htmlspecialchars($user['fullname']); ?></span>
                </div>
                
                <div class="profile-field">
                    <label>Email:</label>
                    <span class="profile-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="profile-settings">
            <h2 class="settings-title">Settings</h2>
            <?php if(isset($update_error)) echo "<p style='color:red;'>".htmlspecialchars($update_error)."</p>"; ?>
            <form class="settings-form" method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="new-username">Change Username:</label>
                    <input type="text" id="new-username" name="new_username" placeholder="New Username" value="<?php echo htmlspecialchars($user['fullname']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="new-password">Change Password:</label>
                    <input type="password" id="new-password" name="new_password" placeholder="New Password">
                </div>

                <div class="form-group">
                    <label for="profile-pic">Change Profile Picture:</label>
                    <input type="file" id="profile-pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp">
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    <a href="profile.php?logout=true" class="btn btn-danger">Log Out</a>
                </div>
            </form>
        </div>
    </section>
</main>

 <footer class="simple-footer">
            <div class="container">
                <div class="footer-box" style="text-align: center;">
                    <h3 style="margin-bottom: 18px;">Connect With Us</h3>
                    <div class="contact-line" style="margin-bottom: 18px;">
                        <span class="contact-icon" style="color:#ff69b4;font-size:1.5em;vertical-align:middle;">&#128222;</span>
                        <span style="margin-left:8px;font-size:1.15em;vertical-align:middle;">+8801747-525412</span>
                    </div>
                    <button 
                        onclick="window.open('mailto:rahat.parvez.cse@ulab.edu.bd');"
                        style="
                          background: #333;
                          border: none;
                          border-radius: 50%;
                          width: 56px;
                          height: 56px;
                          display: flex;
                          align-items: center;
                          justify-content: center;
                          cursor: pointer;
                          margin: 0 auto;
                          box-shadow: 0 2px 8px rgba(0,0,0,0.12);
                          transition: background 0.2s;
                        "
                        aria-label="Email"
                    >
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                          <rect width="24" height="24" rx="12" fill="#333"/>
                          <path d="M6 8h12v8H6V8zm0 0l6 5 6-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        </svg>
                    </button>
                    <div class="container" style="text-align:center;">
        <p>© TreeTalk 2025 </p>
    </div>
                </div>
            </div>
        </footer>
</body>
</html>
