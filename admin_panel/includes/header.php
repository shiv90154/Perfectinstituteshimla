<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo htmlspecialchars($pageTitle ?? 'Perfect Institute'); ?></title>
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } }
        };
    </script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js (for pages that need it) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Admin design system -->
    <link rel="stylesheet" href="assets/css/custom.css?v=3">
</head>
<body class="admin-body">

<!-- Mobile Header (visible only on mobile) -->
<header class="lg:hidden admin-mobilebar fixed top-0 left-0 right-0 z-40">
    <div class="flex items-center justify-between px-4 h-14">
        <button id="sidebarToggle" class="p-2 rounded-lg" aria-label="Toggle sidebar">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <h1 class="text-base font-semibold">Perfect Institute</h1>
        <div class="w-10"></div>
    </div>
</header>

<!-- Shell: sidebar (fixed) + content column -->
<div class="admin-shell pt-14 lg:pt-0">
