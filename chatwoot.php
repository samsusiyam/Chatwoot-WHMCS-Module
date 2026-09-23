<?php
/**
 * WHMCS Addon Module: Chatwoot Live Chat & CRM
 *
 * Seamless Chatwoot Live Chat widget integration with logged-in user auto-sync,
 * HMAC identity security, and Chatwoot Agent CRM Dashboard App for WHMCS.
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

// Helper: Ensure module settings table exists
if (!function_exists('chatwoot_ensure_settings_table')) {
    function chatwoot_ensure_settings_table()
    {
        try {
            if (!Capsule::schema()->hasTable('mod_chatwoot_settings')) {
                Capsule::schema()->create('mod_chatwoot_settings', function ($table) {
                    $table->increments('id');
                    $table->string('setting', 191)->unique();
                    $table->text('value')->nullable();
                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                });
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

// Helper: Get module setting with fallback to tbladdonmodules
if (!function_exists('chatwoot_get_setting')) {
    function chatwoot_get_setting($setting, $default = '')
    {
        $setting = (string) $setting;

        try {
            chatwoot_ensure_settings_table();
            $row = Capsule::table('mod_chatwoot_settings')
                ->where('setting', $setting)
                ->first(['value']);

            if ($row) {
                return (string) ($row->value ?? $default);
            }
        } catch (\Exception $e) {
        }

        try {
            $row = Capsule::table('tbladdonmodules')
                ->where('module', 'chatwoot')
                ->where('setting', $setting)
                ->first(['value']);

            if ($row) {
                $val = (string) ($row->value ?? $default);
                chatwoot_save_setting($setting, $val);
                return $val;
            }
        } catch (\Exception $e) {
        }

        return $default;
    }
}

// Helper: Save module setting
if (!function_exists('chatwoot_save_setting')) {
    function chatwoot_save_setting($setting, $value)
    {
        $setting = (string) $setting;
        $value = (string) $value;
        $now = date('Y-m-d H:i:s');

        chatwoot_ensure_settings_table();

        try {
            $exists = Capsule::table('mod_chatwoot_settings')
                ->where('setting', $setting)
                ->exists();

            if ($exists) {
                Capsule::table('mod_chatwoot_settings')
                    ->where('setting', $setting)
                    ->update([
                        'value' => $value,
                        'updated_at' => $now,
                    ]);
            } else {
                Capsule::table('mod_chatwoot_settings')->insert([
                    'setting' => $setting,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Sync with tbladdonmodules for WHMCS core compatibility
            $tblExists = Capsule::table('tbladdonmodules')
                ->where('module', 'chatwoot')
                ->where('setting', $setting)
                ->exists();

            if ($tblExists) {
                Capsule::table('tbladdonmodules')
                    ->where('module', 'chatwoot')
                    ->where('setting', $setting)
                    ->update(['value' => $value]);
            } else {
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'chatwoot',
                    'setting' => $setting,
                    'value' => $value,
                ]);
            }
        } catch (\Exception $e) {
        }
    }
}

// Helper: Seed default settings if empty
if (!function_exists('chatwoot_seed_default_settings')) {
    function chatwoot_seed_default_settings()
    {
        chatwoot_ensure_settings_table();

        $crmSecret = chatwoot_get_setting('crm_api_secret', '');
        if (empty($crmSecret)) {
            $crmSecret = bin2hex(random_bytes(16));
        }

        $defaults = [
            'widget_enabled'       => 'on',
            'base_url'             => 'https://app.chatwoot.com',
            'website_token'        => '',
            'hmac_token'           => '',
            'hmac_enabled'         => '',
            'visibility'           => 'all',
            'widget_position'      => 'right',
            'widget_launcher_title'=> 'Chat with us',
            'sync_client_profile'  => 'on',
            'sync_attributes'      => 'on',
            'sync_avatar'          => 'on',
            'crm_enabled'          => 'on',
            'crm_api_secret'       => $crmSecret,
            'crm_show_services'    => 'on',
            'crm_show_invoices'    => 'on',
            'crm_show_tickets'     => 'on',
            'crm_show_balance'     => 'on',
            'crm_show_login_btn'   => 'on',
        ];

        foreach ($defaults as $key => $val) {
            $current = chatwoot_get_setting($key, null);
            if ($current === null || $current === '') {
                if ($key === 'crm_api_secret' || $key === 'base_url' || $key === 'widget_enabled' || $key === 'sync_attributes' || $key === 'sync_client_profile') {
                    chatwoot_save_setting($key, $val);
                }
            }
        }
    }
}

// Helper: HTML escape
if (!function_exists('chatwoot_h')) {
    function chatwoot_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Define addon module configuration parameters.
 * Kept minimal so all configurations are managed directly inside the module UI.
 *
 * @return array
 */
function chatwoot_config()
{
    return [
        'name'        => 'Chatwoot Live Chat & CRM',
        'description' => '<div style="margin-top:10px;padding:14px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;border-left:4px solid #1e3a5f;font-size:13.5px;line-height:1.6;color:#334155;"><div style="font-weight:700;color:#1e293b;margin-bottom:6px;"><i class="fas fa-comments" style="color:#1e3a5f;margin-right:6px;"></i>Chatwoot Live Chat & Agent CRM for WHMCS</div>Seamless Chatwoot live chat widget integration with logged-in user auto-sync, HMAC identity security, and Chatwoot Agent CRM Dashboard App for WHMCS — all configured from a unified module interface.</div>',
        'version'     => '2.0.0',
        'author'      => '<a href="https://client.bahariit.com" target="_blank" style="color:#1e3a5f;font-weight:700;text-decoration:none;"><i class="fas fa-shield-alt" style="margin-right:5px;font-size:12px;"></i> BahariIT</a>',
        'language'    => 'english',
        'fields'      => [
            'option1' => [
                'FriendlyName' => 'Module Status',
                'Type'         => 'yesno',
                'Description'  => 'Tick to enable the module (Manage all settings in Addons -> Chatwoot Live Chat & CRM)',
                'Default'      => 'yes',
            ],
        ],
    ];
}

/**
 * Activate addon module.
 *
 * @return array
 */
function chatwoot_activate()
{
    chatwoot_ensure_settings_table();
    chatwoot_seed_default_settings();

    return [
        'status' => 'success',
        'description' => 'Chatwoot Live Chat & CRM module activated successfully.'
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
        'description' => 'Chatwoot Live Chat & CRM module deactivated successfully. Settings have been preserved in database.'
    ];
}

/**
 * Upgrade addon module.
 */
function chatwoot_upgrade($vars)
{
    chatwoot_ensure_settings_table();
    chatwoot_seed_default_settings();
}

