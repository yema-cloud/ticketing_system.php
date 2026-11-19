<?php
// Session safety check: Prevents duplicate session notices if already initialized
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mock resident data (Replace with database query via PDO/mysqli as needed)
$resident = [
    'name'    => 'Nomsa Dlamini',
    'account' => 'MUN-000482',
    'address' => '12 Hill Street, Makhanda',
    'ward'    => 'Ward 7'
];

// Municipal Service Data
$services = [
    'rates' => [
        'label'            => 'Property Rates',
        'dueDate'          => '2026-09-25',
        'previousBalance'  => 0.00,
        'lines'            => [
            ['desc' => 'Monthly rates levy — Erf 4021', 'amount' => 842.50],
            ['desc' => 'Refuse removal', 'amount' => 165.00]
        ]
    ],
    'water' => [
        'label'            => 'Water',
        'dueDate'          => '2026-09-25',
        'previousBalance'  => 120.00,
        'lines'            => [
            ['desc' => 'Usage: 18 kL @ tariff block 2', 'amount' => 356.40],
            ['desc' => 'Basic service charge', 'amount' => 95.00]
        ]
    ],
    'electricity' => [
        'label'            => 'Electricity',
        'dueDate'          => '2026-09-20',
        'previousBalance'  => 0.00,
        'lines'            => [
            ['desc' => 'Usage: 410 kWh @ tariff block 3', 'amount' => 921.80],
            ['desc' => 'Basic service charge', 'amount' => 140.00]
        ]
    ]
];

// Helper functions for totals and formatting
function getServiceTotal(array $service): float {
    $sum = $service['previousBalance'];
    foreach ($service['lines'] as $line) {
        $sum += $line['amount'];
    }
    return $sum;
}

function formatCurrency(float $amount): string {
    return 'R ' . number_format($amount, 2, '.', ' ');
}

// Initialise payment history in session if empty
if (!isset($_SESSION['payment_history'])) {
    $_SESSION['payment_history'] = [
        ['ref' => 'TXN-88213', 'date' => '2026-08-22', 'services' => ['Electricity'], 'amount' => 890.15, 'method' => 'CARD', 'status' => 'approved'],
        ['ref' => 'TXN-87904', 'date' => '2026-08-18', 'services' => ['Water'], 'amount' => 410.00, 'method' => 'EFT', 'status' => 'approved'],
        ['ref' => 'TXN-87561', 'date' => '2026-08-03', 'services' => ['Rates'], 'amount' => 1007.50, 'method' => 'CARD', 'status' => 'approved']
    ];
}

// Handle Payment Submission (POST)
$lastReceipt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token execution.");
    }

    $selectedKeys = $_POST['selected_services'] ?? [];
    $method       = strtoupper(htmlspecialchars($_POST['method'] ?? 'CARD'));
    
    if (!empty($selectedKeys)) {
        $totalPaid = 0;
        $paidServiceLabels = [];
        
        foreach ($selectedKeys as $key) {
            if (isset($services[$key])) {
                $totalPaid += getServiceTotal($services[$key]);
                $paidServiceLabels[] = $services[$key]['label'];
            }
        }

        $ref = 'TXN-' . rand(10000, 99999);
        $newReceipt = [
            'ref'      => $ref,
            'date'     => date('Y-m-d'),
            'services' => $paidServiceLabels,
            'amount'   => $totalPaid,
            'method'   => $method,
            'status'   => 'approved'
        ];

        // Store in session history
        array_unshift($_SESSION['payment_history'], $newReceipt);
        $lastReceipt = $newReceipt;
        $_GET['view'] = 'confirm';
    }
}

