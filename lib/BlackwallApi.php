<?php
/**
 * BlackwallApi - API client for BotGuard and GateKeeper services
 *
 * @author Zencommerce India
 * @version 2.0.0
 */

declare(strict_types=1);

class BlackwallApi {
    
    private string $api_key;
    private string $botguard_base_url = 'https://apiv2.botguard.net';
    private string $gatekeeper_base_url = 'https://api.blackwall.klikonline.nl:8443/v1.0';
    private int $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct(string $api_key) {
        if (empty($api_key)) {
            throw new InvalidArgumentException('API key cannot be empty');
        }
        
        $this->api_key = $api_key;
    }
    
    /**
     * Get all domains from BotGuard
     */
    public function getDomains(): array {
        $result = $this->doRequest('GET', '/website');
        
        if (!$result) {
            throw new Exception('No domains found or API request failed.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Unknown API error');
        }
        
        return is_array($result) ? $result : [$result];
    }
    
    /**
     * Get domain information from BotGuard
     */
    public function getDomain(string $domain): array {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }
        
        $encoded_domain = urlencode($domain);
        $result = $this->doRequest('GET', "/website/{$encoded_domain}");
        
        if (!$result) {
            throw new Exception('Domain is not registered or not found.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Failed to retrieve domain information');
        }
        
