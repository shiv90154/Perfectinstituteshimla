<?php
// pages/youtube.php - Video lessons page

$meta_title = 'Video Lessons - Perfect Institute';
$meta_description = 'Watch free educational videos on English grammar, spoken English, personality development, and more from expert faculty. Learn anytime, anywhere.';
$meta_image = 'https://perfectinstitute.in/assets/images/og-youtube.jpeg'; // Update with actual image URL

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . strtok($request_uri, '?');

require_once __DIR__ . '/../includes/config.php';

// Helper function to extract YouTube video ID
function getYouTubeId($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
    if (preg_match($pattern, $url, $match)) {
        return $match[1];
    }
    return '';
}

// Helper function to build proper embed URL
function getYouTubeEmbedUrl($url) {
    $id = getYouTubeId($url);
    return $id ? "https://www.youtube.com/embed/{$id}" : '';
}

// Get current category filter
$current_category = isset($_GET['category']) ? trim($_GET['category']) : 'all';

// Fetch distinct categories for filter
$catResult = $conn->query("SELECT DISTINCT category FROM youtube_videos WHERE status='active' ORDER BY category");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Fetch videos (with prepared statement)
if ($current_category !== 'all') {
    $stmt = $conn->prepare("SELECT * FROM youtube_videos WHERE status='active' AND category = ? ORDER BY display_order ASC");
    $stmt->bind_param("s", $current_category);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM youtube_videos WHERE status='active' ORDER BY display_order ASC");
}
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

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
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--off-white);
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* HERO */
        .youtube-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 60px 0;
            text-align: center;
        }

        .youtube-hero h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 10px;
        }

        .youtube-hero p {
            opacity: 0.9;
        }

        /* SECTION */
        .youtube-section {
            padding: 70px 0 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-header h2 {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            color: var(--primary);
        }

        .section-header p {
            color: var(--gray);
        }

        .underline {
            height: 4px;
            width: 70px;
            background: var(--secondary);
            margin: 12px auto;
            border-radius: 2px;
        }

        /* CATEGORY BUTTONS */
        .categories {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .category-btn {
            padding: 8px 20px;
            border-radius: 30px;
            border: 2px solid var(--primary);
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .category-btn:hover,
        .category-btn.active {
            background: var(--primary);
            color: var(--white);
        }

        /* GRID */
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        @media (max-width: 992px) {
            .videos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .videos-grid {
                grid-template-columns: 1fr;
            }
        }

        /* CARD */
        .video-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }

        .video-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.12);
        }

        /* VIDEO CONTAINER */
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            background: #000;
        }

        .video-container iframe {
            position: absolute;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* INFO */
        .video-info {
            padding: 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .video-category {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(249, 115, 22, 0.1);
            color: var(--secondary);
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            align-self: flex-start;
        }

        .video-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--gray);
            border-top: 1px solid var(--border);
            padding-top: 12px;
            margin-top: auto;
        }

        .video-meta i {
            margin-right: 4px;
        }

        /* CTA */
        .youtube-cta {
            margin-top: 70px;
            padding: 70px 0;
            background: var(--primary);
            color: var(--white);
            text-align: center;
        }

        .cta-button {
            background: var(--secondary);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: 600;
            transition: 0.3s;
        }

        .cta-button:hover {
            background: var(--secondary-light);
            transform: translateY(-2px);
        }

        /* NO VIDEOS MESSAGE */
        .no-videos {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: var(--gray);
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- HERO -->
<section class="youtube-hero">
    <div class="container">
        <h1>Video Lessons</h1>
        <p>Watch free educational lessons from our expert faculty.</p>
    </div>
</section>

<!-- VIDEOS -->
<section class="youtube-section">
    <div class="container">
        <div class="section-header">
            <h2>Latest Lessons</h2>
            <p>Learn anytime, anywhere</p>
            <div class="underline"></div>
        </div>

        <!-- Category filter -->
        <div class="categories">
            <a href="?category=all" class="category-btn <?= $current_category == 'all' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat) ?>" class="category-btn <?= $current_category == $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Video grid -->
        <div class="videos-grid">
            <?php if ($result->num_rows == 0): ?>
                <div class="no-videos">No videos available in this category.</div>
            <?php endif; ?>

            <?php while ($video = $result->fetch_assoc()): 
                $embedUrl = getYouTubeEmbedUrl($video['youtube_url']);
                // Skip if invalid URL (no embed URL)
                if (empty($embedUrl)) continue;
            ?>
                <div class="video-card">
                    <div class="video-container">
                        <iframe 
                            src="<?= htmlspecialchars($embedUrl) ?>" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen
                            loading="lazy">
                        </iframe>
                    </div>
                    <div class="video-info">
                        <span class="video-category"><?= htmlspecialchars($video['category']) ?></span>
                        <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                        <div class="video-meta">
                            <span><i class="bi bi-clock"></i> <?= htmlspecialchars($video['duration'] ?? '—') ?></span>
                            <span><i class="bi bi-eye"></i> <?= htmlspecialchars($video['views'] ?? '—') ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="youtube-cta">
    <div class="container">
        <h2>Subscribe to Our Channel</h2>
        <a href="https://www.youtube.com/channel/UChQ3sgDxx8gyWFl8ISppRHQ" target="_blank" class="cta-button">
            <i class="bi bi-youtube"></i> Subscribe Now
        </a>
    </div>
</section>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>