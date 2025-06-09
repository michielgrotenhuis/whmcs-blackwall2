<?php
/**
 * Blackwall (BotGuard) WHMCS Module
 * Provides website protection services - Informational Only
 *
 * @author Zencommerce India
 * @version 2.1.0
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/BlackwallApi.php';
require_once __DIR__ . '/lib/BlackwallConstants.php';
require_once __DIR__ . '/lib/DebugLogger.php';

// Initialize debug logging
BlackwallDebugLogger::info('Blackwall module loaded', ['timestamp' => time()]);

/**
 * Define module metadata
 */
function blackwall_MetaData() {
    return [
        'DisplayName' => 'Blackwall (BotGuard) Website Protection',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => 'Login to Blackwall Panel',
        'ListAccountsUniqueIdentifierField' => 'domain',
        'ListAccountsUniqueIdentifierDisplayName' => 'Domain Name',
    ];
}

/**
 * Define product configuration options
 */
function blackwall_ConfigOptions() {
    return [
        'api_key' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'BotGuard API key for monitoring (optional)',
        ],
        'primary_server' => [
            'FriendlyName' => 'Primary Server',
            'Type' => 'text',
            'Size' => '30',
            'Default' => 'de-nbg-ko1.botguard.net',
            'Description' => 'Primary BotGuard server address',
        ],
        'secondary_server' => [
            'FriendlyName' => 'Secondary Server',
            'Type' => 'text',
            'Size' => '30',
            'Default' => 'de-nbg-ko2.botguard.net',
            'Description' => 'Secondary BotGuard server address',
        ],
        'show_dns_setup' => [
            'FriendlyName' => 'Show DNS Setup Instructions',
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Display DNS configuration instructions to customers',
        ],
    ];
}

/**
 * Test connection to the service
 */
