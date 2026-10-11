<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Normalize current page: remove trailing slash and handle empty home
$current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$current_page = $current_path === '' ? '' : $current_path;

$nav_items = [
    ''           => ['Home', 'bi-house'],
    'courses'    => ['Courses', 'bi-book'],
    'admissions' => ['Admission', 'bi-pencil-square'],
    'youtube'    => ['Videos', 'bi-youtube'],
    'blog'       => ['StudyHub', 'bi-newspaper'],
    'about'      => ['About', 'bi-info-circle'],
    'contact'    => ['Contact', 'bi-telephone']
];

$phone_number  = '+919805962233';
$whatsapp_link = 'https://wa.me/message/G42Z3HSZ3ZC4D1';

// Encode logo paths to handle spaces in filenames safely
$desktop_logo = '/Logo/logo.png';
$mobile_logo  = '/Logo/Perfect%20Institute%202021%20logo%20png%20circle%201200X1200.png';
// Alternative: rename the file to avoid spaces, e.g., 'perfect-institute-logo-circle.png'
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>Perfect Institute</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <!-- External stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/css/global.css">  <!-- Your fixed global.css -->
    <link rel="manifest" href="/manifest.json">

    <style>
        /* ================= HEADER SCOPE ================= */
        .pi-header-scope {
            --pi-navy: #0a192f;
            --pi-navy-dark: #071224;
            --pi-red: #e63946;
            --pi-white: #ffffff;
            --pi-gray: #e5e7eb;
            font-family: system-ui, -apple-system, sans-serif;
            color: var(--pi-white);
        }

        /* ================= HEADER ================= */
        .pi-hdr {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(90deg, var(--pi-navy), var(--pi-navy-dark));
            box-shadow: 0 10px 35px rgba(0,0,0,0.55);
        }

        .pi-hdr__container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            gap: 18px;
            flex-wrap: wrap;
        }

        /* ================= LOGO ================= */
        .pi-hdr__logo {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .pi-hdr__logo-desktop {
            height: 46px;
            background: #ffffff;
            padding: 7px 12px;
            border-radius: 10px;
        }

        .pi-hdr__logo-mobile {
            display: none;
            height: 42px;
        }

        .pi-hdr__logo-text {
            display: none;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        /* ================= DESKTOP NAV ================= */
        .pi-hdr__nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            flex: 1;
        }

        .pi-hdr__nav a {
            margin: 6px 12px;
            text-decoration: none;
            color: var(--pi-white);
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
            transition: 0.25s;
            white-space: nowrap;
        }

        .pi-hdr__nav a.pi-hdr-active {
            color: var(--pi-red);
            opacity: 1;
        }

        /* ================= ACTION BUTTONS ================= */
        .pi-hdr__actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .pi-hdr__btn {
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 9px 15px;
            border-radius: 22px;
            color: var(--pi-white);
            transition: 0.25s;
            box-shadow: 0 6px 18px rgba(0,0,0,0.35);
            white-space: nowrap;
        }

        .pi-hdr__btn--whatsapp { background: #25D366; }
        .pi-hdr__btn--call { background: var(--pi-red); }

        .pi-hdr__btn:hover {
            transform: translateY(-2px);
        }

        /* ================= MOBILE ================= */
        @media (max-width: 768px) {
            .pi-hdr__container {
                flex-wrap: nowrap;
            }

            .pi-hdr__nav { display: none; }

            .pi-hdr__logo-desktop { display: none; }
            .pi-hdr__logo-mobile { display: block; }
            .pi-hdr__logo-text { display: inline; }

            .pi-hdr__btn {
                width: 42px;
                height: 42px;
                padding: 0;
                border-radius: 50%;
                font-size: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .pi-hdr__btn span { display: none; }
        }

        /* ================= LANDSCAPE FIX ================= */
        @media (max-width: 900px) and (orientation: landscape) {
            .pi-hdr__container {
                padding: 10px 14px;
                gap: 10px;
                flex-wrap: nowrap;
            }

            .pi-hdr__logo-desktop {
                height: 38px;
                padding: 5px 9px;
            }

            .pi-hdr__nav a {
                font-size: 13.5px;
                margin: 0 6px;
            }

            .pi-hdr__btn {
                padding: 6px 10px;
                font-size: 12.5px;
                border-radius: 18px;
            }

            .pi-hdr__actions {
                gap: 6px;
            }
        }

        /* ================= BOTTOM NAV ================= */
        .pi-hdr-bottom { display: none; }

        @media (max-width: 768px) {
            .pi-hdr-bottom {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 62px;
                background: linear-gradient(180deg, var(--pi-navy), var(--pi-navy-dark));
                border-top: 1px solid rgba(255,255,255,0.12);
                display: flex;
                justify-content: space-around;
                align-items: center;
                z-index: 2000;
            }

            .pi-hdr-bottom__item {
                text-decoration: none;
                color: var(--pi-white);
                text-align: center;
                font-size: 11.5px;
                font-weight: 500;
                opacity: 0.75;
                transition: 0.2s;
            }

            .pi-hdr-bottom__item i {
                display: block;
                font-size: 20px;
                margin-bottom: 2px;
            }

            .pi-hdr-bottom__item.pi-hdr-active {
                color: var(--pi-red);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<div class="pi-header-scope">

<header class="pi-hdr">
    <div class="pi-hdr__container">

        <div class="pi-hdr__logo">
            <img src="<?= $desktop_logo ?>" class="pi-hdr__logo-desktop" alt="Perfect Institute">
            <img src="<?= $mobile_logo ?>" class="pi-hdr__logo-mobile" alt="Perfect Institute">
            <span class="pi-hdr__logo-text">Perfect Institute</span>
        </div>

        <nav class="pi-hdr__nav" aria-label="Main navigation">
            <?php foreach ($nav_items as $url => $item): ?>
                <a href="/<?= $url ?>" 
                   class="<?= $current_page === $url ? 'pi-hdr-active' : '' ?>"
                   <?= $current_page === $url ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($item[0]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="pi-hdr__actions">
            <a href="<?= $whatsapp_link ?>" 
               target="_blank" 
               class="pi-hdr__btn pi-hdr__btn--whatsapp"
               aria-label="Chat on WhatsApp">
                <i class="bi bi-whatsapp"></i><span> WhatsApp</span>
            </a>
            <a href="tel:<?= $phone_number ?>" 
               class="pi-hdr__btn pi-hdr__btn--call"
               aria-label="Call us">
                <i class="bi bi-telephone"></i><span> Call</span>
            </a>
        </div>

    </div>
</header>

<!-- Mobile bottom navigation -->
<div class="pi-hdr-bottom" role="navigation" aria-label="Mobile navigation">
    <?php foreach ($nav_items as $url => $item): ?>
        <a href="/<?= $url ?>" 
           class="pi-hdr-bottom__item <?= $current_page === $url ? 'pi-hdr-active' : '' ?>"
           <?= $current_page === $url ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= $item[1] ?>"></i>
            <?= htmlspecialchars($item[0]) ?>
        </a>
    <?php endforeach; ?>
</div>

</div>

<!-- Page content starts here -->