<?php
/**
 * BlackwallConstants - Constants and configuration for Blackwall module
 *
 * @author Zencommerce India
 * @version 2.0.0
 */

class BlackwallConstants {
    
    // Status constants
    const STATUS_ONLINE = 'online';
    const STATUS_PAUSED = 'paused';
    const STATUS_SETUP = 'setup';
    
    // GateKeeper node IPs
    const GATEKEEPER_NODE_1_IPV4 = '49.13.161.213';
    const GATEKEEPER_NODE_1_IPV6 = '2a01:4f8:c2c:5a72::1';
    const GATEKEEPER_NODE_2_IPV4 = '116.203.242.28';
    const GATEKEEPER_NODE_2_IPV6 = '2a01:4f8:1c1b:7008::1';
    
    /**
     * Get the required DNS records for protection
     */
    public static function getDnsRecords(): array {
        return [
            'A' => [self::GATEKEEPER_NODE_1_IPV4, self::GATEKEEPER_NODE_2_IPV4],
            'AAAA' => [self::GATEKEEPER_NODE_1_IPV6, self::GATEKEEPER_NODE_2_IPV6]
        ];
    }
    
    /**
     * Get GateKeeper nodes information
     */
    public static function getGateKeeperNodes(): array {
        return [
            'bg-gk-01' => [
                'ipv4' => self::GATEKEEPER_NODE_1_IPV4,
                'ipv6' => self::GATEKEEPER_NODE_1_IPV6,
                'name' => 'BotGuard GateKeeper Node 1',
                'location' => 'Germany (Nuremberg)'
            ],
            'bg-gk-02' => [
                'ipv4' => self::GATEKEEPER_NODE_2_IPV4,
                'ipv6' => self::GATEKEEPER_NODE_2_IPV6,
                'name' => 'BotGuard GateKeeper Node 2',
                'location' => 'Germany (Falkenstein)'
            ]
        ];
    }
    
    /**
     * Get default website settings for GateKeeper
     */
    public static function getDefaultWebsiteSettings(): array {
        return [
            'rulesets' => [
                'wordpress' => false,
                'joomla' => false,
                'drupal' => false,
                'cpanel' => false,
                'bitrix' => false,
                'dokuwiki' => false,
                'xenforo' => false,
                'nextcloud' => false,
                'prestashop' => false
            ],
            'rules' => [
                'search_engines' => 'grant',
                'social_networks' => 'grant',
                'services_and_payments' => 'grant',
                'humans' => 'grant',
                'security_issues' => 'deny',
                'content_scrapers' => 'deny',
                'emulated_humans' => 'captcha',
                'suspicious_behaviour' => 'captcha'
            ],
            'loadbalancer' => [
                'upstreams_use_https' => true,
                'enable_http3' => true,
                'force_https' => true,
                'cache_static_files' => true,
                'cache_dynamic_pages' => false,
                'ddos_protection' => false,
                'ddos_protection_advanced' => false,
                'botguard_protection' => true,
                'certs_issuer' => 'letsencrypt',
                'force_subdomains_redirect' => false
            ]
        ];
    }
    
    /**
     * Get protection features list
     */
    public static function getProtectionFeatures(): array {
        return [
            'real_time_protection' => [
                'name' => 'Real-time Bot Detection',
                'description' => 'Advanced algorithms detect and block malicious bots in real-time'
            ],
            'ddos_protection' => [
                'name' => 'DDoS Protection',
                'description' => 'Protection against distributed denial-of-service attacks'
            ],
            'content_scraping' => [
                'name' => 'Content Scraping Prevention',
                'description' => 'Prevents unauthorized scraping of your website content'
            ],
            'search_engine_friendly' => [
                'name' => 'Search Engine Friendly',
                'description' => 'Allows legitimate search engine crawlers while blocking malicious bots'
            ],
            'performance_optimization' => [
                'name' => 'Performance Optimization',
                'description' => 'Reduces server load by filtering out unwanted traffic'
            ],
            'detailed_analytics' => [
                'name' => 'Detailed Analytics',
                'description' => 'Comprehensive reports on blocked threats and traffic patterns'
            ]
        ];
    }
    
