<?php
/**
 * Blackwall (BotGuard) WHMCS Module
 * Provides website protection services with GateKeeper integration
 *
 * @author Zencommerce India
 * @version 2.0.0
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
            'Description' => 'BotGuard API key for provisioning',
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
        'gatekeeper_enabled' => [
            'FriendlyName' => 'GateKeeper Integration',
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Enable GateKeeper integration for advanced protection',
        ],
    ];
}

/**
 * Test connection to the service
 */
function blackwall_TestConnection(array $params) {
    try {
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is required for connection test.');
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
 * Provision a new account
 */
function blackwall_CreateAccount(array $params) {
    try {
        // Validate required parameters
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is not configured for this product.');
        }
        
        $api_key = $params['configoption1'];
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        
        // Get domain from parameters
        $domain = '';
        if (!empty($params['domain'])) {
            $domain = $params['domain'];
        } elseif (!empty($params['customfields']['Domain'])) {
            $domain = $params['customfields']['Domain'];
        } else {
            throw new Exception('Domain name is required for provisioning.');
        }
        
        // Validate domain format
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            throw new Exception('Invalid domain name format.');
        }
        
        $api = new BlackwallApi($api_key);
        
        // Step 1: Create subaccount in BotGuard
        $client = $params['model']['client'] ?? null;
        $subaccount_data = [
            'email' => $client->email ?? $params['clientsdetails']['email'],
            'first_name' => $client->firstname ?? $params['clientsdetails']['firstname'],
            'last_name' => $client->lastname ?? $params['clientsdetails']['lastname'],
            'company' => $client->companyname ?? $params['clientsdetails']['companyname'] ?? '',
            'country' => $client->country ?? $params['clientsdetails']['country'] ?? '',
            'lang' => 'en'
        ];
        
        try {
            $subaccount = $api->createSubaccount($subaccount_data);
            $user_id = $subaccount['id'] ?? null;
            $user_api_key = $subaccount['api_key'] ?? null;
        } catch (Exception $sub_e) {
            // If subaccount creation fails, try to find existing one
            try {
                $existing_subaccounts = $api->getSubaccounts(['email' => $subaccount_data['email']]);
                if (!empty($existing_subaccounts) && is_array($existing_subaccounts)) {
                    $subaccount = $existing_subaccounts[0];
                    $user_id = $subaccount['id'] ?? null;
                    $user_api_key = $subaccount['api_key'] ?? null;
                } else {
                    throw new Exception('Failed to create or find BotGuard subaccount: ' . $sub_e->getMessage());
                }
            } catch (Exception $find_e) {
                throw new Exception('Failed to create or find BotGuard subaccount: ' . $sub_e->getMessage());
            }
        }
        
        if (!$user_id) {
            throw new Exception('Failed to get user ID from BotGuard subaccount.');
        }
        
        // Step 2: Add domain to BotGuard
        $domain_result = $api->addDomain($domain, $user_id);
        
        // Debug: Log all configuration options
        logModuleCall('blackwall', 'CreateAccount_Debug', [
            'configoption1' => !empty($params['configoption1']) ? 'SET' : 'EMPTY',
            'configoption2' => $params['configoption2'] ?? 'NOT_SET',
            'configoption3' => $params['configoption3'] ?? 'NOT_SET', 
            'configoption4' => $params['configoption4'] ?? 'NOT_SET',
            'gatekeeper_enabled' => $gatekeeper_enabled ? 'true' : 'false',
            'domain' => $domain
        ], 'Configuration check', '');
        
        // Step 3: GateKeeper integration (if enabled)
        logModuleCall('blackwall', 'GateKeeper_Check', [
            'gatekeeper_enabled' => $gatekeeper_enabled,
            'configoption4' => $params['configoption4'] ?? 'NOT_SET'
        ], 'Checking if GateKeeper should be enabled', '');
        
        if ($gatekeeper_enabled) {
            logModuleCall('blackwall', 'GateKeeper_Starting', ['domain' => $domain, 'user_id' => $user_id], 'Starting GateKeeper integration', '');
            
            try {
                // Get domain IPs for GateKeeper
                logModuleCall('blackwall', 'GateKeeper_DNS_Lookup', ['domain' => $domain], 'Looking up domain IPs', '');
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                logModuleCall('blackwall', 'GateKeeper_DNS_Result', [
                    'domain' => $domain,
                    'ipv4' => $domain_ips,
                    'ipv6' => $domain_ipv6s
                ], 'DNS lookup completed', '');
                
                // Create user in GateKeeper first
                $gk_user_data = [
                    'id' => $user_id,
                    'tag' => 'whmcs'
                ];
                
                logModuleCall('blackwall', 'GateKeeper_CreateUser_Start', $gk_user_data, 'Attempting to create GateKeeper user', '');
                
                try {
                    $user_result = $api->createGateKeeperUser($gk_user_data);
                    logModuleCall('blackwall', 'GateKeeper_CreateUser_Success', $user_result, 'GateKeeper user created successfully', '');
                } catch (Exception $user_e) {
                    logModuleCall('blackwall', 'GateKeeper_CreateUser_Error', [
                        'user_data' => $gk_user_data,
                        'error' => $user_e->getMessage()
                    ], $user_e->getMessage(), $user_e->getTraceAsString());
                    
                    // Continue anyway - user might already exist
                }
                
                // Small delay to ensure user is created
                sleep(2);
                
                // Create website in GateKeeper
                $gk_website_data = [
                    'domain' => $domain,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'user_id' => $user_id,
                    'tag' => ['whmcs'],
                    'status' => BlackwallConstants::STATUS_SETUP,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                logModuleCall('blackwall', 'GateKeeper_CreateWebsite_Start', $gk_website_data, 'Attempting to create GateKeeper website', '');
                
                try {
                    $website_result = $api->createGateKeeperWebsite($gk_website_data);
                    logModuleCall('blackwall', 'GateKeeper_CreateWebsite_Success', $website_result, 'GateKeeper website created successfully', '');
                    
                    // Update to online status after creation
                    sleep(1);
                    $gk_update_data = array_merge($gk_website_data, [
                        'status' => BlackwallConstants::STATUS_ONLINE
                    ]);
                    
                    logModuleCall('blackwall', 'GateKeeper_UpdateWebsite_Start', $gk_update_data, 'Attempting to update GateKeeper website status', '');
                    
                    try {
                        $update_result = $api->updateGateKeeperWebsite($domain, $gk_update_data);
                        logModuleCall('blackwall', 'GateKeeper_UpdateWebsite_Success', $update_result, 'GateKeeper website status updated to online', '');
                    } catch (Exception $update_e) {
                        logModuleCall('blackwall', 'GateKeeper_UpdateWebsite_Error', [
                            'domain' => $domain,
                            'update_data' => $gk_update_data,
                            'error' => $update_e->getMessage()
                        ], $update_e->getMessage(), $update_e->getTraceAsString());
                    }
                    
                } catch (Exception $website_e) {
                    logModuleCall('blackwall', 'GateKeeper_CreateWebsite_Error', [
                        'website_data' => $gk_website_data,
                        'error' => $website_e->getMessage()
                    ], $website_e->getMessage(), $website_e->getTraceAsString());
                    
                    // Try alternative creation method
                    logModuleCall('blackwall', 'GateKeeper_Alternative_Method', $gk_website_data, 'Trying alternative GateKeeper creation method', '');
                    
                    // Alternative: Try with minimal data first
                    $minimal_data = [
                        'domain' => $domain,
                        'user_id' => $user_id,
                        'status' => BlackwallConstants::STATUS_SETUP
                    ];
                    
                    try {
                        $alt_result = $api->createGateKeeperWebsite($minimal_data);
                        logModuleCall('blackwall', 'GateKeeper_Alternative_Success', $alt_result, 'Alternative GateKeeper creation successful', '');
                    } catch (Exception $alt_e) {
                        logModuleCall('blackwall', 'GateKeeper_Alternative_Error', [
                            'minimal_data' => $minimal_data,
                            'error' => $alt_e->getMessage()
                        ], $alt_e->getMessage(), $alt_e->getTraceAsString());
                    }
                }
                
            } catch (Exception $gk_e) {
                // Log comprehensive GateKeeper errors
                logModuleCall(
                    'blackwall',
                    'GateKeeper_CreateAccount_Critical_Error',
                    [
                        'domain' => $domain,
                        'user_id' => $user_id,
                        'gatekeeper_enabled' => $gatekeeper_enabled,
                        'api_key_available' => !empty($params['configoption1']),
                        'error' => $gk_e->getMessage(),
                        'error_code' => $gk_e->getCode()
                    ],
                    $gk_e->getMessage(),
                    $gk_e->getTraceAsString()
                );
            }
        } else {
            logModuleCall('blackwall', 'GateKeeper_Disabled', [
                'configoption4' => $params['configoption4'] ?? 'NOT_SET'
            ], 'GateKeeper integration is disabled', '');
        }
        
        // Step 4: Activate domain
        sleep(1); // Brief delay
        $api->updateDomainStatus($domain, BlackwallConstants::STATUS_ONLINE);
        
        // Store service properties
        BlackwallDebugLogger::info('Storing service properties', [
            'domain' => $domain,
            'user_id' => $user_id,
            'user_api_key_length' => strlen($user_api_key),
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            try {
                $params['model']->serviceProperties->save([
                    'Blackwall Domain' => $domain,
                    'Blackwall User ID' => $user_id,
                    'Blackwall API Key' => $user_api_key,
                    'GateKeeper Enabled' => $gatekeeper_enabled ? 'Yes' : 'No',
                ]);
                BlackwallDebugLogger::info('Service properties saved successfully');
            } catch (Exception $props_e) {
                BlackwallDebugLogger::error('Failed to save service properties', [
                    'error' => $props_e->getMessage()
                ]);
            }
        } else {
            BlackwallDebugLogger::warning('Service properties not available', [
                'model_exists' => isset($params['model']),
                'method_exists' => isset($params['model']) && method_exists($params['model'], 'serviceProperties')
            ]);
        }
        
        BlackwallDebugLogger::info('=== CreateAccount COMPLETED SUCCESSFULLY ===');
        return 'success';
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('=== CreateAccount FAILED ===', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'params' => $params
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
 * Suspend an account
 */
function blackwall_SuspendAccount(array $params) {
    try {
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is not configured.');
        }
        
        $domain = blackwall_getDomainFromParams($params);
        $api = new BlackwallApi($params['configoption1']);
        
        // Suspend in BotGuard
        $api->updateDomainStatus($domain, BlackwallConstants::STATUS_PAUSED);
        
        // Suspend in GateKeeper if enabled
        if (!empty($params['configoption4']) && $params['configoption4'] === 'on') {
            try {
                $user_id = blackwall_getUserIdFromParams($params);
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                $gk_data = [
                    'domain' => $domain,
                    'user_id' => $user_id,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'status' => BlackwallConstants::STATUS_PAUSED,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                $api->updateGateKeeperWebsite($domain, $gk_data);
            } catch (Exception $gk_e) {
                logModuleCall('blackwall', 'GateKeeper_Suspend', $params, $gk_e->getMessage(), $gk_e->getTraceAsString());
            }
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Unsuspend an account
 */
function blackwall_UnsuspendAccount(array $params) {
    try {
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is not configured.');
        }
        
        $domain = blackwall_getDomainFromParams($params);
        $api = new BlackwallApi($params['configoption1']);
        
        // Unsuspend in BotGuard
        $api->updateDomainStatus($domain, BlackwallConstants::STATUS_ONLINE);
        
        // Unsuspend in GateKeeper if enabled
        if (!empty($params['configoption4']) && $params['configoption4'] === 'on') {
            try {
                $user_id = blackwall_getUserIdFromParams($params);
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                $gk_data = [
                    'domain' => $domain,
                    'user_id' => $user_id,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'status' => BlackwallConstants::STATUS_ONLINE,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                $api->updateGateKeeperWebsite($domain, $gk_data);
            } catch (Exception $gk_e) {
                logModuleCall('blackwall', 'GateKeeper_Unsuspend', $params, $gk_e->getMessage(), $gk_e->getTraceAsString());
            }
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Terminate an account
 */
function blackwall_TerminateAccount(array $params) {
    try {
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is not configured.');
        }
        
        $domain = blackwall_getDomainFromParams($params);
        $api = new BlackwallApi($params['configoption1']);
        
        // Delete from BotGuard
        $api->deleteDomain($domain);
        
        // Delete from GateKeeper if enabled
        if (!empty($params['configoption4']) && $params['configoption4'] === 'on') {
            try {
                $api->deleteGateKeeperWebsite($domain);
            } catch (Exception $gk_e) {
                logModuleCall('blackwall', 'GateKeeper_Terminate', $params, $gk_e->getMessage(), $gk_e->getTraceAsString());
            }
        }
        
        // Clean up service properties
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $params['model']->serviceProperties->save([
                'Blackwall Domain' => '',
                'Blackwall User ID' => '',
                'Blackwall API Key' => '',
                'GateKeeper Enabled' => '',
            ]);
        }
        
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
        $api_key = $params['configoption1'];
        $user_api_key = blackwall_getUserApiKeyFromParams($params);
        $gatekeeper_enabled = $params['configoption4'] === 'on';
        
        // Try to get user API key if missing - TEMPORARILY DISABLED to prevent client area crashes
        // TODO: Re-enable once API stability is confirmed
        /*
        if (!$user_api_key && !empty($api_key)) {
            try {
                $api = new BlackwallApi($api_key);
                $client_email = $params['clientsdetails']['email'] ?? '';
                
                if ($client_email) {
                    // Wrap in additional try-catch to prevent any API errors
                    try {
                        $subaccounts = $api->getSubaccounts(['email' => $client_email]);
                        if (!empty($subaccounts) && is_array($subaccounts) && isset($subaccounts[0]['api_key'])) {
                            $user_api_key = $subaccounts[0]['api_key'];
                            
                            // Update service properties with the found API key
                            if ($user_api_key && isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
                                try {
                                    $params['model']->serviceProperties->save([
                                        'Blackwall API Key' => $user_api_key,
                                    ]);
                                } catch (Exception $save_e) {
                                    // Log but continue if saving fails
                                    error_log("Failed to save user API key: " . $save_e->getMessage());
                                }
                            }
                        }
                    } catch (Exception $sub_e) {
                        // Log subaccount retrieval error but continue
                        error_log("Failed to get subaccounts: " . $sub_e->getMessage());
                    }
                }
            } catch (Exception $api_e) {
                // Log but continue - don't let API errors break client area
                logModuleCall('blackwall', 'ClientArea_GetApiKey_Error', [
                    'error' => $api_e->getMessage(),
                    'client_email' => $params['clientsdetails']['email'] ?? 'not_available'
                ], $api_e->getMessage(), $api_e->getTraceAsString());
                
                // Continue without user API key - will fallback to admin key
            }
        }
        */
        
        // Check DNS configuration
        $dns_status = BlackwallApi::checkDomainDnsConfiguration($domain);
        $gatekeeper_nodes = BlackwallConstants::getGateKeeperNodes();
        
        return [
            'tabOverviewModuleOutputTemplate' => 'templates/overview.tpl',
            'templateVariables' => [
                'domain' => $domain,
                'api_key' => $api_key,
                'user_api_key' => $user_api_key,
                'primary_server' => $params['configoption2'],
                'secondary_server' => $params['configoption3'],
                'gatekeeper_enabled' => $gatekeeper_enabled,
                'dns_status' => $dns_status,
                'gatekeeper_nodes' => $gatekeeper_nodes,
                'service_status' => $params['status'],
                'client_lang' => $params['clientsdetails']['language'] ?? 'english',
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
        'Force CreateAccount Test' => 'forceCreateAccountTest',
        'Test Connection' => 'testConnectionAdmin',
        'Sync Status' => 'syncStatus',
        'Check DNS' => 'checkDns',
        'Check GateKeeper' => 'checkGateKeeper',
        'Manual GateKeeper Test' => 'manualGateKeeperTest',
        'Debug Account Creation' => 'debugAccountCreation',
        'Test Simplified Creation' => 'testSimplifiedCreation',
        'View Debug Logs' => 'viewDebugLogs',
        'Clear Debug Logs' => 'clearDebugLogs',
    ];
}

/**
 * Test connection admin function
 */
function blackwall_testConnectionAdmin(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
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
 * Sync status admin function
 */
function blackwall_syncStatus(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
        }

        $domain = blackwall_getDomainFromParams($params);
        $api = new BlackwallApi($params['configoption1']);
        $domain_info = $api->getDomain($domain);
        
        return 'Domain status: ' . ($domain_info['status'] ?? 'Unknown');
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'Sync failed: ' . $e->getMessage();
    }
}

/**
 * Check DNS admin function
 */
function blackwall_checkDns(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $is_configured = BlackwallApi::checkDomainDnsConfiguration($domain);
        
        return $is_configured['status'] ? 'DNS is correctly configured.' : 'DNS is not correctly configured.';
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'DNS check failed: ' . $e->getMessage();
    }
}

/**
 * Check module version admin function
 */
function blackwall_checkModuleVersion(array $params) {
    $version_info = [
        'module_file' => __FILE__,
        'has_debug_logger' => class_exists('BlackwallDebugLogger'),
        'createaccount_method' => 'UPDATED_NO_SUBACCOUNTS',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'function_exists' => [
            'blackwall_CreateAccount' => function_exists('blackwall_CreateAccount'),
            'blackwall_viewDebugLogs' => function_exists('blackwall_viewDebugLogs'),
        ]
    ];
    
    // Test if we can create debug logger
    try {
        BlackwallDebugLogger::info('Version check called');
        $version_info['debug_logger_working'] = true;
        $version_info['log_file'] = BlackwallDebugLogger::getLogFile();
    } catch (Exception $e) {
        $version_info['debug_logger_working'] = false;
        $version_info['debug_logger_error'] = $e->getMessage();
    }
    
    return "Module Version Info:\n" . json_encode($version_info, JSON_PRETTY_PRINT);
}

/**
 * Force create account test admin function
 */
function blackwall_forceCreateAccountTest(array $params) {
    BlackwallDebugLogger::info('=== FORCE CREATE ACCOUNT TEST ===');
    
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        // Force call the CreateAccount function directly
        $test_params = $params;
        $test_params['domain'] = $domain;
        
        BlackwallDebugLogger::info('Calling blackwall_CreateAccount directly', [
            'domain' => $domain,
            'params_keys' => array_keys($test_params)
        ]);
        
        $result = blackwall_CreateAccount($test_params);
        
        BlackwallDebugLogger::info('CreateAccount result', ['result' => $result]);
        
        return "CreateAccount test result: " . $result . "\nCheck debug logs for details.";
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('CreateAccount test failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return "CreateAccount test failed: " . $e->getMessage();
    }
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
 * Test simplified creation (no users)
 */
function blackwall_testSimplifiedCreation(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
        }

        $domain = blackwall_getDomainFromParams($params);
        $api_key = $params['configoption1'];
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        
        $api = new BlackwallApi($api_key);
        $user_id = time(); // Simple timestamp ID
        
        // Test 1: Add domain to BotGuard (no user)
        try {
            $botguard_result = $api->addDomain($domain);
            $botguard_status = 'BotGuard: SUCCESS. Result: ' . json_encode($botguard_result);
        } catch (Exception $bg_e) {
            $botguard_status = 'BotGuard: FAILED. Error: ' . $bg_e->getMessage();
        }
        
        // Test 2: Set domain status to online
        try {
            $api->updateDomainStatus($domain, BlackwallConstants::STATUS_ONLINE);
            $status_update = 'Status Update: SUCCESS';
        } catch (Exception $status_e) {
            $status_update = 'Status Update: FAILED. Error: ' . $status_e->getMessage();
        }
        
        // Test 3: GateKeeper (if enabled)
        $gatekeeper_status = 'GateKeeper: DISABLED';
        if ($gatekeeper_enabled) {
            try {
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                $gk_website_data = [
                    'domain' => $domain,
                    'user_id' => (string) $user_id,
                    'status' => BlackwallConstants::STATUS_SETUP,
                    'subdomain' => 'www',
                    'ip' => implode(',', $domain_ips),
                    'ipv6' => implode(',', $domain_ipv6s),
                    'tag' => 'whmcs_test'
                ];
                
                $gk_result = $api->createGateKeeperWebsite($gk_website_data);
                $gatekeeper_status = 'GateKeeper: SUCCESS. Result: ' . json_encode($gk_result);
            } catch (Exception $gk_e) {
                $gatekeeper_status = 'GateKeeper: FAILED. Error: ' . $gk_e->getMessage();
            }
        }
        
        return $botguard_status . ' | ' . $status_update . ' | ' . $gatekeeper_status;
        
    } catch (Exception $e) {
        logModuleCall('blackwall', 'TestSimplifiedCreation_Error', $params, $e->getMessage(), $e->getTraceAsString());
        return 'Test failed: ' . $e->getMessage();
    }
}
function blackwall_debugAccountCreation(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $api_key = $params['configoption1'];
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        
        $debug_info = [
            'domain' => $domain,
            'api_key_present' => !empty($api_key),
            'api_key_length' => strlen($api_key ?? ''),
            'configoption4_raw' => $params['configoption4'] ?? 'NOT_SET',
            'configoption4_type' => gettype($params['configoption4'] ?? null),
            'gatekeeper_enabled' => $gatekeeper_enabled,
            'checkbox_checked' => ($params['configoption4'] ?? '') === 'on' ? 'YES' : 'NO',
            'all_config_options' => [
                'configoption1' => $params['configoption1'] ?? 'NOT_SET',
                'configoption2' => $params['configoption2'] ?? 'NOT_SET',
                'configoption3' => $params['configoption3'] ?? 'NOT_SET',
                'configoption4' => $params['configoption4'] ?? 'NOT_SET',
            ]
        ];
        
        logModuleCall('blackwall', 'DebugAccountCreation', $debug_info, 'Debug information for account creation', '');
        
        if (!$gatekeeper_enabled) {
            return "GateKeeper DISABLED. Checkbox value: '{$params['configoption4']}' | Expected: 'on' when checked";
        }
        
        return "GateKeeper ENABLED. Checkbox is checked. Would proceed with creation. Check module logs for full debug info.";
        
    } catch (Exception $e) {
        logModuleCall('blackwall', 'DebugAccountCreation_Error', $params, $e->getMessage(), $e->getTraceAsString());
        return 'Debug failed: ' . $e->getMessage();
    }
}
function blackwall_checkGateKeeper(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
        }
        
        if (!(!empty($params['configoption4']) && $params['configoption4'] === 'on')) {
            return 'GateKeeper integration is disabled for this product.';
        }

        $domain = blackwall_getDomainFromParams($params);
        $api = new BlackwallApi($params['configoption1']);
        
        // Try to get website from GateKeeper
        try {
            $website_info = $api->getGateKeeperWebsite($domain);
            return 'GateKeeper website found. Status: ' . ($website_info['status'] ?? 'Unknown') . '. Data: ' . json_encode($website_info);
        } catch (Exception $get_e) {
            return 'Website not found in GateKeeper. Error: ' . $get_e->getMessage();
        }
        
    } catch (Exception $e) {
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return 'GateKeeper check failed: ' . $e->getMessage();
    }
}

/**
 * Manual GateKeeper test admin function
 */
function blackwall_manualGateKeeperTest(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
        }

        $domain = blackwall_getDomainFromParams($params);
        $api_key = $params['configoption1'];
        
        // Check GateKeeper enabled status
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        
        logModuleCall('blackwall', 'ManualGateKeeperTest_Start', [
            'domain' => $domain,
            'api_key_length' => strlen($api_key),
            'configoption4_raw' => $params['configoption4'] ?? 'NOT_SET',
            'gatekeeper_enabled' => $gatekeeper_enabled ? 'true' : 'false'
        ], 'Starting manual GateKeeper test', '');
        
        if (!$gatekeeper_enabled) {
            return "GateKeeper is disabled. configoption4 = '{$params['configoption4']}' (expected 'on')";
        }
        
        // Get user ID from service
        try {
            $user_id = blackwall_getUserIdFromParams($params);
        } catch (Exception $e) {
            $user_id = rand(1000, 9999); // Use random for testing
        }
        
        $api = new BlackwallApi($api_key);
        
        // Test 1: Create user
        $gk_user_data = [
            'id' => (string) $user_id,
            'tag' => 'whmcs_manual_test'
        ];
        
        try {
            $user_result = $api->createGateKeeperUser($gk_user_data);
            $user_status = 'User creation: SUCCESS. Result: ' . json_encode($user_result);
            logModuleCall('blackwall', 'ManualTest_UserSuccess', $user_result, 'User created successfully', '');
        } catch (Exception $user_e) {
            $user_status = 'User creation: FAILED. Error: ' . $user_e->getMessage();
            logModuleCall('blackwall', 'ManualTest_UserError', $gk_user_data, $user_e->getMessage(), $user_e->getTraceAsString());
        }
        
        // Test 2: Create website
        $domain_ips = BlackwallApi::getDomainARecords($domain);
        $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
        
        $gk_website_data = [
            'domain' => $domain,
            'subdomain' => ['www'],
            'ip' => $domain_ips,
            'ipv6' => $domain_ipv6s,
            'user_id' => (string) $user_id,
            'tag' => ['whmcs_manual_test'],
            'status' => BlackwallConstants::STATUS_SETUP
        ];
        
        try {
            $website_result = $api->createGateKeeperWebsite($gk_website_data);
            $website_status = 'Website creation: SUCCESS. Result: ' . json_encode($website_result);
            logModuleCall('blackwall', 'ManualTest_WebsiteSuccess', $website_result, 'Website created successfully', '');
        } catch (Exception $website_e) {
            $website_status = 'Website creation: FAILED. Error: ' . $website_e->getMessage();
            logModuleCall('blackwall', 'ManualTest_WebsiteError', $gk_website_data, $website_e->getMessage(), $website_e->getTraceAsString());
        }
        
        return $user_status . ' | ' . $website_status;
        
    } catch (Exception $e) {
        logModuleCall('blackwall', 'ManualGateKeeperTest_Error', $params, $e->getMessage(), $e->getTraceAsString());
        return 'Test failed: ' . $e->getMessage();
    }
}

/**
 * Single Sign-On function
 */
function blackwall_ServiceSingleSignOn(array $params) {
    try {
        $domain = blackwall_getDomainFromParams($params);
        $user_api_key = blackwall_getUserApiKeyFromParams($params);
        
        // If no user API key, try to get it from BotGuard
        if (!$user_api_key && !empty($params['configoption1'])) {
            try {
                $api = new BlackwallApi($params['configoption1']);
                $client_email = $params['clientsdetails']['email'] ?? '';
                
                if ($client_email) {
                    $subaccounts = $api->getSubaccounts(['email' => $client_email]);
                    if (!empty($subaccounts) && is_array($subaccounts)) {
                        $user_api_key = $subaccounts[0]['api_key'] ?? null;
                        
                        // Update service properties with the found API key
                        if ($user_api_key && isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
                            $params['model']->serviceProperties->save([
                                'Blackwall API Key' => $user_api_key,
                            ]);
                        }
                    }
                }
            } catch (Exception $api_e) {
                // Log but don't fail
                logModuleCall('blackwall', 'SSO_GetApiKey', $params, $api_e->getMessage(), $api_e->getTraceAsString());
            }
        }
        
        if (!$user_api_key) {
            // Fallback to admin API key if no user key available
            $user_api_key = $params['configoption1'];
        }
        
        if (!$user_api_key) {
            throw new Exception('No API key available for SSO access.');
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
        
        $sso_url = "https://apiv2.botguard.net/{$lang}/website/" . urlencode($domain) . "/statistics?api-key=" . urlencode($user_api_key);
        
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

/**
 * Helper function to get user ID from params
 */
function blackwall_getUserIdFromParams(array $params) {
    try {
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $user_id = $params['model']->serviceProperties->get('Blackwall User ID');
            if ($user_id) {
                return $user_id;
            }
        }
    } catch (Exception $e) {
        // Log error and continue to throw
        error_log("Error getting user ID: " . $e->getMessage());
    }
    
    throw new Exception('User ID not found in service parameters.');
}

/**
 * Helper function to get user API key from params
 */
function blackwall_getUserApiKeyFromParams(array $params) {
    try {
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            return $params['model']->serviceProperties->get('Blackwall API Key');
        }
    } catch (Exception $e) {
        // Log error but return null
        error_log("Error getting user API key: " . $e->getMessage());
    }
    
    return null;
}
