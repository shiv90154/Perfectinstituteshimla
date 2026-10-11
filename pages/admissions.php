<?php
// pages/admissions.php

// ========== META DATA FOR SEO & SOCIAL SHARING ==========
$meta_title       = 'Admissions - Perfect Institute | Register for English Courses';
$meta_description = 'Admissions are now open for spoken English, grammar, and personality development courses at Perfect Institute. Register online and start your learning journey today.';
$meta_image       = 'https://perfectinstitute.in/assets/images/og-admissions.jpeg'; // Update with actual image URL

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . strtok($request_uri, '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- Primary Meta Tags -->
    <title><?= htmlspecialchars($meta_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta_image) ?>">
    <meta property="og:site_name" content="Perfect Institute of Languages">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($meta_image) ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <!-- Google Fonts & Font Awesome (optional, but used in header) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global CSS (same as homepage) -->
    <style>
        /* =========================================
           GLOBAL RESET
        ========================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* =========================================
           HTML + BODY
        ========================================= */
        html {
            scroll-behavior: smooth;
        }

        body {
            /* HEADER OFFSET – adjust 80px to match your header height */
      
            background-color: #ffffff;
            color: #1e293b;
            line-height: 1.6;
        }

        /* =========================================
           SCROLLBAR (DESKTOP ONLY)
        ========================================= */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #e63946;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #c92e3a;
        }

        /* =========================================
           UTILITY CLASSES
        ========================================= */
        .text-center {
            text-align: center;
        }

        .hidden {
            display: none !important;
        }

        /* =========================================
           MAIN CONTENT SAFETY
        ========================================= */
        main {
            display: block;
            width: 100%;
        }
    </style>

    <!-- Page-specific styles (enhanced with theme variables) -->
    <style>
        :root {
            --primary: #1a365d;
            --primary-light: #2d4f8b;
            --secondary: #f97316;
            --secondary-light: #fb923c;
            --gray: #475569;
            --light-gray: #e2e8f0;
            --white: #ffffff;
            --off-white: #f9fafc;
        }

        .admission-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: var(--off-white);
        }

        .admission-box {
            max-width: 700px;
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--light-gray);
        }

        .admission-box h1 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 2rem;
        }

        .admission-box p {
            color: var(--gray);
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .admission-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .admission-actions a {
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 50px; /* Rounded buttons to match theme */
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-register {
            background: var(--primary);
            color: #fff;
        }

        .btn-register:hover {
            background: var(--primary-light);
        }

        .btn-contact {
            background: var(--secondary);
            color: #fff;
        }

        .btn-contact:hover {
            background: var(--secondary-light);
        }

        .notice {
            margin-top: 30px;
            font-size: 0.9rem;
            color: #64748b;
            border-top: 1px solid var(--light-gray);
            padding-top: 20px;
        }

        @media (max-width: 576px) {
            .admission-box {
                padding: 30px 20px;
            }
            .admission-box h1 {
                font-size: 1.8rem;
            }
            .admission-actions a {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="admission-page">
        <div class="admission-box">
            <h1>Admission Information</h1>
            <p>
                To access any course at <strong>Perfect Institute</strong>,
                registration is mandatory. No course content is available
                without completing the admission process.
            </p>
            <p>
                <strong>Important:</strong><br>
                For payment and admission confirmation, students must first
                complete registration and then inform the institute directly.
            </p>
            <div class="admission-actions">
                <a href="/registrations/" class="btn-register">
                    <i class="fas fa-user-plus"></i> Register Now
                </a>
                <a href="/contact" class="btn-contact">
                    <i class="fas fa-phone-alt"></i> Contact Institute
                </a>
            </div>
            <div class="notice">
                ⚠️ Payments are accepted only after class & batch confirmation.<br>
                Please do not make any payment without confirmation from the institute.
            </div>
        </div>
    </div>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>