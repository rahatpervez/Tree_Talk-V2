<?php
require_once __DIR__ . '/config.php';

/**
 * TreeTalk — Posts page
 * - Guests can view posts
 * - Logged-in users can create posts via AJAX (create_post.php)
 * - Like uses like_post.php (expects JSON)
 */

// Fetch posts (latest first)
try {
    $sql = "SELECT p.id, p.user_id, p.description, p.image, p.created_at,
                   u.fullname AS username, u.profile_pic, u.email
            FROM posts p
            JOIN users u ON u.id = p.user_id
            ORDER BY p.created_at DESC";
    $posts_result = $conn->query($sql);
} catch (Throwable $e) {
    $posts_result = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Posts | TreeTalk</title>
<link rel="stylesheet" href="style.css">
<style>
/* তোমার আগের স্টাইলই রেখেছি, সামান্য সেফ ডিফল্ট */
.create-post-section { display:none; max-width:800px;margin:auto;padding:1rem;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
.post-card{background:#fff;padding:1rem;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,0.1);margin-bottom:1rem;}
.post-header{display:flex;gap:.75rem;align-items:center;}
.post-header img{width:35px;height:35px;border-radius:50%;object-fit:cover;}
.form-group{margin-bottom:.75rem}
textarea{width:100%;min-height:100px;padding:.6rem;border:1px solid #ddd;border-radius:6px}
input[type="file"]{width:100%}
.btn{cursor:pointer}
</style>
</head>
<body>
<header>
    <div class="container">
        <nav>
            <a href="index.php" class="logo">Tree<span>Talk</span></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="post.php" class="active">Posts</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="marketplace.php">Marketplace</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="auth.php">Log Out</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container py-3">
    <div style="display:flex;justify-content:center; margin-bottom:20px;">
        <button id="add-post-btn" class="btn btn-primary">Create Post</button>
    </div>

    <!-- Create Post Form (AJAX) -->
    <section class="create-post-section" id="create-post-section">
        <form id="post-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="post-description">Description:</label>
                <textarea id="post-description" name="description" required></textarea>
            </div>
            <div class="form-group">
                <label for="post-image">Image (optional):</label>
                <input type="file" id="post-image" name="image" accept="image/*">
                <div id="image-preview" style="margin-top:10px;"></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Post</button>
                <button type="button" id="cancel-post" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </section>

    <section class="posts-feed py-2" id="posts-feed">
        <?php if (!$posts_result || $posts_result->num_rows === 0): ?>
            <div class="post-card">
                <div class="post-header">
                    <img src="images/user1.jpg" alt="User">
                    <div>
                        <div class="post-username">TreeEnthusiast</div>
                        <span class="post-date">No posts yet</span>
                    </div>
                </div>
                <div class="post-content">
                    <p>Be the first to share your experience!</p>
                </div>
            </div>
        <?php else: ?>
            <?php while ($post = $posts_result->fetch_assoc()): ?>
                <?php
                // like count (prepared)
                $like_count = 0;
                try {
                    $like_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
                    $like_stmt->bind_param("i", $post['id']);
                    $like_stmt->execute();
                    $resCnt = $like_stmt->get_result();
                    $like_count = (int)($resCnt->fetch_assoc()['total'] ?? 0);
                    $like_stmt->close();
                } catch (Throwable $e) {
                    $like_count = 0;
                }

                $avatar = !empty($post['profile_pic']) ? 'uploads/' . htmlspecialchars($post['profile_pic']) : 'default.png';
                ?>
                <div class="post-card" data-post-id="<?= (int)$post['id'] ?>">
                    <div class="post-header">
                        <img src="<?= $avatar ?>" alt="User">
                        <div>
                            <div class="post-username"><?= htmlspecialchars($post['username']) ?></div>
                            <span class="post-date"><?= htmlspecialchars($post['created_at']) ?></span>
                        </div>
                    </div>
                    <div class="post-content">
                        <p><?= nl2br(htmlspecialchars($post['description'])) ?></p>
                        <?php if (!empty($post['image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($post['image']) ?>" style="max-width:100%;margin-top:10px;border-radius:6px;">
                        <?php endif; ?>
                    </div>
                    <div class="post-actions">
                        <button class="action-btn like-btn">Like (<?= $like_count ?>)</button>
                        <button class="action-btn email-btn"
                                onclick="window.location.href='mailto:<?= htmlspecialchars($post['email'] ?? '') ?>?subject=Check%20this%20post&body=See%20this%20post%20by%20<?= rawurlencode($post['username']) ?>';">
                            Email
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
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
const loggedIn = <?= is_logged_in() ? 'true' : 'false' ?>;

const addPostBtn = document.getElementById('add-post-btn');
const createPostSection = document.getElementById('create-post-section');
const cancelPostBtn = document.getElementById('cancel-post');
const postImage = document.getElementById('post-image');
const imagePreview = document.getElementById('image-preview');

addPostBtn.addEventListener('click', () => {
    if (!loggedIn) {
        // guest হলে auth.php তে পাঠাই
        window.location.href = 'auth.php';
        return;
    }
    createPostSection.style.display = 'block';
});

cancelPostBtn.addEventListener('click', () => { createPostSection.style.display = 'none'; });

// Image preview
postImage.addEventListener('change', function(){
    const file = this.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = function(e){
            imagePreview.innerHTML = `<img src="${e.target.result}" style="max-width:100%;border-radius:6px;">`;
        }
        reader.readAsDataURL(file);
    } else { imagePreview.innerHTML = ''; }
});

// Submit Post via AJAX (unchanged flow)
document.getElementById('post-form').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('create_post.php', {
        method:'POST',
        body:formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success'){
            // new post on top
            document.getElementById('posts-feed').insertAdjacentHTML('afterbegin', data.post_html);
            this.reset();
            imagePreview.innerHTML='';
            createPostSection.style.display='none';
            setupLikeButtons(); // re-init like buttons for the new card
        } else {
            alert(data.message || 'Failed to post');
        }
    })
    .catch(err => console.error(err));
});

// Like button toggle (unchanged)
function setupLikeButtons(){
    document.querySelectorAll('.like-btn').forEach(btn=>{
        btn.onclick = function(){
            const postCard = btn.closest('.post-card');
            const postId = postCard.dataset.postId;
            fetch('like_post.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'post_id='+encodeURIComponent(postId)
            })
            .then(res=>res.json())
            .then(data=>{
                if(data.status==='success'){
                    btn.textContent = `Like (${data.like_count})`;
                } else {
                    alert(data.message || 'Failed to like');
                    if (data.redirect) window.location.href = data.redirect;
                }
            });
        }
    });
}
setupLikeButtons();
</script>
</body>
</html>