/**
 * Shared CSS for the modern addon interface
 */
if (!function_exists('chatwoot_shared_css')) {
    function chatwoot_shared_css()
    {
        return '<style>
            .cw-module-wrap {
                background: #eef3f8;
                border: 0;
                border-radius: 8px;
                box-shadow: 0 18px 42px rgba(15,23,42,0.12);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin-bottom: 30px;
                overflow: hidden;
            }
            .cw-module-header {
                background: #12589b;
                padding: 24px 28px;
                border-bottom: 1px solid rgba(255,255,255,0.18);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }
            .cw-header-brand {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .cw-header-icon {
                width: 52px;
                height: 52px;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.28);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
            }
            .cw-header-icon img {
                width: 32px;
                height: 32px;
                border-radius: 6px;
            }
            .cw-header-title {
                font-size: 22px;
                font-weight: 800;
                color: #ffffff;
                margin: 0;
                line-height: 1.2;
            }
            .cw-header-subtitle {
                color: rgba(255,255,255,0.85);
                font-size: 13px;
                margin-top: 4px;
            }
            .cw-header-version {
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.3);
                color: #ffffff;
                font-size: 12px;
                font-weight: 800;
                padding: 6px 14px;
                border-radius: 20px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .cw-nav-wrapper {
                background: #ffffff;
                padding: 14px 20px;
                border-bottom: 1px solid #dbe5f1;
                box-shadow: 0 1px 0 rgba(15,23,42,0.03);
            }
            .cw-nav-container {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cw-nav-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 42px;
                padding: 10px 16px;
                background: #f8fafc;
                border: 1px solid #d6e0ec;
                border-radius: 8px;
                color: #334155 !important;
                font-size: 13px;
                font-weight: 700;
                text-decoration: none !important;
                transition: all 0.15s ease;
                box-shadow: 0 1px 2px rgba(15,23,42,0.04);
            }
            .cw-nav-btn:hover {
                transform: translateY(-1px);
                background: #edf6ff;
                border-color: #9bc8ee;
                color: #0f5ea8 !important;
                box-shadow: 0 8px 18px rgba(18,88,155,0.12);
            }
            .cw-nav-btn.active {
                background: #1267b3;
                border-color: #1267b3;
                color: #ffffff !important;
                box-shadow: 0 10px 22px rgba(18,103,179,0.22);
            }
            .cw-module-body {
                padding: 26px;
                background: #eef3f8;
            }
            .cw-card, .cw-table-card {
                background: #ffffff;
                border: 1px solid #dce6f2;
                border-radius: 8px;
                box-shadow: 0 10px 26px rgba(15,23,42,0.07);
                margin-bottom: 20px;
                overflow: hidden;
            }
            .cw-card-header, .cw-table-header {
                padding: 18px 22px;
                background: #ffffff;
                border-bottom: 1px solid #e3ebf5;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cw-card-header h3, .cw-table-header h3 {
                margin: 0;
                color: #172033;
                font-size: 18px;
                font-weight: 800;
                letter-spacing: 0;
            }
            .cw-card-body {
                padding: 22px;
            }
            .cw-muted {
                color: #64748b !important;
                font-size: 13px;
                margin-top: 4px;
            }
            .cw-stats {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 18px;
                margin-bottom: 22px;
            }
            .cw-stat {
                background: #ffffff;
                position: relative;
                min-height: 100px;
                padding: 18px 20px;
                border-radius: 8px;
                border: 1px solid #dce6f2;
                box-shadow: 0 10px 24px rgba(15,23,42,0.06);
                overflow: hidden;
            }
            .cw-stat:before {
                content: "";
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: #1267b3;
            }
            .cw-stat:nth-child(2):before { background: #16a34a; }
            .cw-stat:nth-child(3):before { background: #f59e0b; }
            .cw-stat:nth-child(4):before { background: #8b5cf6; }
            .cw-stat-label {
                color: #64748b;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .cw-stat-value {
                margin-top: 8px;
                color: #12589b;
                font-size: 26px;
                font-weight: 800;
            }
            .cw-check-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-top: 10px;
            }
            .cw-check-row {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                border: 1px solid #dce6f2;
                border-radius: 8px;
                background: #f8fafc;
                padding: 12px 14px;
                margin: 0;
                cursor: pointer;
                transition: all 0.15s ease;
            }
            .cw-check-row:hover {
                background: #f1f5f9;
                border-color: #cbd5e1;
            }
            .cw-check-row input[type="checkbox"], .cw-check-row input[type="radio"] {
                margin-top: 3px;
                width: 16px;
                height: 16px;
                cursor: pointer;
            }
            .cw-check-row span strong {
                display: block;
                color: #1e293b;
                font-size: 13px;
            }
            .cw-check-row span small {
                display: block;
                color: #64748b;
                font-size: 12px;
                margin-top: 2px;
                line-height: 1.4;
            }
            .cw-actions-toolbar {
                padding: 18px 22px;
                background: #ffffff;
                border: 1px solid #dce6f2;
                border-radius: 8px;
                box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 20px;
            }
            .cw-module-footer {
                background: #ffffff;
                border-top: 1px solid #dce6f2;
                padding: 16px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                color: #64748b;
                font-size: 13px;
            }
            .cw-footer-badge {
                background: #eaf4ff;
                color: #0f5ea8;
                padding: 4px 12px;
                border-radius: 20px;
                font-weight: 700;
                font-size: 12px;
            }
            .cw-form-group {
                margin-bottom: 20px;
            }
            .cw-form-group label {
                display: block;
                color: #172033;
                font-weight: 800;
                font-size: 13px;
                margin-bottom: 6px;
            }
            .cw-form-group .help-block {
                color: #64748b;
                font-size: 12px;
                margin-top: 5px;
                line-height: 1.5;
            }
            .cw-module-wrap .form-control {
                border-radius: 8px;
                border-color: #cfdbe8;
                box-shadow: none;
                color: #243247;
                height: 42px;
                font-size: 13px;
            }
            .cw-module-wrap .form-control:focus {
                border-color: #1267b3;
                box-shadow: 0 0 0 3px rgba(18,103,179,0.12);
            }
            .cw-module-wrap .btn {
                border-radius: 8px !important;
                font-weight: 700;
                padding: 9px 18px;
                font-size: 13px;
                box-shadow: 0 1px 2px rgba(15,23,42,0.08);
            }
            .cw-module-wrap .btn-primary {
                background: #1267b3;
                border-color: #1267b3;
                color: #fff;
            }
            .cw-module-wrap .btn-primary:hover {
                background: #0f5ea8;
                border-color: #0f5ea8;
            }
            .cw-module-wrap .btn-default {
                background: #fff;
                border-color: #cfdbe8;
                color: #334155;
            }
            .cw-module-wrap .btn-default:hover {
                background: #f5f9fd;
                border-color: #aebfd2;
            }
            .cw-module-wrap .alert {
                border-radius: 8px;
                box-shadow: 0 8px 20px rgba(15,23,42,0.06);
                padding: 14px 18px;
                margin-bottom: 20px;
                font-size: 13px;
            }
            /* Changelog timeline */
            .cw-changelog-wrap {
                padding: 30px 34px 38px;
                background: #fff;
                border-radius: 8px;
                border: 1px solid #dce6f2;
                box-shadow: 0 10px 26px rgba(15,23,42,0.07);
            }
            .cw-changelog-timeline {
                position: relative;
                padding-left: 38px;
                margin-top: 24px;
            }
            .cw-changelog-timeline:before {
                content: "";
                position: absolute;
                left: 7px;
                top: 8px;
                bottom: 8px;
                width: 2px;
                background: #dbe8fb;
            }
            .cw-changelog-entry {
                position: relative;
                margin-bottom: 30px;
            }
            .cw-changelog-entry:last-child {
                margin-bottom: 0;
            }
            .cw-changelog-dot {
                position: absolute;
                left: -38px;
                top: 6px;
                width: 14px;
                height: 14px;
                border-radius: 50%;
                background: #1267b3;
                border: 3px solid #eaf2ff;
                box-shadow: 0 0 0 1px #93c5fd;
            }
            .cw-changelog-head {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 12px;
            }
            .cw-version-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 64px;
                padding: 5px 12px;
                border-radius: 8px;
                background: #1267b3;
                color: #fff;
                font-weight: 800;
                font-size: 12px;
            }
            .cw-change-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .cw-change-list li {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin: 0 0 10px;
                color: #243247;
                font-size: 13px;
                line-height: 1.6;
            }
            .cw-change-type {
                flex: 0 0 auto;
                min-width: 80px;
                text-align: center;
                border-radius: 6px;
                padding: 2px 8px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
            }
            .cw-change-new { background: #dcfce7; color: #166534; }
            .cw-change-improved { background: #ede9fe; color: #5b21b6; }
            .cw-change-fixed { background: #fee2e2; color: #991b1b; }
            /* Developer Card */
            .cw-dev-wrap { max-width: 980px; margin: 0 auto; }
            .cw-dev-hero {
                background: #12589b;
                color: #fff;
                padding: 30px;
                border-radius: 8px;
                margin-bottom: 24px;
                box-shadow: 0 14px 30px rgba(18,88,155,0.20);
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .cw-dev-hero-icon {
                font-size: 48px;
                opacity: 0.9;
            }
            .cw-dev-cards-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 20px;
            }
            .cw-dev-card {
                background: #fff;
                border: 1px solid #dce6f2;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 10px 26px rgba(15,23,42,0.07);
            }
            .cw-dev-card-title {
                font-size: 16px;
                font-weight: 800;
                color: #0f5ea8;
                padding-bottom: 12px;
                margin-bottom: 16px;
                border-bottom: 1px solid #e3ebf5;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .cw-contact-item {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
                font-size: 13px;
                color: #334155;
            }
            .cw-contact-item a {
                color: #1267b3;
                text-decoration: none;
                font-weight: 600;
            }
            @media (max-width: 980px) {
                .cw-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .cw-dev-cards-row { grid-template-columns: 1fr; }
            }
            @media (max-width: 680px) {
                .cw-stats, .cw-check-grid { grid-template-columns: 1fr; }
                .cw-nav-btn { width: 100%; justify-content: center; }
            }
        </style>';
    }
}

/**
 * Header Renderer
 */
if (!function_exists('chatwoot_render_header')) {
    function chatwoot_render_header($vars, $action)
    {
        $moduleLink = $vars['modulelink'];
        $version = $vars['version'] ?: '2.0.0';

        return chatwoot_shared_css() . '
        <div class="cw-module-wrap">
            <div class="cw-module-header">
                <div class="cw-header-brand">
                    <div class="cw-header-icon">
                        <img src="../modules/addons/chatwoot/logo.png" alt="Chatwoot" onerror="this.outerHTML=\'<i class=\\\'fas fa-comments\\\' style=\\\'color:#fff;font-size:24px;\\\'></i>\'">
                    </div>
                    <div>
                        <div class="cw-header-title">Chatwoot Live Chat &amp; CRM</div>
                        <div class="cw-header-subtitle">Live Chat Widget &amp; Agent CRM Dashboard App for WHMCS</div>
                    </div>
                </div>
                <span class="cw-header-version">v' . chatwoot_h($version) . '</span>
            </div>
            <div class="cw-nav-wrapper">
                <div class="cw-nav-container">
                    <a href="' . chatwoot_h($moduleLink) . '&action=widget_settings" class="cw-nav-btn' . ($action === 'widget_settings' ? ' active' : '') . '">
                        <i class="fas fa-comment-dots"></i> Live Chat Widget
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=crm_app" class="cw-nav-btn' . ($action === 'crm_app' ? ' active' : '') . '">
                        <i class="fas fa-id-card-alt"></i> Agent CRM App
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=security" class="cw-nav-btn' . ($action === 'security' ? ' active' : '') . '">
                        <i class="fas fa-shield-alt"></i> Identity &amp; Security
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=client_sync" class="cw-nav-btn' . ($action === 'client_sync' ? ' active' : '') . '">
                        <i class="fas fa-sync-alt"></i> Client Sync
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=module_setup" class="cw-nav-btn' . ($action === 'module_setup' ? ' active' : '') . '">
                        <i class="fas fa-cogs"></i> Module Setup
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=changelog" class="cw-nav-btn' . ($action === 'changelog' ? ' active' : '') . '">
                        <i class="fas fa-list-alt"></i> Changelog
                    </a>
                    <a href="' . chatwoot_h($moduleLink) . '&action=developer_info" class="cw-nav-btn' . ($action === 'developer_info' ? ' active' : '') . '">
                        <i class="fas fa-code"></i> Developer Info
                    </a>
                </div>
            </div>
            <div class="cw-module-body">';
    }
}

/**
 * Footer Renderer
 */
if (!function_exists('chatwoot_render_footer')) {
    function chatwoot_render_footer($action)
    {
        $pageLabels = [
            'widget_settings' => 'Live Chat Widget',
            'crm_app'         => 'Agent CRM App',
            'security'        => 'Identity & Security',
            'client_sync'     => 'Client Sync',
            'module_setup'    => 'Module Setup',
            'changelog'       => 'Changelog',
            'developer_info'  => 'Developer Info',
        ];

        $pageLabel = $pageLabels[$action] ?? 'Live Chat Widget';

        return '</div>
            <div class="cw-module-footer">
                <span>&copy; ' . date('Y') . ' <strong>Bahari IT</strong> &bull; Chatwoot Live Chat &amp; CRM for WHMCS</span>
                <span class="cw-footer-badge"><i class="fas fa-map-marker-alt"></i> ' . chatwoot_h($pageLabel) . '</span>
            </div>
        </div>';
    }
}

/**
 * Page: Widget Settings
 */
if (!function_exists('chatwoot_render_widget_settings_page')) {
    function chatwoot_render_widget_settings_page($vars)
    {
        $moduleLink = $vars['modulelink'];
        $enabled = chatwoot_get_setting('widget_enabled', 'on') === 'on';
        $baseUrl = chatwoot_get_setting('base_url', 'https://app.chatwoot.com');
        $token = chatwoot_get_setting('website_token', '');
        $visibility = chatwoot_get_setting('visibility', 'all');
        $position = chatwoot_get_setting('widget_position', 'right');
        $launcherTitle = chatwoot_get_setting('widget_launcher_title', 'Chat with us');

        $html = '';
        if (isset($_GET['saved'])) {
            $html .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Widget settings have been saved successfully.</div>';
        }

        if (empty($token)) {
            $html .= '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <strong>Website Inbox Token is required:</strong> Please paste your Chatwoot Website Inbox Token below to activate the live chat widget on your client area.</div>';
        }

        // Stats Bar
        $html .= '<div class="cw-stats">
            <div class="cw-stat">
                <div class="cw-stat-label">Widget Status</div>
                <div class="cw-stat-value" style="color:' . ($enabled && !empty($token) ? '#16a34a' : '#dc2626') . ';">
                    ' . ($enabled && !empty($token) ? '<i class="fas fa-circle-check" style="font-size:22px;"></i> Active' : '<i class="fas fa-circle-xmark" style="font-size:22px;"></i> Disabled') . '
                </div>
            </div>
            <div class="cw-stat">
                <div class="cw-stat-label">Chatwoot Instance</div>
                <div class="cw-stat-value" style="font-size:16px;margin-top:14px;word-break:break-all;">
                    ' . (strpos($baseUrl, 'app.chatwoot.com') !== false ? '<span class="label label-info">Cloud Hosted</span>' : '<span class="label label-primary">Self Hosted</span>') . '
                </div>
            </div>
            <div class="cw-stat">
                <div class="cw-stat-label">Audience Visibility</div>
                <div class="cw-stat-value" style="font-size:16px;margin-top:14px;">
                    ' . ($visibility === 'logged_in' ? 'Logged-in Only' : 'All Visitors') . '
                </div>
            </div>
            <div class="cw-stat">
                <div class="cw-stat-label">Screen Position</div>
                <div class="cw-stat-value" style="font-size:16px;margin-top:14px;">
                    ' . (ucfirst($position)) . '
                </div>
            </div>
        </div>';

        $html .= '<form method="post" action="' . chatwoot_h($moduleLink) . '&action=save_widget_settings">
            <div class="cw-card">
                <div class="cw-card-header">
                    <h3><i class="fas fa-comment-dots text-primary"></i> Live Chat Widget Configuration</h3>
                    <div>
                        <a href="' . chatwoot_h($baseUrl) . '" target="_blank" class="btn btn-default btn-sm">
                            <i class="fas fa-external-link-alt"></i> Open Chatwoot
                        </a>
                    </div>
                </div>
                <div class="cw-card-body">
                    <div class="cw-form-group">
                        <label class="cw-check-row" style="margin-bottom:15px;">
                            <input type="checkbox" name="widget_enabled" value="on"' . ($enabled ? ' checked' : '') . '>
                            <span>
                                <strong>Enable Live Chat Widget in WHMCS</strong>
                                <small>Automatically injects the Chatwoot chat bubble into your WHMCS client area and public pages.</small>
                            </span>
                        </label>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="cw-form-group">
                                <label>Chatwoot Base URL <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="url" name="base_url" class="form-control" value="' . chatwoot_h($baseUrl) . '" required placeholder="https://app.chatwoot.com">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button" onclick="document.getElementsByName(\'base_url\')[0].value=\'https://app.chatwoot.com\'">Use Cloud</button>
                                    </span>
                                </div>
                                <div class="help-block">Enter <code>https://app.chatwoot.com</code> for Chatwoot Cloud or your self-hosted server URL (e.g. <code>https://chat.yourdomain.com</code>).</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cw-form-group">
                                <label>Website Inbox Token <span class="text-danger">*</span></label>
                                <input type="text" name="website_token" class="form-control" value="' . chatwoot_h($token) . '" placeholder="e.g. abcd1234efgh5678" autocomplete="off">
                                <div class="help-block">Found in Chatwoot &rarr; <strong>Settings</strong> &rarr; <strong>Inboxes</strong> &rarr; Choose Website Inbox &rarr; <strong>Configuration</strong> tab.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="cw-form-group">
                                <label>Widget Visibility</label>
                                <select name="visibility" class="form-control">
                                    <option value="all"' . ($visibility === 'all' ? ' selected' : '') . '>Show to All Visitors (Guests &amp; Logged-in Clients)</option>
                                    <option value="logged_in"' . ($visibility === 'logged_in' ? ' selected' : '') . '>Logged-in Clients Only</option>
                                </select>
                                <div class="help-block">Choose whether guests/pre-sale visitors can see the chat widget or only registered clients who have logged in.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="cw-form-group">
                                <label>Widget Position</label>
                                <select name="widget_position" class="form-control">
                                    <option value="right"' . ($position === 'right' ? ' selected' : '') . '>Bottom Right</option>
                                    <option value="left"' . ($position === 'left' ? ' selected' : '') . '>Bottom Left</option>
                                </select>
                                <div class="help-block">Screen alignment of the floating chat bubble on your website.</div>
                            </div>
                        </div>
                    </div>

                    <div class="cw-form-group" style="margin-bottom:0;">
                        <label>Widget Launcher Title / Greeting</label>
                        <input type="text" name="widget_launcher_title" class="form-control" value="' . chatwoot_h($launcherTitle) . '" placeholder="Chat with us">
                        <div class="help-block">Optional text displayed on the collapsed chat widget button.</div>
                    </div>
                </div>
            </div>

            <div class="cw-actions-toolbar">
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Widget Settings</button>
                    <a href="' . chatwoot_h($moduleLink) . '&action=crm_app" class="btn btn-default"><i class="fas fa-id-card-alt"></i> Configure Agent CRM App</a>
                </div>
                <div class="cw-muted">Settings apply immediately to all visiting and logged-in users.</div>
            </div>
        </form>';

        return $html;
    }
}

/**
 * Page: Agent CRM Dashboard App
 */
if (!function_exists('chatwoot_render_crm_app_page')) {
    function chatwoot_render_crm_app_page($vars)
    {
        $moduleLink = $vars['modulelink'];
        $crmSecret  = chatwoot_get_setting('crm_api_secret', '');
        if (empty($crmSecret)) {
            $crmSecret = bin2hex(random_bytes(16));
            chatwoot_save_setting('crm_api_secret', $crmSecret);
        }

        $systemUrl  = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?: '', '/');
        $dashboardAppUrl = "{$systemUrl}/modules/addons/chatwoot/api.php?secret=" . urlencode($crmSecret) . "&email={{contact.email}}";

        $showServices = chatwoot_get_setting('crm_show_services', 'on') === 'on';
        $showInvoices = chatwoot_get_setting('crm_show_invoices', 'on') === 'on';
        $showTickets  = chatwoot_get_setting('crm_show_tickets', 'on') === 'on';
        $showBalance  = chatwoot_get_setting('crm_show_balance', 'on') === 'on';
        $showLoginBtn = chatwoot_get_setting('crm_show_login_btn', 'on') === 'on';

        $html = '';
        if (isset($_GET['saved'])) {
            $html .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> CRM Dashboard App settings saved successfully.</div>';
        }

        $html .= '<form method="post" action="' . chatwoot_h($moduleLink) . '&action=save_crm_settings">
            <div class="cw-card">
                <div class="cw-card-header">
                    <h3><i class="fas fa-id-card-alt text-primary"></i> Chatwoot Agent CRM App (Sidebar CRM)</h3>
                    <span class="label label-success" style="padding:6px 12px;font-size:12px;border-radius:12px;">Real-Time WHMCS Integration</span>
                </div>
                <div class="cw-card-body">
                    <p style="font-size:14px;color:#334155;margin-bottom:18px;">
                        The <strong>Chatwoot Agent CRM App</strong> gives your customer support team full context by rendering live WHMCS customer data (client profile, active services, unpaid invoices, support tickets, and one-click actions) right inside the Chatwoot Agent conversation screen!
                    </p>

                    <div style="background:#f8fafc;border:1px solid #dce6f2;border-radius:8px;padding:18px;margin-bottom:20px;">
                        <label style="font-size:13px;font-weight:800;color:#1e293b;margin-bottom:6px;display:block;">
                            <i class="fas fa-link text-primary"></i> Your Chatwoot Dashboard App Endpoint URL:
                        </label>
                        <div class="input-group">
                            <input type="text" id="cwDashboardUrl" class="form-control" value="' . chatwoot_h($dashboardAppUrl) . '" readonly style="background:#fff;font-family:monospace;font-size:12px;font-weight:600;color:#0f172a;">
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="button" onclick="copyCwDashboardUrl()" id="btnCopyCwUrl">
                                    <i class="fas fa-copy"></i> Copy URL
                                </button>
                            </span>
                        </div>
                        <div class="help-block" style="margin-top:8px;">
                            <i class="fas fa-shield-alt text-success"></i> Secured with your private CRM Secret Key. Chatwoot dynamically replaces <code>{{contact.email}}</code> with the chatting user\'s email.
                        </div>
                    </div>

                    <!-- Step by Step Setup Box -->
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:18px;margin-bottom:24px;color:#1e3a8a;">
                        <h4 style="margin:0 0 10px;font-weight:800;font-size:14px;color:#1e40af;">
                            <i class="fas fa-circle-info"></i> How to Add This App to Chatwoot (3 Easy Steps):
                        </h4>
                        <ol style="margin:0;padding-left:20px;font-size:13px;line-height:1.7;">
                            <li>Log in to your <strong>Chatwoot Dashboard</strong> &rarr; Click <strong>Settings ⚙️</strong> &rarr; <strong>Dashboard Apps</strong>.</li>
                            <li>Click <strong>Add New App</strong> button.</li>
                            <li>Enter <strong>App Name</strong>: <code>WHMCS CRM</code> and paste the <strong>Dashboard App Endpoint URL</strong> copied above into the <strong>Endpoint</strong> field, then click <strong>Create</strong>.</li>
                        </ol>
                    </div>

                    <!-- CRM Sidebar Feature Customizations -->
                    <h4 style="font-weight:800;font-size:14px;color:#1e293b;margin-bottom:12px;">
                        <i class="fas fa-sliders-h text-primary"></i> CRM Sidebar Display Options:
                    </h4>
                    <div class="cw-check-grid">
                        <label class="cw-check-row">
                            <input type="checkbox" name="crm_show_services" value="on"' . ($showServices ? ' checked' : '') . '>
                            <span>
                                <strong>Active Services &amp; Products</strong>
                                <small>Displays product name, assigned domain, billing amount, due date, and direct WHMCS link.</small>
                            </span>
                        </label>
                        <label class="cw-check-row">
                            <input type="checkbox" name="crm_show_invoices" value="on"' . ($showInvoices ? ' checked' : '') . '>
                            <span>
                                <strong>Recent Invoices &amp; Unpaid Count</strong>
                                <small>Displays invoice IDs, total amounts, due dates, and paid/unpaid status badges.</small>
                            </span>
                        </label>
                        <label class="cw-check-row">
                            <input type="checkbox" name="crm_show_tickets" value="on"' . ($showTickets ? ' checked' : '') . '>
                            <span>
                                <strong>Support Tickets History</strong>
                                <small>Displays active/closed WHMCS tickets with subject, status, and last update time.</small>
                            </span>
                        </label>
                        <label class="cw-check-row">
                            <input type="checkbox" name="crm_show_balance" value="on"' . ($showBalance ? ' checked' : '') . '>
                            <span>
                                <strong>Client Credit &amp; Balance Summary</strong>
                                <small>Shows client ID, credit balance, and total unpaid amount at the top of the sidebar.</small>
                            </span>
                        </label>
                        <label class="cw-check-row">
                            <input type="checkbox" name="crm_show_login_btn" value="on"' . ($showLoginBtn ? ' checked' : '') . '>
                            <span>
                                <strong>One-Click "Login as Client" Action</strong>
                                <small>Adds direct action buttons to jump straight into the WHMCS client account or summary.</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="cw-actions-toolbar">
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save CRM Options</button>
                    <a href="' . chatwoot_h($moduleLink) . '&action=security" class="btn btn-default"><i class="fas fa-shield-alt"></i> Manage Secret Key</a>
                </div>
                <div class="cw-muted">Changes reflect instantly in the Chatwoot agent conversation sidebar.</div>
            </div>
        </form>

        <script>
        function copyCwDashboardUrl() {
            var copyText = document.getElementById("cwDashboardUrl");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            var btn = document.getElementById("btnCopyCwUrl");
            btn.innerHTML = \'<i class="fas fa-check"></i> Copied!\';
            setTimeout(function() {
                btn.innerHTML = \'<i class="fas fa-copy"></i> Copy URL\';
            }, 2000);
        }
        </script>';

        return $html;
    }
}

/**
 * Page: Identity & Security
 */
if (!function_exists('chatwoot_render_security_page')) {
    function chatwoot_render_security_page($vars)
    {
        $moduleLink = $vars['modulelink'];
        $hmacToken = chatwoot_get_setting('hmac_token', '');
        $hmacEnabled = !empty($hmacToken);
        $crmSecret = chatwoot_get_setting('crm_api_secret', '');

        $html = '';
        if (isset($_GET['saved'])) {
            $html .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Security settings updated successfully.</div>';
        }

        $html .= '<form method="post" action="' . chatwoot_h($moduleLink) . '&action=save_security_settings">
            <div class="cw-card">
                <div class="cw-card-header">
                    <h3><i class="fas fa-shield-alt text-primary"></i> Identity Verification &amp; API Security</h3>
                </div>
                <div class="cw-card-body">
                    <!-- HMAC Identity Validation -->
                    <div style="border-bottom:1px solid #e2e8f0;padding-bottom:22px;margin-bottom:22px;">
                        <h4 style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px;">
                            <i class="fas fa-user-lock text-success"></i> HMAC Identity Validation (Prevents Impersonation)
                        </h4>
                        <p style="font-size:13px;color:#64748b;margin-bottom:14px;">
                            Chatwoot Identity Validation uses SHA-256 HMAC tokens to verify that chat messages sent under a client email address truly originate from your WHMCS website, preventing malicious users from impersonating other clients.
                        </p>

                        <div class="cw-form-group">
                            <label>HMAC Secret Key</label>
                            <input type="text" name="hmac_token" class="form-control" value="' . chatwoot_h($hmacToken) . '" placeholder="Enter HMAC secret key from Chatwoot Inbox settings" autocomplete="off">
                            <div class="help-block">
                                Enable in Chatwoot &rarr; <strong>Settings</strong> &rarr; <strong>Inboxes</strong> &rarr; Your Website Inbox &rarr; <strong>Identity Validation</strong> &rarr; Copy the HMAC Key here. Leave blank if not using HMAC.
                            </div>
                        </div>
                    </div>

                    <!-- CRM Dashboard App Secret Key -->
                    <div>
                        <h4 style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px;">
                            <i class="fas fa-key text-primary"></i> Agent CRM Dashboard Secret Key
                        </h4>
                        <p style="font-size:13px;color:#64748b;margin-bottom:14px;">
                            This private token protects the WHMCS CRM iframe endpoint (<code>api.php</code>) so only authorized requests from your Chatwoot dashboard can query customer information.
                        </p>

                        <div class="cw-form-group">
                            <label>CRM Secret Key <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="crm_api_secret" id="crmSecretField" class="form-control" value="' . chatwoot_h($crmSecret) . '" required autocomplete="off">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="generateNewSecret()">
                                        <i class="fas fa-random"></i> Generate Key
                                    </button>
                                </span>
                            </div>
                            <div class="help-block">
                                <strong class="text-warning"><i class="fas fa-exclamation-triangle"></i> Warning:</strong> If you change this secret key, you must also update the Endpoint URL in your Chatwoot Dashboard Apps settings.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cw-actions-toolbar">
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Security Settings</button>
                    <a href="' . chatwoot_h($moduleLink) . '&action=crm_app" class="btn btn-default"><i class="fas fa-id-card-alt"></i> View Dashboard App URL</a>
                </div>
                <div class="cw-muted">Cryptographic keys are securely stored in your WHMCS database.</div>
            </div>
        </form>

        <script>
        function generateNewSecret() {
            var chars = "abcdef0123456789";
            var result = "";
            for (var i = 0; i < 32; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById("crmSecretField").value = result;
        }
        </script>';

        return $html;
    }
}

/**
 * Page: Client Sync & Attributes
 */
if (!function_exists('chatwoot_render_client_sync_page')) {
    function chatwoot_render_client_sync_page($vars)
    {
        $moduleLink = $vars['modulelink'];
        $syncProfile = chatwoot_get_setting('sync_client_profile', 'on') === 'on';
        $syncAttrs   = chatwoot_get_setting('sync_attributes', 'on') === 'on';
        $syncAvatar  = chatwoot_get_setting('sync_avatar', 'on') === 'on';

        $html = '';
        if (isset($_GET['saved'])) {
            $html .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Client synchronization settings saved successfully.</div>';
        }

        $html .= '<form method="post" action="' . chatwoot_h($moduleLink) . '&action=save_client_sync">
            <div class="cw-card">
                <div class="cw-card-header">
                    <h3><i class="fas fa-sync-alt text-primary"></i> Logged-in Client Synchronization</h3>
                </div>
                <div class="cw-card-body">
                    <p style="font-size:14px;color:#334155;margin-bottom:18px;">
                        When a client is logged in to WHMCS, this module automatically binds their identity to the Chatwoot live chat widget, setting their profile name, email, phone number, and custom attributes.
                    </p>

                    <div class="cw-check-grid">
                        <label class="cw-check-row">
                            <input type="checkbox" name="sync_client_profile" value="on"' . ($syncProfile ? ' checked' : '') . '>
                            <span>
                                <strong>Sync Client Profile (Name, Email, Phone)</strong>
                                <small>Passes the client\'s Full Name, Email Address, and Phone Number directly to Chatwoot so they do not have to re-enter them.</small>
                            </span>
                        </label>

                        <label class="cw-check-row">
                            <input type="checkbox" name="sync_avatar" value="on"' . ($syncAvatar ? ' checked' : '') . '>
                            <span>
                                <strong>Sync Gravatar Avatar</strong>
                                <small>Sets the user avatar in Chatwoot based on their WHMCS client email Gravatar profile.</small>
                            </span>
                        </label>

                        <label class="cw-check-row" style="grid-column: span 2;">
                            <input type="checkbox" name="sync_attributes" value="on"' . ($syncAttrs ? ' checked' : '') . '>
                            <span>
                                <strong>Sync Custom Client Attributes &amp; Billing Stats</strong>
                                <small>Attaches live WHMCS billing attributes to the Chatwoot contact profile: Client ID, Company Name, Account Status, Active Services Count, Active Domains Count, Unpaid Invoices Count, Unpaid Balance, and Credit Balance.</small>
                            </span>
                        </label>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-top:20px;">
                        <h4 style="font-size:13px;font-weight:800;color:#1e293b;margin-bottom:8px;">
                            <i class="fas fa-database text-primary"></i> Custom Attributes Sent to Chatwoot:
                        </h4>
                        <div style="display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:10px;font-size:12px;">
                            <div><code>client_id</code> &bull; WHMCS Client ID</div>
                            <div><code>company</code> &bull; Company Name</div>
                            <div><code>status</code> &bull; Account Status</div>
                            <div><code>active_services</code> &bull; Services Count</div>
                            <div><code>active_domains</code> &bull; Domains Count</div>
                            <div><code>unpaid_invoices</code> &bull; Invoices Count</div>
                            <div><code>unpaid_balance</code> &bull; Total Due</div>
                            <div><code>credit_balance</code> &bull; Credit Balance</div>
                            <div><code>currency</code> &bull; Currency Code</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cw-actions-toolbar">
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Sync Settings</button>
                </div>
                <div class="cw-muted">Automatic session reset is performed when a user logs out to preserve privacy.</div>
            </div>
        </form>';

        return $html;
    }
}

/**
 * Page: Module Setup & Diagnostics
 */
if (!function_exists('chatwoot_render_module_setup_page')) {
    function chatwoot_render_module_setup_page($vars)
    {
        $moduleLink = $vars['modulelink'];
        $systemUrl = rtrim(Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') ?: '', '/');
        $phpVersion = phpversion();
        $curlEnabled = function_exists('curl_version');
        $opensslEnabled = extension_loaded('openssl');
        $tableExists = Capsule::schema()->hasTable('mod_chatwoot_settings');

        $html = '<div class="cw-card">
            <div class="cw-card-header">
                <h3><i class="fas fa-cogs text-primary"></i> System Diagnostics &amp; Environment</h3>
                <span class="label label-info" style="padding:6px 12px;font-size:12px;border-radius:12px;">WHMCS ' . chatwoot_h($vars['whmcsVersion'] ?? '8.x') . '</span>
            </div>
            <div class="cw-card-body">
                <table class="table table-bordered" style="margin-bottom:0;background:#fff;">
                    <tr>
                        <th style="width:35%;background:#f8fafc;">WHMCS System URL</th>
                        <td><code>' . chatwoot_h($systemUrl) . '</code></td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">PHP Version</th>
                        <td>' . chatwoot_h($phpVersion) . ' ' . (version_compare($phpVersion, '7.4', '>=') ? '<span class="label label-success">Supported</span>' : '<span class="label label-danger">Outdated</span>') . '</td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">cURL Support</th>
                        <td>' . ($curlEnabled ? '<span class="label label-success"><i class="fas fa-check"></i> Enabled</span>' : '<span class="label label-danger"><i class="fas fa-times"></i> Disabled</span>') . '</td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">OpenSSL &amp; SHA-256 HMAC</th>
                        <td>' . ($opensslEnabled ? '<span class="label label-success"><i class="fas fa-check"></i> Supported</span>' : '<span class="label label-danger"><i class="fas fa-times"></i> Missing</span>') . '</td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">Settings Database Table</th>
                        <td>' . ($tableExists ? '<span class="label label-success"><i class="fas fa-check"></i> mod_chatwoot_settings (OK)</span>' : '<span class="label label-warning"><i class="fas fa-triangle-exclamation"></i> Using tbladdonmodules</span>') . '</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="cw-actions-toolbar">
            <div>
                <a href="' . chatwoot_h($moduleLink) . '&action=widget_settings" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Widget Settings</a>
            </div>
            <div class="cw-muted">Module Version: 2.0.0 &bull; Built with standard WHMCS Capsule ORM</div>
        </div>';

        return $html;
    }
}

/**
 * Page: Changelog
 */
if (!function_exists('chatwoot_render_changelog_page')) {
    function chatwoot_render_changelog_page()
    {
        $releases = [
            'v2.0.0' => [
                'title' => 'Complete UI Revamp & CRM Sidebar Search Overhaul',
                'date'  => 'April 2026',
                'changes' => [
                    ['type' => 'new', 'text' => 'Redesigned the entire module admin interface into a unified, high-productivity tabbed experience inspired by the modern WHMCS enterprise standard.'],
                    ['type' => 'new', 'text' => 'Moved all configuration options from the WHMCS Addon config page directly into the dedicated module admin dashboard with instant flash feedback.'],
                    ['type' => 'new', 'text' => 'Added live interactive Client Search in the Chatwoot Agent CRM App sidebar (search by Name, Email, Phone, Domain, or Client ID) with AJAX lookups.'],
                    ['type' => 'improved', 'text' => 'Fixed the {{contact.email}} unreplaced template token issue gracefully when Chatwoot passes unpopulated email attributes.'],
                    ['type' => 'improved', 'text' => 'Added real-time postMessage listener for Chatwoot dashboard events (chatwoot:dashboard-app:context / appContext) to dynamically bind customer context.'],
                    ['type' => 'new', 'text' => 'Added dedicated mod_chatwoot_settings database table with automatic legacy migration.'],
                ],
            ],
            'v1.0.0' => [
                'title' => 'Initial Release',
                'date'  => 'March 2026',
                'changes' => [
                    ['type' => 'new', 'text' => 'Initial release of Chatwoot Live Chat widget integration for WHMCS.'],
                    ['type' => 'new', 'text' => 'Automatic logged-in user identification and SHA-256 HMAC identity validation.'],
                    ['type' => 'new', 'text' => 'Chatwoot Agent CRM App endpoint (api.php) showing WHMCS services, invoices, and tickets.'],
                ],
            ],
        ];

        $labels = [
            'new' => 'NEW',
            'improved' => 'IMPROVED',
            'fixed' => 'BUG FIX',
        ];

        $html = '<div class="cw-changelog-wrap">
            <h3 style="margin:0 0 20px;font-size:18px;font-weight:800;color:#172033;"><i class="fas fa-clock text-primary"></i> Release History &amp; Changelog</h3>
            <div class="cw-changelog-timeline">';

        foreach ($releases as $ver => $rel) {
            $html .= '<div class="cw-changelog-entry">
                <span class="cw-changelog-dot"></span>
                <div class="cw-changelog-head">
                    <span class="cw-version-pill">' . chatwoot_h($ver) . '</span>
                    <strong style="color:#172033;font-size:15px;">' . chatwoot_h($rel['title']) . '</strong>
                    <span style="color:#64748b;font-size:13px;">' . chatwoot_h($rel['date']) . '</span>
                </div>
                <ul class="cw-change-list">';

            foreach ($rel['changes'] as $c) {
                $type = $c['type'] ?? 'improved';
                $html .= '<li>
                    <span class="cw-change-type cw-change-' . chatwoot_h($type) . '">' . chatwoot_h($labels[$type] ?? 'UPDATE') . '</span>
                    <span>' . chatwoot_h($c['text']) . '</span>
                </li>';
            }

            $html .= '</ul></div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}

/**
 * Page: Developer Info
 */
if (!function_exists('chatwoot_render_developer_page')) {
    function chatwoot_render_developer_page()
    {
        return '
        <div class="cw-dev-wrap">
            <div class="cw-dev-hero">
                <div class="cw-dev-hero-icon"><i class="fas fa-comments"></i></div>
                <div>
                    <h2 style="margin:0 0 6px;font-size:24px;font-weight:800;color:#fff;">Chatwoot Live Chat &amp; CRM</h2>
                    <p style="margin:0;opacity:0.9;font-size:14px;">Next-generation customer support and live chat integration for WHMCS</p>
                    <span style="display:inline-block;margin-top:10px;background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">
                        v2.0.0 &bull; Developed by Bahari IT
                    </span>
                </div>
            </div>

            <div class="cw-dev-cards-row">
                <div class="cw-dev-card">
                    <div class="cw-dev-card-title"><i class="fas fa-info-circle"></i> About This Module</div>
                    <p style="font-size:13px;color:#475569;line-height:1.6;">
                        <strong>Chatwoot Live Chat &amp; CRM</strong> bridges the gap between WHMCS and the open-source Chatwoot customer engagement platform. It empowers hosting providers and digital agencies to deliver world-class omnichannel support directly to their clients while maintaining airtight data security and giving agents instantaneous access to customer billing and hosting records.
                    </p>
                </div>

                <div class="cw-dev-card">
                    <div class="cw-dev-card-title"><i class="fas fa-address-card"></i> Contact &amp; Support</div>
                    <div class="cw-contact-item">
                        <i class="fas fa-globe text-primary"></i>
                        <a href="https://client.bahariit.com" target="_blank">client.bahariit.com</a>
                    </div>
                    <div class="cw-contact-item">
                        <i class="fas fa-envelope text-primary"></i>
                        <a href="mailto:support@bahariit.com">support@bahariit.com</a>
                    </div>
                    <div style="margin-top:18px;padding:12px;background:#f0f7ff;border-radius:8px;border-left:3px solid #1565c0;font-size:12px;color:#4a5568;line-height:1.7;">
                        <div>Version: <strong>2.0.0</strong></div>
                        <div>Publisher: <strong>Bahari IT</strong></div>
                        <div>Compatibility: <strong>WHMCS 8.x &bull; Chatwoot v2 / v3 / Cloud</strong></div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

/**
 * Main Admin Output Function
 */
function chatwoot_output($vars)
{
    $action = isset($_GET['action']) ? (string) $_GET['action'] : 'widget_settings';

    // POST Handlers
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if ($action === 'save_widget_settings') {
            chatwoot_save_setting('widget_enabled', !empty($_POST['widget_enabled']) ? 'on' : '');
            chatwoot_save_setting('base_url', trim($_POST['base_url'] ?? 'https://app.chatwoot.com'));
            chatwoot_save_setting('website_token', trim($_POST['website_token'] ?? ''));
            chatwoot_save_setting('visibility', in_array($_POST['visibility'] ?? '', ['all', 'logged_in'], true) ? $_POST['visibility'] : 'all');
            chatwoot_save_setting('widget_position', in_array($_POST['widget_position'] ?? '', ['right', 'left'], true) ? $_POST['widget_position'] : 'right');
            chatwoot_save_setting('widget_launcher_title', trim($_POST['widget_launcher_title'] ?? 'Chat with us'));

            header('Location: ' . $vars['modulelink'] . '&action=widget_settings&saved=1');
            exit;
        }

        if ($action === 'save_crm_settings') {
            chatwoot_save_setting('crm_show_services', !empty($_POST['crm_show_services']) ? 'on' : '');
            chatwoot_save_setting('crm_show_invoices', !empty($_POST['crm_show_invoices']) ? 'on' : '');
            chatwoot_save_setting('crm_show_tickets', !empty($_POST['crm_show_tickets']) ? 'on' : '');
            chatwoot_save_setting('crm_show_balance', !empty($_POST['crm_show_balance']) ? 'on' : '');
            chatwoot_save_setting('crm_show_login_btn', !empty($_POST['crm_show_login_btn']) ? 'on' : '');

            header('Location: ' . $vars['modulelink'] . '&action=crm_app&saved=1');
            exit;
        }

        if ($action === 'save_security_settings') {
            chatwoot_save_setting('hmac_token', trim($_POST['hmac_token'] ?? ''));
            $newSecret = trim($_POST['crm_api_secret'] ?? '');
            if (!empty($newSecret)) {
                chatwoot_save_setting('crm_api_secret', $newSecret);
            }

            header('Location: ' . $vars['modulelink'] . '&action=security&saved=1');
            exit;
        }

        if ($action === 'save_client_sync') {
            chatwoot_save_setting('sync_client_profile', !empty($_POST['sync_client_profile']) ? 'on' : '');
            chatwoot_save_setting('sync_avatar', !empty($_POST['sync_avatar']) ? 'on' : '');
            chatwoot_save_setting('sync_attributes', !empty($_POST['sync_attributes']) ? 'on' : '');

            header('Location: ' . $vars['modulelink'] . '&action=client_sync&saved=1');
            exit;
        }
    }

    if (!in_array($action, ['widget_settings', 'crm_app', 'security', 'client_sync', 'module_setup', 'changelog', 'developer_info'], true)) {
        $action = 'widget_settings';
    }

    echo chatwoot_render_header($vars, $action);

    if ($action === 'crm_app') {
        echo chatwoot_render_crm_app_page($vars);
    } elseif ($action === 'security') {
        echo chatwoot_render_security_page($vars);
    } elseif ($action === 'client_sync') {
        echo chatwoot_render_client_sync_page($vars);
    } elseif ($action === 'module_setup') {
        echo chatwoot_render_module_setup_page($vars);
    } elseif ($action === 'changelog') {
        echo chatwoot_render_changelog_page();
    } elseif ($action === 'developer_info') {
        echo chatwoot_render_developer_page();
    } else {
        echo chatwoot_render_widget_settings_page($vars);
    }

    echo chatwoot_render_footer($action);
}
