<?php
$meta_title = 'Refund Policy - Perfect Institute';
$meta_description = 'Read the Refund Policy of Perfect Institute regarding admissions, course fees and payment refunds.';

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
    background:#fff;
    color:#1e293b;
    line-height:1.7;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:0 20px;
}

.hero{
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:#fff;
    text-align:center;
    padding:80px 20px;
}

.hero h1{
    font-size:3rem;
    margin-bottom:15px;
}

.hero p{
    opacity:.9;
}

.content-section{
    padding:70px 0;
    background:var(--off-white);
}

.card{
    background:#fff;
    padding:40px;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.card h2{
    color:var(--primary);
    margin-top:30px;
    margin-bottom:15px;
}

.card h2:first-child{
    margin-top:0;
}

.card ul{
    padding-left:25px;
}

.card li{
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

.notice{
    background:#fff7ed;
    border-left:4px solid #f97316;
    padding:15px;
    margin:20px 0;
    border-radius:6px;
}

@media(max-width:768px){
.hero h1{
font-size:2rem;
}

.card{
padding:25px;
}
}
</style>
</head>
<body>

<?php include __DIR__.'/includes/header.php'; ?>

<section class="hero">
    <div class="container">
        <h1>Refund Policy</h1>
        <p>Information regarding refunds, cancellations and fee adjustments.</p>
    </div>
</section>

<section class="content-section">
<div class="container">

<div class="card">

<span class="updated">
Last Updated: June 2026
</span>

<h2>Refund Policy Overview</h2>

<p>
Perfect Institute strives to provide high-quality educational services.
This Refund Policy explains the conditions under which refunds may be approved.
</p>

<div class="notice">
<strong>Important:</strong>
Registration fees, processing fees and administrative charges are generally non-refundable.
</div>

<h2>Registration Fee Refund</h2>

<ul>
<li>Admission registration fees are non-refundable.</li>
<li>Application processing charges are non-refundable.</li>
<li>Administrative charges are non-refundable.</li>
</ul>

<h2>Course Fee Refund</h2>

<p>
Students may submit a written refund request within 7 days of admission,
provided that classes have not been substantially attended.
</p>

<ul>
<li>Refund requests must be submitted through official communication channels.</li>
<li>Each request will be reviewed individually.</li>
<li>Approval of refunds remains at the discretion of institute management.</li>
</ul>

<h2>Non-Refundable Situations</h2>

<ul>
<li>Course access has already been granted.</li>
<li>Classes have commenced and attendance has been recorded.</li>
<li>Study material has been provided.</li>
<li>The student voluntarily discontinues the course after commencement.</li>
<li>The student violates institute policies.</li>
<li>The student remains absent from classes without notice.</li>
</ul>

<h2>Duplicate Payments</h2>

<p>
If a payment is accidentally made more than once for the same transaction,
the duplicate amount may be refunded after successful verification.
</p>

<h2>Approved Refund Processing</h2>

<ul>
<li>Approved refunds may take 7–15 business days.</li>
<li>Refunds will be processed through the original payment method whenever possible.</li>
<li>Processing time may vary depending on banking institutions and payment gateways.</li>
</ul>

<h2>Institute Cancellation</h2>

<p>
If Perfect Institute cancels a course, batch or program due to operational reasons,
students may be eligible for a full or partial refund as determined by management.
</p>

<h2>Payment Gateway Refunds</h2>

<p>
Online payments processed through Razorpay or other approved gateways will follow
the respective payment gateway settlement and refund procedures.
</p>

<h2>Policy Changes</h2>

<p>
Perfect Institute reserves the right to modify this Refund Policy at any time.
Updated policies will be published on this page.
</p>

<h2>Contact Information</h2>

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

