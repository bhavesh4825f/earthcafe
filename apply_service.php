<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

require_citizen('login.php');

// ensure aadhar present before accessing service selection
$stmt = $conn->prepare("SELECT aadhar FROM users WHERE id = ?");
$uid = (int)$_SESSION['user_id'];
$stmt->bind_param("i", $uid);
$stmt->execute();
$urow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (empty($urow['aadhar'])) {
    header("Location: user_profile.php?complete=1");
    exit();
}

$username = $_SESSION['username'] ?? 'Citizen';
$curPage = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

if (isset($_GET['payment'])) {
    if ($_GET['payment'] === 'cancelled') {
        $message = "Payment cancelled. You can continue from dashboard using Pay Now.";
        $message_type = "warning";
    } elseif ($_GET['payment'] === 'failed') {
        $message = "Payment failed. Please try again.";
        $message_type = "danger";
    }
}

$services = [];
$stmt = $conn->prepare("
    SELECT id, name, description, category, service_fee, document_fee, consultancy_fee, processing_time_days
    FROM services
    WHERE active = 1
    ORDER BY category, name
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="stylesheet" href="css/panel-unified.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
    <style>
        body { background: #edf3ee; font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        html { scrollbar-gutter: stable; }
        .navbar { background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-brand { font-weight: 700; font-size: 24px; color: #fff !important; }
        .nav-link { color: rgba(255,255,255,0.86) !important; font-weight: 500; }
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px; height: calc(100vh - 60px);
            background: linear-gradient(180deg, #143730 0%, #0f2924 100%);
            padding: 20px; color: #fff; overflow-y: auto; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1020;
        }
        .nav-menu { list-style: none; margin-top: 25px; padding-left: 0; }
        .nav-menu li { margin-bottom: 10px; }
        .nav-menu a {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.84);
            text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-weight: 500; font-size: 14px;
        }
        .nav-menu a:hover, .nav-menu a.active { background: rgba(255,255,255,0.2); color: #fff; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 30px; }
        .card-box { background: #fff; border-radius: 14px; box-shadow: 0 8px 24px rgba(12,37,32,0.08); border: 1px solid #d2dfd4; padding: 24px; }
        .service-search { max-width: 480px; }
        .section-title { color: #123933; font-weight: 700; font-size: clamp(1.2rem, 2vw, 1.45rem); }
        .service-count { color: #59726d; font-size: 0.9rem; }
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .service-card-item {
            border: 1px solid #d6e2d8;
            border-radius: 12px;
            padding: 14px;
            background: #fff;
            transition: all 0.2s ease;
        }
        .service-card-item:hover {
            border-color: #a6c4b2;
            box-shadow: 0 10px 22px rgba(15,106,93,0.14);
            transform: translateY(-2px);
        }
        .service-card-title { font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .service-card-cat { font-size: 12px; color: #6b7280; margin-bottom: 10px; }
        .service-card-desc { font-size: 13px; color: #4b5563; min-height: 38px; }
        .service-card-meta { margin-top: 10px; font-size: 13px; color: #111827; font-weight: 600; }
        .service-card-item .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 600;
        }
        .search-empty {
            display: none;
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #d8e6da;
            background: #f4fbf5;
            color: #3c5a53;
            font-size: 0.92rem;
        }
        @media (max-width: 768px) {
            .sidebar { position: static; width: 100%; height: auto; margin-top: 60px; }
            .main-content { margin-left: 0; margin-top: 0; padding: 14px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-user-shield"></i> Earth Cafe Citizen</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><span class="nav-link">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <aside class="sidebar">
        <h5 class="fw-bold mb-3"><i class="fas fa-bars"></i> Menu</h5>
        <ul class="nav-menu">
            <li><a href="client_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="user_profile.php"<?php if($curPage==='user_profile.php') echo ' class="active"'; ?>><i class="fas fa-user-edit"></i> Profile</a></li>
            <li><a href="apply_service.php" class="active"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
            <li><a href="citizen_applications.php"><i class="fas fa-tasks"></i> My Applications</a></li>
            <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
            <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0 section-title"><i class="fas fa-layer-group"></i> Select Service</h4>
                    <div class="service-count" id="serviceCountText"><?php echo count($services); ?> services available</div>
                </div>
                <input type="text" id="serviceSearch" class="form-control service-search" placeholder="Search service by name, category, description..." oninput="filterServiceCards()">
            </div>

            <?php if (empty($services)): ?>
                <div class="alert alert-info mt-3">No active services available right now.</div>
            <?php else: ?>
                <div id="serviceCards" class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $total_fee = (float)$service['service_fee'] + (float)$service['document_fee'] + (float)$service['consultancy_fee'];
                            $search_blob = strtolower(trim(($service['name'] ?? '') . ' ' . ($service['category'] ?? '') . ' ' . ($service['description'] ?? '')));
                        ?>
                        <div class="service-card-item" data-search="<?php echo htmlspecialchars($search_blob); ?>">
                            <div class="service-card-title"><?php echo htmlspecialchars((string)$service['name']); ?></div>
                            <div class="service-card-cat"><?php echo htmlspecialchars((string)$service['category']); ?> | <?php echo (int)$service['processing_time_days']; ?> day(s)</div>
                            <div class="service-card-desc"><?php echo htmlspecialchars((string)($service['description'] ?: 'No description provided.')); ?></div>
                            <div class="service-card-meta">Total Fee: <?php echo number_format($total_fee, 2); ?> INR</div>
                            <a href="apply_service_form.php?service_id=<?php echo (int)$service['id']; ?>" class="btn btn-sm btn-primary mt-2">
                                Apply Service
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="searchEmptyState" class="search-empty">
                    No services match your search. Try a different keyword.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterServiceCards() {
            const query = (document.getElementById('serviceSearch').value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('#serviceCards .service-card-item');
            const emptyState = document.getElementById('searchEmptyState');
            const countText = document.getElementById('serviceCountText');
            let visibleCount = 0;

            cards.forEach(card => {
                const haystack = (card.getAttribute('data-search') || '').toLowerCase();
                const visible = haystack.includes(query);
                card.style.display = visible ? '' : 'none';
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (emptyState) {
                emptyState.style.display = (cards.length > 0 && visibleCount === 0) ? 'block' : 'none';
            }
            if (countText) {
                countText.textContent = visibleCount + ' service' + (visibleCount === 1 ? '' : 's') + ' shown';
            }
        }
    </script>
</body>
</html>
