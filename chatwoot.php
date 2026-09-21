<?php
/**
 * WHMCS Addon Module: Chatwoot Live Chat & Agent CRM
 *
 * @package    WHMCS
 * @author     Bahari IT
 * @copyright  Copyright (c) 2026 Bahari IT
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Define addon module configuration parameters.
 *
 * @return array
 */
function chatwoot_config()
{
    return [
        'name' => 'Chatwoot Live Chat & CRM',
        'description' => 'Seamless Chatwoot Live Chat widget integration with logged-in user auto-sync, HMAC identity security, and Chatwoot Agent CRM Dashboard App for WHMCS.',
        'author' => 'Bahari IT',
        'language' => 'english',
        'version' => '1.0.0',
        'logo' => 'logo.png',
        'fields' => [
            'base_url' => [
                'FriendlyName' => 'Chatwoot Base URL',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'https://app.chatwoot.com',
                'Description' => 'Your Chatwoot instance URL (e.g. <code>https://app.chatwoot.com</code> or your self-hosted URL).',
            ],
            'website_token' => [
                'FriendlyName' => 'Website Inbox Token',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'The Website Inbox Token from Chatwoot Settings &rarr; Inboxes.',
            ],
            'hmac_token' => [
                'FriendlyName' => 'Identity Validation HMAC Secret',
                'Type' => 'password',
                'Size' => '50',
                'Default' => '',
                'Description' => '(Optional) HMAC key for secure identity validation to prevent user impersonation.',
            ],
            'visibility' => [
                'FriendlyName' => 'Widget Visibility',
                'Type' => 'dropdown',
                'Options' => [
                    'all' => 'Show to All Visitors (Guests & Logged-in)',
                    'logged_in' => 'Logged-in Clients Only',
                ],
                'Default' => 'all',
                'Description' => 'Choose who can see the live chat widget.',
            ],
            'widget_position' => [
                'FriendlyName' => 'Widget Position',
                'Type' => 'dropdown',
                'Options' => [
                    'right' => 'Bottom Right',
                    'left' => 'Bottom Left',
                ],
                'Default' => 'right',
                'Description' => 'Screen position for the live chat bubble.',
            ],
            'sync_attributes' => [
                'FriendlyName' => 'Sync Custom Client Attributes',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Send client details (Account Balance, Active Services, Unpaid Invoices) to Chatwoot.',
            ],
            'crm_api_secret' => [
                'FriendlyName' => 'Agent CRM Dashboard Secret Key',
                'Type' => 'text',
                'Size' => '40',
                'Default' => bin2hex(random_bytes(16)),
                'Description' => 'Security key to protect the Chatwoot Agent CRM Dashboard App iframe.',
            ],
        ]
    ];
}

/**
 * Activate addon module.
 *
 * @return array
 */
function chatwoot_activate()
{
    return [
        'status' => 'success',
        'description' => 'Chatwoot Live Chat & CRM module has been successfully activated.'
    ];
}

/**
 * Deactivate addon module.
 *
 * @return array
 */
function chatwoot_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Chatwoot Live Chat & CRM module has been deactivated.'
    ];
}

/**
 * Admin Area Output.
 *
 * @param array $vars
 */
