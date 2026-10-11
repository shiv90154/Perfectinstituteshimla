
<?php
$meta_title = 'Privacy Policy - Perfect Institute';
$meta_description = 'Read the Privacy Policy of Perfect Institute and understand how we collect, use and protect your information.';
$meta_image = 'https://perfectinstitute.in/assets/images/privacy-policy.jpg';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$current_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($meta_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
<meta name="robots" content="index, follow">

<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
<meta property="og:title" content="<?= htmlspecialchars($meta_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --primary:#1a365d;
    --primary-light:#2d4f8b;
    --secondary:#f97316;
    --gray:#475569;
    --white:#fff;
    --off-white:#f8fafc;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',sans-serif;
    color:#1e293b;
    line-height:1.7;
    background:#fff;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:0 20px;
}

.hero{
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:white;
    text-align:center;
    padding:80px 20px;
}

.hero h1{
    font-size:3rem;
    margin-bottom:15px;
}

.hero p{
    opacity:.9;
    font-size:1.1rem;
}

.content-section{
    padding:70px 0;
    background:var(--off-white);
}

.policy-card{
    background:white;
    padding:40px;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.policy-card h2{
    color:var(--primary);
    margin-top:30px;
    margin-bottom:15px;
}

.policy-card h2:first-child{
    margin-top:0;
}

.policy-card ul{
    padding-left:25px;
}

.policy-card li{
    margin-bottom:10px;
}

.updated{
    display:inline-block;
    background:#eef2ff;
    color:var(--primary);
    padding:8px 15px;
    border-radius:6px;
    margin-bottom:25px;
}

@media(max-width:768px){
.hero h1{
font-size:2rem;
}
.policy-card{
padding:25px;
}
}
</style>
</head>
<body>

<?php include __DIR__.'/includes/header.php'; ?>

<section class="hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Your privacy and data protection are important to us.</p>
    </div>
</section>

<section class="content-section">
<div class="container">

<div class="policy-card">

<span class="updated">
Last Updated: June 2026
</span>

<h2>Introduction</h2>

<p>
Perfect Institute is committed to protecting your privacy.
This Privacy Policy explains how we collect, use and safeguard
your personal information when you visit our website or use our services.
</p>

<h2>Information We Collect</h2>

<ul>
<li>Full Name</li>
<li>Mobile Number</li>
<li>Email Address</li>
<li>Address Information</li>
<li>Educational Details</li>
<li>Course Preferences</li>
<li>Payment Information</li>
<li>Documents uploaded during admission</li>
</ul>

<h2>How We Use Your Information</h2>

<ul>
<li>Student registration and admission processing</li>
<li>Course enrollment management</li>
<li>Student communication and support</li>
<li>Payment verification and receipt generation</li>
<li>Service improvement and analytics</li>
<li>Compliance with applicable laws</li>
</ul>

<h2>Payment Information</h2>

<p>
Payments made through our website may be processed through trusted payment gateways such as Razorpay.
Perfect Institute does not store complete debit card, credit card or banking credentials on its servers.
</p>

<h2>Data Protection</h2>

<p>
We implement appropriate technical and administrative safeguards
to protect personal information from unauthorized access,
disclosure, misuse or alteration.
</p>

<h2>Third-Party Services</h2>

<p>
We may use third-party service providers including payment gateways,
hosting providers, email services and analytics providers to operate
our services effectively.
</p>

<h2>Cookies</h2>

<p>
Our website may use cookies and similar technologies to improve user experience,
analyze website traffic and enhance functionality.
</p>

<h2>Your Rights</h2>

<ul>
<li>Request correction of inaccurate information</li>
<li>Request deletion where legally permitted</li>
<li>Request access to personal information</li>
<li>Withdraw consent where applicable</li>
</ul>

<h2>Policy Updates</h2>

<p>
Perfect Institute reserves the right to update this Privacy Policy at any time.
Changes will be posted on this page with the updated effective date.
</p>

<h2>Contact Us</h2>

<p>
<strong>Perfect Institute</strong><br>
Website: https://perfectinstitute.in<br>
Email: info@perfectinstitute.in
</p>

</div>

</div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>

</body>
</html>
