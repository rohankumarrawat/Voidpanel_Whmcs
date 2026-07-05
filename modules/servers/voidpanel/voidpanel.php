<?php
/**
 * VoidPanel WHMCS Server Provisioning Module
 *
 * Compatible with VoidPanel API v2.
 * Compatible with WHMCS 8.0+
 *
 * @copyright Copyright (c) VoidPanel 2026
 * @license GPL-3.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Define module metadata.
 */
function voidpanel_MetaData()
{
    return array(
        'DisplayName' => 'VoidPanel',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '8000',
        'DefaultSSLPort' => '443',
    );
}

/**
 * Define server configuration options.
 */
function voidpanel_ConfigOptions()
{
    return array(
        'package' => array(
            'FriendlyName' => 'Hosting Package',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'default',
            'Description' => 'Enter the exact Hosting Package Name defined in VoidPanel (e.g. default, basic, business).',
        ),
    );
}

/**
 * Execute API Request to VoidPanel.
 */
function voidpanel_APIRequest(array $params, $endpoint, $method = 'POST', array $postData = [])
{
    // Resolve Server Connection Details
    $host = $params['serverhostname'] ? $params['serverhostname'] : $params['serveripaddress'];
    $port = $params['serverport'] ? $params['serverport'] : '8080';
    $secure = $params['serversecure'] ? 'https' : 'http';
    $token = $params['serverpassword'] ? $params['serverpassword'] : $params['serveraccesshash'];

    if (empty($host)) {
        return [
            'success' => false,
            'error' => 'Server Hostname or IP Address is missing in server configuration.'
        ];
    }
    if (empty($token)) {
        return [
            'success' => false,
            'error' => 'API Token (Server Password) is missing in server configuration.'
        ];
    }

    $url = "{$secure}://{$host}:{$port}/api/v2/{$endpoint}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Disable SSL verification for self-signed certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        "X-API-Token: {$token}",
        "Content-Type: application/json"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (($method === 'POST' || $method === 'PUT') && !empty($postData)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'error' => "CURL Error: " . $curlError
        ];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => "Invalid API Response (HTTP {$httpCode}): " . $response
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['status']) && $data['status'] === 'success') {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    $errorMsg = isset($data['error']) ? $data['error'] : (isset($data['message']) ? $data['message'] : 'Unknown api error occurred.');
    return [
        'success' => false,
        'error' => "HTTP {$httpCode}: " . $errorMsg
    ];
}

/**
 * Helper to resolve the package name.
 */
function voidpanel_ResolvePackage(array $params)
{
    if (isset($params['configoptions']['Package'])) {
        return $params['configoptions']['Package'];
    } elseif (isset($params['configoptions']['Package Name'])) {
        return $params['configoptions']['Package Name'];
    } elseif (isset($params['configoptions']['package'])) {
        return $params['configoptions']['package'];
    }
    return !empty($params['configoption1']) ? $params['configoption1'] : 'default';
}

/**
 * Test Server Connection.
 */
