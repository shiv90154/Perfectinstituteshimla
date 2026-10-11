<?php
// pages/blog.php - Blog listing page
$meta_title = 'StudyHub Blog - Perfect Institute';
$meta_description = 'Read our latest articles, study tips, and educational resources to boost your learning.';
$meta_image = 'https://perfectinstitute.in/assets/images/og-blog.jpeg'; // Update with actual image URL

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . strtok($request_uri, '?');

// Include configuration and database
include $_SERVER['DOCUMENT_ROOT'] . "/includes/config.php";

// ======================== CATEGORY FILTER ========================
$category = $_GET['category'] ?? '';
$category = trim(strtolower($category));

// ======================== FETCH CATEGORIES ========================
$catRes = $conn->query("
    SELECT DISTINCT TRIM(category) AS category
    FROM blogs
    WHERE category IS NOT NULL AND category != ''
    ORDER BY category ASC
");

// ======================== FETCH BLOGS ========================
if ($category !== '') {
    $stmt = $conn->prepare("
        SELECT *
        FROM blogs
        WHERE TRIM(LOWER(category)) = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("
        SELECT *
        FROM blogs
        ORDER BY id DESC
    ");
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

    <!-- Font Awesome (already used in the page) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

    <!-- Blog-specific styles (updated to match site theme) -->
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
            --shadow: 0 5px 12px rgba(0,0,0,0.04);
            --shadow-hover: 0 12px 24px rgba(0,0,0,0.08);
        }

        /* ----- HERO SECTION ----- */
        .blog-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            padding: 80px 0 60px;
            text-align: center;
            color: var(--white);
        }

        .blog-hero h1 {
            font-size: clamp(2rem, 5vw, 2.5rem);
            font-weight: 700;
            margin-bottom: 12px;
        }

        .blog-hero p {
            font-size: clamp(1rem, 2vw, 1.1rem);
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* ----- CONTAINER ----- */
        .blog-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ----- CATEGORY FILTER (scrollable) ----- */
        .blog-category-filter {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 20px 0 20px;
            margin: 0 0 40px 0;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) var(--light-gray);
            background: var(--white);
        }

        .blog-category-filter::-webkit-scrollbar {
            height: 5px;
        }
        .blog-category-filter::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        .blog-category-filter::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        .blog-cat-btn {
            padding: 8px 20px;
            border-radius: 30px;
            border: 1px solid var(--light-gray);
            background: var(--white);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .blog-cat-btn:hover,
        .blog-cat-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* ----- BLOG GRID – RESPONSIVE CSS GRID ----- */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 60px;
        }

        @media (max-width: 992px) {
            .blog-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .blog-grid { grid-template-columns: 1fr; }
        }

        /* ----- BLOG CARD (matching site card style) ----- */
        .blog-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--light-gray);
        }

        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        /* Image container */
        .blog-card-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background: var(--off-white);
        }

        .blog-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .blog-card:hover .blog-card-image img {
            transform: scale(1.05);
        }

        /* Placeholder when no image */
        .blog-card-image.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 2rem;
            background: var(--light-gray);
        }

        /* Date */
        .blog-card-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin: 16px 20px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Title */
        .blog-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0 20px 10px;
            line-height: 1.4;
        }

        /* Excerpt */
        .blog-card-excerpt {
            font-size: 0.9rem;
            color: var(--gray);
            line-height: 1.5;
            margin: 0 20px 16px;
            flex-grow: 1;
        }

        /* PDF Badge */
        .blog-card-pdf {
            font-size: 0.8rem;
            font-weight: 600;
            color: #dc2626;
            background: #fff5f5;
            padding: 5px 12px;
            border-radius: 30px;
            margin: 0 20px 15px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
        }

        /* Read More Button */
        .blog-card-btn {
            display: block;
            background: var(--primary);
            color: var(--white);
            padding: 12px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
            margin: 0 20px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .blog-card-btn:hover {
            background: var(--secondary);
        }

        /* No blogs message */
        .blog-no-results {
            text-align: center;
            padding: 80px 0;
            font-size: 1.2rem;
            color: var(--gray);
            grid-column: 1 / -1;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .blog-hero { padding: 60px 0 40px; }
            .blog-category-filter { margin-bottom: 30px; }
            .blog-card-title { font-size: 1.1rem; }
            .blog-card-excerpt { font-size: 0.85rem; }
            .blog-card-date, .blog-card-title, .blog-card-excerpt, .blog-card-pdf, .blog-card-btn {
                margin-left: 16px;
                margin-right: 16px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="blog-scope">
    <!-- HERO -->
    <section class="blog-hero">
        <div class="blog-container">
            <h1>Start Learning Here</h1>
            <p>Explore useful educational articles curated especially for learners.</p>
        </div>
    </section>

    <!-- CATEGORY FILTER -->
    <div class="blog-container">
        <div class="blog-category-filter">
            <a href="/blog" class="blog-cat-btn <?= $category == '' ? 'active' : '' ?>">All</a>
            <?php while ($c = $catRes->fetch_assoc()):
                $catName = trim($c['category']);
                $catSlug = strtolower($catName);
            ?>
                <a href="/blog?category=<?= urlencode($catSlug) ?>" class="blog-cat-btn <?= $category === $catSlug ? 'active' : '' ?>">
                    <?= htmlspecialchars(ucfirst($catName)) ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- BLOG LIST -->
    <section class="blog-page">
        <div class="blog-container">
            <div class="blog-grid">
                <?php
                if ($res && $res->num_rows > 0):
                    while ($b = $res->fetch_assoc()):
                        if (empty($b['slug'])) continue;

                        $hasImage = !empty($b['image']);
                        $hasPDF   = !empty($b['pdf_file']);

                        $excerpt = !empty($b['content']) 
                            ? substr(strip_tags($b['content']), 0, 120) . '…' 
                            : 'Practical learning insights crafted to help students improve knowledge and skills.';
                ?>
                        <div class="blog-card">
                            <?php if ($hasImage): ?>
                                <div class="blog-card-image">
                                    <img src="/uploads/<?= htmlspecialchars($b['image']) ?>" 
                                         alt="<?= htmlspecialchars($b['title']) ?>"
                                         loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="blog-card-image placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>

                            <div class="blog-card-date">
                                <?= date("d M Y", strtotime($b['created_at'])) ?>
                            </div>

                            <h3 class="blog-card-title">
                                <?= htmlspecialchars($b['title']) ?>
                            </h3>

                            <p class="blog-card-excerpt">
                                <?= htmlspecialchars($excerpt) ?>
                            </p>

                            <?php if ($hasPDF): ?>
                                <div class="blog-card-pdf">
                                    <i class="fas fa-file-pdf"></i> Study Material Included
                                </div>
                            <?php endif; ?>

                            <a href="/blog-details?slug=<?= urlencode($b['slug']) ?>" class="blog-card-btn">
                                Read Full Blog <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                <?php
                    endwhile;
                else:
                ?>
                    <div class="blog-no-results">
                        No blogs found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>