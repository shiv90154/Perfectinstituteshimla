<?php 
$title = "403 - Access Denied";
include('includes/header.php'); 
?>

<div class="error-page">
    <div class="error-container">
        <div class="error-number">403</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">You don't have permission to access this page.</p>
        <div class="error-actions">
            <a href="/public_html/index.php" class="btn-home">
                <i class="fas fa-home"></i> Go to Homepage
            </a>
            <a href="javascript:history.back()" class="btn-courses">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>