function voidpanel_TestConnection(array $params)
{
    try {
        $response = voidpanel_APIRequest($params, 'ping/', 'GET');
        if ($response['success']) {
            return array(
                'success' => true,
            );
        }
        return array(
            'success' => false,
            'error' => $response['error'],
        );
    } catch (\Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Provision hosting account.
 */
function voidpanel_CreateAccount(array $params)
{
    try {
        $package = voidpanel_ResolvePackage($params);
        $postData = [
            'domain' => $params['domain'],
            'email' => $params['clientsdetails']['email'],
            'package' => $package,
            'password' => $params['password'],
        ];

        $response = voidpanel_APIRequest($params, 'accounts/create/', 'POST', $postData);
        if (!$response['success']) {
            return $response['error'];
        }

        // VoidPanel generates its own username (e.g. domain suffix checks)
        // We capture this username and update the WHMCS database
        $resolvedUsername = isset($response['data']['username']) ? $response['data']['username'] : '';
        if (!empty($resolvedUsername)) {
            Capsule::table('tblhosting')
                ->where('id', $params['serviceid'])
                ->update(['username' => $resolvedUsername]);
        }

        return 'success';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Suspend hosting account.
 */
function voidpanel_SuspendAccount(array $params)
{
    try {
        $postData = [
            'domain' => $params['domain'],
        ];

        $response = voidpanel_APIRequest($params, 'accounts/suspend/', 'POST', $postData);
        if ($response['success']) {
            return 'success';
        }
        return $response['error'];
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Unsuspend hosting account.
 */
function voidpanel_UnsuspendAccount(array $params)
{
    try {
        $postData = [
            'domain' => $params['domain'],
        ];

        $response = voidpanel_APIRequest($params, 'accounts/unsuspend/', 'POST', $postData);
        if ($response['success']) {
            return 'success';
        }
        return $response['error'];
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Terminate hosting account.
 */
function voidpanel_TerminateAccount(array $params)
{
    try {
        $postData = [
            'domain' => $params['domain'],
        ];

        $response = voidpanel_APIRequest($params, 'accounts/terminate/', 'POST', $postData);
        if ($response['success']) {
            return 'success';
        }
        return $response['error'];
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Change hosting account password.
 */
function voidpanel_ChangePassword(array $params)
{
    try {
        $postData = [
            'domain' => $params['domain'],
            'password' => $params['password'],
        ];

        $response = voidpanel_APIRequest($params, 'accounts/change-password/', 'POST', $postData);
        if ($response['success']) {
            return 'success';
        }
        return $response['error'];
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Change hosting account package.
 */
function voidpanel_ChangePackage(array $params)
{
    try {
        $package = voidpanel_ResolvePackage($params);
        $postData = [
            'domain' => $params['domain'],
            'package' => $package,
        ];

        $response = voidpanel_APIRequest($params, 'accounts/change-package/', 'POST', $postData);
        if ($response['success']) {
            return 'success';
        }
        return $response['error'];
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Client area output.
 */
function voidpanel_ClientArea(array $params)
{
    $host = $params['serverhostname'] ? $params['serverhostname'] : $params['serveripaddress'];
    $port = $params['serverport'] ? $params['serverport'] : '8080';
    $secure = $params['serversecure'] ? 'https' : 'http';
    $panelUrl = "{$secure}://{$host}:{$port}/";

    $username = htmlspecialchars($params['username']);
    $domain = htmlspecialchars($params['domain']);

    $code = <<<HTML
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default" style="border: 1px solid rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <div class="panel-heading" style="background-color: #f8fafc; font-weight: bold; border-bottom: 1px solid rgba(0,0,0,0.06); padding: 12px 15px;">
                <i class="fa fa-dashboard" style="color: #2563eb; margin-right: 6px;"></i> VoidPanel Dashboard
            </div>
            <div class="panel-body text-center" style="padding: 24px;">
                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 16px;">
                    Log in directly to your VoidPanel dashboard to manage emails, subdomains, databases, cron jobs, and PHP settings.
                </p>
                <a href="{$panelUrl}" target="_blank" class="btn btn-primary btn-lg btn-block" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border: none; font-weight: bold; border-radius: 6px; padding: 12px 20px; color: #fff; display: block; text-decoration: none;">
                    <i class="fa fa-external-link"></i> Open Control Panel
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default" style="border: 1px solid rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <div class="panel-heading" style="background-color: #f8fafc; font-weight: bold; border-bottom: 1px solid rgba(0,0,0,0.06); padding: 12px 15px;">
                <i class="fa fa-info-circle" style="color: #2563eb; margin-right: 6px;"></i> Connection Details
            </div>
            <div class="panel-body" style="padding: 15px;">
                <table class="table table-striped" style="margin-bottom: 0; font-size: 0.9rem;">
                    <tbody>
                        <tr>
                            <td style="padding: 10px 8px; border-top: none;"><strong>Domain:</strong></td>
                            <td style="padding: 10px 8px; border-top: none;"><a href="http://{$domain}" target="_blank" style="color: #2563eb; font-weight: 500;">{$domain}</a></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 8px;"><strong>Username:</strong></td>
                            <td style="padding: 10px 8px;"><code style="background-color: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px;">{$username}</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 8px;"><strong>Admin URL:</strong></td>
                            <td style="padding: 10px 8px;"><a href="{$panelUrl}" target="_blank" style="color: #2563eb; font-size: 0.85rem;">{$panelUrl}</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;
    return $code;
}
