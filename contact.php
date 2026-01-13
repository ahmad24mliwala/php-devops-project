<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

log_visit($pdo);

// Fetch dynamic contact details
$shop_address = get_setting('shop_address', $pdo) ?: "123 Pickle Street, Foodie Town, India";
$shop_phone = explode(',', get_setting('shop_phone', $pdo) ?: "+91 9876543210,+91 9123456780");
$shop_email = explode(',', get_setting('shop_email', $pdo) ?: "info@picklehub.com,support@picklehub.com");
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
}
.map-container iframe {
    width: 100%; height: 420px;
    border:0;
}
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <h2 class="text-center mb-4">Contact Us</h2>

    <div class="row g-4">
        <!-- ENQUIRY FORM -->
        <div class="col-md-6">
            <form id="enquiryForm" class="p-4 shadow-sm rounded bg-lightgreen">

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
            <div class="contact-info p-4 shadow-sm rounded bg-light">
                
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

                <a class="whatsapp-btn"
                   href="https://wa.me/<?= $clean_wa ?>" target="_blank">💬 WhatsApp Us</a>

            </div>
        </div>
    </div>
</div>

<!-- Map -->
<section class="map-section">
    <div class="map-container">
        <?= $map_iframe ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
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
</script>

</body>
</html>
