<?php
// pages/blog-details.php
// Include config once
require_once $_SERVER['DOCUMENT_ROOT'] . "/includes/config.php";

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($slug === '') {
    http_response_code(404);
    die("Invalid blog URL.");
}

// Secure: prevent any injection
if (!preg_match('/^[a-z0-9\-]+$/i', $slug)) {
    http_response_code(404);
    die("Invalid blog URL.");
}

// Fetch blog using prepared statement
$stmt = $conn->prepare("SELECT * FROM blogs WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    http_response_code(404);
    die("Blog not found.");
}

// Helper to remove <script> tags (basic XSS protection for stored content)
function safeHtml($html) {
    // Remove script tags and their content
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    // Optionally remove event handlers (onclick, etc.) – for extra safety
    $html = preg_replace('/\son\w+="[^"]*"/i', '', $html);
    return $html;
}

// Sanitize output
$title = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars($data['category'] ?? '', ENT_QUOTES, 'UTF-8');
$content = safeHtml($data['content']); // stored HTML
$image = htmlspecialchars($data['image'] ?? '', ENT_QUOTES, 'UTF-8');
$pdf_file = htmlspecialchars($data['pdf_file'] ?? '', ENT_QUOTES, 'UTF-8');
$created_at = date("d M Y", strtotime($data['created_at']));
include __DIR__ . '/../includes/header.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo substr(strip_tags($content), 0, 160); ?>">
    <meta property="og:title" content="<?php echo $title; ?>">
    <meta property="og:description" content="<?php echo substr(strip_tags($content), 0, 160); ?>">
    <?php if ($image): ?>
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/uploads/<?php echo $image; ?>">
    <?php endif; ?>
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/blog/<?php echo urlencode($slug); ?>">
    <title><?php echo $title; ?> - Perfect Institute</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... (your existing CSS unchanged) ... */
        :root {
            --navy: #0a192f;
            --navy-dark: #071224;
            --red: #e63946;
            --white: #ffffff;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #e5e7eb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: #333;
            line-height: 1.6;
        }

        .blog-details-page {
            padding: 60px 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .blog-details-card {
            background: var(--white);
            border-radius: 22px;
            padding: 42px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        }

        .blog-details-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 12px;
            line-height: 1.25;
        }

        .blog-meta {
            font-size: 0.95rem;
            color: var(--gray);
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .blog-details-img {
            width: 100%;
            max-height: 480px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 34px;
        }

        .blog-details-content {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #2d3e50;
        }

        /* ... (rest of your CSS unchanged) ... */
        .blog-details-content h1,
        .blog-details-content h2,
        .blog-details-content h3,
        .blog-details-content h4,
        .blog-details-content h5,
        .blog-details-content h6 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--navy);
            line-height: 1.3;
        }
        .blog-details-content h1:first-child,
        .blog-details-content h2:first-child,
        .blog-details-content h3:first-child,
        .blog-details-content h4:first-child,
        .blog-details-content h5:first-child,
        .blog-details-content h6:first-child {
            margin-top: 0;
        }
        .blog-details-content p {
            margin-bottom: 1rem;
        }
        .blog-details-content p:last-child {
            margin-bottom: 0;
        }
        .blog-details-content ul,
        .blog-details-content ol {
            margin: 0 0 1rem 1.5rem;
        }
        .blog-details-content li {
            margin-bottom: 0.25rem;
        }
        .blog-details-content li:last-child {
            margin-bottom: 0;
        }
        .blog-details-content blockquote {
            border-left: 4px solid var(--red);
            padding: 0.75rem 1.25rem;
            background: #f9f9f9;
            color: #555;
            font-style: italic;
            margin: 1.5rem 0;
            border-radius: 0 12px 12px 0;
        }
        .blog-details-content pre {
            background: #f4f4f4;
            padding: 1rem;
            overflow-x: auto;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .blog-details-content code {
            background: #f0f0f0;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }
        .blog-details-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .blog-details-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        .blog-details-content th,
        .blog-details-content td {
            border: 1px solid var(--border);
            padding: 0.5rem;
            text-align: left;
        }
        .blog-details-content th {
            background: #f2f2f2;
        }
        .blog-details-content hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 2px dashed var(--border);
        }
        .blog-pdf {
            margin-top: 40px;
            padding: 24px;
            background: #fff5f5;
            border-radius: 14px;
            font-weight: 600;
            color: #dc2626;
            border-left: 5px solid var(--red);
        }
        .blog-pdf a {
            display: inline-block;
            margin-top: 12px;
            background: var(--navy);
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.2s;
        }
        .blog-pdf a:hover {
            background: var(--red);
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 45px;
            background: var(--navy);
            color: var(--white);
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: var(--red);
            transform: translateX(-5px);
        }
        @media (max-width: 768px) {
            .blog-details-card { padding: 26px; }
            .blog-details-title { font-size: 1.8rem; }
            .blog-details-img { max-height: 300px; }
            .blog-details-content { font-size: 1rem; }
        }
        @media (max-width: 480px) {
            .blog-details-card { padding: 20px; }
            .blog-details-title { font-size: 1.5rem; }
            .blog-meta { flex-direction: column; gap: 5px; }
            .back-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<section class="blog-details-page">
    <div class="container">
        <article class="blog-details-card">
            <h1 class="blog-details-title"><?php echo $title; ?></h1>
            <div class="blog-meta">
                <span><i class="far fa-calendar-alt"></i> <?php echo $created_at; ?></span>
                <?php if (!empty($category)): ?>
                    <span><i class="far fa-folder"></i> <?php echo ucfirst($category); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($image): ?>
                <img src="/uploads/<?php echo $image; ?>" 
                     class="blog-details-img" 
                     alt="<?php echo $title; ?>"
                     loading="lazy">
            <?php endif; ?>

            <div class="blog-details-content">
                <?php echo $content; ?>
            </div>

            <?php if ($pdf_file): ?>
                <div class="blog-pdf">
                    <i class="fas fa-file-pdf"></i> Study Material Available
                    <br>
                    <a href="/uploads/<?php echo $pdf_file; ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
            <?php endif; ?>

            <a href="/blog" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Blogs
            </a>
        </article>
    </div>
</section>

</body>
</html>
<?php
// ===== INCLUDE FOOTER =====
include __DIR__ . '/../includes/footer.php';
?>