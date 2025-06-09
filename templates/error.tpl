{* Blackwall WHMCS Module - Error Template *}

<link rel="stylesheet" type="text/css" href="{$WEB_ROOT}/modules/servers/blackwall/assets/css/blackwall.css">

<div class="blackwall-client-area">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Blackwall Service Error
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                        <h4>Oops! Something went wrong</h4>
                        <p class="text-muted">We encountered an issue while loading your Blackwall service information.</p>
                    </div>
                    
                    <div class="alert alert-danger" role="alert">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-times-circle me-3 mt-1"></i>
                            <div>
                                <h6 class="alert-heading mb-2">Error Details</h6>
                                <p class="mb-0">{$error_message|escape}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">What can you do?</h6>
                                    <p class="card-text text-muted mb-4">
                                        This error is typically temporary. Here are some steps you can try:
                                    </p>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-primary w-100" onclick="location.reload()">
                                                <i class="fas fa-redo me-2"></i>
                                                Refresh Page
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-outline-secondary w-100" onclick="history.back()">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Go Back
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Common Solutions</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Refresh the page and try again</li>
                                <li><i class="fas fa-check text-success me-2"></i> Clear your browser cache and cookies</li>
                                <li><i class="fas fa-check text-success me-2"></i> Check if your internet connection is stable</li>
                                <li><i class="fas fa-check text-success me-2"></i> Try accessing the service from a different browser</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="/submitticket.php" class="btn btn-success btn-lg">
                                <i class="fas fa-life-ring me-2"></i>
                                Contact Support
                            </a>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <details class="collapse-details">
                            <summary class="btn btn-link text-muted p-0 border-0 bg-transparent" style="cursor: pointer;">
                                <i class="fas fa-info-circle me-2"></i>
                                Technical Information
                            </summary>
                            <div class="mt-3 p-3 bg-light rounded">
                                <p class="small text-muted mb-2"><strong>Service:</strong> Blackwall Website Protection</p>
                                <p class="small text-muted mb-2"><strong>Error occurred at:</strong> {$smarty.now|date_format:"%Y-%m-%d %H:%M:%S"}</p>
                                <p class="small text-muted mb-0"><strong>Error message:</strong> {$error_message|escape}</p>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.collapse-details summary {
    outline: none;
    font-size: 0.875rem;
}

.collapse-details summary:hover {
    text-decoration: underline;
}

.blackwall-client-area .card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.alert-danger {
    border-left: 4px solid #dc3545;
}

@media (max-width: 768px) {
    .fa-4x {
        font-size: 2.5rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on refresh button for accessibility
    const refreshBtn = document.querySelector('button[onclick="location.reload()"]');
    if (refreshBtn) {
        refreshBtn.focus();
    }
    
    // Add keyboard navigation for details
    const details = document.querySelector('details');
    if (details) {
        const summary = details.querySelector('summary');
        if (summary) {
            summary.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    details.open = !details.open;
                }
            });
        }
    }
});
</script>