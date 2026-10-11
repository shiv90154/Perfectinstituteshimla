<?php 
$title = "500 - Server Error";
include('includes/header.php'); 
?>

<div class="error-page">
    <div class="error-container">
        <div class="error-number">500</div>
        <h1 class="error-title">Internal Server Error</h1>
        <p class="error-message">Something went wrong on our server. Please try again later.</p>
        <div class="error-actions">
            <a href="/public_html/index.php" class="btn-home">
                <i class="fas fa-home"></i> Go to Homepage
            </a>
            <a href="mailto:support@public_html.com" class="btn-contact">
                <i class="fas fa-envelope"></i> Contact Support
            </a>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>