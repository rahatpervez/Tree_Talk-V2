<?php
require_once __DIR__ . '/config.php';

/**
 * NOTE:
 * - UI/HTML/CSS classes are kept same as your original file.
 * - Only the "Demo posts" grid now renders dynamic posts from DB (latest 20).
 * - Fallback avatar uses images/user1.jpg if profile_pic is empty.
 * - Like button redirects to like_post.php?post_id=... (guest -> auth.php)
 */

// Fetch recent posts
try {
    $stmt = $conn->prepare(
        "SELECT p.id, p.description, p.image, p.created_at,
                u.fullname, u.email, u.profile_pic
         FROM posts p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC
         LIMIT 20"
    );
    $stmt->execute();
    $postsRes = $stmt->get_result();
} catch (Throwable $e) {
    $postsRes = false; // show fallback card below
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TreeTalk - Connect with Nature</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="background-image"></div>
    <div class="content-wrapper">
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
                        <li><a href="auth.php">Login/SignUp</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main>
            <section class="welcome-section py-2">
                <div class="welcome-image">
                     <h1>Welcome to TreeTalk!</h1>
                </div>
                <!-- <div class="search-bar">
                    <input type="text" placeholder="Search for profiles or posts...">
                    <button class="btn">Search</button>
                </div> -->
                <div class="create-post-center">
                    <a href="post.php#create-post-section" class="btn btn-primary btn-small">Create Post</a>
                </div>
            </section>

            <!-- Feed -->
            <section class="posts-feed py-2">
                <div class="post-grid">
                    <?php if (!$postsRes || $postsRes->num_rows === 0): ?>
                        <!-- Fallback demo card (unchanged UI) -->
                        <div class="post-card left-aligned">
                            <div class="post-header">
                                <img src="images/user1.jpg" alt="User" class="avatar">
                                <div>
                                    <h3>TreeEnthusiast</h3>
                                    <small>No posts yet — be the first to share!</small>
                                </div>
                            </div>
                            <div class="post-content">
                                <p>It's our final showcasing. It's the show time.</p>
                            </div>
                            <div class="post-actions">
                                <button class="btn">Like</button>
                                <button class="btn">Email</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php while ($p = $postsRes->fetch_assoc()): ?>
                            <div class="post-card left-aligned" data-post-id="<?= (int)$p['id'] ?>">
                                <div class="post-header">
                                    <?php if (!empty($p['profile_pic'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($p['profile_pic']) ?>" alt="User" class="avatar">
                                    <?php else: ?>
                                        <img src="images/user1.jpg" alt="User" class="avatar">
                                    <?php endif; ?>
                                    <div>
                                        <h3><?= htmlspecialchars($p['fullname']) ?></h3>
                                        <small><?= htmlspecialchars($p['created_at']) ?></small>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <p><?= nl2br(htmlspecialchars($p['description'])) ?></p>
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="post" style="max-width:100%;margin-top:10px;border-radius:6px;">
                                    <?php endif; ?>
                                </div>
                                <!-- <div class="post-actions">
                                    <button
                                        class="btn"
                                        onclick="window.location.href='<?= is_logged_in() ? 'like_post.php?post_id=' . (int)$p['id'] : 'auth.php' ?>'">
                                        Like
                                    </button>
                                    <button
                                        class="btn"
                                        onclick="window.location.href='mailto:<?= htmlspecialchars($p['email']) ?>?subject=Regarding%20your%20TreeTalk%20post'">
                                        Email
                                    </button>
                                </div> -->
                                <div class="post-actions">
    <button class="btn like-btn">Like (<?= (int)($p['like_count'] ?? 0) ?>)</button>
    <?php if (!empty($p['email'])): ?>
      <a class="btn" href="mailto:<?= htmlspecialchars($p['email']) ?>?subject=Regarding%20your%20TreeTalk%20post"
         target="_blank" rel="noopener">Email</a>
    <?php endif; ?>
</div>

                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
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
    </div>
    <script>
// Like buttons (POST to like_post.php, then update count)
function setupLikeButtons(){
  document.querySelectorAll('.post-card .like-btn').forEach(btn=>{
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', ()=>{
      const card = btn.closest('.post-card');
      const postId = card?.dataset.postId;
      if (!postId) return;
      fetch('like_post.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId)
      })
      .then(r=>r.json())
      .then(data=>{
        if (data.status === 'success') {
          btn.textContent = `Like (${data.like_count})`;
        } else {
          alert(data.message || 'Failed');
          if (data.redirect) window.location.href = data.redirect;
        }
      })
      .catch(()=> alert('Network error'));
    });
  });
}
setupLikeButtons();
</script>

</body>
</html>
