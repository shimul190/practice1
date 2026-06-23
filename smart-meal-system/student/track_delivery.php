<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['student']);

$pdo = getDB();
$userId = currentUserId();
$orderId = (int)($_GET['order_id'] ?? 0);

// Ownership check
$stmt = $pdo->prepare(
    "SELECT o.*, m.meal_name, r.restaurant_name
     FROM orders o
     JOIN meals m ON m.meal_id = o.meal_id
     JOIN restaurants r ON r.restaurant_id = o.restaurant_id
     WHERE o.order_id = :oid AND o.user_id = :uid"
);
$stmt->execute([':oid' => $orderId, ':uid' => $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('/student/my_orders.php');
}

$pageTitle = 'Track Delivery';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Tracking Order #<?= $orderId ?></h2>
  <p class="text-muted"><?= e($order['meal_name']) ?> from <?= e($order['restaurant_name']) ?> &middot; Qty <?= (int)$order['quantity'] ?></p>
</div>

<div class="card">
  <div class="flex-between">
    <div>
      <strong id="statusLabel">Loading status…</strong>
      <p class="text-muted" id="etaLabel">Fetching delivery info…</p>
    </div>
    <div style="text-align:right">
      <div id="riderName" class="text-muted"></div>
      <div id="riderPhone" class="text-muted"></div>
    </div>
  </div>
  <div id="liveMap" class="mt-2"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const ORDER_ID = <?= $orderId ?>;
const API_URL = '<?= BASE_URL ?>/api/track_order.php?order_id=' + ORDER_ID;

let map, riderMarker, destMarker, routeLine;
let mapInitialized = false;

function initMap(lat, lng) {
  map = L.map('liveMap').setView([lat, lng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  mapInitialized = true;
}

const statusLabels = {
  'assigned': 'Delivery person assigned',
  'picked_up': 'Order picked up',
  'on_the_way': 'On the way to you',
  'delivered': 'Delivered'
};

async function pollTracking() {
  try {
    const res = await fetch(API_URL);
    const data = await res.json();

    if (!data.tracking_available) {
      document.getElementById('statusLabel').textContent =
        data.order_status === 'pending' ? 'Waiting for restaurant to accept your order' : 'Tracking will appear once your order is accepted';
      document.getElementById('etaLabel').textContent = '';
      return;
    }

    document.getElementById('statusLabel').textContent = statusLabels[data.delivery_status] || data.delivery_status;
    document.getElementById('etaLabel').textContent = data.delivery_status === 'delivered'
      ? 'Your order has arrived!'
      : `Estimated arrival: ${data.estimated_minutes} min`;
    document.getElementById('riderName').textContent = '🛵 ' + (data.delivery_person_name || '');
    document.getElementById('riderPhone').textContent = data.delivery_person_phone || '';

    if (!mapInitialized) {
      initMap(data.current_lat, data.current_lng);
      destMarker = L.marker([data.destination_lat, data.destination_lng])
        .addTo(map).bindPopup('Delivery Address').openPopup();
      riderMarker = L.marker([data.current_lat, data.current_lng]).addTo(map).bindPopup('Delivery Person');
      routeLine = L.polyline([[data.current_lat, data.current_lng],[data.destination_lat, data.destination_lng]], {color:'#C2603D', dashArray:'6,8'}).addTo(map);
    } else {
      riderMarker.setLatLng([data.current_lat, data.current_lng]);
      routeLine.setLatLngs([[data.current_lat, data.current_lng],[data.destination_lat, data.destination_lng]]);
      map.panTo([data.current_lat, data.current_lng]);
    }

    if (data.delivery_status === 'delivered') {
      clearInterval(pollHandle);
    }
  } catch (err) {
    console.error('Tracking fetch failed', err);
  }
}

pollTracking();
const pollHandle = setInterval(pollTracking, 4000); // poll every 4s — swap for WebSocket push in production
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
