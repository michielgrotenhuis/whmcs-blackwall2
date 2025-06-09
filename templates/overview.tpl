{* Blackwall WHMCS Module - Client Area Overview Template (Simplified Mode) *}
{* Compatible with WHMCS 8.13 and modern themes *}

<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/servers/blackwall/assets/css/blackwall.css">

<div class="blackwall-client-area">
    {* Service Status Header *}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Blackwall Website Protection - {$domain}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Protected Domain:</strong> <a href="http://{$domain}" target="_blank" rel="noopener">{$domain}</a></p>
                            <p><strong>Service Status:</strong> 
                                <span class="badge badge-{if $service_status == 'Active'}success{else}warning{/if}">
                                    {$service_status}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Primary Server:</strong> {$primary_server}</p>
                            <p><strong>Secondary Server:</strong> {$secondary_server}</p>
                        </div>
                    </div>
                    
                    {if $simplified_mode}
                        <div class="alert alert-info mt-3" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Information Mode:</strong> This service provides DNS configuration instructions and protection guidance. 
                            {if !$api_key}No API integration is configured.{/if}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* DNS Configuration Status *}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-globe me-2"></i>
                        DNS Configuration Status
                    </h6>
                </div>
                <div class="card-body">
                    {if $dns_status.status}
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>DNS Correctly Configured!</strong>
                            <p class="mb-0">Your domain is connected to node <strong>{$dns_status.connected_to}</strong> and is protected by Blackwall.</p>
                            
                            {if !empty($dns_status.missing_records)}
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Optional:</strong> For complete protection, consider adding these missing records:
                                        {foreach $dns_status.missing_records as $record}
                                            <br>• {$record.type} record: <code>{$record.value}</code>
                                        {/foreach}
                                    </small>
                                </div>
                            {/if}
                        </div>
                    {else}
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>DNS Configuration Required</strong>
                            <p>Your domain is not correctly configured for Blackwall protection. Please see the Setup Instructions tab for details.</p>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* Protection Features *}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Protection Features
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Real-time Bot Detection</li>
                                <li><i class="fas fa-check text-success me-2"></i> DDoS Protection</li>
                                <li><i class="fas fa-check text-success me-2"></i> Content Scraping Prevention</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Search Engine Friendly</li>
                                <li><i class="fas fa-check text-success me-2"></i> Performance Optimization</li>
                                <li><i class="fas fa-check text-success me-2"></i> Detailed Analytics</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Management Tabs *}
    {if $service_status == 'Active'}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="blackwall-tabs" role="tablist">
                            {if $api_key}
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="statistics-tab" data-bs-toggle="tab" data-bs-target="#statistics" type="button" role="tab">
                                        <i class="fas fa-chart-bar me-2"></i>Statistics
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                                        <i class="fas fa-list me-2"></i>Events Log
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </button>
                                </li>
                            {/if}
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {if !$api_key}active{/if}" id="setup-tab" data-bs-toggle="tab" data-bs-target="#setup" type="button" role="tab">
                                    <i class="fas fa-wrench me-2"></i>Setup Instructions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="help-tab" data-bs-toggle="tab" data-bs-target="#help" type="button" role="tab">
                                    <i class="fas fa-question-circle me-2"></i>Help
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="blackwall-tab-content">
                            {* Statistics Tab (only if API key available) *}
                            {if $api_key}
                                <div class="tab-pane fade show active" id="statistics" role="tabpanel">
                                    <div class="iframe-container">
                                        <div class="iframe-header">
                                            <span>Statistics Dashboard</span>
                                            <a href="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/statistics?api-key={$api_key|escape:'url'}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-external-link-alt"></i> Open in New Window
                                            </a>
                                        </div>
                                        <iframe id="statistics-iframe" 
                                                src="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/statistics?api-key={$api_key|escape:'url'}" 
                                                frameborder="0"
                                                onload="handleIframeLoad('statistics-iframe')"
                                                onerror="handleIframeError('statistics-iframe')"
                                                style="display: none;"></iframe>
                                        <div id="statistics-fallback" class="p-4 text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                            <p class="mt-2">Loading statistics dashboard...</p>
                                            <small class="text-muted">If this takes too long, <a href="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/statistics?api-key={$api_key|escape:'url'}" target="_blank">click here to open in new window</a></small>
                                        </div>
                                    </div>
                                </div>

                                {* Events Tab *}
                                <div class="tab-pane fade" id="events" role="tabpanel">
                                    <div class="iframe-container">
                                        <div class="iframe-header">
                                            <span>Protection Events Log</span>
                                            <a href="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/events?api-key={$api_key|escape:'url'}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-external-link-alt"></i> Open in New Window
                                            </a>
                                        </div>
                                        <iframe id="events-iframe" 
                                                src="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/events?api-key={$api_key|escape:'url'}" 
                                                frameborder="0"
                                                onload="handleIframeLoad('events-iframe')"
                                                onerror="handleIframeError('events-iframe')"></iframe>
                                    </div>
                                </div>

                                {* Settings Tab *}
                                <div class="tab-pane fade" id="settings" role="tabpanel">
                                    <div class="iframe-container">
                                        <div class="iframe-header">
                                            <span>Protection Settings</span>
                                            <a href="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/edit?api-key={$api_key|escape:'url'}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-external-link-alt"></i> Open in New Window
                                            </a>
                                        </div>
                                        <iframe id="settings-iframe" 
                                                src="https://apiv2.botguard.net/en/website/{$domain|escape:'url'}/edit?api-key={$api_key|escape:'url'}" 
                                                frameborder="0"
                                                onload="handleIframeLoad('settings-iframe')"
                                                onerror="handleIframeError('settings-iframe')"></iframe>
                                    </div>
                                </div>
                            {/if}

                            {* Setup Tab *}
                            <div class="tab-pane fade {if !$api_key}show active{/if}" id="setup" role="tabpanel">
                                <div class="p-4">
                                    {if $dns_status && $dns_status.status}
                                        {* DNS is configured correctly *}
                                        <div class="alert alert-success mb-4">
                                            <h5><i class="fas fa-check-circle me-2"></i>DNS Configuration Complete!</h5>
                                            <p class="mb-0">Your domain <strong>{$domain}</strong> is properly configured and protected by Blackwall{if $dns_status.connected_to} via node <strong>{$dns_status.connected_to}</strong>{/if}.</p>
                                        </div>

                                        <h6>What's Next?</h6>
                                        <ul>
                                            {if $api_key}
                                                <li>View real-time protection statistics in the <strong>Statistics</strong> tab</li>
                                                <li>Monitor blocked threats in the <strong>Events Log</strong> tab</li>
                                                <li>Customize protection rules in the <strong>Settings</strong> tab</li>
                                            {else}
                                                <li>Your DNS is correctly pointed to Blackwall protection servers</li>
                                                <li>All traffic to your domain is now being filtered for malicious bots</li>
                                                <li>Contact support if you need access to detailed statistics and settings</li>
                                            {/if}
                                        </ul>

                                        {if $dns_status.missing_records && !empty($dns_status.missing_records)}
                                            <div class="alert alert-info mt-3">
                                                <h6><i class="fas fa-info-circle me-2"></i>Optional: Complete Protection Setup</h6>
                                                <p>For comprehensive IPv4 and IPv6 protection, consider adding these missing DNS records:</p>
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Record Type</th>
                                                                <th>Value</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {foreach $dns_status.missing_records as $index => $record}
                                                                <tr>
                                                                    <td><span class="badge badge-secondary">{$record.type}</span></td>
                                                                    <td><code id="missing-record-{$index}">{$record.value}</code></td>
                                                                    <td>
                                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('missing-record-{$index}')">
                                                                            <i class="fas fa-copy"></i> Copy
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            {/foreach}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        {/if}
                                    {else}
                                        {* DNS needs configuration *}
                                        <div class="alert alert-warning mb-4">
                                            <h5><i class="fas fa-exclamation-triangle me-2"></i>DNS Configuration Required</h5>
                                            <p class="mb-0">Your domain <strong>{$domain}</strong> is not correctly pointed to Blackwall protection servers. Please follow the instructions below.</p>
                                        </div>

                                        <h6>Required DNS Records</h6>
                                        <p>Add the following DNS records to your domain's DNS configuration:</p>

                                        {assign var="required_records" value=[
                                            ['type' => 'A', 'value' => '49.13.161.213'],
                                            ['type' => 'AAAA', 'value' => '2a01:4f8:c2c:5a72::1']
                                        ]}

                                        {if $dns_status && $dns_status.missing_records && !empty($dns_status.missing_records)}
                                            {assign var="required_records" value=$dns_status.missing_records}
                                        {/if}

                                        <div class="table-responsive mb-4">
                                            <table class="table table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Record Type</th>
                                                        <th>Name</th>
                                                        <th>Value</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {foreach $required_records as $index => $record}
                                                        <tr>
                                                            <td><span class="badge badge-primary">{$record.type}</span></td>
                                                            <td><code>@</code> or <code>{$domain}</code></td>
                                                            <td><code id="dns-record-{$index}">{$record.value}</code></td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('dns-record-{$index}')">
                                                                    <i class="fas fa-copy"></i> Copy
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    {/foreach}
                                                </tbody>
                                            </table>
                                        </div>

                                        <h6>Available Protection Nodes</h6>
                                        <p>You can use any of the following nodes for protection. Use the same node for both A and AAAA records:</p>

                                        <div class="table-responsive mb-4">
                                            <table class="table table-striped">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Node</th>
                                                        <th>Location</th>
                                                        <th>IPv4 (A Record)</th>
                                                        <th>IPv6 (AAAA Record)</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {if $gatekeeper_nodes && !empty($gatekeeper_nodes)}
                                                        {foreach $gatekeeper_nodes as $node_id => $node}
                                                            <tr>
                                                                <td><strong>{$node_id}</strong></td>
                                                                <td>{$node.location|default:'Germany'}</td>
                                                                <td><code id="node-ipv4-{$node_id}">{$node.ipv4}</code></td>
                                                                <td><code id="node-ipv6-{$node_id}">{$node.ipv6}</code></td>
                                                                <td>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary me-1" onclick="copyToClipboard('node-ipv4-{$node_id}')" title="Copy IPv4">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="copyToClipboard('node-ipv6-{$node_id}')" title="Copy IPv6">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        {/foreach}
                                                    {else}
                                                        <tr>
                                                            <td><strong>bg-gk-01</strong></td>
                                                            <td>Germany (Nuremberg)</td>
                                                            <td><code id="node-ipv4-1">49.13.161.213</code></td>
                                                            <td><code id="node-ipv6-1">2a01:4f8:c2c:5a72::1</code></td>
                                                            <td>
                                                                <button type="button" class="btn btn-xs btn-outline-secondary me-1" onclick="copyToClipboard('node-ipv4-1')" title="Copy IPv4">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="copyToClipboard('node-ipv6-1')" title="Copy IPv6">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>bg-gk-02</strong></td>
                                                            <td>Germany (Falkenstein)</td>
                                                            <td><code id="node-ipv4-2">116.203.242.28</code></td>
                                                            <td><code id="node-ipv6-2">2a01:4f8:1c1b:7008::1</code></td>
                                                            <td>
                                                                <button type="button" class="btn btn-xs btn-outline-secondary me-1" onclick="copyToClipboard('node-ipv4-2')" title="Copy IPv4">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="copyToClipboard('node-ipv6-2')" title="Copy IPv6">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    {/if}
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle me-2"></i>Important Notes</h6>
                                            <ul class="mb-0">
                                                <li>DNS changes can take up to 24-48 hours to propagate worldwide</li>
                                                <li>Use the same node for both IPv4 and IPv6 records for optimal performance</li>
                                                <li>For subdomains (like www), create a CNAME record pointing to your main domain</li>
                                                <li>Contact support if you need assistance with DNS configuration</li>
                                            </ul>
                                        </div>

                                        <h6>How to Update DNS Records</h6>
                                        <ol>
                                            <li>Log in to your domain registrar or DNS provider's control panel</li>
                                            <li>Navigate to the DNS management section</li>
                                            <li>Add or modify the A and AAAA records with the values above</li>
                                            <li>Save your changes and wait for DNS propagation</li>
                                            <li>Return to this page to verify the configuration</li>
                                        </ol>

                                        <div class="mt-4">
                                            <button type="button" class="btn btn-primary" onclick="location.reload()">
                                                <i class="fas fa-sync-alt me-2"></i>
                                                Check DNS Configuration Again
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            {* Help Tab *}
                            <div class="tab-pane fade" id="help" role="tabpanel">
                                <div class="p-4">
                                    <h5><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
                                    
                                    <div class="accordion" id="helpAccordion">
                                        <div class="card">
                                            <div class="card-header" id="faq1">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                                        What is Blackwall Website Protection?
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapse1" class="collapse" data-bs-parent="#helpAccordion">
                                                <div class="card-body">
                                                    Blackwall is an advanced website protection service that shields your website from malicious bots, scrapers, and automated attacks while allowing legitimate traffic including search engines to access your site normally.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="faq2">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                                        How long does DNS propagation take?
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapse2" class="collapse" data-bs-parent="#helpAccordion">
                                                <div class="card-body">
                                                    DNS changes typically propagate within 1-2 hours, but can take up to 24-48 hours to fully propagate worldwide. You can check the current status on this page.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="faq3">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                                        Will this affect my website's SEO?
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapse3" class="collapse" data-bs-parent="#helpAccordion">
                                                <div class="card-body">
                                                    No, Blackwall is designed to be search engine friendly. Legitimate search engine crawlers from Google, Bing, and other major search engines are automatically allowed through while malicious bots are blocked.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="faq4">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                                        What types of threats does Blackwall block?
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapse4" class="collapse" data-bs-parent="#helpAccordion">
                                                <div class="card-body">
                                                    Blackwall blocks malicious bots, content scrapers, vulnerability scanners, DDoS attacks, spam bots, and other automated threats while allowing legitimate traffic including real users and search engines.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="faq5">
                                                <h6 class="mb-0">
                                                    <button class="btn btn-link collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                                        How do I know if the protection is working?
                                                    </button>
                                                </h6>
                                            </div>
                                            <div id="collapse5" class="collapse" data-bs-parent="#helpAccordion">
                                                <div class="card-body">
                                                    Once your DNS is correctly configured (as shown in the DNS Configuration Status section), your website traffic will be filtered through Blackwall's protection servers. {if $api_key}You can view detailed statistics and blocked threats in the Statistics and Events tabs.{else}Contact support for access to detailed analytics.{/if}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h6>Need More Help?</h6>
                                        <p>If you need additional assistance with your Blackwall protection service:</p>
                                        <ul>
                                            <li><a href="/submitticket.php" class="text-primary">Submit a support ticket</a> for technical assistance</li>
                                            <li>Check the DNS configuration status above to ensure proper setup</li>
                                            <li>Allow 24-48 hours for DNS changes to fully propagate</li>
                                            {if $api_key}
                                                <li>Visit <a href="https://botguard.net/en/docs" target="_blank" rel="noopener">BotGuard Documentation</a> for advanced configuration</li>
                                            {/if}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {else}
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Service Inactive</strong>
                    <p class="mb-0">Your Blackwall service is currently {$service_status|lower}. Protection features are not available at this time.</p>
                </div>
            </div>
        </div>
    {/if}
