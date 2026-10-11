<?php
// pages/courses.php - Dynamic course listing
require_once __DIR__ . '/../includes/config.php';

// ========== META DATA FOR SEO & SOCIAL SHARING ==========
$meta_title       = 'English Language Courses - Perfect Institute';
$meta_description = 'Explore our wide range of English courses designed to boost your communication skills, grammar, and confidence. Online & offline classes available.';
$meta_image       = 'https://perfectinstitute.in/assets/images/og-courses.jpeg'; // Update with actual image URL

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . strtok($request_uri, '?');

// Fetch only non-deleted courses
$result = $conn->query("SELECT * FROM courses WHERE is_deleted = 0 ORDER BY id");
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

    <!-- Favicon (adjust path if needed) -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

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

    <!-- Page-specific styles (updated to site theme) -->
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--off-white);
            color: #333;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        /* Page header (no standalone header, rely on included header.php) */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 40px 0;
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            margin-bottom: 8px;
        }

        .page-header .subtitle {
            opacity: 0.9;
            font-size: 1rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .course-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.3s ease;
            border-top: 4px solid var(--secondary);
            display: flex;
            flex-direction: column;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
        }

        .course-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .course-card:hover .course-image img {
            transform: scale(1.05);
        }

        .card-header {
            background: var(--primary);
            color: var(--white);
            padding: 14px 18px;
        }

        .course-type {
            display: inline-block;
            background: var(--secondary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-header h3 {
            font-size: 1.1rem;
            line-height: 1.4;
        }

        .card-content {
            padding: 18px;
            flex-grow: 1;
        }

        .detail-row {
            display: flex;
            font-size: 0.88rem;
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary);
            min-width: 85px;
        }

        .card-footer {
            padding: 14px 18px;
            background: var(--off-white);
            display: flex;
            gap: 12px;
            border-top: 1px solid var(--light-gray);
        }

        .btn {
            flex: 1;
            border: none;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .enroll-btn {
            background: var(--secondary);
            color: var(--white);
        }

        .enroll-btn:hover {
            background: var(--secondary-light);
        }

        .view-btn {
            background: var(--primary);
            color: var(--white);
        }

        .view-btn:hover {
            background: var(--primary-light);
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 1.6rem;
            }
            .card-footer {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="page-header">
        <div class="container">
            <h1>English Language Courses</h1>
            <p class="subtitle">Comprehensive English training for all levels</p>
        </div>
    </div>

    <div class="container">
        <div class="courses-grid">
            <?php while ($row = $result->fetch_assoc()): 
                // ✅ Correct image path: if image exists, prepend /uploads/
                $image = $row['image_path'] ? '/uploads/' . $row['image_path'] : '/images/placeholder.jpg';
                $fee_display = $row['fee_display'] ?: '₹' . number_format($row['fee']);
            ?>
            <div class="course-card">
                <div class="course-image">
                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($row['title']) ?>" loading="lazy">
                </div>
                <div class="card-header">
                    <span class="course-type"><?= htmlspecialchars($row['type']) ?></span>
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                </div>
                <div class="card-content">
                    <div class="detail-row">
                        <div class="detail-label">Batch:</div>
                        <div><?= htmlspecialchars($row['batch_time'] ?: 'Flexible') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Duration:</div>
                        <div><?= htmlspecialchars($row['duration'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Days:</div>
                        <div><?= htmlspecialchars($row['class_days'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Seats:</div>
                        <div><?= htmlspecialchars($row['seats'] ?: '—') ?></div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/admissions" class="btn enroll-btn">
                        <i class="fas fa-user-plus"></i> Enroll
                    </a>
                    <a href="?page=view_course&id=<?= $row['id'] ?>" class="btn view-btn">
                        <i class="fas fa-eye"></i> Details
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>