<?php
/**
 * Chatwoot Dashboard App - WHMCS CRM Endpoint
 *
 * Provides real-time WHMCS client profile, active services, unpaid invoices,
 * support tickets, and live client search inside the Chatwoot Agent Dashboard.
 *
 * @package    WHMCS
 * @author     Bahari IT
 * @copyright  Copyright (c) 2026 Bahari IT
 * @version    2.0.0
 */

// Initialize WHMCS Core
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/chatwoot.php';

use WHMCS\Database\Capsule;

// Security headers: Allow iframe embedding in Chatwoot
header_remove('X-Frame-Options');
header("Content-Security-Policy: frame-ancestors *;");

// Verify CRM Secret Key
$configSecret = chatwoot_get_setting('crm_api_secret', '');
if (empty($configSecret)) {
    $configSecret = Capsule::table('tbladdonmodules')
        ->where('module', 'chatwoot')
        ->where('setting', 'crm_api_secret')
        ->value('value');
}

$requestSecret = isset($_GET['secret']) ? trim($_GET['secret']) : (isset($_POST['secret']) ? trim($_POST['secret']) : '');

if (empty($configSecret) || !hash_equals($configSecret, $requestSecret)) {
    http_response_code(403);
    die('<div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 30px; color: #dc2626; text-align: center;">
        <h3 style="margin-bottom:8px;">403 Forbidden</h3>
        <p style="color:#64748b;font-size:13px;">Invalid or missing Chatwoot CRM Dashboard Secret Key.</p>
    </div>');
}

// System URLs
$systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?: '', '/');
$adminFolder = chatwoot_get_admin_folder(); // Auto-detected or configured WHMCS admin directory

// AJAX Live Search API
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    try {
        $clientsQuery = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'phonenumber', 'status', 'credit')
            ->where(function ($q) use ($query) {
                if (is_numeric($query)) {
                    $q->orWhere('id', (int)$query);
                }
                $q->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('firstname', 'LIKE', "%{$query}%")
                  ->orWhere('lastname', 'LIKE', "%{$query}%")
                  ->orWhere('companyname', 'LIKE', "%{$query}%")
                  ->orWhere('phonenumber', 'LIKE', "%{$query}%");
            });

        // Search by domain if query looks like a domain
        if (strpos($query, '.') !== false) {
            $domainClientIds = Capsule::table('tblhosting')
                ->where('domain', 'LIKE', "%{$query}%")
                ->pluck('userid')
                ->toArray();
            if (!empty($domainClientIds)) {
                $clientsQuery->orWhereIn('id', $domainClientIds);
            }
        }

        $results = $clientsQuery->take(8)->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => trim($c->firstname . ' ' . $c->lastname),
                'company' => $c->companyname ?: '',
                'email' => $c->email,
                'phone' => $c->phonenumber ?: '',
                'status' => $c->status,
                'credit' => number_format((float)$c->credit, 2),
            ];
        });

        echo json_encode(['results' => $results]);
    } catch (\Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'results' => []]);
    }
    exit;
}

// Function to fetch full client context
function chatwoot_crm_get_client_data($client)
{
    if (!$client) {
        return null;
    }

    $uid = $client->id;
    $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
    $prefix = $currency ? $currency->prefix : '';
    $suffix = $currency ? $currency->suffix : 'BDT';

    // Services
    $services = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblhosting.userid', $uid)
        ->select('tblhosting.id', 'tblhosting.domain', 'tblhosting.amount', 'tblhosting.nextduedate', 'tblhosting.domainstatus as status', 'tblproducts.name as product_name')
        ->orderBy('tblhosting.id', 'DESC')
        ->take(5)
        ->get();

    // Invoices
    $invoices = Capsule::table('tblinvoices')
        ->where('userid', $uid)
        ->orderBy('id', 'DESC')
        ->take(5)
        ->get();

    // Tickets
    $tickets = Capsule::table('tbltickets')
        ->where('userid', $uid)
        ->orderBy('id', 'DESC')
        ->take(5)
        ->get();

    $activeServicesCount = Capsule::table('tblhosting')->where('userid', $uid)->where('domainstatus', 'Active')->count();
    $unpaidInvoicesCount = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')->count();
    $unpaidInvoicesTotal = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')->sum('total');

    return [
        'client' => $client,
        'prefix' => $prefix,
        'suffix' => $suffix,
        'services' => $services,
        'invoices' => $invoices,
        'tickets'  => $tickets,
        'active_services_count' => $activeServicesCount,
        'unpaid_invoices_count' => $unpaidInvoicesCount,
        'unpaid_invoices_total' => $unpaidInvoicesTotal,
    ];
}