</div>

{* Copy notification *}
<div id="copy-notification" class="copy-notification" style="display: none;">
    <i class="fas fa-check me-2"></i>Copied to clipboard!
</div>

<script src="{$WEB_ROOT}/modules/servers/blackwall/assets/js/blackwall.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        var triggerTabList = [].slice.call(document.querySelectorAll('#blackwall-tabs button'))
        triggerTabList.forEach(function (triggerEl) {
            new bootstrap.Tab(triggerEl)
        })
    }
    
    // Fallback for older Bootstrap versions
    if (typeof jQuery !== 'undefined' && jQuery.fn.tab) {
        jQuery('#blackwall-tabs button').on('click', function (e) {
            e.preventDefault()
            jQuery(this).tab('show')
        })
    }
    
    // Initialize Bootstrap accordion if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
        var collapseElementList = [].slice.call(document.querySelectorAll('.collapse'))
        var collapseList = collapseElementList.map(function (collapseEl) {
            return new bootstrap.Collapse(collapseEl, {
                toggle: false
            })
        })
    }
});

// Handle iframe load success
function handleIframeLoad(iframeId) {
    var iframe = document.getElementById(iframeId);
    if (iframe) {
        var container = iframe.closest('.iframe-container');
        var fallback = document.getElementById(iframeId.replace('-iframe', '-fallback'));
        
        if (container) {
            container.classList.add('loaded');
            container.classList.remove('error');
        }
        
        // Show iframe and hide fallback
        iframe.style.display = 'block';
        if (fallback) {
            fallback.style.display = 'none';
        }
        
        console.log('Iframe loaded successfully:', iframeId);
    }
}