function chatwoot_output($vars)
{
    $modulelink = $vars['modulelink'];
    $version    = $vars['version'];
    $baseUrl    = rtrim($vars['base_url'] ?: 'https://app.chatwoot.com', '/');
    $websiteToken = $vars['website_token'];
    $crmSecret  = $vars['crm_api_secret'];
    $systemUrl  = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?: '', '/');
    $dashboardAppUrl = "{$systemUrl}/modules/addons/chatwoot/api.php?secret=" . urlencode($crmSecret) . "&email={{contact.email}}";

    ?>
    <div class="chatwoot-admin-wrap" style="padding: 15px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- Header Banner -->
        <div style="background: linear-gradient(135deg, #1f2937, #111827); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0 0 6px 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    <img src="../modules/addons/chatwoot/logo.png" style="width: 34px; height: 34px; border-radius: 8px; vertical-align: middle;" alt="Chatwoot">
                    Chatwoot Live Chat & Agent CRM
                    <span style="background: #10b981; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700; text-transform: uppercase;">v<?php echo $version; ?></span>
                </h2>
                <p style="margin: 0; color: #9ca3af; font-size: 14px;">Official integration module by Bahari IT for WHMCS & Chatwoot</p>
            </div>
            <div>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>" target="_blank" class="btn btn-primary" style="background: #2563eb; border: none; font-weight: 700; padding: 8px 18px; border-radius: 8px;">
                    <i class="fa-solid fa-external-link"></i> Open Chatwoot Dashboard
                </a>
            </div>
        </div>

        <!-- Setup Status Alert -->
        <?php if (empty($websiteToken)): ?>
            <div class="alert alert-warning" style="border-radius: 8px; border-left: 5px solid #f59e0b; padding: 15px;">
                <h4 style="margin: 0 0 5px 0; font-weight: 700;"><i class="fa-solid fa-triangle-exclamation"></i> Action Required: Website Inbox Token is Missing</h4>
                <p style="margin: 0;">Please enter your Chatwoot Website Inbox Token in the Addon Module configuration to activate the live chat widget on your website.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="border-radius: 8px; border-left: 5px solid #10b981; padding: 15px;">
                <h4 style="margin: 0 0 5px 0; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Live Chat Widget is Active</h4>
                <p style="margin: 0;">Your Chatwoot widget is successfully configured and broadcasting to your client area and public pages.</p>
            </div>
        <?php endif; ?>

        <!-- Two Column Configuration Guide -->
        <div class="row">
            <!-- Left Card: Live Chat Details -->
            <div class="col-md-6">
                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div class="panel-heading" style="background: #f8fafc; font-weight: 700; font-size: 15px; padding: 14px 18px;">
                        <i class="fa-solid fa-comments text-primary"></i> Live Chat Integration Settings
                    </div>
                    <div class="panel-body" style="padding: 18px;">
                        <table class="table table-bordered" style="margin-bottom: 0;">
                            <tr>
                                <th style="width: 40%; background: #f8fafc;">Chatwoot URL</th>
                                <td><code><?php echo htmlspecialchars($baseUrl); ?></code></td>
                            </tr>
                            <tr>
                                <th style="background: #f8fafc;">Inbox Token</th>
                                <td><?php echo $websiteToken ? '<code>' . htmlspecialchars($websiteToken) . '</code>' : '<span class="text-danger">Not Set</span>'; ?></td>
                            </tr>
                            <tr>
                                <th style="background: #f8fafc;">HMAC Identity Security</th>
                                <td><?php echo !empty($vars['hmac_token']) ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Optional (Not configured)</span>'; ?></td>
                            </tr>
                            <tr>
                                <th style="background: #f8fafc;">Widget Visibility</th>
                                <td><span class="label label-info"><?php echo htmlspecialchars($vars['visibility'] == 'logged_in' ? 'Logged-in Clients Only' : 'All Visitors'); ?></span></td>
                            </tr>
                            <tr>
                                <th style="background: #f8fafc;">Widget Position</th>
                                <td><span class="label label-default"><?php echo htmlspecialchars(ucfirst($vars['widget_position'] ?: 'right')); ?></span></td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px;">
                            <a href="configaddonmods.php#chatwoot" class="btn btn-default btn-block" style="font-weight: 600;">
                                <i class="fa-solid fa-gear"></i> Edit Addon Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Card: Agent CRM Dashboard App Setup Guide -->
            <div class="col-md-6">
                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div class="panel-heading" style="background: #f8fafc; font-weight: 700; font-size: 15px; padding: 14px 18px;">
                        <i class="fa-solid fa-id-card-clip text-success"></i> Chatwoot Agent CRM App (Sidebar CRM)
                    </div>
                    <div class="panel-body" style="padding: 18px;">
                        <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">
                            Display live WHMCS client profile, active services, unpaid invoices, and tickets right inside Chatwoot when agents chat with clients!
                        </p>

                        <label style="font-size: 12px; font-weight: 700; color: #1e293b;">Your Dashboard App URL:</label>
                        <div class="input-group" style="margin-bottom: 14px;">
                            <input type="text" id="csmDashboardUrl" class="form-control" value="<?php echo htmlspecialchars($dashboardAppUrl); ?>" readonly style="background: #f8fafc; font-family: monospace; font-size: 12px;">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="copyDashboardUrl()" id="btnCopyUrl" title="Copy to Clipboard">
                                    <i class="fa-regular fa-copy"></i> Copy
                                </button>
                            </span>
                        </div>

                        <div style="background: #f1f5f9; padding: 12px; border-radius: 8px; font-size: 12px; line-height: 1.6; color: #334155;">
                            <strong>How to add to Chatwoot:</strong>
                            <ol style="margin: 6px 0 0 0; padding-left: 18px;">
                                <li>In Chatwoot, go to <strong>Settings &rarr; Dashboard Apps &rarr; Add New App</strong>.</li>
                                <li>Set App Name: <strong>WHMCS CRM</strong>.</li>
                                <li>Paste the <strong>Dashboard App URL</strong> copied above.</li>
                                <li>Click <strong>Save</strong>. Now your agents have full WHMCS context inside Chatwoot!</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copyDashboardUrl() {
        var copyText = document.getElementById("csmDashboardUrl");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        var btn = document.getElementById("btnCopyUrl");
        btn.innerHTML = '<i class="fa-solid fa-check text-success"></i> Copied!';
        setTimeout(function() {
            btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
        }, 2000);
    }
    </script>
    <?php
}
