<?php
/**
 * BlackwallApi - API client for BotGuard services
 * Clean version without type declarations
 *
 * @author Zencommerce India
 * @version 2.2.1
 */

class BlackwallApi {
    
    private $api_key;
    private $botguard_base_url = 'https://apiv2.botguard.net';
    private $gatekeeper_base_url = 'https://api.blackwall.klikonline.nl:8443/v1.0';
    private $timeout = 15;
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function getDomains() {
        if (empty($this->api_key)) {
            return [];
        }
        
        try {
            $result = $this->doRequest('GET', '/website');
            
            if (!$result) {
                return [];
            }
            
            if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
                error_log("BotGuard API Error: " . ($result->message ?? 'Unknown error'));
                return [];
            }
            
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            error_log("BotGuard getDomains failed: " . $e->getMessage());
            return [];
        }
    }
    
    public function getDomain($domain) {
        if (empty($this->api_key)) {
            return [];
        }
        
        try {
            $domain = trim($domain);
            if (empty($domain)) {
                return [];
            }
            
            $encoded_domain = urlencode($domain);
            $result = $this->doRequest('GET', "/website/{$encoded_domain}");
            
            if (!$result) {
                return [];
            }
            
            if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
                error_log("BotGuard getDomain error: " . ($result->message ?? 'Unknown error'));
                return [];
            }
            
            return (array) $result;
        } catch (Exception $e) {
            error_log("BotGuard getDomain failed: " . $e->getMessage());
            return [];
        }
    }
    
    public function createSubaccount($user_data) {
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
    
    public function getSubaccounts($filter = []) {
        try {
            $result = $this->doRequest('GET', '/user', $filter);
            
            if (!$result) {
                return [];
            }
            
            if (is_object($result) && property_exists($result, 'status') && $result->status === 'error') {
                if (strpos($result->message ?? '', 'not found') !== false || 
                    strpos($result->message ?? '', 'No users') !== false) {
                    return [];
                }
                error_log("BotGuard getSubaccounts error: " . ($result->message ?? 'Unknown error'));
                return [];
            }
            
            return is_array($result) ? $result : [$result];
        } catch (Exception $e) {
            error_log("BotGuard getSubaccounts exception: " . $e->getMessage());
            return [];
        }
    }
    
    public function addDomain($domain, $user_id = null) {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }
        
        $data = ['domain' => $domain];
        
        if ($user_id !== null) {
            $data['user'] = $user_id;
        }
        
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard addDomain API call', [
                'domain' => $domain,
                'user_id' => $user_id,
                'data' => $data
            ]);
        }
        
        $result = $this->doRequest('POST', '/website', $data);
        
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
    
    public function updateDomainStatus($domain, $status) {
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
    
    public function deleteDomain($domain) {
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
    
    public function createGateKeeperUser($user_data) {
        if (empty($user_data['id'])) {
            throw new InvalidArgumentException('User ID is required for GateKeeper user creation');
        }
        
        if (is_numeric($user_data['id'])) {
            $user_data['id'] = (string) $user_data['id'];
        }
        
        if (isset($user_data['tag']) && is_array($user_data['tag'])) {
            $user_data['tag'] = implode(',', $user_data['tag']);
        }
        
        $result = $this->doGateKeeperRequest('POST', '/user', $user_data);
        
        if (isset($result['status'])) {
            if ($result['status'] === 'error') {
                if (strpos($result['message'] ?? '', 'already exists') !== false ||
                    strpos($result['message'] ?? '', 'duplicate') !== false) {
                    return ['status' => 'exists', 'message' => 'User already exists'];
                }
                throw new Exception($result['message'] ?? 'GateKeeper user creation failed');
            }
        }
        
        if (isset($result['code']) && $result['code'] >= 400) {
            if (strpos($result['message'] ?? '', 'already exists') !== false ||
                $result['code'] == 303) {
                return ['status' => 'exists', 'message' => 'User already exists'];
            }
            throw new Exception($result['message'] ?? 'GateKeeper user creation failed');
        }
        
        return $result;
    }
    
    public function createGateKeeperWebsite($website_data) {
        if (empty($website_data['domain'])) {
            throw new InvalidArgumentException('Domain is required for GateKeeper website creation');
        }
        
        if (empty($website_data['user_id'])) {
            throw new InvalidArgumentException('User ID is required for GateKeeper website creation');
        }
        
        if (is_numeric($website_data['user_id'])) {
            $website_data['user_id'] = (string) $website_data['user_id'];
        }
        
        $formatted_data = [
            'domain' => $website_data['domain'],
            'user_id' => $website_data['user_id'],
            'status' => $website_data['status'] ?? 'setup'
        ];
        
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
        
        $result = $this->doGateKeeperRequest('POST', '/website', $formatted_data);
        
        if (isset($result['status'])) {
            if ($result['status'] === 'error') {
                if (strpos($result['message'] ?? '', 'already exists') !== false ||
                    strpos($result['message'] ?? '', 'duplicate') !== false) {
                    return $this->updateGateKeeperWebsite($website_data['domain'], $formatted_data);
                }
                throw new Exception($result['message'] ?? 'GateKeeper website creation failed');
            }
        }
        
        if (isset($result['code']) && $result['code'] >= 400) {
            if (strpos($result['message'] ?? '', 'already exists') !== false ||
                $result['code'] == 303) {
                return $this->updateGateKeeperWebsite($website_data['domain'], $formatted_data);
            }
            throw new Exception($result['message'] ?? 'GateKeeper website creation failed with code: ' . $result['code']);
        }
        
        return $result;
    }
    
    public function getGateKeeperWebsite($domain) {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('GET', "/website/{$encoded_domain}");
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website retrieval failed');
        }
        
        return $result;
    }
    
    public function updateGateKeeperWebsite($domain, $website_data) {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('PUT', "/website/{$encoded_domain}", $website_data);
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website update failed');
        }
        
        return $result;
    }
    
    public function deleteGateKeeperWebsite($domain) {
        $encoded_domain = urlencode($domain);
        $result = $this->doGateKeeperRequest('DELETE', "/website/{$encoded_domain}");
        
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new Exception($result['message'] ?? 'GateKeeper website deletion failed');
        }
        
        return $result;
    }
    
    public static function getDomainARecords($domain) {
        $default_ip = ['1.2.3.4'];
        
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
            
            $ip = gethostbyname($domain);
            if ($ip && $ip !== $domain) {
                return [$ip];
            }
            
            return $default_ip;
            
        } catch (Exception $e) {
            return $default_ip;
        }
    }
    
    public static function getDomainAAAARecords($domain) {
        $default_ipv6 = ['2001:db8::1'];
        
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
    
    public static function checkDomainDnsConfiguration($domain) {
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
            $a_records = @dns_get_record($domain, DNS_A);
            $aaaa_records = @dns_get_record($domain, DNS_AAAA);
            
            if ($a_records) {
                $result['ipv4_records'] = array_column($a_records, 'ip');
            }
            
            if ($aaaa_records) {
                $result['ipv6_records'] = array_column($aaaa_records, 'ipv6');
            }
            
            $nodes = BlackwallConstants::getGateKeeperNodes();
            
            foreach ($nodes as $node_name => $node_ips) {
                $ipv4_match = in_array($node_ips['ipv4'], $result['ipv4_records']);
                $ipv6_match = in_array($node_ips['ipv6'], $result['ipv6_records']);
                
                if ($ipv4_match || $ipv6_match) {
                    $result['connected_to'] = $node_name;
                    $result['ipv4_status'] = $ipv4_match;
                    $result['ipv6_status'] = $ipv6_match;
                    $result['status'] = true;
                    
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
    
    private function doRequest($method, $endpoint, $params = null) {
        if (empty($this->api_key)) {
            return [];
        }
        
        $url = $this->botguard_base_url . $endpoint;
        
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard API request', [
                'method' => $method,
                'url' => $url,
                'has_params' => !empty($params)
            ]);
        }
        
        try {
            $result = $this->makeHttpRequest($method, $url, $params, 'BotGuard', true);
            
            if (class_exists('BlackwallDebugLogger')) {
                BlackwallDebugLogger::debug('BotGuard API response', [
                    'success' => !empty($result)
                ]);
            }
            
            return $result ?? [];
        } catch (Exception $e) {
            error_log("BotGuard API error: " . $e->getMessage());
            return [];
        }
    }
    
    private function doGateKeeperRequest($method, $endpoint, $params = null) {
        $url = $this->gatekeeper_base_url . $endpoint;
        
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('GateKeeper API request', [
                'method' => $method,
                'url' => $url,
                'has_params' => !empty($params)
            ]);
        }
        
        try {
            $result = $this->makeHttpRequest($method, $url, $params, 'GateKeeper', false);
            
            if (class_exists('BlackwallDebugLogger')) {
                BlackwallDebugLogger::debug('GateKeeper API response', [
                    'success' => !empty($result)
                ]);
            }
            
            return is_array($result) ? $result : [];
        } catch (Exception $e) {
            error_log("GateKeeper API error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function makeHttpRequest($method, $url, $params = null, $api_name = '', $use_json = true) {
        $ch = curl_init();
        if (!$ch) {
            if ($api_name === 'BotGuard') {
                error_log("Failed to initialize cURL for BotGuard");
                return [];
            }
            throw new Exception('Failed to initialize cURL');
        }
        
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'WHMCS-Blackwall-Module/2.2-Clean',
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_FAILONERROR => false,
            ]);
            
            $headers = [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json'
            ];
            
            if ($api_name === 'BotGuard' || !$use_json) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                $headers[] = 'Content-Type: application/json';
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            if ($params !== null) {
                if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                    if ($use_json && $api_name !== 'BotGuard') {
                        $json_data = json_encode($params);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                        
                        if (class_exists('BlackwallDebugLogger')) {
                            BlackwallDebugLogger::debug("{$api_name} JSON Data", ['data' => $json_data]);
                        }
                    } else {
                        $form_data = http_build_query($params);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
                        
                        if (class_exists('BlackwallDebugLogger')) {
                            BlackwallDebugLogger::debug("{$api_name} Form Data", ['data' => $form_data]);
                        }
                    }
                } elseif (strtoupper($method) === 'GET') {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }
            
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
            
            if (class_exists('BlackwallDebugLogger')) {
                BlackwallDebugLogger::debug("{$api_name} Response", [
                    'http_code' => $http_code,
                    'response_length' => strlen($response),
                    'response_preview' => substr($response, 0, 200)
                ]);
            }
            
            if ($http_code >= 400) {
                $error_message = "HTTP {$http_code} error";
                
                $decoded_response = json_decode($response, true);
                if ($decoded_response && isset($decoded_response['message'])) {
                    $error_message .= ": {$decoded_response['message']}";
                }
                
                if ($api_name === 'GateKeeper' && $decoded_response) {
                    return $decoded_response;
                }
                
                if ($api_name === 'BotGuard') {
                    error_log("BotGuard HTTP Error {$http_code}: " . $response);
                    return [];
                }
                
                throw new Exception($error_message);
            }
            
            $decoded_response = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($api_name === 'BotGuard') {
                    return [];
                }
                return !empty($response) ? ['raw_response' => $response] : [];
            }
            
            return $decoded_response ?? [];
            
        } catch (Exception $e) {
            if ($api_name === 'BotGuard') {
                error_log("BotGuard API Exception: " . $e->getMessage());
                return [];
            }
            throw $e;
        } finally {
            curl_close($ch);
        }
    }
    
    public function setTimeout($timeout) {
        if ($timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be greater than 0');
        }
        
        $this->timeout = $timeout;
    }
    
    public function getTimeout() {
        return $this->timeout;
    }
    
    public function hasApiKey() {
        return !empty($this->api_key);
    }
}