// Handle iframe load error
function handleIframeError(iframeId) {
    var iframe = document.getElementById(iframeId);
    if (iframe) {
        var container = iframe.closest('.iframe-container');
        var fallback = document.getElementById(iframeId.replace('-iframe', '-fallback'));
        
        if (container) {
            container.classList.add('error');
            container.classList.remove('loaded');
        }
        
        // Hide iframe and show error in fallback
        iframe.style.display = 'none';
        if (fallback) {
            fallback.innerHTML = '<div class="p-4 text-center text-danger"><i class="fas fa-exclamation-triangle fa-3x mb-3"></i><p>Failed to load interface</p><small>Please <a href="' + iframe.src + '" target="_blank">open in new window</a></small></div>';
            fallback.style.display = 'block';
        }
        
        console.error('Iframe failed to load:', iframeId);
    }
}

// Set timeout for iframe loading
document.addEventListener('DOMContentLoaded', function() {
    var iframes = document.querySelectorAll('.iframe-container iframe');
    iframes.forEach(function(iframe) {
        setTimeout(function() {
            if (iframe.style.display === 'none') {
                console.warn('Iframe load timeout, showing iframe anyway:', iframe.id);
                handleIframeLoad(iframe.id);
            }
        }, 10000); // 10 second timeout
    });
});

