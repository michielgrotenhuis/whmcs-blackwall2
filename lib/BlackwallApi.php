<?php
/**
 * BlackwallApi - API client for BotGuard services (Simplified Mode)
 *
 * @author Zencommerce India
 * @version 2.1.0
 */

declare(strict_types=1);

class BlackwallApi {
    
    private string $api_key;
    private string $botguard_base_url = 'https://apiv2.botguard.net';
    private int $timeout = 15; // Reduced timeout
    
    /**
     * Constructor
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key; // Allow empty API key
    }
    
    /**
     * Get all domains from BotGuard (SAFE MODE)
     */
    public function getDomains(): array {
        if (empty($this->api_key)) {
            return []; // Return empty array if no API key
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
    
    /**
     * Get domain information from BotGuard (SAFE MODE)
     */
    public function getDomain(string $domain): array {
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
    
    /**
     * Get A record IPs for a domain
     */
    public static function getDomainARecords(string $domain): array {
        $default_ip = ['1.2.3.4']; // Generic fallback IP
        
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
        $default_ipv6 = ['2001:db8::1']; // Generic fallback IPv6
        
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
     * Make HTTP request to BotGuard API (SAFE MODE)
     */
    private function doRequest(string $method, string $endpoint, ?array $params = null): mixed {
        if (empty($this->api_key)) {
            return []; // Return empty array if no API key
        }
        
        $url = $this->botguard_base_url . $endpoint;
        
        // Debug logging
        if (class_exists('BlackwallDebugLogger')) {
            BlackwallDebugLogger::debug('BotGuard API request (safe mode)', [
                'method' => $method,
                'url' => $url,
                'has_params' => !empty($params)
            ]);
        }
        
        try {
            $result = $this->makeHttpRequest($method, $url, $params);
            
            // Debug logging
            if (class_exists('BlackwallDebugLogger')) {
                BlackwallDebugLogger::debug('BotGuard API response (safe mode)', [
                    'success' => !empty($result)
                ]);
            }
            
            return $result ?? [];
        } catch (Exception $e) {
            error_log("BotGuard API error (safe mode): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Make generic HTTP request (SAFE MODE)
     */
    private function makeHttpRequest(string $method, string $url, ?array $params = null): mixed {
        $ch = curl_init();
        if (!$ch) {
            throw new Exception('Failed to initialize cURL');
        }
        
        try {
            // Basic cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5, // Quick connection timeout
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'WHMCS-Blackwall-Module/2.1-Simplified',
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_FAILONERROR => false, // Don't fail on HTTP errors
            ]);
            
            // Set headers
            $headers = [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json',
                'Content-Type: application/json'
            ];
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Handle request data
            if ($params !== null) {
                if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                    $json_data = json_encode($params);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                } elseif (strtoupper($method) === 'GET') {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }
            
            // Execute request
            $response = curl_exec($ch);
            
            if ($response === false) {
                $error = curl_error($ch);
                error_log("BotGuard cURL error (safe mode): " . $error);
                return [];
            }
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Handle HTTP errors gracefully
            if ($http_code >= 400) {
                error_log("BotGuard HTTP {$http_code} error (safe mode): " . $response);
                return [];
            }
            
            // Decode JSON response
            $decoded_response = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // For non-JSON responses, return empty array
                return [];
            }
            
            return $decoded_response ?? [];
            
        } catch (Exception $e) {
            error_log("BotGuard API Exception (safe mode): " . $e->getMessage());
            return [];
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
    
    /**
     * Check if API key is configured
     */
    public function hasApiKey(): bool {
        return !empty($this->api_key);
    }
}
