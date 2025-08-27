<?php 
require_once __DIR__ . '/config.php';

// Fetch marketplace listings (latest first)
try {
    $sql = "SELECT m.id, m.user_id, m.title, m.price, m.description, m.image, m.created_at,
                   u.fullname, u.email, u.profile_pic
            FROM marketplace m
            JOIN users u ON u.id = m.user_id
            ORDER BY m.created_at DESC";
    $items = $conn->query($sql);
} catch (Throwable $e) {
    $items = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace | TreeTalk</title>
    <link rel="stylesheet" href="<?= base_url('style.css') ?>">
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="<?= base_url('index.php') ?>" class="logo">Tree<span>Talk</span></a>
                <ul class="nav-links">
                    <li><a href="<?= base_url('index.php') ?>">Home</a></li>
                    <li><a href="<?= base_url('post.php') ?>">Posts</a></li>
                    <li><a href="<?= base_url('events.php') ?>">Events</a></li>
                    <li><a href="<?= base_url('marketplace.php') ?>" class="active">Marketplace</a></li>
                    <li><a href="<?= base_url('profile.php') ?>">Profile</a></li>
                    <li><a href="<?= base_url('auth.php') ?>"><?= is_logged_in() ? 'Logout' : 'Login/SignUp' ?></a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="marketplace-hero py-2">
            <h1>TreeTalk Marketplace</h1>
            <p class="mt-1">Buy, sell, or trade plants, seeds, and gardening supplies with our community</p>
        </section>

        <section class="marketplace-actions py-1" style="display:flex;flex-direction:column;align-items:center;">
            <!-- <div class="search-bar" style="margin-bottom:12px;">
                <input type="text" placeholder="Search for plants...">
                <button class="btn">Search</button>
            </div> -->

            <?php if (is_logged_in()): ?>
            <button id="list-item-btn"
                class="btn btn-primary"
                style="
                    width: 140px;
                    padding: 8px 0;
                    font-size: 1em;
                    border-radius: 6px;
                    margin: 0 auto;
                    display: block;
                    background: #46653b;
                    border: none;
                    box-shadow: 0 2px 0 #b7e4c7;
                    position: relative;
                ">
                List an Item
                <span style="
                    display: block;
                    height: 2px;
                    width: 32px;
                    background: #38b000;
                    margin: 4px auto 0 auto;
                    border-radius: 2px;
                "></span>
            </button>
            <?php else: ?>
            <a href="<?= base_url('auth.php') ?>" class="btn btn-primary">Login to List Item</a>
            <?php endif; ?>
        </section>

        <!-- List Item Card (hidden by default) -->
        <?php if (is_logged_in()):
            $me_name = htmlspecialchars($_SESSION['fullname'] ?? 'You');
            $me_pic  = !empty($_SESSION['profile_pic']) ? base_url('uploads/' . $_SESSION['profile_pic']) : base_url('images/user1.jpg');
        ?>
        <section id="list-item-section" style="display:none;max-width:420px;margin:24px auto 0 auto;">
            <div class="product-card" style="box-shadow:0 4px 16px rgba(44,62,80,0.10);border-radius:14px;padding:18px 18px 14px 18px;background:#fafdff;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                    <img src="<?= $me_pic ?>" alt="User" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                    <div>
                        <div style="font-weight:600;font-size:1.08em;"><?= $me_name ?></div>
                        <span id="listing-date" style="font-size:0.97em;color:#5c7f67;"></span>
                    </div>
                </div>
                <form id="list-item-form" enctype="multipart/form-data">
                    <input type="text" id="product-name" name="title" placeholder="Product Name" required style="width:100%;margin-bottom:8px;padding:6px;border-radius:5px;border:1px solid #cce3de;">
                    <textarea id="product-description" name="description" rows="3" placeholder="Description" required style="width:100%;margin-bottom:8px;padding:6px;border-radius:5px;border:1px solid #cce3de;"></textarea>
                    <input type="file" id="product-image" name="image" accept="image/*" style="margin-bottom:8px;">
                    <input type="text" id="product-price" name="price" placeholder="Price (e.g. 25.00)" required style="width:100%;margin-bottom:8px;padding:6px;border-radius:5px;border:1px solid #cce3de;">
                    <input type="text" id="product-location" name="location" placeholder="Location (optional)" style="width:100%;margin-bottom:8px;padding:6px;border-radius:5px;border:1px solid #cce3de;">
                    <button type="submit" class="btn btn-primary" style="margin-top:6px;">Submit</button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section class="marketplace-categories py-1">
            <h2>Categories</h2>
            <div class="category-tags">
                <a href="#" class="tag">Trees</a>
                <a href="#" class="tag">Houseplants</a>
                <a href="#" class="tag">Seeds</a>
                <a href="#" class="tag">Gardening Tools</a>
                <a href="#" class="tag">Pots & Planters</a>
                <a href="#" class="tag">Fertilizers</a>
            </div>
        </section>

        <section class="product-listings py-2">
            <h2>Recent Listings</h2>
            <div class="product-grid" id="product-grid">
                <?php if (!$items || $items->num_rows === 0): ?>
                    <!-- Fallback static cards (unchanged) -->
                    <div class="product-card">
                        <img src="images/product1.jpg" alt="Monstera Plant">
                        <div class="product-info">
                            <h3>Monstera Deliciosa</h3>
                            <p class="price">BDT25.00</p>
                            <p class="seller">From: PlantLover22</p>
                            <a href="#" class="btn">View Details</a>
                        </div>
                    </div>
                    <div class="product-card">
                        <img src="images/product2.jpg" alt="Bonsai Kit">
                        <div class="product-info">
                            <h3>Beginner Bonsai Kit</h3>
                            <p class="price">BDT45.00</p>
                            <p class="seller">From: BonsaiMaster</p>
                            <a href="#" class="btn">View Details</a>
                        </div>
                    </div>
                    <div class="product-card">
                        <img src="images/product3.jpg" alt="Rare Seeds">
                        <div class="product-info">
                            <h3>Rare Blue Jacaranda Seeds</h3>
                            <p class="price">BDT12.00</p>
                            <p class="seller">From: SeedCollector</p>
                            <a href="#" class="btn">View Details</a>
                        </div>
                    </div>
                    <div class="product-card">
                        <img src="images/product4.jpg" alt="Ceramic Pot">
                        <div class="product-info">
                            <h3>Handmade Ceramic Pot</h3>
                            <p class="price">BDT18.00</p>
                            <p class="seller">From: PotteryArt</p>
                            <a href="#" class="btn">View Details</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($it = $items->fetch_assoc()): 
                        $title   = htmlspecialchars($it['title']);
                        $price   = is_null($it['price']) ? '' : number_format((float)$it['price'], 2);
                        $desc    = nl2br(htmlspecialchars($it['description'] ?? ''));
                        $img     = !empty($it['image']) ? 'uploads/' . htmlspecialchars($it['image']) : 'images/product1.jpg';
                        $seller  = htmlspecialchars($it['fullname']);
                        $email   = htmlspecialchars($it['email'] ?? '');
                        // optional profile (not shown in card top to keep your UI)
                    ?>
                    <div class="product-card" data-id="<?= (int)$it['id'] ?>">
                        <img src="<?= $img ?>" alt="<?= $title ?>">
                        <div class="product-info">
                            <h3><?= $title ?></h3>
                            <?php if ($price !== ''): ?>
                                <p class="price">BDT<?= $price ?></p>
                            <?php endif; ?>
                            <p class="seller">From: <?= $seller ?></p>
                            <p class="location" style="color:#40916c;font-size:0.97em;">
                                <!-- লোকেশন পুরনো রেকর্ডে নাও থাকতে পারে -->
                            </p>
                            <p class="desc" style="margin-top:6px;"><?= $desc ?></p>
                            <?php if ($email): ?>
                                <a href="mailto:<?= $email ?>?subject=Regarding%20your%20listing%20on%20TreeTalk" class="btn">Contact</a>
                            <?php else: ?>
                                <a href="#" class="btn">View Details</a>
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

<script>
const loggedIn = <?= is_logged_in() ? 'true' : 'false' ?>;

// Show the List Item card when button is clicked
const listBtn = document.getElementById('list-item-btn');
const listSection = document.getElementById('list-item-section');
if (listBtn) {
    listBtn.addEventListener('click', function() {
        if (!loggedIn) { window.location.href = '<?= base_url('auth.php') ?>'; return; }
        if (listSection) {
            listSection.style.display = 'block';
            // Set current date and time
            const now = new Date();
            const el = document.getElementById('listing-date');
            if (el) el.textContent = now.toLocaleString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        }
    });
}

// Handle form submission via AJAX to save in DB
const form = document.getElementById('list-item-form');
if (form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);

        fetch('create_market.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                // Insert new product card
                document.getElementById('product-grid').insertAdjacentHTML('afterbegin', data.product_html);
                // Hide form & reset
                listSection.style.display = 'none';
                this.reset();
            } else {
                alert(data.message || 'Could not create listing');
                if (data.redirect) window.location.href = data.redirect;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Server error');
        });
    });
}
</script>

</body>
</html>