function blackwall_TestConnection(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return [
                'success' => true,
                'error' => 'No API key configured - module will work in informational mode only.',
            ];
        }

        $api = new BlackwallApi($params['configoption1']);
        $domains = $api->getDomains();
        
        return [
            'success' => true,
            'error' => '',
        ];
        
    } catch (Exception $e) {
        logModuleCall(
            'blackwall',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Provision a new account - SIMPLIFIED (NO ACCOUNT CREATION)
 */
function blackwall_CreateAccount(array $params) {
    try {
        BlackwallDebugLogger::info('=== CreateAccount STARTED (Simplified Mode) ===', [
            'domain' => $params['domain'] ?? 'not_set',
            'config_options' => [
                'api_key' => !empty($params['configoption1']) ? 'SET' : 'EMPTY',
                'primary_server' => $params['configoption2'] ?? 'not_set',
                'secondary_server' => $params['configoption3'] ?? 'not_set',
                'show_dns_setup' => $params['configoption4'] ?? 'not_set'
            ]
        ]);
        
        // Get domain from parameters
        $domain = '';
        if (!empty($params['domain'])) {
            $domain = $params['domain'];
        } elseif (!empty($params['customfields']['Domain'])) {
            $domain = $params['customfields']['Domain'];
        } else {
            throw new Exception('Domain name is required for service setup.');
        }
        
        // Validate domain format
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            throw new Exception('Invalid domain name format.');
        }
        
        BlackwallDebugLogger::info('Domain validated', ['domain' => $domain]);
        
        // Store service properties (no external API calls)
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            try {
                $params['model']->serviceProperties->save([
                    'Blackwall Domain' => $domain,
                    'Setup Instructions' => 'Please configure DNS records as shown in the client area',
                    'Service Type' => 'Informational',
                    'Configuration Date' => date('Y-m-d H:i:s'),
                ]);
                BlackwallDebugLogger::info('Service properties saved successfully', [
                    'domain' => $domain
                ]);
            } catch (Exception $props_e) {
                BlackwallDebugLogger::error('Failed to save service properties', [
                    'error' => $props_e->getMessage()
                ]);
                // Don't fail the whole process for this
            }
        }
        
        BlackwallDebugLogger::info('=== CreateAccount COMPLETED SUCCESSFULLY (No External Calls) ===');
        return 'success';
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('=== CreateAccount FAILED ===', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        logModuleCall(
            'blackwall',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        return $e->getMessage();
    }
}

/**
 * Suspend an account - SIMPLIFIED
 */
function blackwall_SuspendAccount(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        // Update service properties to indicate suspended status
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $params['model']->serviceProperties->save([
                'Service Status' => 'Suspended',
                'Suspended Date' => date('Y-m-d H:i:s'),
            ]);
        }
        
        BlackwallDebugLogger::info('Account suspended (informational)', ['domain' => $domain]);
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Unsuspend an account - SIMPLIFIED
 */
function blackwall_UnsuspendAccount(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        // Update service properties to indicate active status
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $params['model']->serviceProperties->save([
                'Service Status' => 'Active',
                'Reactivated Date' => date('Y-m-d H:i:s'),
            ]);
        }
        
        BlackwallDebugLogger::info('Account unsuspended (informational)', ['domain' => $domain]);
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Terminate an account - SIMPLIFIED
 */
function blackwall_TerminateAccount(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        // Clean up service properties
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $params['model']->serviceProperties->save([
                'Blackwall Domain' => '',
                'Setup Instructions' => '',
                'Service Type' => '',
                'Service Status' => 'Terminated',
                'Terminated Date' => date('Y-m-d H:i:s'),
            ]);
        }
        
        BlackwallDebugLogger::info('Account terminated (informational)', ['domain' => $domain]);
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Client area output
 */
function blackwall_ClientArea(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $api_key = $params['configoption1'] ?? '';
        $show_dns_setup = ($params['configoption4'] ?? 'yes') === 'yes';
        
        // Check DNS configuration
        $dns_status = BlackwallApi::checkDomainDnsConfiguration($domain);
        $gatekeeper_nodes = BlackwallConstants::getGateKeeperNodes();
        
        return [
            'tabOverviewModuleOutputTemplate' => 'templates/overview.tpl',
            'templateVariables' => [
                'domain' => $domain,
                'api_key' => $api_key,
                'user_api_key' => '', // No user API key in simplified mode
                'primary_server' => $params['configoption2'] ?? 'de-nbg-ko1.botguard.net',
                'secondary_server' => $params['configoption3'] ?? 'de-nbg-ko2.botguard.net',
                'show_dns_setup' => $show_dns_setup,
                'dns_status' => $dns_status,
                'gatekeeper_nodes' => $gatekeeper_nodes,
                'service_status' => $params['status'],
                'client_lang' => $params['clientsdetails']['language'] ?? 'english',
                'simplified_mode' => true, // Flag to indicate simplified mode
                'protection_features' => BlackwallConstants::getProtectionFeatures(),
            ],
        ];
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        
        return [
            'tabOverviewModuleOutputTemplate' => 'templates/error.tpl',
            'templateVariables' => [
                'error_message' => $e->getMessage(),
            ],
        ];
    }
}

/**
 * Admin custom button array
 */
function blackwall_AdminCustomButtonArray() {
    return [
        'Check Module Version' => 'checkModuleVersion',
        'Test Connection' => 'testConnectionAdmin',
        'Check DNS' => 'checkDns',
        'View Debug Logs' => 'viewDebugLogs',
        'Clear Debug Logs' => 'clearDebugLogs',
        'Validate Domain' => 'validateDomain',
    ];
}

/**
 * Test connection admin function
 */
function blackwall_testConnectionAdmin(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'No API Key configured. Module works in informational mode.';
        }

        $api = new BlackwallApi($params['configoption1']);
        $domains = $api->getDomains();
        
        return 'Connection successful. Found ' . count($domains) . ' domains.';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'Connection failed: ' . $e->getMessage();
    }
}

/**
 * Check DNS admin function
 */
function blackwall_checkDns(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $dns_status = BlackwallApi::checkDomainDnsConfiguration($domain);
        
        $status_text = $dns_status['status'] ? 'correctly configured' : 'not correctly configured';
        $connected_to = $dns_status['connected_to'] ?? 'none';
        
        return "DNS is {$status_text}. Connected to: {$connected_to}";
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'DNS check failed: ' . $e->getMessage();
    }
}

/**
 * Validate domain admin function
 */
