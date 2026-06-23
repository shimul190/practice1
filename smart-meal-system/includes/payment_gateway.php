<?php
/**
 * Payment Gateway Abstraction (STUB IMPLEMENTATION)
 * ----------------------------------------------------
 * This file simulates bKash, Nagad, and Card payments so the whole
 * application is runnable without real merchant credentials.
 *
 * TO GO LIVE:
 *   - bKash: replace processBkashPayment() with calls to the bKash
 *     Checkout/Tokenized API (https://developer.bka.sh). You'll need
 *     app_key, app_secret, username, password from bKash merchant portal.
 *   - Nagad: replace processNagadPayment() with Nagad's Merchant API
 *     (requires merchant ID + RSA key pair for request signing).
 *   - Card: replace processCardPayment() with a PCI-compliant processor
 *     such as Stripe (recommended over building raw card handling yourself).
 *
 * The function signature processPayment($method, $amount, $postData)
 * is the only thing the rest of the app depends on, so swapping the
 * internals here is a self-contained change.
 */

/**
 * @return array{success: bool, transaction_ref: string|null, message: string}
 */
function processPayment(string $method, float $amount, array $postData): array
{
    return match ($method) {
        'bkash' => processBkashPayment($amount, $postData),
        'nagad' => processNagadPayment($amount, $postData),
        'card'  => processCardPayment($amount, $postData),
        default => ['success' => false, 'transaction_ref' => null, 'message' => 'Unsupported payment method.'],
    };
}

function processBkashPayment(float $amount, array $postData): array
{
    $number = trim($postData['wallet_number'] ?? '');
    if (!preg_match('/^01[0-9]{9}$/', $number)) {
        return ['success' => false, 'transaction_ref' => null, 'message' => 'Please enter a valid bKash mobile number (01XXXXXXXXX).'];
    }

    // --- Simulated gateway call ---
    $ref = 'BKS' . strtoupper(bin2hex(random_bytes(5)));
    return ['success' => true, 'transaction_ref' => $ref, 'message' => 'Payment successful via bKash.'];
}

function processNagadPayment(float $amount, array $postData): array
{
    $number = trim($postData['wallet_number'] ?? '');
    if (!preg_match('/^01[0-9]{9}$/', $number)) {
        return ['success' => false, 'transaction_ref' => null, 'message' => 'Please enter a valid Nagad mobile number (01XXXXXXXXX).'];
    }

    $ref = 'NGD' . strtoupper(bin2hex(random_bytes(5)));
    return ['success' => true, 'transaction_ref' => $ref, 'message' => 'Payment successful via Nagad.'];
}

function processCardPayment(float $amount, array $postData): array
{
    $cardNumber = preg_replace('/\s+/', '', $postData['card_number'] ?? '');
    $expiry     = trim($postData['card_expiry'] ?? '');
    $cvv        = trim($postData['card_cvv'] ?? '');

    if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
        return ['success' => false, 'transaction_ref' => null, 'message' => 'Please enter a valid card number.'];
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
        return ['success' => false, 'transaction_ref' => null, 'message' => 'Please enter expiry as MM/YY.'];
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        return ['success' => false, 'transaction_ref' => null, 'message' => 'Please enter a valid CVV.'];
    }

    $ref = 'CARD' . strtoupper(bin2hex(random_bytes(5)));
    return ['success' => true, 'transaction_ref' => $ref, 'message' => 'Payment successful via Card.'];
}