// Copy to clipboard function
function copyToClipboard(elementId) {
    var element = document.getElementById(elementId);
    if (!element) return;
    
    var text = element.textContent || element.innerText;
    
    if (navigator.clipboard && window.isSecureContext) {
        // Use modern clipboard API
        navigator.clipboard.writeText(text).then(function() {
            showCopyNotification();
            highlightElement(element);
        }).catch(function(err) {
            console.error('Failed to copy to clipboard:', err);
            fallbackCopyToClipboard(text);
            showCopyNotification();
            highlightElement(element);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyToClipboard(text);
        showCopyNotification();
        highlightElement(element);
    }
}

function fallbackCopyToClipboard(text) {
    var tempInput = document.createElement("input");
    tempInput.value = text;
    tempInput.style.position = "absolute";
    tempInput.style.left = "-9999px";
    document.body.appendChild(tempInput);
    tempInput.select();
    
    try {
        document.execCommand("copy");
    } catch (err) {
        console.error('Fallback copy failed:', err);
    }
    
    document.body.removeChild(tempInput);
}

function showCopyNotification() {
    var notification = document.getElementById("copy-notification");
    if (notification) {
        notification.style.display = "block";
        setTimeout(function() {
            notification.style.display = "none";
        }, 2000);
    }
}

function highlightElement(element) {
    var originalBg = element.style.backgroundColor;
    element.style.backgroundColor = "#e6ffe6";
    setTimeout(function() {
        element.style.backgroundColor = originalBg;
    }, 1000);
}
</script>