// Sanitize incoming parameters (fix {{contact.email}} template tags)
$rawEmail = isset($_GET['email']) ? trim($_GET['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
$email = '';
if (!empty($rawEmail) && strpos($rawEmail, '{{') === false && strpos($rawEmail, '{') === false && strpos($rawEmail, '%7B') === false) {
    if (filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
        $email = $rawEmail;
    }
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0);
$phone  = isset($_GET['phone']) ? trim($_GET['phone']) : '';

// Lookup client
$client = null;
if ($userId > 0) {
    $client = Capsule::table('tblclients')->where('id', $userId)->first();
} elseif (!empty($email)) {
    $client = Capsule::table('tblclients')->where('email', $email)->first();
} elseif (!empty($phone)) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) >= 6) {
        $client = Capsule::table('tblclients')->where('phonenumber', 'LIKE', "%{$cleanPhone}%")->first();
    }
}

// Display preferences
$showServices = chatwoot_get_setting('crm_show_services', 'on') === 'on';
$showInvoices = chatwoot_get_setting('crm_show_invoices', 'on') === 'on';
$showTickets  = chatwoot_get_setting('crm_show_tickets', 'on') === 'on';
$showBalance  = chatwoot_get_setting('crm_show_balance', 'on') === 'on';
$showLoginBtn = chatwoot_get_setting('crm_show_login_btn', 'on') === 'on';

$clientData = chatwoot_crm_get_client_data($client);

