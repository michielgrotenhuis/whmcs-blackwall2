/**
 * Blackwall WHMCS Module JavaScript
 * Version 2.0.1 - Updated with tab fixes and refresh functionality
 */

(function() {
    'use strict';
    
    // Global configuration
    const BlackwallClient = {
        config: {
            refreshInterval: 300000, // 5 minutes
            copyNotificationDuration: 2000, // 2 seconds
            highlightDuration: 1000, // 1 second
            iframeLoadTimeout: 15000, // 15 seconds
        },
        
        timers: {},
        
        /**
         * Initialize the Blackwall client interface
         */
        init: function() {
            this.setupTabHandlers();
            this.setupIframeLoading();
            this.setupAutoRefresh();
            this.setupCopyHandlers();
            this.setupAccessibility();
            this.setupRefreshButtons();
            
            console.log('Blackwall Client initialized');
        },
        
        /**
         * Setup tab change handlers with improved compatibility
         */
        setupTabHandlers: function() {
            // Handle both Bootstrap 3/4/5 and manual tab switching
            const tabs = document.querySelectorAll('#blackwall-tabs a[data-toggle="tab"]');
            
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    const iframeId = targetId.replace('#', '') + '-iframe';
                    
                    // Manual tab switching if Bootstrap isn't handling it
                    if (!BlackwallClient.isBootstrapAvailable()) {
                        BlackwallClient.showTab(targetId);
                        BlackwallClient.setActiveTab(this);
                    }
                    
                    // Refresh iframe when tab becomes active (with delay)
                    setTimeout(function() {
                        const iframe = document.getElementById(iframeId);
                        if (iframe && iframe.src && iframe.src !== 'about:blank') {
                            // Only auto-refresh if the tab is visible and iframe hasn't been manually refreshed recently
                            const container = iframe.closest('.iframe-container');
                            if (container && !container.hasAttribute('data-manual-refresh')) {
                                BlackwallClient.refreshIframe(iframeId, false); // Silent refresh
                            }
                        }
                    }, 500);
                });
            });
            
            // Set up keyboard navigation
            this.setupTabKeyboardNavigation();
        },
        
        /**
         * Check if Bootstrap is available
         */
        isBootstrapAvailable: function() {
            return (typeof bootstrap !== 'undefined' && bootstrap.Tab) || 
                   (typeof jQuery !== 'undefined' && jQuery.fn.tab);
        },
        
        /**
         * Manual tab switching
         */
        showTab: function(targetId) {
            // Hide all tab panes
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(function(pane) {
                pane.classList.remove('show', 'active');
            });
            
            // Show target tab pane
            const targetPane = document.querySelector(targetId);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
        },
        
        /**
         * Set active tab
         */
        setActiveTab: function(activeTab) {
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('#blackwall-tabs a');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            });
            
            // Set active tab
            activeTab.classList.add('active');
            activeTab.setAttribute('aria-selected', 'true');
        },
        
        /**
         * Setup keyboard navigation for tabs
         */
        setupTabKeyboardNavigation: function() {
            const tabButtons = document.querySelectorAll('#blackwall-tabs a');
            
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
        },
        
        /**
         * Setup refresh buttons for iframes
         */
        setupRefreshButtons: function() {
            // Add global refresh function to window for onclick handlers
            window.refreshIframeContent = function(iframeId) {
                BlackwallClient.refreshIframe(iframeId, true);
            };
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
                        container.classList.remove('error');
                        console.log('Iframe loaded successfully:', iframe.id);
                    }
                });
                
                iframe.addEventListener('error', function() {
                    const container = iframe.closest('.iframe-container');
                    if (container) {
                        container.classList.add('error');
                        container.classList.remove('loaded');
                        console.error('Iframe failed to load:', iframe.id);
                    }
                });
                
                // Set a timeout to mark as error if not loaded within timeout period
                setTimeout(function() {
                    const container = iframe.closest('.iframe-container');
                    if (container && !container.classList.contains('loaded') && !container.classList.contains('error')) {
                        container.classList.add('error');
                        console.warn('Iframe load timeout:', iframe.id);
                    }
                }, BlackwallClient.config.iframeLoadTimeout);
            });
        },
        
        /**
         * Setup auto-refresh for iframes
         */
        setupAutoRefresh: function() {
            // Auto-refresh statistics every 5 minutes (when visible)
            this.scheduleRefresh('statistics-iframe', this.config.refreshInterval);
            
            // Auto-refresh events every 2 minutes (when visible)
            this.scheduleRefresh('events-iframe', 120000);
        },
        
        /**
         * Schedule iframe refresh (only when tab is active)
         */
        scheduleRefresh: function(iframeId, interval) {
            if (this.timers[iframeId]) {
                clearInterval(this.timers[iframeId]);
            }
            
            this.timers[iframeId] = setInterval(function() {
                const iframe = document.getElementById(iframeId);
                if (iframe) {
                    const tabPane = iframe.closest('.tab-pane');
                    // Only refresh if tab is active and visible
                    if (tabPane && tabPane.classList.contains('active') && 
                        !document.hidden && !tabPane.closest('.iframe-container').hasAttribute('data-manual-refresh')) {
                        BlackwallClient.refreshIframe(iframeId, false); // Silent auto-refresh
                    }
                }
            }, interval);
        },
        
        /**
         * Refresh an iframe with visual feedback
         */
        refreshIframe: function(iframeId, showFeedback = true) {
            const iframe = document.getElementById(iframeId);
            if (!iframe) return;
            
            const container = iframe.closest('.iframe-container');
            const fallback = document.getElementById(iframeId.replace('-iframe', '-fallback'));
            
            if (showFeedback) {
                // Mark as manually refreshed to prevent auto-refresh conflicts
                if (container) {
                    container.setAttribute('data-manual-refresh', 'true');
                    setTimeout(() => {
                        container.removeAttribute('data-manual-refresh');
                    }, 30000); // Clear flag after 30 seconds
                }
                
                // Show loading feedback
                iframe.style.display = 'none';
                if (fallback) {
                    fallback.innerHTML = `
                        <div class="p-4 text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Refreshing content...</p>
                        </div>
                    `;
                    fallback.style.display = 'block';
                }
            }
            
            // Reset container state
            if (container) {
                container.classList.remove('loaded', 'error');
            }
            
            // Reload iframe
            const src = iframe.src;
            iframe.src = 'about:blank';
            
            // Small delay to ensure the iframe is cleared
            setTimeout(function() {
                iframe.src = src;
            }, showFeedback ? 500 : 100);
            
            console.log('Refreshed iframe:', iframeId, showFeedback ? '(manual)' : '(auto)');
        },
        
        /**
         * Setup copy to clipboard handlers
         */
        setupCopyHandlers: function() {
            // Add global copy function to window
            window.copyToClipboard = function(elementId) {
                BlackwallClient.copyToClipboard(elementId);
            };
            
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
            // Add ARIA labels to copy buttons
            const copyButtons = document.querySelectorAll('button[onclick*="copyToClipboard"]');
            copyButtons.forEach(function(button) {
                if (!button.getAttribute('aria-label')) {
                    button.setAttribute('aria-label', 'Copy to clipboard');
                }
            });
            
            // Add ARIA labels to refresh buttons
            const refreshButtons = document.querySelectorAll('button[onclick*="refreshIframeContent"]');
            refreshButtons.forEach(function(button) {
                if (!button.getAttribute('aria-label')) {
                    button.setAttribute('aria-label', 'Refresh content');
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
         * Show success message
         */
        showSuccess: function(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const container = document.querySelector('.blackwall-client-area');
            if (container) {
                container.insertBefore(alert, container.firstChild);
                
                // Auto-remove after 3 seconds
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 3000);
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
        BlackwallClient.refreshIframe(iframeId, true);
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
            console.log('Page hidden, pausing auto-refresh');
        } else {
            // Resume auto-refresh when tab becomes visible
            console.log('Page visible, resuming auto-refresh');
            BlackwallClient.setupAutoRefresh();
        }
    });
    
    // Export for external use
    window.BlackwallClient = BlackwallClient;
    
})();