<?php
/**
 * WHMCS Addon Module: Chatwoot Client Area Hooks
 *
 * @package    WHMCS
 * @author     Bahari IT
 * @copyright  Copyright (c) 2026 Bahari IT
 * @version    2.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/chatwoot.php';

/**
 * Injects Chatwoot live chat widget into WHMCS Client Area Footer
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    try {
        // Check if widget is enabled
        $widgetEnabled = chatwoot_get_setting('widget_enabled', 'on') === 'on';
        if (!$widgetEnabled) {
            return '';
        }

        $websiteToken = trim(chatwoot_get_setting('website_token', ''));
        if (empty($websiteToken)) {
            return '';
        }

        $baseUrl        = rtrim(chatwoot_get_setting('base_url', 'https://app.chatwoot.com'), '/');
        $visibility     = chatwoot_get_setting('visibility', 'all');
        $hmacToken      = trim(chatwoot_get_setting('hmac_token', ''));
        $widgetPosition = chatwoot_get_setting('widget_position', 'right');
        $launcherTitle  = chatwoot_get_setting('widget_launcher_title', 'Chat with us');
        $syncProfile    = chatwoot_get_setting('sync_client_profile', 'on') === 'on';
        $syncAvatar     = chatwoot_get_setting('sync_avatar', 'on') === 'on';
        $syncAttributes = chatwoot_get_setting('sync_attributes', 'on') === 'on';

        // Check user session
        $uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;

        if ($visibility === 'logged_in' && $uid <= 0) {
            return '';
        }

        $clientScript = '';
        if ($uid > 0 && $syncProfile) {
            $client = Capsule::table('tblclients')->where('id', $uid)->first();
            if ($client) {
                $fullName = trim($client->firstname . ' ' . $client->lastname);
                $email    = trim($client->email);
                $rawPhone = trim($client->phonenumber);
                $cleanPhone = preg_replace('/[^0-9+]/', '', str_replace('.', '', $rawPhone));
                $company  = trim($client->companyname ?: '');

                // HMAC Identity Hash
                $identifierHash = '';
                if (!empty($hmacToken) && !empty($email)) {
                    $identifierHash = hash_hmac('sha256', $email, $hmacToken);
                }

                // Custom Client Attributes
                $customAttrs = [];
                if ($syncAttributes) {
                    $activeServices = Capsule::table('tblhosting')->where('userid', $uid)->where('domainstatus', 'Active')->count();
                    $activeDomains  = Capsule::table('tbldomains')->where('userid', $uid)->where('status', 'Active')->count();
                    $unpaidInvoices = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')->count();
                    $unpaidTotal    = Capsule::table('tblinvoices')->where('userid', $uid)->where('status', 'Unpaid')->sum('total');

                    $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
                    $currencyCode = $currency ? $currency->code : 'BDT';

                    $customAttrs = [
                        'client_id' => (string)$client->id,
                        'company' => $company ?: 'N/A',
                        'status' => $client->status,
                        'active_services' => (int)$activeServices,
                        'active_domains' => (int)$activeDomains,
                        'unpaid_invoices' => (int)$unpaidInvoices,
                        'unpaid_balance' => number_format((float)$unpaidTotal, 2) . ' ' . $currencyCode,
                        'credit_balance' => number_format((float)$client->credit, 2) . ' ' . $currencyCode,
                    ];
                }

                $userData = [
                    'name' => $fullName,
                    'email' => $email,
                    'phone_number' => $cleanPhone ?: null,
                ];

                if ($syncAvatar && !empty($email)) {
                    $userData['avatar_url'] = 'https://www.gravatar.com/avatar/' . md5(strtolower($email)) . '?s=120&d=mp';
                }

                if (!empty($identifierHash)) {
                    $userData['identifier_hash'] = $identifierHash;
                }

                if (!empty($customAttrs)) {
                    $userData['custom_attributes'] = $customAttrs;
                }

                $jsonUserData = json_encode($userData);
                $clientScript = "
                    window.addEventListener('chatwoot:ready', function () {
                        if (window.\$chatwoot) {
                            window.\$chatwoot.setUser('{$uid}', {$jsonUserData});
                        }
                    });
                ";
            }
        } else {
            // Guest or Logged-out user: reset Chatwoot session
            $clientScript = "
                window.addEventListener('chatwoot:ready', function () {
                    if (window.\$chatwoot) {
                        window.\$chatwoot.reset();
                    }
                });
            ";
        }

        // Widget Script Configuration
        $widgetConfig = json_encode([
            'position' => $widgetPosition,
            'type' => 'standard',
            'launcherTitle' => $launcherTitle,
        ]);

        $output = <<<HTML
<!-- Chatwoot Live Chat Integration by Bahari IT -->
<script>
    (function(d,t) {
        var BASE_URL="{$baseUrl}";
        var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
        g.src=BASE_URL+"/packs/js/sdk.js";
        g.defer = true;
        g.async = true;
        s.parentNode.insertBefore(g,s);
        g.onload=function(){
            window.chatwootSettings = {$widgetConfig};
            window.chatwootSDK.run({
                websiteToken: '{$websiteToken}',
                baseUrl: BASE_URL
            });
        }
    })(document,"script");

    {$clientScript}
</script>
<!-- End Chatwoot Live Chat Integration -->
HTML;

        return $output;
    } catch (\Exception $e) {
        return '';
    }
});
