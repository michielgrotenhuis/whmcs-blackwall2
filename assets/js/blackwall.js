/**
 * Blackwall WHMCS Module JavaScript
 * Version 2.0.0
 */

(function() {
    'use strict';
    
    // Global configuration
    const BlackwallClient = {
        config: {
            refreshInterval: 300000, // 5 minutes
            copyNotificationDuration: 2000, // 2 seconds
            highlightDuration: 1000, // 1 second
        },
        
        timers: {},
        
        /**
         * Initialize the Blackwall client interface
         */
        init: function() {
            this.setupIframeLoading();
            this.setupAutoRefresh();
            this.setupTabHandlers();
            this.setupCopyHandlers();
            this.setupAccessibility();
            
            console.log('Blackwall Client initialized');
        },
        
        /**
         * Setup iframe loading indicators
         */
        setupIframeLoading: function() {
            const iframes = document.querySelectorAll('.iframe-container iframe');
            
            iframes.forEach(function(iframe) {
                iframe.addEventListener('load', function() {
                    const container = iframe.closest('.iframe-container');
                    if (container) {
                        container.classList.add('loaded');
                        console.log('Iframe loaded successfully:', iframe.id);
                    }
                });
                
                iframe.addEventListener('error', function() {
                    const container = iframe.closest('.iframe-container');
                    if (container) {
                        container.classList.add('error');
                        console.error('Iframe failed to load:', iframe.id);
                    }
                });
                
                // Set a timeout to mark as error if not loaded within 30 seconds
                setTimeout(function() {
                    const container = iframe.closest('.iframe-container');
                    if (container && !container.classList.contains('loaded')) {
                        container.classList.add('error');
                        console.warn('Iframe load timeout:', iframe.id);
                    }
                }, 30000);
            });
        },
        
        /**
         * Setup auto-refresh for iframes
         */
        setupAutoRefresh: function() {
            // Auto-refresh statistics every 5 minutes
            this.scheduleRefresh('statistics-iframe', this.config.refreshInterval);
            
            // Auto-refresh events every 2 minutes
            this.scheduleRefresh('events-iframe', 120000);
        },
        
        /**
         * Schedule iframe refresh
         */
        scheduleRefresh: function(iframeId, interval) {
            if (this.timers[iframeId]) {
                clearInterval(this.timers[iframeId]);
            }
            
            this.timers[iframeId] = setInterval(function() {
                BlackwallClient.refreshIframe(iframeId);
            }, interval);
        },
        
        /**
         * Refresh an iframe
         */
        refreshIframe: function(iframeId) {
            const iframe = document.getElementById(iframeId);
            if (iframe) {
                const src = iframe.src;
                iframe.src = 'about:blank';
                
                // Small delay to ensure the iframe is cleared
                setTimeout(function() {
                    iframe.src = src;
                }, 100);
                
                console.log('Refreshed iframe:', iframeId);
            }
        },
        
        /**
         * Setup tab change handlers
         */
        setupTabHandlers: function() {
            const tabs = document.querySelectorAll('#blackwall-tabs button[data-bs-target]');
            
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-bs-target');
                    const iframeId = targetId.replace('#', '') + '-iframe';
                    
                    // Refresh iframe when tab becomes active
                    setTimeout(function() {
                        const iframe = document.getElementById(iframeId);
                        if (iframe && iframe.src && iframe.src !== 'about:blank') {
                            BlackwallClient.refreshIframe(iframeId);
                        }
                    }, 300);
                });
            });
        },
        
        /**
         * Setup copy to clipboard handlers
         */
        setupCopyHandlers: function() {
            // Add click handlers to all copy buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('button') && e.target.closest('button').getAttribute('onclick') && 
                    e.target.closest('button').getAttribute('onclick').includes('copyToClipboard')) {
                    e.preventDefault();
                    
                    const button = e.target.closest('button');
                    const onclickAttr = button.getAttribute('onclick');
                    const match = onclickAttr.match(/copyToClipboard\(['"]([^'"]+)['"]\)/);
                    
                    if (match) {
                        BlackwallClient.copyToClipboard(match[1]);
                    }
                }
            });
        },
        
        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(elementId) {
            const element = document.getElementById(elementId);
            if (!element) {
                console.error('Element not found:', elementId);
                return;
            }
            
            const text = element.textContent || element.innerText;
            
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(text).then(function() {
                    BlackwallClient.showCopyNotification();
                    BlackwallClient.highlightElement(element);
                }).catch(function(err) {
                    console.error('Failed to copy to clipboard:', err);
                    BlackwallClient.fallbackCopyToClipboard(text);
                    BlackwallClient.showCopyNotification();
                    BlackwallClient.highlightElement(element);
                });
            } else {
                // Fallback for older browsers
                BlackwallClient.fallbackCopyToClipboard(text);
                BlackwallClient.showCopyNotification();
                BlackwallClient.highlightElement(element);
            }
        },
        
        /**
         * Fallback copy method for older browsers
         */
        fallbackCopyToClipboard: function(text) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-9999px';
            document.body.appendChild(tempInput);
            tempInput.select();
            
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback copy failed:', err);
            }
            
            document.body.removeChild(tempInput);
        },
        
        /**
         * Show copy notification
         */
        showCopyNotification: function() {
            const notification = document.getElementById('copy-notification');
            if (!notification) return;
            
            notification.style.display = 'block';
            notification.style.opacity = '1';
            
            setTimeout(function() {
                notification.style.opacity = '0';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            }, this.config.copyNotificationDuration);
        },
        
        /**
         * Highlight an element temporarily
         */
        highlightElement: function(element) {
            const originalBackground = element.style.backgroundColor;
            const originalTransition = element.style.transition;
            
            element.style.transition = 'background-color 0.3s ease';
            element.style.backgroundColor = '#e6ffe6';
            
            setTimeout(function() {
                element.style.backgroundColor = originalBackground;
                setTimeout(function() {
                    element.style.transition = originalTransition;
                }, 300);
            }, this.config.highlightDuration);
        },
        
        /**
         * Setup accessibility features
         */
        setupAccessibility: function() {
            // Add keyboard navigation for tabs
            const tabButtons = document.querySelectorAll('#blackwall-tabs button');
            
            tabButtons.forEach(function(button, index) {
                button.addEventListener('keydown', function(e) {
                    let targetIndex = index;
                    
                    switch(e.key) {
                        case 'ArrowRight':
                            e.preventDefault();
                            targetIndex = (index + 1) % tabButtons.length;
                            break;
                        case 'ArrowLeft':
                            e.preventDefault();
                            targetIndex = (index - 1 + tabButtons.length) % tabButtons.length;
                            break;
                        case 'Home':
                            e.preventDefault();
                            targetIndex = 0;
                            break;
                        case 'End':
                            e.preventDefault();
                            targetIndex = tabButtons.length - 1;
                            break;
                        default:
                            return;
                    }
                    
                    tabButtons[targetIndex].focus();
                    tabButtons[targetIndex].click();
                });
            });
            
            // Add ARIA labels to copy buttons
            const copyButtons = document.querySelectorAll('button[onclick*="copyToClipboard"]');
            copyButtons.forEach(function(button) {
                if (!button.getAttribute('aria-label')) {
                    button.setAttribute('aria-label', 'Copy to clipboard');
                }
            });
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const container = document.querySelector('.blackwall-client-area');
            if (container) {
                container.insertBefore(alert, container.firstChild);
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 5000);
            }
        },
        
        /**
         * Check DNS configuration status
         */
        checkDnsStatus: function() {
            // This could be enhanced to make AJAX calls to check DNS in real-time
            console.log('DNS status check would be implemented here');
        },
        
        /**
         * Clean up timers when page unloads
         */
        cleanup: function() {
            Object.values(this.timers).forEach(function(timer) {
                clearInterval(timer);
            });
            this.timers = {};
        }
    };
    
    // Global functions for backwards compatibility
    window.refreshIframe = function(iframeId) {
        BlackwallClient.refreshIframe(iframeId);
    };
    
    window.copyToClipboard = function(elementId) {
        BlackwallClient.copyToClipboard(elementId);
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BlackwallClient.init();
        });
    } else {
        BlackwallClient.init();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        BlackwallClient.cleanup();
    });
    
    // Handle visibility change to pause/resume auto-refresh
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Pause auto-refresh when tab is not visible
            Object.values(BlackwallClient.timers).forEach(function(timer) {
                clearInterval(timer);
            });
        } else {
            // Resume auto-refresh when tab becomes visible
            BlackwallClient.setupAutoRefresh();
        }
    });
    
    // Export for external use
    window.BlackwallClient = BlackwallClient;
    
})();