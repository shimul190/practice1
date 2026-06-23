<?php
/**
 * GET /api/track_order.php?order_id=123
 *
 * Returns live delivery tracking data as JSON. Polled by the
 * student/employee/public_user track_delivery.php pages every few
 * seconds to simulate real-time updates.
 *
 * UPGRADE PATH: replace this polling approach with a WebSocket push
 * (Socket.io + Node, or Pusher/Ably) by having the delivery person's
 * mobile app emit location updates to a socket server, which then
 * pushes them to subscribed clients. This endpoint's JSON shape can
 * stay the same so the frontend JS barely changes.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
$pdo = getDB();
$userId = currentUserId();
$role = currentUserRole();

// Authorization: the customer who owns the order, OR the restaurant that owns it, may view tracking.
$stmt = $pdo->prepare(
    "SELECT o.user_id AS customer_id, r.user_id AS restaurant_owner_id
     FROM orders o JOIN restaurants r ON r.restaurant_id = o.restaurant_id
     WHERE o.order_id = :oid"
);
$stmt->execute([':oid' => $orderId]);
$owner = $stmt->fetch();

if (!$owner) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$isAuthorized = ($role === 'admin')
    || ((int)$owner['customer_id'] === $userId)
    || ((int)$owner['restaurant_owner_id'] === $userId);

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized to view this order']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*, o.order_status
     FROM delivery_tracking t
     JOIN orders o ON o.order_id = t.order_id
     WHERE t.order_id = :oid"
);
$stmt->execute([':oid' => $orderId]);
$tracking = $stmt->fetch();

if (!$tracking) {
    echo json_encode(['tracking_available' => false, 'order_status' => null]);
    exit;
}

/**
 * DEMO SIMULATION: nudge the delivery person's position a little closer
 * to the destination each time this endpoint is polled, so the map
 * visibly animates without needing a real GPS device. Stops once
 * "arrived". In production this UPDATE is replaced by real GPS pings
 * coming from the delivery person's app.
 */
if (in_array($tracking['delivery_status'], ['picked_up', 'on_the_way'], true)
    && $tracking['current_lat'] !== null && $tracking['destination_lat'] !== null) {

    $stepFraction = 0.15; // move 15% of the remaining distance per poll
    $newLat = (float)$tracking['current_lat'] + ((float)$tracking['destination_lat'] - (float)$tracking['current_lat']) * $stepFraction;
    $newLng = (float)$tracking['current_lng'] + ((float)$tracking['destination_lng'] - (float)$tracking['current_lng']) * $stepFraction;
    $newEta = max(1, (int)$tracking['estimated_minutes'] - 1);

    $upd = $pdo->prepare(
        "UPDATE delivery_tracking SET current_lat = :lat, current_lng = :lng, estimated_minutes = :eta WHERE tracking_id = :tid"
    );
    $upd->execute([':lat' => $newLat, ':lng' => $newLng, ':eta' => $newEta, ':tid' => $tracking['tracking_id']]);

    $tracking['current_lat'] = $newLat;
    $tracking['current_lng'] = $newLng;
    $tracking['estimated_minutes'] = $newEta;
}

echo json_encode([
    'tracking_available'    => true,
    'order_status'          => $tracking['order_status'],
    'delivery_status'       => $tracking['delivery_status'],
    'delivery_person_name'  => $tracking['delivery_person_name'],
    'delivery_person_phone' => $tracking['delivery_person_phone'],
    'current_lat'           => (float)$tracking['current_lat'],
    'current_lng'           => (float)$tracking['current_lng'],
    'destination_lat'       => (float)$tracking['destination_lat'],
    'destination_lng'       => (float)$tracking['destination_lng'],
    'estimated_minutes'     => (int)$tracking['estimated_minutes'],
    'last_updated'          => $tracking['last_updated'],
]);
