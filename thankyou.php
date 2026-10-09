<?php
$username = $_GET['username'] ?? null;
$expiry   = $_GET['expiry'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Thank You - Hotspot Operators Union</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/mainstyles.css" rel="stylesheet">
    <link href="css/thankyou.css" rel="stylesheet">
</head>

<body class="ty-page">

    <div class="ty-wrap">

        <img src="images/thankyou.jpeg" alt="Thank You - Hotspot Operators Union" class="ty-flier">

        <?php if ($username): ?>
            <div class="ty-welcome">
                <i class="fas fa-user-check"></i>
                Welcome, <strong><?= htmlspecialchars($username) ?></strong>
                <?php if ($expiry): ?><span class="ty-welcome-expiry">· member since <?= htmlspecialchars($expiry) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="index.php" class="ty-wa-banner">
            <span class="ty-wa-icon"><i class="fas fa-users"></i></span>
            <span class="ty-wa-text">
                <span class="ty-wa-title">Thank you for joining us!</span>
                <span class="ty-wa-sub">We are ready to support and strengthen the hotspot operators community</span>
            </span>
            <span class="ty-wa-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script>
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && window.confetti) {
            const brandColors = ['#2563eb', '#0ea5e9', '#22c55e', '#f59e0b'];
            const corners = () => {
                confetti({ particleCount: 70, spread: 65, startVelocity: 38, origin: { x: 0.2, y: 0.25 }, colors: brandColors });
                confetti({ particleCount: 70, spread: 65, startVelocity: 38, origin: { x: 0.8, y: 0.25 }, colors: brandColors });
            };

            corners();
            setTimeout(corners, 2000);
            setTimeout(() => {
                confetti({ particleCount: 200, spread: 100, startVelocity: 45, origin: { x: 0.5, y: 0.3 }, colors: brandColors });
            }, 4500);
        }
    </script>
</body>

</html>