        return (array) $result;
    }
    
    /**
     * Create subaccount in BotGuard
     */
    public function createSubaccount(array $user_data): array {
        if (empty($user_data['email'])) {
            throw new InvalidArgumentException('Email is required for subaccount creation');
        }
        
        $result = $this->doRequest('POST', '/user', $user_data);
        
        if (!$result) {
            throw new Exception('Subaccount creation error. Please try again.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Subaccount creation failed');
        }
        
        return (array) $result;
    }
    
    /**
     * Get subaccounts from BotGuard
     */
    public function getSubaccounts(array $filter = []): array {
        try {
            $result = $this->doRequest('GET', '/user', $filter);
            
            if (!$result) {
                // Return empty array instead of throwing exception
                return [];
            }
            
            if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
                // Return empty array for "not found" type errors
                if (strpos($result->message ?? '', 'not found') !== false || 
                    strpos($result->message ?? '', 'No users') !== false) {
                    return [];
                }
                // Log error but return empty array instead of throwing
                error_log("BotGuard getSubaccounts error: " . ($result->message ?? 'Unknown error'));
                return [];
            }
            
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            // Log error but return empty array instead of throwing
            error_log("BotGuard getSubaccounts exception: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add domain to BotGuard
     */
    public function addDomain(string $domain, ?int $user_id = null): array {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }
        
        $data = ['domain' => $domain];
        
        // Only add user if provided
        if ($user_id !== null) {
            $data['user'] = $user_id;
        }
        
        // Log the API call
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard addDomain API call', [
                'domain' => $domain,
                'user_id' => $user_id,
                'data' => $data
            ]);
        }
        
        $result = $this->doRequest('POST', '/website', $data);
        
        // Log the result
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard addDomain API result', [
                'result' => $result
            ]);
        }
        
        if (!$result) {
            throw new Exception('Domain registration error. Please try again.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Domain registration failed');
        }
        
        return (array) $result;
    }
    
    /**
     * Update domain status in BotGuard
     */
    public function updateDomainStatus(string $domain, string $status): array {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }
        
        $encoded_domain = urlencode($domain);
        $result = $this->doRequest('PUT', "/website/{$encoded_domain}", [
            'status' => $status
        ]);
        
        if (!$result) {
            throw new Exception('Domain status update error. Please try again.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Domain status update failed');
        }
        
        return (array) $result;
    }
    
    /**
     * Delete domain from BotGuard
     */
    public function deleteDomain(string $domain): array {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }
        
        $encoded_domain = urlencode($domain);
        $result = $this->doRequest('DELETE', "/website/{$encoded_domain}");
        
        if (!$result) {
            throw new Exception('Domain deletion error. Please try again.');
        }
        
        if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
            throw new Exception($result->message ?? 'Domain deletion failed');
        }
        
        return (array) $result;
    }
    
    /**
     * Create user in GateKeeper
     */
    public function createGateKeeperUser(array $user_data): array {
        // Validate required fields
        if (empty($user_data['id'])) {
            throw new InvalidArgumentException('User ID is required for GateKeeper user creation');
        }
        
        // Convert user ID to string if it's numeric
        if (is_numeric($user_data['id'])) {
            $user_data['id'] = (string) $user_data['id'];
        }
        
        // Convert tag array to comma-separated string if it's an array
        if (isset($user_data['tag']) && is_array($user_data['tag'])) {
            $user_data['tag'] = implode(',', $user_data['tag']);
        }
        
        $result = $this->doGateKeeperRequest('POST', '/user', $user_data);
        
        // Handle specific GateKeeper responses
        if (isset($result['status'])) {
            if ($result['status'] === 'error') {
                // Check if user already exists
                if (strpos($result['message'] ?? '', 'already exists') !== false ||
                    strpos($result['message'] ?? '', 'duplicate') !== false) {
                    // User already exists, return success
                    return ['status' => 'exists', 'message' => 'User already exists'];
                }
                throw new Exception($result['message'] ?? 'GateKeeper user creation failed');
            }
        }
        
        // Handle HTTP error responses
        if (isset($result['code']) && $result['code'] >= 400) {
            if (strpos($result['message'] ?? '', 'already exists') !== false ||
                $result['code'] == 303) { // 303 means item already exists according to docs
                return ['status' => 'exists', 'message' => 'User already exists'];
            }
            throw new Exception($result['message'] ?? 'GateKeeper user creation failed');
        }
        
        return $result;
    }
    
    /**
     * Create website in GateKeeper
     */
    public function createGateKeeperWebsite(array $website_data): array {
        // Validate required fields
        if (empty($website_data['domain'])) {
            throw new InvalidArgumentException('Domain is required for GateKeeper website creation');
        }
        
        if (empty($website_data['user_id'])) {
            throw new InvalidArgumentException('User ID is required for GateKeeper website creation');
        }
        
        // Convert user_id to string if numeric
        if (is_numeric($website_data['user_id'])) {
            $website_data['user_id'] = (string) $website_data['user_id'];
        }
        
        // Convert arrays to proper format for form encoding
        $formatted_data = [
            'domain' => $website_data['domain'],
            'user_id' => $website_data['user_id'],
            'status' => $website_data['status'] ?? 'setup'
        ];
        
        // Handle arrays - convert to comma-separated strings for form encoding
        if (isset($website_data['subdomain']) && is_array($website_data['subdomain'])) {
            $formatted_data['subdomain'] = implode(',', $website_data['subdomain']);
        }
        
        if (isset($website_data['ip']) && is_array($website_data['ip'])) {
            $formatted_data['ip'] = implode(',', $website_data['ip']);
        }
        
        if (isset($website_data['ipv6']) && is_array($website_data['ipv6'])) {
            $formatted_data['ipv6'] = implode(',', $website_data['ipv6']);
        }
        
        if (isset($website_data['tag']) && is_array($website_data['tag'])) {
            $formatted_data['tag'] = implode(',', $website_data['tag']);
        }
        
        // For settings, we might need to JSON encode them or handle differently
        // Let's skip settings for now and add them later via update
        
        $result = $this->doGateKeeperRequest('POST', '/website', $formatted_data);
        
        // Handle specific GateKeeper responses
        if (isset($result['status'])) {
            if ($result['status'] === 'error') {
                // Check if website already exists
                if (strpos($result['message'] ?? '', 'already exists') !== false ||
                    strpos($result['message'] ?? '', 'duplicate') !== false) {
                    // Website already exists, try to update it instead
                    return $this->updateGateKeeperWebsite($website_data['domain'], $formatted_data);
                }
                throw new Exception($result['message'] ?? 'GateKeeper website creation failed');
            }
        }
        
        // Handle HTTP error responses
        if (isset($result['code']) && $result['code'] >= 400) {
            if (strpos($result['message'] ?? '', 'already exists') !== false ||
                $result['code'] == 303) { // 303 means item already exists
                return $this->updateGateKeeperWebsite($website_data['domain'], $formatted_data);
            }
            throw new Exception($result['message'] ?? 'GateKeeper website creation failed with code: ' . $result['code']);
        }
        
        return $result;
    }
    
    /**
     * Get website from GateKeeper
     */
    public function getGateKeeperWebsite(string $domain): array {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('GET', "/website/{$encoded_domain}");
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website retrieval failed');
        }
        
        return $result;
    }
    
    /**
     * Update website in GateKeeper
     */
    public function updateGateKeeperWebsite(string $domain, array $website_data): array {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('PUT', "/website/{$encoded_domain}", $website_data);
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website update failed');
        }
        
        return $result;
    }
    
    /**
     * Delete website from GateKeeper
     */
    public function deleteGateKeeperWebsite(string $domain): array {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('DELETE', "/website/{$encoded_domain}");
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website deletion failed');
        }
        
        return $result;
    }
    
    /**
     * Get A record IPs for a domain
     */
    public static function getDomainARecords(string $domain): array {
        $default_ip = ['1.23.45.67']; // Fallback IP
        
        try {
            $dns_records = @dns_get_record($domain, DNS_A);
            
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                $ips = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ip']) && !empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                }
                
                if (!empty($ips)) {
                    return $ips;
                }
            }
            
            // Try with www prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_A);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ips = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ip']) && !empty($record['ip'])) {
                            $www_ips[] = $record['ip'];
                        }
                    }
                    
                    if (!empty($www_ips)) {
                        return $www_ips;
                    }
                }
            }
            
            // Fallback to gethostbyname
            $ip = gethostbyname($domain);
            if ($ip && $ip !== $domain) {
                return [$ip];
            }
            
            return $default_ip;
            
        } catch (Exception $e) {
            return $default_ip;
        }
    }
    
    /**
     * Get AAAA record IPs for a domain
     */
    public static function getDomainAAAARecords(string $domain): array {
        $default_ipv6 = ['2a01:4f8:c2c:5a72::1']; // Fallback IPv6
        
        try {
            $dns_records = @dns_get_record($domain, DNS_AAAA);
            
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                $ipv6s = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                        $ipv6s[] = $record['ipv6'];
                    }
                }
                
                if (!empty($ipv6s)) {
                    return $ipv6s;
                }
            }
            
            // Try with www prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_AAAA);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ipv6s = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                            $www_ipv6s[] = $record['ipv6'];
                        }
                    }
                    
                    if (!empty($www_ipv6s)) {
                        return $www_ipv6s;
                    }
                }
            }
            
            return $default_ipv6;
            
        } catch (Exception $e) {
            return $default_ipv6;
        }
    }
    
    /**
     * Check if domain DNS is correctly configured for Blackwall
     */
    public static function checkDomainDnsConfiguration(string $domain): array {
        $required_records = BlackwallConstants::getDnsRecords();
        $result = [
            'status' => false,
            'connected_to' => null,
            'ipv4_status' => false,
            'ipv6_status' => false,
            'ipv4_records' => [],
            'ipv6_records' => [],
            'missing_records' => []
        ];
        
        try {
            // Get current DNS records
            $a_records = @dns_get_record($domain, DNS_A);
            $aaaa_records = @dns_get_record($domain, DNS_AAAA);
            
            if ($a_records) {
                $result['ipv4_records'] = array_column($a_records, 'ip');
            }
            
            if ($aaaa_records) {
                $result['ipv6_records'] = array_column($aaaa_records, 'ipv6');
            }
            
            // Check if domain points to any of our nodes
            $nodes = BlackwallConstants::getGateKeeperNodes();
            
            foreach ($nodes as $node_name => $node_ips) {
                $ipv4_match = in_array($node_ips['ipv4'], $result['ipv4_records']);
                $ipv6_match = in_array($node_ips['ipv6'], $result['ipv6_records']);
                
                if ($ipv4_match || $ipv6_match) {
                    $result['connected_to'] = $node_name;
                    $result['ipv4_status'] = $ipv4_match;
                    $result['ipv6_status'] = $ipv6_match;
                    $result['status'] = true;
                    
                    // Check for missing records
                    if (!$ipv4_match) {
                        $result['missing_records'][] = [
                            'type' => 'A',
                            'value' => $node_ips['ipv4']
                        ];
                    }
                    
                    if (!$ipv6_match) {
                        $result['missing_records'][] = [
                            'type' => 'AAAA',
                            'value' => $node_ips['ipv6']
                        ];
                    }
                    
                    break;
                }
            }
            
            // If not connected to any node, recommend first node
            if (!$result['status']) {
                $first_node = reset($nodes);
                $result['missing_records'] = [
                    [
                        'type' => 'A',
                        'value' => $first_node['ipv4']
                    ],
                    [
                        'type' => 'AAAA',
                        'value' => $first_node['ipv6']
                    ]
                ];
            }
            
        } catch (Exception $e) {
            // Return default missing records on error
            $first_node = reset(BlackwallConstants::getGateKeeperNodes());
            $result['missing_records'] = [
                [
                    'type' => 'A',
                    'value' => $first_node['ipv4']
                ],
                [
                    'type' => 'AAAA',
                    'value' => $first_node['ipv6']
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * Make HTTP request to BotGuard API
     */
    private function doRequest(string $method, string $endpoint, ?array $params = null): mixed {
        $url = $this->botguard_base_url . $endpoint;
        
        // Debug logging
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard API request', [
                'method' => $method,
                'url' => $url,
                'params' => $params
            ]);
        }
        
        // For BotGuard, always return safe results - never throw exceptions
        $result = $this->makeHttpRequest($method, $url, $params, 'BotGuard');
        
        // Debug logging
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard API response', [
                'result' => $result
            ]);
        }
        
        // makeHttpRequest should already return [] for BotGuard on errors
        return $result ?? [];
    }
    
    /**
     * Make HTTP request to GateKeeper API
     */
    private function doGateKeeperRequest(string $method, string $endpoint, ?array $params = null): array {
        $url = $this->gatekeeper_base_url . $endpoint;
        
        // Log the request details
        error_log("GateKeeper API Request: {$method} {$url}");
        if ($params) {
            error_log("GateKeeper API Data: " . json_encode($params));
        }
        
        // GateKeeper uses form-encoded, not JSON!
        $result = $this->makeHttpRequest($method, $url, $params, 'GateKeeper', false);
        
        // Log the response
        error_log("GateKeeper API Response: " . json_encode($result));
        
        return is_array($result) ? $result : [];
    }
    
    /**
     * Make generic HTTP request
     */
    private function makeHttpRequest(string $method, string $url, ?array $params = null, string $api_name = '', bool $use_json = false): mixed {
        $ch = curl_init();
        if (!$ch) {
            if ($api_name === 'BotGuard') {
                error_log("Failed to initialize cURL for BotGuard");
                return [];
            }
            throw new Exception('Failed to initialize cURL');
        }
        
        try {
            // Basic cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'WHMCS-Blackwall-Module/2.0',
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
            ]);
            
            // Set headers
            $headers = [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json'
            ];
            
            if ($use_json) {
                $headers[] = 'Content-Type: application/json';
            } else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Handle request data
            if ($params !== null) {
                if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                    if ($use_json) {
                        $json_data = json_encode($params);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                        
                        // Log the JSON data being sent
                        if ($api_name === 'GateKeeper') {
                            error_log("GateKeeper JSON Data: " . $json_data);
                        }
                    } else {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                    }
                } elseif (strtoupper($method) === 'GET') {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }
            
            // Execute request
            $response = curl_exec($ch);
            
            if ($response === false) {
                $error = curl_error($ch);
                if ($api_name === 'BotGuard') {
                    error_log("BotGuard cURL error: " . $error);
                    return [];
                }
                throw new Exception("cURL error: {$error}");
            }
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Log response for GateKeeper
            if ($api_name === 'GateKeeper') {
                error_log("GateKeeper Response HTTP {$http_code}: " . $response);
            }
            
            // Handle HTTP errors
            if ($http_code >= 400) {
                $error_message = "HTTP {$http_code} error";
                
                $decoded_response = json_decode($response);
                if ($decoded_response && isset($decoded_response->message)) {
                    $error_message .= ": {$decoded_response->message}";
                }
                
                // For GateKeeper, return the decoded response instead of throwing
                if ($api_name === 'GateKeeper' && $decoded_response) {
                    return (array) $decoded_response;
                }
                
                // For BotGuard, log and return empty array instead of throwing
                if ($api_name === 'BotGuard') {
                    error_log("BotGuard HTTP Error {$http_code}: " . $response);
                    return [];
                }
                
                throw new Exception($error_message);
            }
            
            // Decode JSON response
            $decoded_response = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // For non-JSON responses (like 204 No Content), return empty array
                if ($api_name === 'BotGuard') {
                    return [];
                }
                return !empty($response) ? ['raw_response' => $response] : [];
            }
            
            return $decoded_response;
            
        } catch (Exception $e) {
            // For BotGuard API, log and return empty array instead of throwing
            if ($api_name === 'BotGuard') {
                error_log("BotGuard API Exception: " . $e->getMessage());
                return [];
            }
            // Re-throw for other APIs
            throw $e;
        } finally {
            curl_close($ch);
        }
    }
    
    /**
     * Set custom timeout
     */
    public function setTimeout(int $timeout): void {
        if ($timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be greater than 0');
        }
        
        $this->timeout = $timeout;
    }
    
    /**
     * Get current timeout
     */
    public function getTimeout(): int {
        return $this->timeout;
    }
}