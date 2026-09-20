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

function getContractInviteEmailBody(string $p2Name, string $p1Name, string $refNum, string $confirmUrl): string {
    return <<<HTML
    <div style="font-family:Poppins,Arial,sans-serif;max-width:540px;margin:auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);">
      <div style="background:linear-gradient(135deg,#E90101,#007ED2);padding:30px 28px;text-align:center;">
        <h1 style="color:#fff;margin:0 0 4px;font-size:26px;font-weight:800;">VrakeIT</h1>
        <p style="color:rgba(255,255,255,.85);margin:0;font-size:13px;">Settlement Contract Invitation</p>
      </div>
      <div style="padding:32px 28px;">
        <h2 style="font-size:18px;margin:0 0 6px;color:#1a1a1a;">Hi, {$p2Name}!</h2>
        <p style="color:#555;line-height:1.7;margin:0 0 20px;">
          <strong>{$p1Name}</strong> has invited you to review and confirm a road incident settlement contract on VrakeIT.
        </p>
        <div style="background:#f0f7ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:16px 18px;margin-bottom:24px;">
          <div style="font-size:11px;font-weight:700;color:#007ED2;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Reference Number</div>
          <div style="font-size:20px;font-weight:800;color:#1a1a1a;letter-spacing:2px;">{$refNum}</div>
        </div>
        <p style="color:#555;line-height:1.7;margin:0 0 20px;font-size:14px;">
          By clicking the button below, you can review the full contract details and confirm your agreement. Your confirmation will serve as your digital consent and signature.
        </p>
        <div style="text-align:center;margin:28px 0;">
          <a href="{$confirmUrl}" style="display:inline-block;background:linear-gradient(135deg,#007ED2,#0056a3);color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-weight:700;font-size:15px;letter-spacing:0.3px;">
            Review &amp; Confirm Contract
          </a>
        </div>
        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;font-size:12px;color:#92400e;line-height:1.6;">
          ⚠️ <strong>Important:</strong> This invite link expires in <strong>7 days</strong>. You must be logged in to your VrakeIT account to confirm. Do not share this link.
        </div>
        <p style="color:#9ca3af;font-size:11px;margin-top:20px;line-height:1.6;">
          If you cannot click the button, copy and paste this URL into your browser:<br>
          <span style="color:#007ED2;word-break:break-all;">{$confirmUrl}</span>
        </p>
      </div>
      <div style="background:#f0f0f0;padding:14px;text-align:center;">
        <p style="color:#9ca3af;font-size:11px;margin:0;">© 2025 VrakeIT · vrakeit@gmail.com</p>
      </div>
    </div>
    HTML;
}

function sendContractInviteEmail(string $toEmail, string $toName, string $fromName, string $refNum, string $confirmUrl): array {
    $subject = "VrakeIT: Settlement Contract Invitation — {$refNum}";
    $body    = getContractInviteEmailBody($toName, $fromName, $refNum, $confirmUrl);
    return sendEmail($toEmail, $toName, $subject, $body);
}
