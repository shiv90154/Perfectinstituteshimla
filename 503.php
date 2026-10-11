<?php 
$title = "503 - Service Unavailable";
include('includes/header.php'); 
?>

<div class="error-page">
    <div class="error-container">
        <div class="error-number">503</div>
        <h1 class="error-title">Service Unavailable</h1>
        <p class="error-message">We're currently performing maintenance. Please check back soon.</p>
        <div class="error-actions">
            <a href="/public_html/index.php" class="btn-home">
                <i class="fas fa-home"></i> Go to Homepage
            </a>
            <a href="javascript:location.reload()" class="btn-courses">
                <i class="fas fa-redo"></i> Refresh Page
            </a>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>