<?php
/**
 * Chatwoot Dashboard App - WHMCS CRM Endpoint
 *
 * Provides real-time WHMCS client profile, active services, unpaid invoices,
 * and support tickets inside the Chatwoot Agent Dashboard.
 *
 * @package    WHMCS
 * @author     Bahari IT
 * @copyright  Copyright (c) 2026 Bahari IT
 * @version    1.0.0
 */

// Initialize WHMCS
require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

// Set security headers to allow iframe embedding in Chatwoot
header_remove('X-Frame-Options');
header("Content-Security-Policy: frame-ancestors *;");

// Verify Secret Key
$configSecret = Capsule::table('tbladdonmodules')
    ->where('module', 'chatwoot')
    ->where('setting', 'crm_api_secret')
    ->value('value');

$requestSecret = isset($_GET['secret']) ? trim($_GET['secret']) : (isset($_POST['secret']) ? trim($_POST['secret']) : '');

if (empty($configSecret) || !hash_equals($configSecret, $requestSecret)) {
    http_response_code(403);
    die('<div style="font-family: sans-serif; padding: 20px; color: #dc2626; text-align: center;"><h3>403 Forbidden</h3><p>Invalid or missing Dashboard Secret Key.</p></div>');
}

$email = isset($_GET['email']) ? trim($_GET['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$client = null;
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $client = Capsule::table('tblclients')->where('email', $email)->first();
} elseif ($userId > 0) {
    $client = Capsule::table('tblclients')->where('id', $userId)->first();
}

$systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?: '', '/');
$adminFolder = 'admin'; // Standard WHMCS admin directory
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS CRM - Chatwoot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: #f8fafc; color: #1e293b; font-size: 13px; line-height: 1.5; padding: 12px; }
        .card { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03); margin-bottom: 12px; overflow: hidden; }
        .card-header { background: #f1f5f9; padding: 10px 14px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 12px 14px; }
        
        .client-profile-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .client-avatar { width: 44px; height: 44px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
        .client-name { font-size: 15px; font-weight: 700; color: #0f172a; }
        .client-email { font-size: 12px; color: #64748b; }
        
        .badge { display: inline-block; padding: 2px 7px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-suspended { background: #ffedd5; color: #c2410c; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        .badge-paid { background: #dcfce7; color: #15803d; }

        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
        .stat-box { background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .stat-label { font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .stat-val { font-size: 14px; font-weight: 700; color: #0f172a; }

        .item-list { list-style: none; }
        .item-row { padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .item-row:last-child { border-bottom: none; }
        .item-title { font-weight: 600; color: #1e40af; text-decoration: none; }
        .item-meta { font-size: 11px; color: #64748b; margin-top: 2px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-primary { background: #2563eb; color: #ffffff !important; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-default { background: #ffffff; color: #334155 !important; border: 1px solid #cbd5e1; }
        .btn-default:hover { background: #f1f5f9; }
        .btn-block { width: 100%; margin-bottom: 6px; }

        .empty-notice { text-align: center; padding: 30px 10px; color: #64748b; }
        .empty-notice i { font-size: 36px; margin-bottom: 8px; color: #cbd5e1; }
    </style>
</head>
<body>

<?php if (!$client): ?>
    <div class="card">
        <div class="empty-notice">
            <i class="fa-solid fa-user-slash"></i>
            <h4 style="font-size: 14px; margin-bottom: 4px; color: #1e293b;">Client Not Found in WHMCS</h4>
            <p style="font-size: 12px;">No WHMCS account matches <code><?php echo htmlspecialchars($email ?: 'this contact'); ?></code></p>
            <div style="margin-top: 15px;">
                <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientsadd.php?email=' . urlencode($email)); ?>" target="_blank" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-user-plus"></i> Create New Client in WHMCS
                </a>
            </div>
        </div>
    </div>
<?php else: 
    $uid = $client->id;
    $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
    $prefix = $currency ? $currency->prefix : '';
    $suffix = $currency ? $currency->suffix : 'BDT';

    // Fetch Services
    $services = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblhosting.userid', $uid)
        ->select('tblhosting.id', 'tblhosting.domain', 'tblhosting.amount', 'tblhosting.nextduedate', 'tblhosting.domainstatus as status', 'tblproducts.name as product_name')
        ->orderBy('tblhosting.id', 'DESC')
        ->take(5)
        ->get();

    // Fetch Invoices
    $invoices = Capsule::table('tblinvoices')
        ->where('userid', $uid)
        ->orderBy('id', 'DESC')
        ->take(5)
        ->get();

    // Fetch Tickets
    $tickets = Capsule::table('tbltickets')
        ->where('userid', $uid)
        ->orderBy('id', 'DESC')
        ->take(5)
        ->get();

    $activeServicesCount = Capsule::table('tblhosting')->where('userid', $uid)->where('domainstatus', 'Active')->count();
    $unpaidInvoicesCount = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')->count();
?>
    <!-- Client Profile Card -->
    <div class="card">
        <div class="card-body">
            <div class="client-profile-header">
                <div class="client-avatar">
                    <?php echo strtoupper(substr($client->firstname, 0, 1) . substr($client->lastname, 0, 1)); ?>
                </div>
                <div style="flex-grow: 1;">
                    <div class="client-name">
                        <?php echo htmlspecialchars($client->firstname . ' ' . $client->lastname); ?>
                        <span class="badge badge-<?php echo strtolower($client->status); ?>"><?php echo htmlspecialchars($client->status); ?></span>
                    </div>
                    <div class="client-email"><?php echo htmlspecialchars($client->email); ?></div>
                    <?php if ($client->phonenumber): ?>
                        <div class="client-email"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars(str_replace('.', ' ', $client->phonenumber)); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-label">Active Services</div>
                    <div class="stat-val"><?php echo $activeServicesCount; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Unpaid Invoices</div>
                    <div class="stat-val" style="<?php echo $unpaidInvoicesCount > 0 ? 'color: #dc2626;' : ''; ?>">
                        <?php echo $unpaidInvoicesCount; ?>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Credit Balance</div>
                    <div class="stat-val"><?php echo $prefix . number_format((float)$client->credit, 2) . $suffix; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Client ID</div>
                    <div class="stat-val">#<?php echo $client->id; ?></div>
                </div>
            </div>

            <!-- Quick Action Links -->
            <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientssummary.php?userid=' . $uid); ?>" target="_blank" class="btn btn-primary btn-block">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full WHMCS Profile
            </a>
            <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/dologin.php?userid=' . $uid); ?>" target="_blank" class="btn btn-default btn-block">
                <i class="fa-solid fa-right-to-bracket"></i> Login as Client
            </a>
        </div>
    </div>

    <!-- Active Services Card -->
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-server"></i> Services & Hosting</span>
            <span><?php echo count($services); ?></span>
        </div>
        <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
            <?php if ($services->isEmpty()): ?>
                <p style="color: #64748b; padding: 8px 0;">No active services found.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($services as $srv): ?>
                        <li class="item-row">
                            <div>
                                <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientsservices.php?id=' . $srv->id); ?>" target="_blank" class="item-title">
                                    <?php echo htmlspecialchars($srv->product_name); ?>
                                </a>
                                <div class="item-meta">
                                    <?php echo $srv->domain ? htmlspecialchars($srv->domain) . ' &bull; ' : ''; ?>
                                    Due: <?php echo date('d/m/Y', strtotime($srv->nextduedate)); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge badge-<?php echo strtolower($srv->status); ?>"><?php echo htmlspecialchars($srv->status); ?></span>
                                <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                    <?php echo $prefix . number_format((float)$srv->amount, 2) . $suffix; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoices Card -->
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-file-invoice-dollar"></i> Recent Invoices</span>
            <span><?php echo count($invoices); ?></span>
        </div>
        <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
            <?php if ($invoices->isEmpty()): ?>
                <p style="color: #64748b; padding: 8px 0;">No invoices found.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($invoices as $inv): ?>
                        <li class="item-row">
                            <div>
                                <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/invoices.php?action=edit&id=' . $inv->id); ?>" target="_blank" class="item-title">
                                    Invoice #<?php echo $inv->invoicenum ?: $inv->id; ?>
                                </a>
                                <div class="item-meta">
                                    Date: <?php echo date('d/m/Y', strtotime($inv->date)); ?> &bull; Due: <?php echo date('d/m/Y', strtotime($inv->duedate)); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge badge-<?php echo strtolower($inv->status); ?>"><?php echo htmlspecialchars($inv->status); ?></span>
                                <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                    <?php echo $prefix . number_format((float)$inv->total, 2) . $suffix; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Support Tickets Card -->
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-ticket"></i> Support Tickets</span>
            <span><?php echo count($tickets); ?></span>
        </div>
        <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
            <?php if ($tickets->isEmpty()): ?>
                <p style="color: #64748b; padding: 8px 0;">No support tickets found.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($tickets as $tkt): ?>
                        <li class="item-row">
                            <div>
                                <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/supporttickets.php?action=viewticket&id=' . $tkt->id); ?>" target="_blank" class="item-title">
                                    #<?php echo htmlspecialchars($tkt->tid); ?> - <?php echo htmlspecialchars($tkt->title); ?>
                                </a>
                                <div class="item-meta">
                                    Updated: <?php echo date('d/m/Y H:i', strtotime($tkt->lastreply)); ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge" style="background: #e2e8f0; color: #334155;"><?php echo htmlspecialchars($tkt->status); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
