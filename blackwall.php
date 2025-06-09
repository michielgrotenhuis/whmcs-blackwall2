<?php
/**
 * Blackwall (BotGuard) WHMCS Module
 * Provides website protection services with GateKeeper integration
 *
 * @author Zencommerce India
 * @version 2.2.0
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
 * Provision a new account - Following WiseCP Flow
 */
function blackwall_CreateAccount(array $params) {
    try {
        BlackwallDebugLogger::info('=== CreateAccount STARTED (WiseCP Flow) ===', [
            'params' => array_keys($params)
        ]);
        
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
        
        // Get client information
        $client = $params['model']['client'] ?? null;
        $user_email = $client->email ?? $params['clientsdetails']['email'];
        $first_name = $client->firstname ?? $params['clientsdetails']['firstname'];
        $last_name = $client->lastname ?? $params['clientsdetails']['lastname'];
        $company = $client->companyname ?? $params['clientsdetails']['companyname'] ?? '';
        $country = $client->country ?? $params['clientsdetails']['country'] ?? '';
        
        BlackwallDebugLogger::info('Creating Blackwall service', [
            'domain' => $domain,
            'email' => $user_email,
            'name' => $first_name . ' ' . $last_name,
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        $api = new BlackwallApi($api_key);
        
        // Step 1: Create subaccount in BotGuard
        $subaccount_data = [
            'email' => $user_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'company' => $company,
            'country' => $country,
            'lang' => 'en'
        ];
        
        BlackwallDebugLogger::info('Creating BotGuard subaccount', $subaccount_data);
        
        try {
            $subaccount = $api->createSubaccount($subaccount_data);
            $user_id = $subaccount['id'] ?? null;
            $user_api_key = $subaccount['api_key'] ?? null;
        } catch (Exception $sub_e) {
            // If subaccount creation fails, try to find existing one
            BlackwallDebugLogger::warning('Subaccount creation failed, trying to find existing', [
                'error' => $sub_e->getMessage()
            ]);
            
            try {
                $existing_subaccounts = $api->getSubaccounts(['email' => $user_email]);
                if (!empty($existing_subaccounts) && is_array($existing_subaccounts)) {
                    $subaccount = $existing_subaccounts[0];
                    $user_id = $subaccount['id'] ?? null;
                    $user_api_key = $subaccount['api_key'] ?? null;
                    BlackwallDebugLogger::info('Found existing subaccount', ['user_id' => $user_id]);
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
        
        BlackwallDebugLogger::info('BotGuard subaccount ready', [
            'user_id' => $user_id,
            'has_api_key' => !empty($user_api_key)
        ]);
        
        // Step 2: Add domain to BotGuard
        $website_data = [
            'domain' => $domain,
            'user' => $user_id
        ];
        
        BlackwallDebugLogger::info('Adding domain to BotGuard', $website_data);
        $domain_result = $api->addDomain($domain, $user_id);
        BlackwallDebugLogger::info('Domain added to BotGuard', $domain_result);
        
        // Step 3: GateKeeper integration (if enabled)
        if ($gatekeeper_enabled) {
            BlackwallDebugLogger::info('Starting GateKeeper integration', ['domain' => $domain]);
            
            try {
                // Step 3a: Create user in GateKeeper
                $gk_user_data = [
                    'id' => (string) $user_id,
                    'tag' => 'whmcs'
                ];
                
                BlackwallDebugLogger::info('Creating GateKeeper user', $gk_user_data);
                
                try {
                    $user_result = $api->createGateKeeperUser($gk_user_data);
                    BlackwallDebugLogger::info('GateKeeper user created', $user_result);
                } catch (Exception $user_e) {
                    BlackwallDebugLogger::warning('GateKeeper user creation failed (might already exist)', [
                        'error' => $user_e->getMessage()
                    ]);
                    // Continue - user might already exist
                }
                
                // Small delay to ensure user is created
                sleep(2);
                
                // Step 3b: Get domain DNS records
                BlackwallDebugLogger::info('Looking up DNS records for domain', ['domain' => $domain]);
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                BlackwallDebugLogger::info('DNS lookup completed', [
                    'domain' => $domain,
                    'ipv4' => $domain_ips,
                    'ipv6' => $domain_ipv6s
                ]);
                
                // Step 3c: Create website in GateKeeper
                $gk_website_data = [
                    'domain' => $domain,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'user_id' => (string) $user_id,
                    'tag' => ['whmcs'],
                    'status' => BlackwallConstants::STATUS_SETUP,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                BlackwallDebugLogger::info('Creating GateKeeper website', $gk_website_data);
                
                try {
                    $website_result = $api->createGateKeeperWebsite($gk_website_data);
                    BlackwallDebugLogger::info('GateKeeper website created', $website_result);
                    
                    // Step 3d: Update to online status
                    sleep(1);
                    $gk_update_data = array_merge($gk_website_data, [
                        'status' => BlackwallConstants::STATUS_ONLINE
                    ]);
                    
                    BlackwallDebugLogger::info('Updating GateKeeper website to online', ['domain' => $domain]);
                    
                    try {
                        $update_result = $api->updateGateKeeperWebsite($domain, $gk_update_data);
                        BlackwallDebugLogger::info('GateKeeper website updated to online', $update_result);
                    } catch (Exception $update_e) {
                        BlackwallDebugLogger::warning('Failed to update GateKeeper website status', [
                            'domain' => $domain,
                            'error' => $update_e->getMessage()
                        ]);
                    }
                    
                } catch (Exception $website_e) {
                    BlackwallDebugLogger::error('GateKeeper website creation failed', [
                        'domain' => $domain,
                        'error' => $website_e->getMessage(),
                        'trace' => $website_e->getTraceAsString()
                    ]);
                    
                    // Try alternative creation method with minimal data
                    BlackwallDebugLogger::info('Trying alternative GateKeeper creation method');
                    $minimal_data = [
                        'domain' => $domain,
                        'user_id' => (string) $user_id,
                        'status' => BlackwallConstants::STATUS_SETUP
                    ];
                    
                    try {
                        $alt_result = $api->createGateKeeperWebsite($minimal_data);
                        BlackwallDebugLogger::info('Alternative GateKeeper creation successful', $alt_result);
                    } catch (Exception $alt_e) {
                        BlackwallDebugLogger::error('Alternative GateKeeper creation also failed', [
                            'error' => $alt_e->getMessage()
                        ]);
                    }
                }
                
            } catch (Exception $gk_e) {
                BlackwallDebugLogger::error('GateKeeper integration failed', [
                    'domain' => $domain,
                    'user_id' => $user_id,
                    'error' => $gk_e->getMessage(),
                    'trace' => $gk_e->getTraceAsString()
                ]);
                // Continue without failing the whole process
            }
        } else {
            BlackwallDebugLogger::info('GateKeeper integration disabled');
        }
        
        // Step 4: Activate domain in BotGuard
        sleep(1);
        BlackwallDebugLogger::info('Activating domain in BotGuard', ['domain' => $domain]);
        
        try {
            $api->updateDomainStatus($domain, BlackwallConstants::STATUS_ONLINE);
            BlackwallDebugLogger::info('Domain activated in BotGuard');
        } catch (Exception $status_e) {
            BlackwallDebugLogger::warning('Failed to update domain status in BotGuard', [
                'domain' => $domain,
                'error' => $status_e->getMessage()
            ]);
        }
        
        // Step 5: Store service properties
        BlackwallDebugLogger::info('Storing service properties', [
            'domain' => $domain,
            'user_id' => $user_id,
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            try {
                $params['model']->serviceProperties->save([
                    'Blackwall Domain' => $domain,
                    'Blackwall User ID' => $user_id,
                    'Blackwall API Key' => $user_api_key,
                    'GateKeeper Enabled' => $gatekeeper_enabled ? 'Yes' : 'No',
                    'Creation Method' => 'WiseCP Flow v2.2.0',
                ]);
                BlackwallDebugLogger::info('Service properties saved successfully');
            } catch (Exception $props_e) {
                BlackwallDebugLogger::error('Failed to save service properties', [
                    'error' => $props_e->getMessage()
                ]);
            }
        } else {
            BlackwallDebugLogger::warning('Service properties not available');
        }
        
        BlackwallDebugLogger::info('=== CreateAccount COMPLETED SUCCESSFULLY (WiseCP Flow) ===');
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
 * Suspend an account
 */
function blackwall_SuspendAccount(array $params) {
    try {
        if (empty($params['configoption1'])) {
            throw new Exception('API Key is not configured.');
        }
        
        $domain = blackwall_getDomainFromParams($params);
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        $api = new BlackwallApi($params['configoption1']);
        
        BlackwallDebugLogger::info('Suspending account', [
            'domain' => $domain,
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        // Suspend in BotGuard
        $api->updateDomainStatus($domain, BlackwallConstants::STATUS_PAUSED);
        BlackwallDebugLogger::info('Domain suspended in BotGuard');
        
        // Suspend in GateKeeper if enabled
        if ($gatekeeper_enabled) {
            try {
                $user_id = blackwall_getUserIdFromParams($params);
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                $gk_data = [
                    'domain' => $domain,
                    'user_id' => (string) $user_id,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'status' => BlackwallConstants::STATUS_PAUSED,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                $api->updateGateKeeperWebsite($domain, $gk_data);
                BlackwallDebugLogger::info('Domain suspended in GateKeeper');
            } catch (Exception $gk_e) {
                BlackwallDebugLogger::warning('Failed to suspend in GateKeeper', [
                    'error' => $gk_e->getMessage()
                ]);
            }
        }
        
        return 'success';
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('Suspend failed', [
            'error' => $e->getMessage()
        ]);
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
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        $api = new BlackwallApi($params['configoption1']);
        
        BlackwallDebugLogger::info('Unsuspending account', [
            'domain' => $domain,
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        // Unsuspend in BotGuard
        $api->updateDomainStatus($domain, BlackwallConstants::STATUS_ONLINE);
        BlackwallDebugLogger::info('Domain unsuspended in BotGuard');
        
        // Unsuspend in GateKeeper if enabled
        if ($gatekeeper_enabled) {
            try {
                $user_id = blackwall_getUserIdFromParams($params);
                $domain_ips = BlackwallApi::getDomainARecords($domain);
                $domain_ipv6s = BlackwallApi::getDomainAAAARecords($domain);
                
                $gk_data = [
                    'domain' => $domain,
                    'user_id' => (string) $user_id,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'status' => BlackwallConstants::STATUS_ONLINE,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                ];
                
                $api->updateGateKeeperWebsite($domain, $gk_data);
                BlackwallDebugLogger::info('Domain unsuspended in GateKeeper');
            } catch (Exception $gk_e) {
                BlackwallDebugLogger::warning('Failed to unsuspend in GateKeeper', [
                    'error' => $gk_e->getMessage()
                ]);
            }
        }
        
        return 'success';
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('Unsuspend failed', [
            'error' => $e->getMessage()
        ]);
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
        $gatekeeper_enabled = !empty($params['configoption4']) && $params['configoption4'] === 'on';
        $api = new BlackwallApi($params['configoption1']);
        
        BlackwallDebugLogger::info('Terminating account', [
            'domain' => $domain,
            'gatekeeper_enabled' => $gatekeeper_enabled
        ]);
        
        // Delete from BotGuard
        $api->deleteDomain($domain);
        BlackwallDebugLogger::info('Domain deleted from BotGuard');
        
        // Delete from GateKeeper if enabled
        if ($gatekeeper_enabled) {
            try {
                $api->deleteGateKeeperWebsite($domain);
                BlackwallDebugLogger::info('Domain deleted from GateKeeper');
            } catch (Exception $gk_e) {
                BlackwallDebugLogger::warning('Failed to delete from GateKeeper', [
                    'error' => $gk_e->getMessage()
                ]);
            }
        }
        
        // Clean up service properties
        if (isset($params['model']) && method_exists($params['model'], 'serviceProperties')) {
            $params['model']->serviceProperties->save([
                'Blackwall Domain' => '',
                'Blackwall User ID' => '',
                'Blackwall API Key' => '',
                'GateKeeper Enabled' => '',
                'Creation Method' => '',
            ]);
        }
        
        BlackwallDebugLogger::info('Account terminated successfully');
        return 'success';
        
    } catch (Exception $e) {
        BlackwallDebugLogger::error('Terminate failed', [
            'error' => $e->getMessage()
        ]);
        logModuleCall('blackwall', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
} blackwall_TerminateAccount(array $params) {
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
        $user_api_key = blackwall_getUserApiKeyFromParams($params);
        $gatekeeper_enabled = ($params['configoption4'] ?? '') === 'on';
        
        // Check DNS configuration
        $dns_status = BlackwallApi::checkDomainDnsConfiguration($domain);
        $gatekeeper_nodes = BlackwallConstants::getGateKeeperNodes();
        
        return [
            'tabOverviewModuleOutputTemplate' => 'templates/overview.tpl',
            'templateVariables' => [
                'domain' => $domain,
                'api_key' => $api_key,
                'user_api_key' => $user_api_key,
                'primary_server' => $params['configoption2'] ?? 'de-nbg-ko1.botguard.net',
                'secondary_server' => $params['configoption3'] ?? 'de-nbg-ko2.botguard.net',
                'gatekeeper_enabled' => $gatekeeper_enabled,
                'dns_status' => $dns_status,
                'gatekeeper_nodes' => $gatekeeper_nodes,
                'service_status' => $params['status'],
                'client_lang' => $params['clientsdetails']['language'] ?? 'english',
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
        'Force CreateAccount Test' => 'forceCreateAccountTest',
        'Sync Status' => 'syncStatus',
        'Check DNS' => 'checkDns',
        'Check GateKeeper' => 'checkGateKeeper',
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
 * Force create account test admin function
 */
function blackwall_forceCreateAccountTest(array $params) {
    BlackwallDebugLogger::info('=== FORCE CREATE ACCOUNT TEST (WiseCP Flow) ===');
    
    try {
        $domain = blackwall_getDomainFromParams($params);
        
        // Force call the CreateAccount function directly
        $test_params = $params;
        $test_params['domain'] = $domain;
        
        BlackwallDebugLogger::info('Calling blackwall_CreateAccount directly with WiseCP flow', [
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
 * Check GateKeeper admin function
 */
function blackwall_checkGateKeeper(array $params) {
    try {
        if (empty($params['configoption1'])) {
            return 'API Key is required.';
        }
        
        if (($params['configoption4'] ?? '') !== 'on') {
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
 * Check module version admin function
 */
function blackwall_checkModuleVersion(array $params) {
    $version_info = [
        'module_file' => __FILE__,
        'version' => '2.2.0 - WiseCP Flow Implementation',
        'mode' => 'FULL_PROVISIONING_WITH_GATEKEEPER',
        'has_debug_logger' => class_exists('BlackwallDebugLogger'),
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'account_creation' => 'ENABLED_WITH_WISECP_FLOW',
        'gatekeeper_integration' => 'ENABLED',
        'function_exists' => [
            'blackwall_CreateAccount' => function_exists('blackwall_CreateAccount'),
            'blackwall_viewDebugLogs' => function_exists('blackwall_viewDebugLogs'),
        ]
    ];
    
    // Test debug logger
    try {
        BlackwallDebugLogger::info('Version check called - WiseCP flow implementation');
        $version_info['debug_logger_working'] = true;
        $version_info['log_file'] = BlackwallDebugLogger::getLogFile();
    } catch (Exception $e) {
        $version_info['debug_logger_working'] = false;
        $version_info['debug_logger_error'] = $e->getMessage();
    }
    
    return "Module Version Info (WiseCP Flow):\n" . json_encode($version_info, JSON_PRETTY_PRINT);
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
