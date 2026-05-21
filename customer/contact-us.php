<?php
/**
 * Triangle Mart - Contact page
 *
 * Static contact information for the marketplace.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

$success = "";
$error = "";

// --- Contact form: send message via email ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fname = trim($_POST["fname"] ?? "");
    $lname = trim($_POST["lname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($fname === "" || $lname === "" || $email === "" || $subject === "" || $message === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $result = send_contact_email($fname, $lname, $email, $subject, $message);

        if ($result === true) {
            $success = "Message sent successfully.";
        } else {
            $error = "Message could not be sent. Error: " . $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
<?php
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/search.php';
?>

<main class="contact-layout">
    <div class="contact-card">

        <div class="contact-info">
            <h1>About Us</h1>

            <p>
                Triangle Mart is a shared online marketplace for local independent traders.
                Customers can order fresh products from multiple shops in one basket and
                collect them from a single collection point.
            </p>

            <div class="info-box">
                <h3>Collection Support</h3>
                <p>Email: trianglemartmngmt@gmail.com</p>
                <p>Phone: +44 1234 567890</p>
                <p>Available: Wed - Fri, 9:00 AM to 6:00 PM</p>
            </div>
        </div>

        <div class="contact-form">
            <h2>Contact Us</h2>

            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>First Name</label>
                <input name="fname" required>

                <label>Last Name</label>
                <input name="lname" required>

                <label>Email</label>
                <input name="email" type="email" required>

                <label>Subject</label>
                <input name="subject" required>

                <label>Message</label>
                <textarea name="message" required></textarea>

                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>

    </div>
</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>

</html>