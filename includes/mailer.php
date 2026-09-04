<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Send email using Gmail SMTP via PHP streams (no Composer needed).
 * Falls back gracefully on failure.
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $body): array {
    // Use PHPMailer if available (via composer), otherwise use SMTP socket
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        return sendEmailPHPMailer($toEmail, $toName, $subject, $body);
    }
    return sendEmailSocket($toEmail, $toName, $subject, $body);
}

function sendEmailPHPMailer(string $toEmail, string $toName, string $subject, string $body): array {
    try {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return ['success' => true, 'message' => 'Email sent.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email error: ' . $e->getMessage()];
    }
}

function sendEmailSocket(string $toEmail, string $toName, string $subject, string $body): array {
    // Simple fallback using PHP mail() — works if XAMPP mail is configured
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $result = @mail($toEmail, $subject, $body, $headers);
    return $result
        ? ['success' => true,  'message' => 'Email sent via mail().']
        : ['success' => false, 'message' => 'Email failed. Install PHPMailer via Composer for Gmail SMTP support.'];
}

function getOtpEmailBody(string $name, string $otp): string {
    return <<<HTML
    <div style="font-family:Poppins,Arial,sans-serif;max-width:500px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
      <div style="background:linear-gradient(135deg,#E90101,#c20000);padding:30px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:28px;font-weight:800;">VrakeIT</h1>
        <p style="color:rgba(255,255,255,.85);margin:4px 0 0;">Road Incident Reporting System</p>
      </div>
      <div style="padding:32px 28px;">
        <h2 style="color:#1a1a1a;font-size:20px;margin:0 0 8px;">Hello, {$name}!</h2>
        <p style="color:#555;line-height:1.6;">Your One-Time Password (OTP) for VrakeIT account verification is:</p>
        <div style="background:#f8f8f8;border:2px dashed #E90101;border-radius:10px;padding:20px;text-align:center;margin:20px 0;">
          <span style="font-size:40px;font-weight:800;color:#E90101;letter-spacing:12px;">{$otp}</span>
        </div>
        <p style="color:#555;font-size:14px;">This OTP is valid for <strong>5 minutes</strong>. Do not share it with anyone.</p>
        <p style="color:#999;font-size:12px;margin-top:24px;">If you did not request this, please ignore this email.</p>
      </div>
      <div style="background:#f0f0f0;padding:14px;text-align:center;">
        <p style="color:#999;font-size:12px;margin:0;">© 2025 VrakeIT · vrakeit@gmail.com</p>
      </div>
    </div>
    HTML;
}

function sendOtpEmail(string $email, string $name, string $otp): array {
    $subject = "Your VrakeIT OTP: {$otp}";
    $body    = getOtpEmailBody($name, $otp);
    return sendEmail($email, $name, $subject, $body);
}
