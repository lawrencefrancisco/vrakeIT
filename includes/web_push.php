<?php
/**
 * includes/web_push.php
 * 
 * Pure-PHP Web Push sender using VAPID authentication.
 * No Composer packages required — uses only PHP's built-in
 * openssl and curl extensions.
 * 
 * Based on RFC 8030 (Web Push Protocol) and RFC 7517 (VAPID).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Base64url encode (RFC 4648 §5, no padding)
 */
function vapid_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64url decode
 */
function vapid_base64url_decode(string $data): string {
    $pad  = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Build a VAPID JWT token signed with ES256 (ECDSA P-256 SHA-256).
 * We perform the signing manually using PHP's openssl extension.
 */
function buildVapidJwt(string $audience, string $subject, string $privateKeyB64u): string {
    // JWT Header
    $header  = vapid_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));

    // JWT Payload
    $exp     = time() + 12 * 3600; // 12 hours
    $payload = vapid_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => $exp,
        'sub' => $subject,
    ]));

    $signingInput = $header . '.' . $payload;

    // Load the private key from the raw 32-byte base64url-encoded D parameter
    $privKeyRaw  = vapid_base64url_decode($privateKeyB64u);

    // Reconstruct the EC private key in DER format (PKCS#8)
    // PKCS#8 wrapper for P-256 EC private key is:
    //   30 81 87 — outer SEQUENCE (135 bytes)
    //     02 01 00 — INTEGER version = 0
    //     30 13 — SEQUENCE (algorithm identifier, 19 bytes)
    //       06 07 2a 86 48 ce 3d 02 01 — OID ecPublicKey
    //       06 08 2a 86 48 ce 3d 03 01 07 — OID P-256
    //     04 6d — OCTET STRING (109 bytes) — inner ECPrivateKey
    //       30 6b — SEQUENCE
    //         02 01 01 — INTEGER version = 1
    //         04 20 — OCTET STRING (32 bytes) — the private key D
    //           [32 bytes of D]
    //         a1 44 — [1] EXPLICIT (optional public key, omitted for minimal)
    //           ...

    // Minimal PKCS#8 DER for P-256 without public key:
    $der = "\x30\x41"                        // SEQUENCE (65 bytes)
         . "\x02\x01\x00"                    // INTEGER version 0
         . "\x30\x13"                        // SEQUENCE (algorithm identifier)
         .   "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"  // OID ecPublicKey
         .   "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
         . "\x04\x27"                        // OCTET STRING (39 bytes)
         .   "\x30\x25"                      // SEQUENCE (37 bytes)
         .     "\x02\x01\x01"               // INTEGER version 1
         .     "\x04\x20"                   // OCTET STRING (32 bytes)
         .     $privKeyRaw;                  // The raw private key D

    $privKey = openssl_pkey_get_private('file://' . tempnam(sys_get_temp_dir(), 'vk'));

    // Instead, use the DER directly
    $pem = "-----BEGIN PRIVATE KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PRIVATE KEY-----\n";

    $privKey = openssl_pkey_get_private($pem);
    if (!$privKey) {
        // Fallback: try with explicit curve DER structure (larger wrapper)
        $der2 = buildPkcs8Der($privKeyRaw);
        $pem2 = "-----BEGIN PRIVATE KEY-----\n"
              . chunk_split(base64_encode($der2), 64, "\n")
              . "-----END PRIVATE KEY-----\n";
        $privKey = openssl_pkey_get_private($pem2);
    }

    if (!$privKey) {
        throw new RuntimeException('Failed to load VAPID private key. OpenSSL error: ' . openssl_error_string());
    }

    // Sign with SHA-256 / ECDSA
    $signature = '';
    openssl_sign($signingInput, $signature, $privKey, OPENSSL_ALGO_SHA256);

    // openssl_sign returns DER-encoded ECDSA signature — convert to raw r||s (64 bytes)
    $rawSig = derToRaw($signature);

    return $signingInput . '.' . vapid_base64url_encode($rawSig);
}

/**
 * Build a full PKCS#8 DER wrapper for a raw P-256 private key.
 */
function buildPkcs8Der(string $d): string {
    // ECPrivateKey ::= SEQUENCE { version, privateKey (OCTET STRING 32 bytes) }
    $ecPrivateKey = "\x30\x25"
                  . "\x02\x01\x01"
                  . "\x04\x20" . $d;

    // PrivateKeyInfo ::= SEQUENCE {
    //   version, AlgorithmIdentifier, OCTET STRING(ECPrivateKey)
    // }
    $algId = "\x30\x13"
           . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
           . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

    $octetString = "\x04" . chr(strlen($ecPrivateKey)) . $ecPrivateKey;

    $inner = "\x02\x01\x00" . $algId . $octetString;

    return "\x30" . chr(strlen($inner)) . $inner;
}