// Current View Handler
$view = $_GET['view'] ?? 'dashboard';
$activeServiceKey = $_GET['service'] ?? 'electricity';
if (!array_key_exists($activeServiceKey, $services)) {
    $activeServiceKey = 'electricity';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NathiSiConnected - Municipal Accounts & Payments</title>
    <!-- Standard System Font Awesome & Stylesheet -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Baseline structural CSS aligning with NathiSiConnected style rules */
        body {
            background-color: rgba(0, 0, 0, .1);
            color: #1C2321;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        /* Standardized Header Bar */
        .top-header {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 1px solid #CFC9BB;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .header-title h1 {
            font-family: 'Iowan Old Style', Georgia, serif;
            color: #0a3654;
            margin: 0;
            font-size: 28px;
            text-align: left;
        }

        .header-subtext {
            color: #4B534F;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Nav Tab Bar */
        .nav-tabs {
            display: flex;
            gap: 20px;
        }

        .nav-tabs a {
            color: #4B534F;
            text-decoration: none;
            font-weight: 500;
            padding-bottom: 8px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .nav-tabs a.active {
            color: #0a3654;
            border-bottom-color: #A6790F;
        }

        /* Overview Summary Banner */
        .summary-banner {
            background-color: #0a3654;
            color: #EFEDE6;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .summary-banner .total-amount {
            font-family: 'Iowan Old Style', Georgia, serif;
            font-size: 42px;
            margin: 10px 0;
        }

        /* Grid Layouts */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .service-card {
            background-color: #F7F5F0;
            border: 1px solid #CFC9BB;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .service-card h3 {
            margin: 0 0 10px 0;
            color: #0a3654;
        }

        .service-card .amount {
            font-family: 'Iowan Old Style', Georgia, serif;
            font-size: 28px;
            margin-bottom: 10px;
        }

        /* Buttons */
        .btn-primary {
            background-color: #25A85A;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
        }

        .btn-primary:hover {
            background-color: #2ecc71;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-secondary {
            background-color: #0a3654;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background-color: #082d46;
        }

        /* Tables */
        .table-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 20px;
            overflow-x: auto;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        table th {
            background-color: #0a3654;
            color: #ffffff;
            padding: 12px 15px;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        /* Badges */
        .badge-approved {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Top Navigation Header -->
    <div class="top-header">
        <div class="header-info">
            <div class="header-title">
                <h1>Makhanda Municipality</h1>
                <div class="header-subtext">
                    <?= htmlspecialchars($resident['name']) ?> · Account: <?= htmlspecialchars($resident['account']) ?> · <?= htmlspecialchars($resident['ward']) ?>
                </div>
            </div>
            <div class="header-subtext" style="text-align: right;">
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($resident['address']) ?>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="?view=dashboard" class="<?= $view === 'dashboard' ? 'active' : '' ?>">Overview</a>
            <a href="?view=statement" class="<?= $view === 'statement' ? 'active' : '' ?>">Statement</a>
            <a href="?view=pay" class="<?= ($view === 'pay' || $view === 'confirm') ? 'active' : '' ?>">Pay Bill</a>
            <a href="?view=history" class="<?= $view === 'history' ? 'active' : '' ?>">Payment History</a>
        </div>
    </div>

    <!-- VIEW 1: DASHBOARD OVERVIEW -->
    <?php if ($view === 'dashboard'): ?>
        <?php
            $grandTotal = 0;
            foreach ($services as $s) {
                $grandTotal += getServiceTotal($s);
            }
        ?>
        <div class="summary-banner">
            <div style="opacity: 0.8; font-size: 14px;">Total outstanding across all municipal accounts</div>
            <div class="total-amount"><?= formatCurrency($grandTotal) ?></div>
            <div style="opacity: 0.8; font-size: 14px;">Earliest Due: <?= date('d M Y', strtotime($services['electricity']['dueDate'])) ?></div>
        </div>

        <div class="service-grid">
            <?php foreach ($services as $key => $s): ?>
                <?php $total = getServiceTotal($s); ?>
                <div class="service-card">
                    <div>
                        <h3><?= htmlspecialchars($s['label']) ?></h3>
                        <div class="amount"><?= formatCurrency($total) ?></div>
                        <div style="font-size: 13px; color: #4B534F;">Due <?= date('d M Y', strtotime($s['dueDate'])) ?></div>
                    </div>
                    <div style="margin-top: 20px;">
                        <a href="?view=statement&service=<?= $key ?>" class="btn-secondary">View Statement</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <!-- VIEW 2: STATEMENT -->
    <?php elseif ($view === 'statement'): ?>
        <?php 
            $current = $services[$activeServiceKey]; 
            $statementTotal = getServiceTotal($current);
        ?>
        <div class="nav-tabs" style="margin-bottom: 20px;">
            <?php foreach ($services as $key => $s): ?>
                <a href="?view=statement&service=<?= $key ?>" class="<?= $activeServiceKey === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-card">
            <h2><?= htmlspecialchars($current['label']) ?> Statement</h2>
            <p style="text-align: left; color: #4B534F; padding: 0;">Due Date: <strong><?= date('d M Y', strtotime($current['dueDate'])) ?></strong></p>
            
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($current['previousBalance'] > 0): ?>
                    <tr>
                        <td>Balance brought forward</td>
                        <td style="text-align: right;"><?= formatCurrency($current['previousBalance']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($current['lines'] as $line): ?>
                    <tr>
                        <td><?= htmlspecialchars($line['desc']) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($line['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; font-size: 18px; background-color: #f8f9fa;">
                        <td>Total Amount Due</td>
                        <td style="text-align: right; color: #0a3654;"><?= formatCurrency($statementTotal) ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 25px; display: flex; gap: 15px;">
                <a href="?view=pay&preselect=<?= $activeServiceKey ?>" class="btn-primary">Pay This Bill</a>
                <button onclick="window.print()" class="btn-secondary"><i class="fa-solid fa-print"></i> Print / Download PDF</button>
            </div>
        </div>

    <!-- VIEW 3: PAY BILL FORM -->
    <?php elseif ($view === 'pay'): ?>
        <?php $preselect = $_GET['preselect'] ?? null; ?>
        <div class="table-card" style="max-width: 600px; margin: 0 auto;">
            <h2>Make a Payment</h2>
            
            <form method="POST" action="?view=pay">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <p style="text-align: left; padding:0; font-weight: bold;">Select accounts to pay:</p>
                <div style="border-bottom: 1px solid #CFC9BB; padding-bottom: 15px; margin-bottom: 20px;">
                    <?php foreach ($services as $key => $s): ?>
                        <?php $sTotal = getServiceTotal($s); ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0;">
                            <label style="cursor: pointer;">
                                <input type="checkbox" name="selected_services[]" value="<?= $key ?>" <?= ($preselect === $key || $preselect === null) ? 'checked' : '' ?>>
                                <strong><?= htmlspecialchars($s['label']) ?></strong>
                            </label>
                            <span><?= formatCurrency($sTotal) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="text-align: left; padding:0; font-weight: bold;">Payment Method:</p>
                <div style="display: flex; gap: 15px; margin-bottom: 25px;">
                    <label><input type="radio" name="method" value="Card" checked> Credit/Debit Card</label>
                    <label><input type="radio" name="method" value="EFT"> Electronic Funds Transfer (EFT)</label>
                    <label><input type="radio" name="method" value="USSD"> USSD Mobile</label>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;">Process Payment</button>
            </form>
        </div>

    <!-- VIEW 4: PAYMENT CONFIRMATION -->
    <?php elseif ($view === 'confirm' && $lastReceipt): ?>
        <div class="table-card" style="max-width: 500px; margin: 0 auto; text-align: center;">
            <div class="badge-approved" style="margin-bottom: 15px;">Payment Successful</div>
            <h1 style="color: #0a3654; margin: 10px 0;"><?= formatCurrency($lastReceipt['amount']) ?></h1>
            <p style="color: #4B534F;">Reference: <strong><?= htmlspecialchars($lastReceipt['ref']) ?></strong></p>

            <table style="margin-top: 20px;">
                <tr>
                    <td>Date</td>
                    <td style="text-align: right;"><?= htmlspecialchars($lastReceipt['date']) ?></td>
                </tr>
                <tr>
                    <td>Services Paid</td>
                    <td style="text-align: right;"><?= htmlspecialchars(implode(', ', $lastReceipt['services'])) ?></td>
                </tr>
                <tr>
                    <td>Payment Method</td>
                    <td style="text-align: right;"><?= htmlspecialchars($lastReceipt['method']) ?></td>
                </tr>
            </table>

            <div style="margin-top: 25px;">
                <a href="?view=history" class="btn-primary">View Payment History</a>
            </div>
        </div>

    <!-- VIEW 5: PAYMENT HISTORY -->
    <?php elseif ($view === 'history'): ?>
        <div class="table-card">
            <h2>Payment History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Services</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['payment_history'] as $item): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($item['date'])) ?></td>
                        <td><strong><?= htmlspecialchars($item['ref']) ?></strong></td>
                        <td><?= htmlspecialchars(implode(', ', $item['services'])) ?></td>
                        <td><?= htmlspecialchars($item['method']) ?></td>
                        <td><span class="badge-approved">Approved</span></td>
                        <td style="text-align: right; font-weight: bold;"><?= formatCurrency($item['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

</body>
</html>