// If AJAX render request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'client_view') {
    // Return HTML of the client view only
    if ($clientData) {
        include __DIR__ . '/crm_client_view.php';
    } else {
        include __DIR__ . '/crm_empty_view.php';
    }
    exit;
}
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
        
        /* Top Search Bar */
        .search-container {
            position: relative;
            margin-bottom: 12px;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 2px 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.15s ease;
        }
        .search-box:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .search-box i {
            color: #94a3b8;
            font-size: 13px;
            margin-right: 6px;
        }
        .search-box input {
            width: 100%;
            border: none;
            outline: none;
            padding: 7px 4px;
            font-size: 12px;
            color: #1e293b;
            background: transparent;
        }
        .search-clear {
            display: none;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
        }
        .search-clear:hover { color: #64748b; }
        
        /* Search Dropdown Results */
        .search-results-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-height: 280px;
            overflow-y: auto;
            z-index: 100;
        }
        .search-result-item {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.1s ease;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: #f8fafc; }
        .search-result-name { font-weight: 700; color: #1e293b; font-size: 13px; display: flex; justify-content: space-between; }
        .search-result-meta { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Cards */
        .card { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03); margin-bottom: 12px; overflow: hidden; }
        .card-header { background: #f8fafc; padding: 10px 14px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 12px 14px; }
        
        .client-profile-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .client-avatar { width: 44px; height: 44px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; flex-shrink: 0; box-shadow: 0 2px 5px rgba(37,99,235,0.25); }
        .client-name { font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .client-email { font-size: 12px; color: #64748b; word-break: break-all; }
        
        .badge { display: inline-block; padding: 2px 7px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }
        .badge-closed { background: #f1f5f9; color: #64748b; }
        .badge-suspended { background: #ffedd5; color: #c2410c; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        .badge-paid { background: #dcfce7; color: #15803d; }

        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
        .stat-box { background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .stat-label { font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .stat-val { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }

        .item-list { list-style: none; }
        .item-row { padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .item-row:last-child { border-bottom: none; }
        .item-title { font-weight: 600; color: #1d4ed8; text-decoration: none; font-size: 12px; }
        .item-title:hover { text-decoration: underline; }
        .item-meta { font-size: 11px; color: #64748b; margin-top: 2px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-primary { background: #2563eb; color: #ffffff !important; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-default { background: #ffffff; color: #334155 !important; border: 1px solid #cbd5e1; }
        .btn-default:hover { background: #f1f5f9; }
        .btn-block { width: 100%; margin-bottom: 6px; }

        .empty-notice { text-align: center; padding: 28px 14px; color: #64748b; }
        .empty-icon { width: 48px; height: 48px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 10px; }
        .empty-notice h4 { font-size: 14px; margin-bottom: 4px; color: #1e293b; font-weight: 700; }
        .empty-notice p { font-size: 12px; color: #64748b; margin-bottom: 12px; }
    </style>
</head>
<body>

    <!-- Real-Time Client Search Bar -->
    <div class="search-container">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="clientSearchInput" placeholder="Search client by Name, Email, Domain, or ID..." autocomplete="off">
            <button type="button" class="search-clear" id="searchClearBtn" title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="search-results-dropdown" id="searchResultsDropdown"></div>
    </div>

    <!-- Main Container -->
    <div id="crmContentArea">
        <?php if ($clientData): ?>
            <?php 
            $c = $clientData['client'];
            $uid = $c->id;
            $prefix = $clientData['prefix'];
            $suffix = $clientData['suffix'];
            $services = $clientData['services'];
            $invoices = $clientData['invoices'];
            $tickets = $clientData['tickets'];
            $activeServicesCount = $clientData['active_services_count'];
            $unpaidInvoicesCount = $clientData['unpaid_invoices_count'];
            ?>
            <!-- Client Profile Card -->
            <div class="card">
                <div class="card-body">
                    <div class="client-profile-header">
                        <div class="client-avatar">
                            <?php echo strtoupper(substr($c->firstname, 0, 1) . substr($c->lastname, 0, 1)); ?>
                        </div>
                        <div style="flex-grow: 1; min-width: 0;">
                            <div class="client-name">
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                    <?php echo htmlspecialchars($c->firstname . ' ' . $c->lastname); ?>
                                </span>
                                <span class="badge badge-<?php echo strtolower($c->status); ?>"><?php echo htmlspecialchars($c->status); ?></span>
                            </div>
                            <div class="client-email"><?php echo htmlspecialchars($c->email); ?></div>
                            <?php if ($c->companyname): ?>
                                <div class="client-email"><i class="fa-solid fa-building" style="font-size:11px;"></i> <?php echo htmlspecialchars($c->companyname); ?></div>
                            <?php endif; ?>
                            <?php if ($c->phonenumber): ?>
                                <div class="client-email"><i class="fa-solid fa-phone" style="font-size:11px;"></i> <?php echo htmlspecialchars(str_replace('.', ' ', $c->phonenumber)); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($showBalance): ?>
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
                            <div class="stat-val"><?php echo $prefix . number_format((float)$c->credit, 2) . $suffix; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Client ID</div>
                            <div class="stat-val">#<?php echo $c->id; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientssummary.php?userid=' . $uid); ?>" target="_blank" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full WHMCS Profile
                    </a>
                    <?php if ($showLoginBtn): ?>
                    <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/dologin.php?userid=' . $uid); ?>" target="_blank" class="btn btn-default btn-block">
                        <i class="fa-solid fa-right-to-bracket"></i> Login as Client
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Services Card -->
            <?php if ($showServices): ?>
            <div class="card">
                <div class="card-header">
                    <span><i class="fa-solid fa-server"></i> Services &amp; Hosting</span>
                    <span><?php echo count($services); ?></span>
                </div>
                <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
                    <?php if ($services->isEmpty()): ?>
                        <p style="color: #64748b; padding: 8px 0; font-size: 12px;">No active services found.</p>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($services as $srv): ?>
                                <li class="item-row">
                                    <div style="min-width:0; flex-grow:1; padding-right:8px;">
                                        <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientsservices.php?id=' . $srv->id); ?>" target="_blank" class="item-title" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                                            <?php echo htmlspecialchars($srv->product_name); ?>
                                        </a>
                                        <div class="item-meta">
                                            <?php echo $srv->domain ? htmlspecialchars($srv->domain) . ' &bull; ' : ''; ?>
                                            Due: <?php echo date('d/m/Y', strtotime($srv->nextduedate)); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right; flex-shrink:0;">
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
            <?php endif; ?>

            <!-- Invoices Card -->
            <?php if ($showInvoices): ?>
            <div class="card">
                <div class="card-header">
                    <span><i class="fa-solid fa-file-invoice-dollar"></i> Recent Invoices</span>
                    <span><?php echo count($invoices); ?></span>
                </div>
                <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
                    <?php if ($invoices->isEmpty()): ?>
                        <p style="color: #64748b; padding: 8px 0; font-size: 12px;">No invoices found.</p>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($invoices as $inv): ?>
                                <li class="item-row">
                                    <div>
                                        <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/invoices.php?action=edit&id=' . $inv->id); ?>" target="_blank" class="item-title">
                                            Invoice #<?php echo $inv->invoicenum ?: $inv->id; ?>
                                        </a>
                                        <div class="item-meta">
                                            Due: <?php echo date('d/m/Y', strtotime($inv->duedate)); ?>
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
            <?php endif; ?>

            <!-- Support Tickets Card -->
            <?php if ($showTickets): ?>
            <div class="card">
                <div class="card-header">
                    <span><i class="fa-solid fa-ticket"></i> Support Tickets</span>
                    <span><?php echo count($tickets); ?></span>
                </div>
                <div class="card-body" style="padding-top: 4px; padding-bottom: 4px;">
                    <?php if ($tickets->isEmpty()): ?>
                        <p style="color: #64748b; padding: 8px 0; font-size: 12px;">No support tickets found.</p>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($tickets as $tkt): ?>
                                <li class="item-row">
                                    <div style="min-width:0; flex-grow:1; padding-right:8px;">
                                        <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/supporttickets.php?action=viewticket&id=' . $tkt->id); ?>" target="_blank" class="item-title" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
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

        <?php else: ?>
            <!-- Friendly Empty State -->
            <div class="card">
                <div class="empty-notice">
                    <div class="empty-icon">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <h4>No WHMCS Client Selected</h4>
                    <p>
                        <?php if (!empty($email)): ?>
                            No client found matching <code><?php echo htmlspecialchars($email); ?></code>.
                        <?php else: ?>
                            Waiting for Chatwoot contact details or search manually using the search bar above.
                        <?php endif; ?>
                    </p>
                    <div style="margin-top: 14px;">
                        <a href="<?php echo htmlspecialchars($systemUrl . '/' . $adminFolder . '/clientsadd.php' . (!empty($email) ? '?email=' . urlencode($email) : '')); ?>" target="_blank" class="btn btn-primary btn-block">
                            <i class="fa-solid fa-user-plus"></i> Create New Client in WHMCS
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript for Live Search & Chatwoot postMessage Integration -->
    <script>
        var crmSecret = "<?php echo htmlspecialchars($configSecret); ?>";
        var searchInput = document.getElementById("clientSearchInput");
        var searchDropdown = document.getElementById("searchResultsDropdown");
        var clearBtn = document.getElementById("searchClearBtn");
        var searchTimeout = null;

        // Search Input Event
        searchInput.addEventListener("input", function() {
            var q = this.value.trim();
            if (q.length > 0) {
                clearBtn.style.display = "block";
            } else {
                clearBtn.style.display = "none";
                searchDropdown.style.display = "none";
                return;
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performClientSearch(q);
            }, 250);
        });

        clearBtn.addEventListener("click", function() {
            searchInput.value = "";
            clearBtn.style.display = "none";
            searchDropdown.style.display = "none";
        });

        document.addEventListener("click", function(e) {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = "none";
            }
        });

        function performClientSearch(query) {
            fetch("api.php?secret=" + encodeURIComponent(crmSecret) + "&action=search&q=" + encodeURIComponent(query))
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.results && data.results.length > 0) {
                        var html = "";
                        data.results.forEach(function(item) {
                            html += '<div class="search-result-item" onclick="loadClientById(' + item.id + ')">';
                            html += '<div class="search-result-name"><span>' + escapeHtml(item.name) + '</span><span class="badge badge-' + escapeHtml(item.status.toLowerCase()) + '">' + escapeHtml(item.status) + '</span></div>';
                            html += '<div class="search-result-meta">' + escapeHtml(item.email) + (item.company ? ' &bull; ' + escapeHtml(item.company) : '') + ' &bull; #' + item.id + '</div>';
                            html += '</div>';
                        });
                        searchDropdown.innerHTML = html;
                        searchDropdown.style.display = "block";
                    } else {
                        searchDropdown.innerHTML = '<div style="padding:12px;text-align:center;color:#64748b;font-size:12px;">No WHMCS clients found</div>';
                        searchDropdown.style.display = "block";
                    }
                })
                .catch(function(err) {
                    console.error("Search error:", err);
                });
        }

        function loadClientById(clientId) {
            searchDropdown.style.display = "none";
            window.location.href = "api.php?secret=" + encodeURIComponent(crmSecret) + "&user_id=" + encodeURIComponent(clientId);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Chatwoot Dashboard App Real-Time postMessage Context Listener
        window.addEventListener("message", function(event) {
            try {
                var payload = typeof event.data === "string" ? JSON.parse(event.data) : event.data;
                if (!payload) return;

                // Handle Chatwoot Dashboard App Context Event
                if (payload.event === "appContext" || payload.event === "chatwoot:dashboard-app:context") {
                    var data = payload.data || {};
                    var contact = data.contact || {};
                    var contactEmail = contact.email || "";
                    var contactPhone = contact.phone_number || "";
                    var clientId = (contact.custom_attributes && contact.custom_attributes.client_id) ? contact.custom_attributes.client_id : "";

                    // If contact email or clientId exists and current page has no client loaded
                    var currentEmail = "<?php echo addslashes($email); ?>";
                    var currentUid = <?php echo (int)($client ? $client->id : 0); ?>;

                    if (clientId && parseInt(clientId) !== currentUid) {
                        window.location.href = "api.php?secret=" + encodeURIComponent(crmSecret) + "&client_id=" + encodeURIComponent(clientId);
                    } else if (contactEmail && contactEmail.indexOf("@") !== -1 && contactEmail !== currentEmail && currentUid === 0) {
                        window.location.href = "api.php?secret=" + encodeURIComponent(crmSecret) + "&email=" + encodeURIComponent(contactEmail);
                    }
                }
            } catch (e) {
                // Ignore non-JSON postMessages
            }
        });
    </script>
</body>
</html>
