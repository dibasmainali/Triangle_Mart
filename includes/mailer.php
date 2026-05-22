<?php
/**
 * Triangle Mart - Email sending (PHPMailer)
 *
 * SMTP configuration and verification email helper.
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Configure and return a PHPMailer instance (Gmail SMTP). */
function create_mailer()
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // Put your NEW Gmail app password here.
    $mail->Username   = 'trianglemartmngmt@gmail.com';
    $mail->Password   = 'tumivuwjtcvvyeby';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('trianglemartmngmt@gmail.com', 'Triangle_Mart');

    return $mail;
}

/** Send account verification email with link; returns true or error message string. */
function send_verification_email($to_email, $to_name, $verify_link)
{
    try {
        $mail = create_mailer();

        $safe_name = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($verify_link, ENT_QUOTES, 'UTF-8');

        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Triangle Mart Account';

        $mail->Body = "
            <h2>Welcome to Triangle Mart</h2>
            <p>Hello {$safe_name},</p>
            <p>Please click the link below to verify your account:</p>
            <p>
                <a href=\"{$safe_link}\">Verify My Account</a>
            </p>
            <p>This link expires in 24 hours.</p>
        ";

        $mail->AltBody =
            "Welcome to Triangle Mart\n\n" .
            "Hello {$to_name},\n\n" .
            "Verify your account using this link:\n{$verify_link}\n\n" .
            "This link expires in 24 hours.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return $e->getMessage() . ' ' . ($mail->ErrorInfo ?? '');
    }
}

function send_contact_email($fname, $lname, $email, $subject, $message)
{
    try {
        $mail = create_mailer();

        $full_name = trim($fname . ' ' . $lname);

        $safe_name    = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
        $safe_email   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safe_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safe_message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $mail->addAddress('trianglemartmngmt@gmail.com', 'Triangle_Mart');
        $mail->addReplyTo($email, $full_name);

        $mail->isHTML(true);
        $mail->Subject = 'Contact Form: ' . $subject;

        $mail->Body = "
            <h2>New Contact Form Message</h2>
            <p><strong>Name:</strong> {$safe_name}</p>
            <p><strong>Email:</strong> {$safe_email}</p>
            <p><strong>Subject:</strong> {$safe_subject}</p>
            <p><strong>Message:</strong></p>
            <p>{$safe_message}</p>
        ";

        $mail->AltBody =
            "New Contact Form Message\n\n" .
            "Name: {$full_name}\n" .
            "Email: {$email}\n" .
            "Subject: {$subject}\n\n" .
            "Message:\n{$message}";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return $e->getMessage() . ' ' . ($mail->ErrorInfo ?? '');
    }
}

function send_password_reset_email($to_email, $to_name, $reset_link)
{
    try {
        $mail = create_mailer();

        $safe_name = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');

        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Triangle Mart Password';

        $mail->Body = "
            <h2>Password reset request</h2>
            <p>Hello {$safe_name},</p>
            <p>Click the link below to reset your password:</p>
            <p><a href=\"{$safe_link}\">Reset my password</a></p>
            <p>This link expires in 1 hour.</p>
            <p>If you did not request this, you can ignore this email.</p>
        ";

        $mail->AltBody =
            "Password reset request\n\n" .
            "Hello {$to_name},\n\n" .
            "Reset your password using this link:\n{$reset_link}\n\n" .
            "This link expires in 1 hour.\n\n" .
            "If you did not request this, you can ignore this email.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return $e->getMessage() . ' ' . ($mail->ErrorInfo ?? '');
    }
}

function send_order_confirmation_email($to_email, $to_name, $order_id, $invoice_id, $collection_text, $total_amount, $invoice_link, $invoice_html)
{
    try {
        $mail = create_mailer();

        $safe_name = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
        $safe_order = htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8');
        $safe_invoice = htmlspecialchars($invoice_id, ENT_QUOTES, 'UTF-8');
        $safe_collection = htmlspecialchars($collection_text, ENT_QUOTES, 'UTF-8');
        $safe_invoice_link = htmlspecialchars($invoice_link, ENT_QUOTES, 'UTF-8');

        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmed: {$order_id} - Triangle Mart";

        $mail->Body = "
            <h2>Order confirmed</h2>
            <p>Hello {$safe_name},</p>
            <p>Your order <strong>#{$safe_order}</strong> has been confirmed and placed successfully.</p>
            <p><strong>Collection:</strong> {$safe_collection}</p>
            <p><strong>Invoice:</strong> #{$safe_invoice}</p>
            <p><strong>Total paid:</strong> £" . number_format((float)$total_amount, 2) . "</p>
            <p>
                You can view your invoice here (login required):
                <a href=\"{$safe_invoice_link}\">View Invoice</a>
            </p>
            <p>The invoice is also attached to this email.</p>
        ";

        $mail->AltBody =
            "Order confirmed\n\n" .
            "Hello {$to_name},\n\n" .
            "Your order #{$order_id} has been confirmed and placed successfully.\n" .
            "Collection: {$collection_text}\n" .
            "Invoice: #{$invoice_id}\n" .
            "Total paid: £" . number_format((float)$total_amount, 2) . "\n\n" .
            "Invoice link (login required): {$invoice_link}\n";

        if ($invoice_html !== '') {
            $mail->addStringAttachment(
                $invoice_html,
                "Invoice-{$order_id}.html",
                'base64',
                'text/html'
            );
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        return $e->getMessage() . ' ' . ($mail->ErrorInfo ?? '');
    }
}
?>
