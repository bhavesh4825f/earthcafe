<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/mailer.php';

if (!function_exists('ec_pdf_escape_text')) {
    function ec_pdf_escape_text(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return $text;
    }
}

if (!function_exists('ec_build_simple_pdf')) {
    function ec_build_simple_pdf(array $lines): string
    {
        $safeLines = [];
        foreach ($lines as $line) {
            $safeLines[] = ec_pdf_escape_text((string)$line);
        }

        $stream = "BT\n/F1 12 Tf\n50 800 Td\n";
        $lineHeight = 18;
        foreach ($safeLines as $index => $line) {
            if ($index > 0) {
                $stream .= '0 -' . $lineHeight . " Td\n";
            }
            $stream .= '(' . $line . ") Tj\n";
        }
        $stream .= "ET\n";

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[4] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        for ($i = 1; $i <= 5; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }
}

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';
$message_type = '';
$is_mock_gateway = true;

$request_id = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
if ($request_id <= 0) {
    header("Location: apply_service.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT id, service_name, service_fee, document_fee, consultancy_fee, total_fee, payment_status, status
    FROM service_requests
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: apply_service.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_reference = trim($_POST['payment_reference'] ?? '');
    $mock_outcome = trim($_POST['mock_outcome'] ?? 'success');

    $allowed_methods = ['upi', 'card', 'net_banking', 'wallet'];
    if (!in_array($payment_method, $allowed_methods, true)) {
        $message = "Please select a valid payment method.";
        $message_type = "danger";
    } elseif (!in_array($mock_outcome, ['success', 'failed'], true)) {
        $message = "Please select a valid mock payment result.";
        $message_type = "danger";
    } elseif ($mock_outcome === 'failed') {
        audit_log(
            $conn,
            'citizen',
            $user_id,
            'payment_mock_failed',
            'service_request',
            (string)$request_id,
            'method=' . $payment_method . '; simulated=failed'
        );
        header("Location: client_dashboard.php?payment=failed&request_id=" . $request_id);
        exit();
    } else {
        $send_payment_slip_email = false;
        $payment_email_payload = [];

        if ($payment_reference === '') {
            $payment_reference = 'MOCKTXN' . date('YmdHis') . rand(100, 999);
        }

        $conn->begin_transaction();
        try {
            $update = $conn->prepare("
                UPDATE service_requests
                SET payment_status = 'paid', payment_method = ?, payment_reference = ?, payment_date = NOW()
                WHERE id = ? AND user_id = ? AND payment_status <> 'paid'
            ");
            $update->bind_param("ssii", $payment_method, $payment_reference, $request_id, $user_id);
            $update->execute();
            $changed = $update->affected_rows;
            $update->close();

            if ($changed > 0) {
                $check_pay = $conn->prepare("SELECT id FROM payments WHERE request_id = ?");
                $request_id_text = (string)$request_id;
                $check_pay->bind_param("s", $request_id_text);
                $check_pay->execute();
                $pay_exists = $check_pay->get_result()->num_rows > 0;
                $check_pay->close();

                if (!$pay_exists) {
                    $service_name = $request['service_name'];
                    $service_component = (float)$request['service_fee'] + (float)$request['document_fee'];
                    $consultancy_fee = (float)$request['consultancy_fee'];
                    $total_fee = (float)$request['total_fee'];

                    $payment_stmt = $conn->prepare("
                        INSERT INTO payments (user_id, service_name, service_fees, consultancy_fees, total_fees, request_id, transaction_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $payment_stmt->bind_param(
                        "isdddss",
                        $user_id,
                        $service_name,
                        $service_component,
                        $consultancy_fee,
                        $total_fee,
                        $request_id_text,
                        $payment_reference
                    );
                    $payment_stmt->execute();
                    $payment_stmt->close();
                }

                $remark_by_type = 'citizen';
                $remark_by_id = $user_id;
                $remark_type = 'note';
                $remark_message = "Mock payment completed via " . $payment_method . " (Ref: " . $payment_reference . ").";
                $remark_stmt = $conn->prepare("
                    INSERT INTO application_remarks (request_id, remark_by_type, remark_by_id, remark_type, message)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $remark_stmt->bind_param("isiss", $request_id, $remark_by_type, $remark_by_id, $remark_type, $remark_message);
                $remark_stmt->execute();
                $remark_stmt->close();

                audit_log(
                    $conn,
                    'citizen',
                    $user_id,
                    'payment_completed',
                    'service_request',
                    (string)$request_id,
                    'mode=mock; method=' . $payment_method . '; ref=' . $payment_reference
                );

                $adminRs = $conn->query("SELECT id FROM admins WHERE is_active = 1");
                if ($adminRs) {
                    while ($a = $adminRs->fetch_assoc()) {
                        create_notification(
                            $conn,
                            'admin',
                            (int)$a['id'],
                            'Payment Completed #' . $request_id,
                            'Citizen completed payment for application #' . $request_id . '.',
                            'service_requests.php?q=' . $request_id
                        );
                    }
                }

                $send_payment_slip_email = true;
                $payment_email_payload = [
                    'request_id' => $request_id,
                    'service_name' => (string)$request['service_name'],
                    'payment_method' => (string)$payment_method,
                    'payment_reference' => (string)$payment_reference,
                    'total_fee' => (float)$request['total_fee'],
                    'paid_at' => date('Y-m-d H:i:s'),
                ];
            }

            $conn->commit();

            if ($send_payment_slip_email) {
                $user_email = trim((string)($_SESSION['email'] ?? ''));
                if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                    $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                    $email_stmt->bind_param("i", $user_id);
                    $email_stmt->execute();
                    $email_row = $email_stmt->get_result()->fetch_assoc();
                    $email_stmt->close();
                    $user_email = trim((string)($email_row['email'] ?? ''));
                }

                if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                    $history_link = ec_app_url('citizen_payment_history.php');
                    $details_link = ec_app_url('citizen_application_details.php?id=' . (int)$payment_email_payload['request_id']);
                    $slip_lines = [
                        'Earth Cafe - Payment Slip',
                        '',
                        'Application ID: #' . (int)$payment_email_payload['request_id'],
                        'Service: ' . (string)$payment_email_payload['service_name'],
                        'Transaction ID: ' . (string)$payment_email_payload['payment_reference'],
                        'Payment Method: ' . strtoupper((string)$payment_email_payload['payment_method']),
                        'Amount Paid: INR ' . number_format((float)$payment_email_payload['total_fee'], 2),
                        'Paid On: ' . (string)$payment_email_payload['paid_at'],
                        '',
                        'View full payment history:',
                        $history_link,
                        '',
                        'View application details:',
                        $details_link,
                    ];
                    $pdf_content = ec_build_simple_pdf($slip_lines);
                    $pdf_file_name = 'payment-slip-request-' . (int)$payment_email_payload['request_id'] . '.pdf';

                    $paymentMail = ec_compose_payment_slip_email($payment_email_payload, $history_link, $details_link);

                    if (!send_system_email(
                        $user_email,
                        (string)$paymentMail['subject'],
                        (string)$paymentMail['html'],
                        (string)$paymentMail['text'],
                        [
                            'filename' => $pdf_file_name,
                            'mime_type' => 'application/pdf',
                            'content' => $pdf_content,
                        ]
                    )) {
                        error_log('Payment slip email failed for user_id=' . $user_id . ' request_id=' . $request_id);
                    }
                }
            }

            header("Location: client_dashboard.php?payment=success&request_id=" . $request_id);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $message = "Payment failed. Please try again.";
            $message_type = "danger";
        }
    }
}

if (($request['payment_status'] ?? '') === 'paid' && $message === '') {
    $message = "Payment already completed for this application.";
    $message_type = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Earth Cafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <style>
        .checkout-shell {
            min-height: 100vh;
            padding: 30px 16px;
            background:
                radial-gradient(circle at 15% 15%, rgba(210, 154, 23, 0.18), transparent 30%),
                radial-gradient(circle at 90% 85%, rgba(15, 106, 93, 0.14), transparent 32%),
                linear-gradient(150deg, #f4f7f4 0%, #eef4f2 100%);
        }

        .checkout-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .checkout-topbar {
            background: #ffffff;
            border: 1px solid #d6e0d6;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(13, 36, 33, 0.1);
            padding: 14px 18px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand-lock {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: #0f2f2a;
            font-weight: 800;
            font-family: 'Sora', 'Manrope', sans-serif;
        }

        .brand-lock .logo-badge {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0f6a5d, #09483f);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 18px;
        }

        .checkout-card {
            background: #ffffff;
            border: 1px solid #d6e0d6;
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(13, 36, 33, 0.11);
            overflow: hidden;
        }

        .card-head {
            padding: 18px 22px;
            border-bottom: 1px solid #e2ebe2;
            background: linear-gradient(180deg, #f8fbf9 0%, #f2f7f4 100%);
        }

        .card-head h5 {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f2f2a;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .card-body-inner {
            padding: 20px 22px 24px;
        }

        .mock-ribbon {
            border-radius: 12px;
            border: 1px solid #f4d28c;
            background: #fff8e8;
            color: #7c5a16;
            font-size: 0.9rem;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .method-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .method-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .method-chip {
            border: 1.5px solid #cfdccf;
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.22s ease;
            background: #fbfdfb;
            min-height: 58px;
        }

        .method-chip i {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e7f2ef;
            color: #0f6a5d;
        }

        .method-chip .label {
            font-weight: 700;
            color: #0f2f2a;
            font-size: 0.93rem;
        }

        .method-option input:checked + .method-chip {
            border-color: #0f6a5d;
            box-shadow: 0 0 0 4px rgba(15, 106, 93, 0.13);
            background: #f4fbf8;
        }

        .payment-detail {
            border: 1px solid #d9e5dc;
            border-radius: 12px;
            background: #f8fbf9;
            padding: 14px;
            margin-bottom: 14px;
        }

        .payment-detail-title {
            font-weight: 700;
            font-size: 0.88rem;
            color: #43685f;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .mock-field {
            border: 1px solid #d3e0d7;
            border-radius: 10px;
            padding: 11px 12px;
            background: #ffffff;
            color: #5a7570;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .result-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .result-toggle label {
            flex: 1;
            margin: 0;
        }

        .result-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .result-pill {
            width: 100%;
            border: 1px solid #cedccc;
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-align: center;
            cursor: pointer;
            background: #f8fbf9;
            color: #4e6d65;
            transition: all 0.22s ease;
        }

        .result-toggle input:checked + .result-pill.success {
            border-color: #22864a;
            background: #ecf9f0;
            color: #1c733f;
            box-shadow: 0 0 0 3px rgba(34, 134, 74, 0.16);
        }

        .result-toggle input:checked + .result-pill.fail {
            border-color: #be2f2f;
            background: #fdf0f0;
            color: #9f1f1f;
            box-shadow: 0 0 0 3px rgba(190, 47, 47, 0.16);
        }

        .form-label.small-label {
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.77rem;
            color: #5a7570;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .form-control.mock-input,
        .form-select.mock-input {
            border: 1.5px solid #cedccc;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.93rem;
        }

        .form-control.mock-input:focus,
        .form-select.mock-input:focus {
            border-color: #0f6a5d;
            box-shadow: 0 0 0 4px rgba(15, 106, 93, 0.13);
        }

        .secure-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .secure-tag {
            border-radius: 999px;
            background: #edf5f1;
            color: #355e55;
            border: 1px solid #d2e0d8;
            padding: 6px 10px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .summary-head {
            border-bottom: 1px dashed #d6e0d6;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .summary-service {
            font-weight: 800;
            color: #0f2f2a;
            margin-bottom: 5px;
        }

        .summary-meta {
            color: #5d7570;
            font-size: 0.88rem;
            margin: 0;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: #3f5f58;
            font-size: 0.93rem;
            margin-bottom: 8px;
        }

        .summary-total {
            margin-top: 10px;
            padding-top: 12px;
            border-top: 1px dashed #c4d4cb;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .summary-total .value {
            font-size: 1.9rem;
            font-weight: 800;
            color: #0f6a5d;
            line-height: 1;
        }

        .summary-total .value small {
            font-size: 0.85rem;
            font-weight: 700;
            color: #406961;
        }

        .status-paid {
            border: 1px solid #b8dfc4;
            background: #ecf9f0;
            color: #1d713f;
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 12px;
        }

        .action-row {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .btn-checkout {
            border: 0;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-checkout:hover {
            transform: translateY(-1px);
        }

        .btn-checkout.primary {
            color: #fff;
            background: linear-gradient(135deg, #0f6a5d, #09483f);
            box-shadow: 0 10px 20px rgba(9, 72, 63, 0.3);
        }

        .btn-checkout.secondary {
            color: #486b64;
            border: 1px solid #cedccc;
            background: #fff;
        }

        @media (max-width: 960px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .method-grid {
                grid-template-columns: 1fr;
            }

            .summary-total .value {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <main class="checkout-shell">
        <div class="checkout-wrap">
            <div class="checkout-topbar">
                <div class="brand-lock">
                    <span class="logo-badge"><i class="fas fa-lock"></i></span>
                    <span>Earth Cafe Secure Checkout</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Application #<?php echo (int)$request_id; ?></span>
                    <a href="apply_service.php" class="btn-checkout secondary" style="text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <?php if ($is_mock_gateway): ?>
                <div class="mock-ribbon">
                    <i class="fas fa-vial me-1"></i>
                    Sandbox mode enabled. This checkout looks real, but no real money will be charged.
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="checkout-grid">
                <section class="checkout-card">
                    <div class="card-head">
                        <h5><i class="fas fa-credit-card"></i> Payment Details</h5>
                    </div>
                    <div class="card-body-inner">
                        <?php if (($request['payment_status'] ?? '') !== 'paid'): ?>
                            <form method="POST" id="mockPaymentForm">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$request_id; ?>">
                                <input type="hidden" name="pay_now" value="1">

                                <label class="form-label small-label">Choose Payment Method</label>
                                <div class="method-grid">
                                    <label class="method-option">
                                        <input type="radio" name="payment_method" value="upi" checked>
                                        <span class="method-chip">
                                            <i class="fas fa-mobile-screen"></i>
                                            <span class="label">UPI</span>
                                        </span>
                                    </label>
                                    <label class="method-option">
                                        <input type="radio" name="payment_method" value="card">
                                        <span class="method-chip">
                                            <i class="fas fa-credit-card"></i>
                                            <span class="label">Card</span>
                                        </span>
                                    </label>
                                    <label class="method-option">
                                        <input type="radio" name="payment_method" value="net_banking">
                                        <span class="method-chip">
                                            <i class="fas fa-building-columns"></i>
                                            <span class="label">Net Banking</span>
                                        </span>
                                    </label>
                                    <label class="method-option">
                                        <input type="radio" name="payment_method" value="wallet">
                                        <span class="method-chip">
                                            <i class="fas fa-wallet"></i>
                                            <span class="label">Wallet</span>
                                        </span>
                                    </label>
                                </div>

                                <div class="payment-detail" id="paymentDetailBox">
                                    <div class="payment-detail-title">UPI Collector</div>
                                    <div class="mock-field" id="mockHint1">earthcafe.services@upi</div>
                                    <div class="mock-field" id="mockHint2">Scanner and app confirmation are simulated in sandbox mode.</div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small-label" for="payment_reference">Transaction Reference (Optional)</label>
                                        <input type="text" id="payment_reference" name="payment_reference" class="form-control mock-input" placeholder="Leave empty to auto-generate a MOCKTXN reference">
                                    </div>
                                </div>

                                <label class="form-label small-label mt-3">Simulate Payment Result</label>
                                <div class="result-toggle">
                                    <label>
                                        <input type="radio" name="mock_outcome" value="success" checked>
                                        <span class="result-pill success"><i class="fas fa-circle-check me-1"></i> Success</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="mock_outcome" value="failed">
                                        <span class="result-pill fail"><i class="fas fa-circle-xmark me-1"></i> Fail</span>
                                    </label>
                                </div>

                                <div class="secure-row">
                                    <span class="secure-tag"><i class="fas fa-shield-halved me-1"></i> SSL Secured</span>
                                    <span class="secure-tag"><i class="fas fa-key me-1"></i> PCI-DSS Lookalike</span>
                                    <span class="secure-tag"><i class="fas fa-vial me-1"></i> 100% Mock Gateway</span>
                                </div>

                                <div class="action-row">
                                    <button type="submit" class="btn-checkout primary" id="payButton">
                                        <i class="fas fa-lock"></i> Pay Securely (Mock)
                                    </button>
                                    <a href="apply_service.php?payment=cancelled" class="btn-checkout secondary" style="text-decoration:none;">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="status-paid">
                                <i class="fas fa-badge-check"></i>
                                Payment already completed for this application.
                            </div>
                            <div class="action-row">
                                <a href="client_dashboard.php?payment=success&request_id=<?php echo (int)$request_id; ?>" class="btn-checkout primary" style="text-decoration:none;">
                                    <i class="fas fa-grid-2"></i> Go to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <aside class="checkout-card">
                    <div class="card-head">
                        <h5><i class="fas fa-receipt"></i> Order Summary</h5>
                    </div>
                    <div class="card-body-inner">
                        <div class="summary-head">
                            <div class="summary-service"><?php echo htmlspecialchars($request['service_name']); ?></div>
                            <p class="summary-meta mb-0">Applicant: <?php echo htmlspecialchars($username); ?></p>
                        </div>

                        <div class="summary-line">
                            <span>Service Fee</span>
                            <strong>INR <?php echo number_format((float)$request['service_fee'], 2); ?></strong>
                        </div>
                        <div class="summary-line">
                            <span>Document Fee</span>
                            <strong>INR <?php echo number_format((float)$request['document_fee'], 2); ?></strong>
                        </div>
                        <div class="summary-line">
                            <span>Consultancy Fee</span>
                            <strong>INR <?php echo number_format((float)$request['consultancy_fee'], 2); ?></strong>
                        </div>

                        <div class="summary-total">
                            <span class="fw-bold">Total Payable</span>
                            <div class="value">
                                INR <?php echo number_format((float)$request['total_fee'], 2); ?>
                                <small>/ only</small>
                            </div>
                        </div>

                        <div class="mt-3 text-muted" style="font-size:0.86rem;">
                            <i class="fas fa-circle-info me-1"></i>
                            This is a simulated checkout for testing workflow and UI only.
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const methodRadios = Array.from(document.querySelectorAll('input[name="payment_method"]'));
            const detailTitle = document.querySelector('.payment-detail-title');
            const hint1 = document.getElementById('mockHint1');
            const hint2 = document.getElementById('mockHint2');
            const payButton = document.getElementById('payButton');
            const form = document.getElementById('mockPaymentForm');

            const methodMap = {
                upi: {
                    title: 'UPI Collector',
                    h1: 'earthcafe.services@upi',
                    h2: 'Scanner and app confirmation are simulated in sandbox mode.'
                },
                card: {
                    title: 'Card Checkout',
                    h1: 'Card ending in 4242 (demo visualization)',
                    h2: '3D Secure and OTP challenge are mocked for this page.'
                },
                net_banking: {
                    title: 'Net Banking Redirect',
                    h1: 'Demo bank gateway handoff (simulated)',
                    h2: 'No real bank login is triggered in this environment.'
                },
                wallet: {
                    title: 'Wallet Authorization',
                    h1: 'EarthPay Wallet - Sandbox',
                    h2: 'Wallet debit and callback response are simulated.'
                }
            };

            function updateMethodPanel() {
                const checked = methodRadios.find(function (el) { return el.checked; });
                if (!checked || !detailTitle || !hint1 || !hint2) return;
                const info = methodMap[checked.value] || methodMap.upi;
                detailTitle.textContent = info.title;
                hint1.textContent = info.h1;
                hint2.textContent = info.h2;
            }

            methodRadios.forEach(function (radio) {
                radio.addEventListener('change', updateMethodPanel);
            });
            updateMethodPanel();

            if (form && payButton) {
                form.addEventListener('submit', function () {
                    payButton.disabled = true;
                    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Mock Payment...';
                });
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
