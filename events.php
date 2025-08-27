<?php 
require_once __DIR__ . '/config.php';

// Fetch events from DB with interested count and author name
$events_sql = "
  SELECT
    e.*,
    u.fullname AS author_name,
    u.profile_pic,
    (SELECT COUNT(*) FROM interested i WHERE i.event_id = e.id) AS interested_count
  FROM events e
  JOIN users u ON e.user_id = u.id
  ORDER BY e.created_at DESC
";
$events_result = $conn->query($events_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Events | TreeTalk</title>
<link rel="stylesheet" href="style.css" />
<style>
.container { max-width:1100px; margin:0 auto; padding:0 18px; }
.event-card{ background:#fafdff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(44,62,80,0.08); margin-bottom:24px; }
.event-card .inner{ display:flex; gap:20px; padding:20px; align-items:flex-start; }
.event-image{ width:180px; height:180px; object-fit:cover; border-radius:10px; flex-shrink:0; }
.event-meta .author-row{ display:flex; align-items:center; gap:10px; }
.event-meta .author-row img{ width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #b7e4c7; }
.event-meta .title{ font-size:1.2rem; color:#184d27; margin:8px 0; font-weight:700; }
.event-meta .desc{ color:#2b2b2b; margin:8px 0; }
.event-actions{ margin-top:12px; display:flex; gap:12px; align-items:center; }
.btn { padding:8px 16px; border-radius:8px; cursor:pointer; }
.btn-primary { background:#40916c; color:#fff; border:none; }
.btn-secondary { background:#e9f4ea; color:#234d36; border:none; }
.interested-btn.interested { background:#2d6a4f; color:#fff; cursor:default; }
.create-event-section{ max-width:900px; margin:20px auto 36px auto; background:#fff; padding:18px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
.simple-note{ text-align:center; color:#5c6b5b; margin-bottom:12px; }
@media (max-width:720px){
  .event-card .inner{ flex-direction:column; align-items:stretch; }
  .event-image{ width:100%; height:220px; }
}
</style>
</head>
<body>
<header>
  <div class="container">
    <nav>
      <a href="index.php" class="logo">Tree<span>Talk</span></a>
      <ul class="nav-links">
        <li><a href="index.php">Home</a></li>
        <li><a href="post.php">Posts</a></li>
        <li><a href="events.php" class="active">Events</a></li>
        <li><a href="marketplace.php">Marketplace</a></li>
        <li><a href="profile.php">Profile</a></li>
        <li>
          <?php if(isset($_SESSION['user_id'])): ?>
            <a href="auth.php">Logout</a>
          <?php else: ?>
            <a href="auth.php">Login/SignUp</a>
          <?php endif; ?>
        </li>
      </ul>
    </nav>
  </div>
</header>

<main class="container">
  <div style="text-align:center; margin:20px 0;">
    <?php if(isset($_SESSION['user_id'])): ?>
      <button id="event-post-btn" class="btn btn-primary">Event Post</button>
    <?php else: ?>
      <a href="auth.php" class="btn btn-primary">Login to Post Event</a>
    <?php endif; ?>
  </div>

  <?php if(isset($_SESSION['user_id'])): ?>
  <section id="create-event-section" class="create-event-section" style="display:none;">
    <div class="simple-note">Create an event — others will see it in the feed below.</div>
    <form id="event-form" enctype="multipart/form-data">
      <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <input type="text" name="title" id="event-title" placeholder="Event title" required style="flex:1; padding:10px; border-radius:6px; border:1px solid #ddd;">
        <input type="date" name="event_date" id="event-date" required style="padding:10px; border-radius:6px; border:1px solid #ddd;">
      </div>

      <div style="margin-top:12px;">
        <input type="text" name="location" id="event-location" placeholder="Location" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ddd;">
      </div>

      <div style="margin-top:12px;">
        <textarea name="description" id="event-description" rows="4" placeholder="Description" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ddd;"></textarea>
      </div>

      <div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap;">
        <select name="category" id="event-category" required style="padding:10px; border-radius:6px; border:1px solid #ddd;">
          <option value="">Select category</option>
          <option value="planting">Tree Planting</option>
          <option value="workshop">Workshop</option>
          <option value="conservation">Conservation</option>
          <option value="social">Social Gathering</option>
          <option value="other">Other</option>
        </select>

        <input type="file" name="image" id="event-image" accept="image/*">
      </div>

      <div style="margin-top:14px; display:flex; gap:10px; align-items:center;">
        <button type="submit" class="btn btn-primary">Create Event</button>
        <button type="button" id="cancel-event" class="btn btn-secondary">Cancel</button>
        <span id="create-event-msg" style="margin-left:12px;color:#2d6a4f;"></span>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="events-list" id="events-list">
    <?php while($e = $events_result->fetch_assoc()): ?>
      <div class="event-card" data-event-id="<?= (int)$e['id'] ?>">
        <div class="inner">
          <img class="event-image" src="<?= !empty($e['image']) ? 'uploads/'.htmlspecialchars($e['image']) : 'images/event-placeholder.jpg' ?>" alt="Event image">
          <div class="event-meta">
            <div class="author-row">
              <img src="<?= !empty($e['profile_pic']) ? 'uploads/'.htmlspecialchars($e['profile_pic']) : 'images/default-profile.png' ?>" alt="Author">
              <div>
                <div style="font-weight:700;color:#184d27;"><?= htmlspecialchars($e['author_name']) ?></div>
                <div style="font-size:0.95rem;color:#6b7f6d;"><?= date("F j, Y", strtotime($e['event_date'])) ?></div>
              </div>
              <div style="margin-left:10px;">
                <span style="display:inline-block;background:#e6f4ea;color:#2d6a4f;padding:4px 10px;border-radius:8px;font-weight:600;"><?= htmlspecialchars($e['category']) ?></span>
              </div>
            </div>

            <h3 class="title"><?= htmlspecialchars($e['title']) ?></h3>
            <p class="desc"><?= nl2br(htmlspecialchars($e['description'])) ?></p>

            <div style="display:flex;gap:12px;align-items:center; margin-top:10px;">
              <div style="color:#40916c;">📍 <?= htmlspecialchars($e['location']) ?></div>
              <div style="color:#6c757d;"><?= date("F j, Y, g:i a", strtotime($e['created_at'])) ?></div>
            </div>

            <div class="event-actions">
              <?php $interestedCount = (int)$e['interested_count']; ?>
              <?php if(isset($_SESSION['user_id'])): ?>
                <button class="btn interested-btn" data-event-id="<?= (int)$e['id'] ?>">
                  Interested (<span class="interested-count"><?= $interestedCount ?></span>)
                </button>
              <?php else: ?>
                <a class="btn btn-secondary" href="auth.php">Login to mark Interested (<?= $interestedCount ?>)</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
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
// Elements
const eventPostBtn = document.getElementById('event-post-btn');
const createSection = document.getElementById('create-event-section');
const cancelEventBtn = document.getElementById('cancel-event');
const eventForm = document.getElementById('event-form');
const createMsg = document.getElementById('create-event-msg');
const eventsList = document.getElementById('events-list');

// Show create form
if(eventPostBtn){
  eventPostBtn.addEventListener('click', () => {
    createSection.style.display = 'block';
    window.scrollTo({ top: createSection.offsetTop - 20, behavior: 'smooth' });
  });
}
if(cancelEventBtn){
  cancelEventBtn.addEventListener('click', () => {
    createSection.style.display = 'none';
    createMsg.textContent = '';
    eventForm.reset();
  });
}

// Create event AJAX
if(eventForm){
  eventForm.addEventListener('submit', function(e){
    e.preventDefault();
    createMsg.style.color = '#2d6a4f';
    createMsg.textContent = 'Creating...';

    const fd = new FormData(this);
    fetch('create_event.php',{
      method:'POST',
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      if(data.status === 'success'){
        eventsList.insertAdjacentHTML('afterbegin', data.event_html);
        createMsg.textContent = 'Event created';
        eventForm.reset();
        createSection.style.display = 'none';
        initInterestedButtons();
        setTimeout(()=> createMsg.textContent='',2000);
      } else {
        createMsg.style.color='crimson';
        createMsg.textContent=data.message||'Could not create event';
      }
    }).catch(err => {
      console.error(err);
      createMsg.style.color='crimson';
      createMsg.textContent='Server error';
    });
  });
}

// Interested buttons
function initInterestedButtons(){
  document.querySelectorAll('.interested-btn').forEach(btn=>{
    if(btn.dataset.bound) return;
    btn.dataset.bound='1';

    btn.addEventListener('click', function(){
      const eventId = this.dataset.eventId;
      const countSpan = this.querySelector('.interested-count');

      fetch('interested.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'event_id='+encodeURIComponent(eventId)
      })
      .then(r=>r.json())
      .then(data=>{
        if(data.status==='success'){
          // Update only the count; keep button HTML structure intact
          if (countSpan) countSpan.textContent = data.count;
          if(data.user_interested){
            this.classList.add('interested');
            this.classList.remove('btn-secondary');
          } else {
            this.classList.remove('interested');
          }
        } else {
          alert(data.message||'Could not update interest');
          if (data.redirect) window.location.href = data.redirect;
        }
      }).catch(err=>console.error(err));
    });
  });
}
initInterestedButtons();
</script>
</body>
</html>