/**
 * Convert DER-encoded ECDSA signature to raw r||s format (64 bytes).
 */
function derToRaw(string $der): string {
    // DER: SEQUENCE { INTEGER r, INTEGER s }
    $offset = 2; // skip SEQUENCE tag + length

    // Read r
    $offset++; // skip INTEGER tag (0x02)
    $rLen = ord($der[$offset++]);
    $r    = substr($der, $offset, $rLen);
    $offset += $rLen;

    // Read s
    $offset++; // skip INTEGER tag (0x02)
    $sLen = ord($der[$offset++]);
    $s    = substr($der, $offset, $sLen);

    // Strip leading zero padding added by DER for positive integers
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");

    // Pad each to 32 bytes
    return str_pad($r, 32, "\x00", STR_PAD_LEFT)
         . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

/**
 * Send a Web Push notification to a single subscription.
 * Returns true on success, false on failure (e.g. expired subscription)
 *
 * NOTE: We send an empty push body (no payload encryption required per RFC 8030).
 * The Service Worker displays a static notification on receipt.
 * Full RFC 8291 AES-GCM payload encryption requires a Composer lib (minishlink/web-push)
 * and is intentionally deferred here to keep this dependency-free.
 */
function sendWebPush(array $subscription, array $payload, string $vapidPub, string $vapidPriv, string $vapidSubject): bool {
    $endpoint = $subscription['endpoint'];

    // Determine push service audience (scheme + host)
    $parts    = parse_url($endpoint);
    $audience = $parts['scheme'] . '://' . $parts['host'];

    // Build the JWT
    try {
        $jwt = buildVapidJwt($audience, $vapidSubject, $vapidPriv);
    } catch (RuntimeException $e) {
        error_log('[WebPush] JWT build failed: ' . $e->getMessage());
        return false;
    }

    // VAPID Authorization header (RFC 8292)
    $vapidPubKeyHeader = 'vapid t=' . $jwt . ',k=' . $vapidPub;

    $headers = [
        'Authorization: ' . $vapidPubKeyHeader,
        'TTL: 86400', // 24 hours — hold the notification if device is offline
        'Content-Length: 0', // Empty body — no payload encryption needed
    ];

    // Send via cURL with NO body
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '', // empty body
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[WebPush] cURL error for {$endpoint}: {$error}");
        return false;
    }

    // 201 = Created (success), 200 = OK (success)
    // 410 = Gone (subscription expired — caller should delete it)
    // 404 = Not Found (subscription expired)
    if ($httpCode === 201 || $httpCode === 200) {
        error_log("[WebPush] ✅ Push sent successfully to {$endpoint} (HTTP {$httpCode})");
        return true;
    }

    error_log("[WebPush] ❌ Push failed for endpoint " . substr($endpoint, 0, 60) . ": HTTP {$httpCode}, Response: {$response}");
    return false;
}

/**
 * Send a notification to ALL enforcers (broadcast).
 * Automatically removes stale/expired subscriptions.
 *
 * @param array $payload The notification data (title, body, url, etc.)
 */
function sendPushToAllEnforcers(array $payload): void {
    try {
        $db   = getDB();
        
        // Get all enforcer subscriptions who are on duty
        $stmt = $db->query("
            SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth
            FROM push_subscriptions ps
            JOIN users u ON ps.user_id = u.id
            WHERE u.role = 'enforcer' AND u.is_on_duty = 1
        ");
        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            return; // No enforcers subscribed
        }

        $deleteIds = [];

        foreach ($subscriptions as $sub) {
            $success = sendWebPush(
                $sub,
                $payload,
                VAPID_PUBLIC_KEY,
                VAPID_PRIVATE_KEY,
                VAPID_SUBJECT
            );

            if (!$success) {
                // Mark for deletion — likely expired
                $deleteIds[] = (int)$sub['id'];
            }
        }

        // Prune expired subscriptions
        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $db->prepare("DELETE FROM push_subscriptions WHERE id IN ({$placeholders})")
               ->execute($deleteIds);
        }
    } catch (Exception $e) {
        // Push errors must NEVER break the report submission flow
        error_log('[WebPush] sendPushToAllEnforcers error: ' . $e->getMessage());
    }
}
