<?php
require_once __DIR__ . '/config.php';

// =================== HELPERS ===================
function safe_filename(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
}

function store_uploaded_image(array $file): ?string {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    // allow-list mime types
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
        return $fname; // DB-তে শুধু ফাইলনেম স্টোর করব
    }
    return null;
}

// =================== SIGN UP ===================
if (isset($_POST['signup'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass_raw = $_POST['password'] ?? '';

    if ($fullname === '' || $email === '' || $pass_raw === '') {
        $signup_error = 'All fields are required.';
    } else {
        try {
            // email unique?
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $signup_error = 'Email already registered. Please login.';
            }
            $stmt->close();

            // optional profile pic
            $img_name = null;
            if (empty($signup_error) && !empty($_FILES['profile_pic']['name'])) {
                $saved = store_uploaded_image($_FILES['profile_pic']);
                if ($saved) $img_name = $saved;
            }

            if (empty($signup_error)) {
                $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, profile_pic) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fullname, $email, $pass_hash, $img_name);
                $stmt->execute();
                $new_id = $stmt->insert_id ?? $conn->insert_id;
                $stmt->close();

                // session set
                $_SESSION['user_id']     = (int)$new_id;
                $_SESSION['fullname']    = $fullname;
                $_SESSION['email']       = $email;
                $_SESSION['profile_pic'] = $img_name;

                header("Location: index.php");
                exit();
            }
        } catch (Throwable $e) {
            $signup_error = DEV_MODE ? ('Signup error: ' . $e->getMessage()) : 'Signup failed. Please try again.';
        }
    }
}

// =================== LOGIN ===================
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $login_error = "Email and password are required.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, fullname, email, password, profile_pic FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($pass, $user['password'])) {
                    $_SESSION['user_id']     = (int)$user['id'];
                    $_SESSION['fullname']    = $user['fullname'];
                    $_SESSION['email']       = $user['email'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                    header("Location: index.php");
                    exit();
                } else {
                    $login_error = "Incorrect password.";
                }
            } else {
                $login_error = "User not found.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $login_error = DEV_MODE ? ('Login error: ' . $e->getMessage()) : 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login/Signup | TreeTalk</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <div class="auth-background"></div>

    <header>
        <div class="container">
            <nav>
                <a href="index.php" class="logo">Tree<span>Talk</span></a>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="post.php">Posts</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="marketplace.php">Marketplace</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="auth.php" class="active">Login/SignUp</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="auth-page">
        <section class="auth-container py-2">
            <div class="auth-tabs">
                <button class="tab-btn active" data-tab="login">Login</button>
                <button class="tab-btn" data-tab="signup">Sign Up</button>
            </div>

            <div class="auth-content">
                <div id="login" class="tab-content active">
                    <h2>Login</h2>
                    <?php if(isset($login_error)) echo "<p style='color:red;'>".htmlspecialchars($login_error)."</p>"; ?>
                    <form class="auth-form" method="POST" action="auth.php">
                        <div class="form-group">
                            <label for="login-email">Email:</label>
                            <input type="email" id="login-email" name="email" required />
                        </div>
                        <div class="form-group">
                            <label for="login-password">Password:</label>
                            <input type="password" id="login-password" name="password" required />
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="login" class="btn btn-primary">Login</button>
                        </div>
                        <p>Don't have an account? <a href="#" onclick="switchTab('signup')">Sign up</a></p>
                    </form>
                </div>

                <div id="signup" class="tab-content">
                    <h2>Create an Account</h2>
                    <?php if(isset($signup_error)) echo "<p style='color:red;'>".htmlspecialchars($signup_error)."</p>"; ?>
                    <form class="auth-form" method="POST" action="auth.php" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="signup-name">Full Name:</label>
                            <input type="text" id="signup-name" name="fullname" required />
                        </div>
                        <div class="form-group">
                            <label for="signup-email">Email:</label>
                            <input type="email" id="signup-email" name="email" required />
                        </div>
                        <div class="form-group">
                            <label for="signup-password">Password:</label>
                            <input type="password" id="signup-password" name="password" required />
                        </div>
                        <div class="form-group">
                            <label for="signup-pic">Profile Picture:</label>
                            <input type="file" id="signup-pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp" />
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="signup" class="btn btn-primary">Sign Up</button>
                        </div>
                        <p>Already have an account? <a href="#" onclick="switchTab('login')">Login</a></p>
                    </form>
                </div>
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

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab).classList.add('active');
            });
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            // fix: template string with backticks
            document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</body>
</html>