    /**
     * Get supported platforms/CMS rulesets
     */
    public static function getSupportedPlatforms(): array {
        return [
            'wordpress' => [
                'name' => 'WordPress',
                'description' => 'Optimized protection for WordPress websites'
            ],
            'joomla' => [
                'name' => 'Joomla',
                'description' => 'Specialized rules for Joomla CMS'
            ],
            'drupal' => [
                'name' => 'Drupal',
                'description' => 'Drupal-specific protection patterns'
            ],
            'cpanel' => [
                'name' => 'cPanel',
                'description' => 'Protection for cPanel interfaces'
            ],
            'bitrix' => [
                'name' => 'Bitrix',
                'description' => 'Bitrix CMS protection rules'
            ],
            'dokuwiki' => [
                'name' => 'DokuWiki',
                'description' => 'Wiki platform protection'
            ],
            'xenforo' => [
                'name' => 'XenForo',
                'description' => 'Forum software protection'
            ],
            'nextcloud' => [
                'name' => 'Nextcloud',
                'description' => 'Cloud storage platform protection'
            ],
            'prestashop' => [
                'name' => 'PrestaShop',
                'description' => 'E-commerce platform protection'
            ]
        ];
    }
    
    /**
     * Get protection rule categories
     */
    public static function getProtectionRules(): array {
        return [
            'search_engines' => [
                'name' => 'Search Engines',
                'description' => 'Legitimate search engine crawlers (Google, Bing, etc.)',
                'default' => 'grant'
            ],
            'social_networks' => [
                'name' => 'Social Networks',
                'description' => 'Social media platform crawlers (Facebook, Twitter, etc.)',
                'default' => 'grant'
            ],
            'services_and_payments' => [
                'name' => 'Services & Payments',
                'description' => 'Payment processors and legitimate service bots',
                'default' => 'grant'
            ],
            'humans' => [
                'name' => 'Human Visitors',
                'description' => 'Real human users accessing your website',
                'default' => 'grant'
            ],
            'security_issues' => [
                'name' => 'Security Threats',
                'description' => 'Known malicious bots and security threats',
                'default' => 'deny'
            ],
            'content_scrapers' => [
                'name' => 'Content Scrapers',
                'description' => 'Bots attempting to scrape website content',
                'default' => 'deny'
            ],
            'emulated_humans' => [
                'name' => 'Emulated Humans',
                'description' => 'Bots attempting to mimic human behavior',
                'default' => 'captcha'
            ],
            'suspicious_behaviour' => [
                'name' => 'Suspicious Behavior',
                'description' => 'Traffic with suspicious patterns or characteristics',
                'default' => 'captcha'
            ]
        ];
    }
    
    /**
     * Get language mappings for BotGuard API
     */
    public static function getLanguageMappings(): array {
        return [
            'english' => 'en',
            'russian' => 'ru',
            'ukrainian' => 'ru',
            'azerbaijani' => 'ru',
            'german' => 'en', // Fallback to English for unsupported languages
            'french' => 'en',
            'spanish' => 'en',
            'italian' => 'en',
            'portuguese' => 'en',
            'dutch' => 'en',
            'polish' => 'en',
            'turkish' => 'en',
            'chinese' => 'en',
            'japanese' => 'en',
            'korean' => 'en',
            'arabic' => 'en',
        ];
    }
    
    /**
     * Get BotGuard API endpoints
     */
    public static function getApiEndpoints(): array {
        return [
            'botguard' => [
                'base_url' => 'https://apiv2.botguard.net',
                'endpoints' => [
                    'domains' => '/website',
                    'users' => '/user',
                    'statistics' => '/stats/usage'
                ]
            ],
            'gatekeeper' => [
                'base_url' => 'https://api.blackwall.klikonline.nl:8443/v1.0',
                'endpoints' => [
                    'websites' => '/website',
                    'users' => '/user'
                ]
            ]
        ];
    }
    
    /**
     * Get SSL certificate issuers
     */
    public static function getSslIssuers(): array {
        return [
            'letsencrypt' => [
                'name' => 'Let\'s Encrypt',
                'description' => 'Free SSL certificates from Let\'s Encrypt'
            ],
            'custom' => [
                'name' => 'Custom Certificate',
                'description' => 'Use your own SSL certificate'
            ]
        ];
    }
    
    /**
     * Get default timeout values
     */
    public static function getTimeouts(): array {
        return [
            'api_request' => 30,
            'dns_lookup' => 10,
            'ssl_verification' => 15
        ];
    }
    
    /**
     * Get module version information
     */
    public static function getModuleInfo(): array {
        return [
            'name' => 'Blackwall (BotGuard) Website Protection',
            'version' => '2.0.0',
            'author' => 'Zencommerce India',
            'description' => 'Advanced website protection against bots and malicious traffic',
            'min_whmcs_version' => '8.0.0',
            'min_php_version' => '7.4.0',
            'supported_whmcs_versions' => ['8.0', '8.1', '8.2', '8.3', '8.4', '8.5', '8.6', '8.7', '8.8', '8.9', '8.10', '8.11', '8.12', '8.13']
        ];
    }
}