function blackwall_validateDomain(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            return "Invalid domain format: {$domain}";
        }
        
        // Check if domain resolves
        $ips = BlackwallApi::getDomainARecords($domain);
        $ipv6s = BlackwallApi::getDomainAAAARecords($domain);
        
        return "Domain valid. IPv4: " . implode(', ', $ips) . " | IPv6: " . implode(', ', $ipv6s);
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'Domain validation failed: ' . $e->getMessage();
    }
}

/**
 * Check module version admin function
 */
function blackwall_checkModuleVersion(array $params) {
    $version_info = [
        'module_file' => __FILE__,
        'version' => '2.1.0 - Simplified Mode (No Account Creation)',
        'mode' => 'INFORMATIONAL_ONLY',
        'has_debug_logger' => class_exists('BlackwallDebugLogger'),
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'account_creation' => 'DISABLED',
        'external_api_calls' => 'MINIMAL',
        'function_exists' => [
            'blackwall_CreateAccount' => function_exists('blackwall_CreateAccount'),
            'blackwall_viewDebugLogs' => function_exists('blackwall_viewDebugLogs'),
        ]
    ];
    
    // Test debug logger
    try {
        BlackwallDebugLogger::info('Version check called - simplified mode');
        $version_info['debug_logger_working'] = true;
        $version_info['log_file'] = BlackwallDebugLogger::getLogFile();
    } catch (Exception $e) {
        $version_info['debug_logger_working'] = false;
        $version_info['debug_logger_error'] = $e->getMessage();
    }
    
    return "Module Version Info (Simplified):\n" . json_encode($version_info, JSON_PRETTY_PRINT);
}

/**
 * View debug logs admin function
 */
function blackwall_viewDebugLogs(array $params) {
    try {
        $log_file = BlackwallDebugLogger::getLogFile();
        $recent_logs = BlackwallDebugLogger::getRecentLogs(100);
        
        if (empty($recent_logs) || $recent_logs === "No log file found at: " . $log_file) {
            return "No debug logs found. Log file: {$log_file}";
        }
        
        // Return recent logs (truncated for display)
        $lines = explode("\n", $recent_logs);
        $recent_lines = array_slice($lines, -20); // Last 20 lines
        
        return "Debug log file: {$log_file}\n\nRecent entries:\n" . implode("\n", $recent_lines);
        
    } catch (Exception $e) {
        return 'Failed to read debug logs: ' . $e->getMessage();
    }
}

/**
 * Clear debug logs admin function
 */
function blackwall_clearDebugLogs(array $params) {
    try {
        $success = BlackwallDebugLogger::clearLogs();
        
        if ($success) {
            return 'Debug logs cleared successfully.';
        } else {
            return 'No log file found to clear.';
        }
        
    } catch (Exception $e) {
        return 'Failed to clear debug logs: ' . $e->getMessage();
    }
}

/**
 * Single Sign-On function - SIMPLIFIED
 */
function blackwall_ServiceSingleSignOn(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $api_key = $params['configoption1'] ?? '';
        
        if (!$api_key) {
            return [
                'success' => false,
                'errorMsg' => 'No API key configured. Single Sign-On is not available in informational mode.',
            ];
        }
        
        $lang = 'en';
        if (isset($params['clientsdetails']['language'])) {
            switch (strtolower($params['clientsdetails']['language'])) {
                case 'russian':
                case 'ukrainian':
                    $lang = 'ru';
                    break;
            }
        }
        
        $sso_url = "https://apiv2.botguard.net/{$lang}/website/" . urlencode($domain) . "/statistics?api-key=" . urlencode($api_key);
        
        return [
            'success' => true,
            'redirectTo' => $sso_url,
        ];
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        
        return [
            'success' => false,
            'errorMsg' => $e->getMessage(),
        ];
    }
}

/**
 * Helper function to get domain from params
 */
function blackwall_getDomainFromParams(array $params) {
    if (!empty($params['domain'])) {
        return $params['domain'];
    }
    
    if (!empty($params['customfields']['Domain'])) {
        return $params['customfields']['Domain'];
    }
    
    // Try service properties
    if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
        $domain = $params['model']->serviceProperties->get('Blackwall Domain');
        if ($domain) {
            return $domain;
        }
    }
    
    throw new Exception('Domain not found in service parameters.');
}
