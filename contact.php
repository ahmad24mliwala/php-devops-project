<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/track_visit.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

log_visit($pdo);

// Fetch dynamic contact details
$shop_address = get_setting('shop_address', $pdo) ?: "123 Pickle Street, Foodie Town, India";
$shop_phone = explode(',', get_setting('shop_phone', $pdo) ?: "+91 9876543210,+91 9123456780");
$shop_email = explode(',', get_setting('shop_email', $pdo) ?: "contact@avojifoods.com");
$whatsapp_number = get_setting('whatsapp_number', $pdo) ?: "+919876543210";
$clean_wa = preg_replace('/\D/', '', $whatsapp_number);

$map_iframe = get_setting('homepage_map_embed', $pdo) ?: '<iframe src="https://www.google.com/maps/embed?..."></iframe>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contact Us - Avoji Foods</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">

<style>
/* ===== base styles (kept) ===== */
.contact-info h5 { color: #388e3c; font-weight: 600; }
.contact-info p, .contact-info a { color: #000; text-decoration: none; }
.contact-info a:hover { color: #81c784; text-decoration: underline; }

.bg-lightgreen { background-color: #e8f5e9; }

/* Buttons */
.whatsapp-btn {
    background-color: #25D366; color: #fff;
    border-radius: 50px;
    padding: 10px 20px;
    font-weight: 600;
    display: inline-block;
    width: 100%;
    text-align: center;
    transition: transform 220ms ease, box-shadow 220ms ease;
    will-change: transform;
}
.whatsapp-btn:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 8px 20px rgba(37,211,102,0.18);
}

.email-btn {
    background-color: #1976d2; color: #fff;
    border-radius: 50px;
    padding: 10px 20px;
    font-weight: 600;
    margin-top: 10px;
    display: inline-block;
    width: 100%;
    text-align: center;
    transition: transform 220ms ease, box-shadow 220ms ease;
    will-change: transform;
}
.email-btn:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 8px 20px rgba(25,118,210,0.18);
}

/* Map */
.map-section {
    background: #f1f8e9;
    padding: 40px 0;
}
.map-container {
    width: 90%; max-width: 1100px;
    margin: auto; border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(18px) scale(0.995);
    transition: opacity 700ms ease, transform 700ms ease;
}
.map-container.animated-in {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.map-container iframe {
    width: 100%; height: 420px;
    border:0;
}

/* ===== slide-in animations for opposite sides ===== */
/* initial hidden states */
.slide-left, .slide-right {
    opacity: 0;
    transition: transform 700ms cubic-bezier(.2,.9,.2,1), opacity 700ms cubic-bezier(.2,.9,.2,1);
    will-change: transform, opacity;
}

/* slide in from left (form appears from left) */
.slide-left {
    transform: translateX(-40px);
}

/* slide in from right (contact appears from right) */
.slide-right {
    transform: translateX(40px);
}

/* when element comes into view apply these */
.in-view-left {
    opacity: 1 !important;
    transform: translateX(0) !important;
}

/* for right side */
.in-view-right {
    opacity: 1 !important;
    transform: translateX(0) !important;
}

/* Title fade-in (optional subtle) */
h2.animate-fade {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 650ms ease, transform 650ms ease;
}
h2.animate-fade.in-view {
    opacity: 1;
    transform: translateY(0);
}

/* small input focus lift */
.form-control:focus {
    box-shadow: 0 8px 24px rgba(56,142,60,0.08);
    border-color: #57b76a;
    transition: box-shadow 200ms ease, border-color 200ms ease;
}

/* accessibility: reduce motion preference */
@media (prefers-reduced-motion: reduce) {
    .slide-left, .slide-right, .map-container, h2.animate-fade {
        transition: none !important;
        transform: none !important;
        opacity: 1 !important;
    }
}
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <h2 class="text-center mb-4 animate-fade">Contact Us</h2>

    <div class="row g-4">
        <!-- ENQUIRY FORM -->
        <div class="col-md-6">
            <!-- slide-left: enquiry form will slide in from left -->
            <form id="enquiryForm" class="p-4 shadow-sm rounded bg-lightgreen slide-left">

                <div class="mb-3">
                    <input type="text" id="name" class="form-control" placeholder="Your Name" required>
                </div>

                <div class="mb-3">
                    <input type="email" id="email" class="form-control" placeholder="Your Email" required>
                </div>

                <div class="mb-3">
                    <input type="text" id="query" class="form-control" placeholder="Your Enquiry Subject" required>
                </div>

                <button type="submit" class="btn btn-success w-100">
                    Send Enquiry
                </button>

            </form>
        </div>

        <!-- CONTACT INFO -->
        <div class="col-md-6">
            <!-- slide-right: contact info will slide in from right -->
            <div class="contact-info p-4 shadow-sm rounded bg-light slide-right">
                
                <h5>Our Address</h5>
                <p><?= h($shop_address) ?></p>

                <h5>Phone Numbers</h5>
                <?php foreach ($shop_phone as $p): ?>
                    <p><a href="tel:<?=h(trim($p))?>">📞 <?=h(trim($p))?></a></p>
                <?php endforeach; ?>

                <h5>Email</h5>
                <?php foreach ($shop_email as $e): ?>
                    <p><a href="mailto:<?=h(trim($e))?>">✉️ <?=h(trim($e))?></a></p>
                <?php endforeach; ?>
                <!-- WHATSAPP BUTTON FIXED -->
<?php 
$waMsg = "Hello Avoji Foods, I want to enquire about Avoji foods products";
$waLink = "https://wa.me/" . $clean_wa . "?text=" . rawurlencode($waMsg);
?>
<a class="whatsapp-btn"
   href="<?= $waLink ?>" 
   target="_blank">💬 WhatsApp Us</a>

            </div>
        </div>
    </div>
</div>

<!-- Map -->
<section class="map-section">
    <div class="map-container" id="mapContainer">
        <?= $map_iframe ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
/* ===== form behaviour (unchanged logic) ===== */
document.getElementById("enquiryForm").addEventListener("submit", function(e){
    e.preventDefault();

    let name = document.getElementById("name").value.trim();
    let email = document.getElementById("email").value.trim();
    let query = document.getElementById("query").value.trim();

    let waNumber = "<?= $clean_wa ?>";

    let waMsg = `Hello, I want to enquire.\nName: ${name}\nEmail: ${email}\nQuery: ${query}`;
    let waLink = "https://wa.me/" + waNumber + "?text=" + encodeURIComponent(waMsg);

    let mailTo = "mailto:<?= trim($shop_email[0]) ?>?subject=Enquiry - " + encodeURIComponent(query)
               + "&body=" + encodeURIComponent(waMsg);

    // Auto open WhatsApp
    window.open(waLink, "_blank");

    // Auto open email
    window.location.href = mailTo;
});

/* ===== Intersection Observer to animate opposite-side entrance ===== */
(function(){
    // Elements that will slide from left and right
    const leftEl = document.querySelector('.slide-left');
    const rightEl = document.querySelector('.slide-right');
    const titleEl = document.querySelector('h2.animate-fade');
    const mapEl = document.getElementById('mapContainer');

    // Helper to add in-view class with optional delay (stagger)
    function revealWithDelay(el, className, delay = 0) {
        if (!el) return;
        if (delay) {
            setTimeout(() => el.classList.add(className), delay);
        } else {
            el.classList.add(className);
        }
    }

    if ('IntersectionObserver' in window) {
        const obsOptions = { threshold: 0.12 };

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;

                const el = entry.target;
                // Add appropriate class based on element type
                if (el.classList.contains('slide-left')) {
                    // left element comes in slightly earlier
                    revealWithDelay(el, 'in-view-left', 60);
                } else if (el.classList.contains('slide-right')) {
                    // right element comes in slightly later to simulate opposite movement
                    revealWithDelay(el, 'in-view-right', 160);
                } else if (el.matches('h2.animate-fade')) {
                    revealWithDelay(el, 'in-view', 20);
                }
                obs.unobserve(el);
            });
        }, obsOptions);

        // observe title, left and right elements
        if (titleEl) observer.observe(titleEl);
        if (leftEl) observer.observe(leftEl);
        if (rightEl) observer.observe(rightEl);

        // animate map separately with its own observer (subtle from bottom)
        if (mapEl) {
            const mapObs = new IntersectionObserver((entries, mop) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated-in');
                        mop.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08 });
            mapObs.observe(mapEl);
        }
    } else {
        // fallback: reveal immediately for browsers without IntersectionObserver
        if (titleEl) titleEl.classList.add('in-view');
        if (leftEl) leftEl.classList.add('in-view-left');
        if (rightEl) rightEl.classList.add('in-view-right');
        if (mapEl) mapEl.classList.add('animated-in');
    }
})();
</script>

</body>
</html>
