<?php
$basePath = '';
$activePage = 'home';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/hou_logo.png?v=<?php echo filemtime(__DIR__ . '/images/hou_logo.png'); ?>">
    <link rel="apple-touch-icon" href="images/hou_logo.png?v=<?php echo filemtime(__DIR__ . '/images/hou_logo.png'); ?>">
    <title>Hotspot Operators Union | Advocacy for Connected Communities</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="css/mainstyles.css?v=<?php echo filemtime(__DIR__ . '/css/mainstyles.css'); ?>" rel="stylesheet">
    <link href="css/responsive.css?v=<?php echo filemtime(__DIR__ . '/css/responsive.css'); ?>" rel="stylesheet">
</head>

<body>

    <?php include 'includes/nav.php'; ?>

    <header class="hero-section">
        <div class="carousel hero-carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <div class="hero-designed-slide" style="background-image:url('images/helo.png')">
                        <div class="hero-design-overlay">
                            <div class="container">
                                <div class="row">
                                    <div class="col-lg-6 col-md-8 col-11">
                                        <div class="hero-design-content">
                                            <h1 class="hd-title">Representing<br>Hotspot Operators</h1>
                                            <p class="hd-sub">
                                                Supporting businesses, communities, and youth-led connectivity ventures across Uganda.
                                            </p>
                                            <div class="hd-wifi-badge">
                                                <i class="fas fa-users"></i>
                                                What We Do
                                            </div>
                                            <div class="hd-pkg-row">
                                                <div class="hd-pkg hd-pkg-blue">
                                                    <i class="fas fa-handshake hd-pkg-icon"></i>
                                                    <div class="hd-pkg-price">Advocacy</div>
                                                    <span class="hd-pkg-tag hd-tag-blue">Voice</span>
                                                </div>
                                                <div class="hd-pkg hd-pkg-red">
                                                    <i class="fas fa-graduation-cap hd-pkg-icon"></i>
                                                    <div class="hd-pkg-price hd-price-red">Training</div>
                                                    <span class="hd-pkg-tag hd-tag-red">Skills</span>
                                                </div>
                                                <div class="hd-pkg hd-pkg-green">
                                                    <i class="fas fa-shield-alt hd-pkg-icon"></i>
                                                    <div class="hd-pkg-price">Support</div>
                                                    <span class="hd-pkg-tag hd-tag-green">Protection</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="hero-cta">
                                <a href="client/index.html?v=20261010-2#signup" class="hd-join-wa">
                                    Become a Member
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="featured-properties-carousel" id="membership">
        <div class="container">
            <div class="section-header text-center text-black" data-aos="fade-up">
                <h2>Why Join the Union?</h2>
                <p>We help hotspot operators build stronger businesses and a more united voice.</p>
            </div>

            <div class="row">
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="membership-benefit">
                        <h4><i class="fas fa-balance-scale"></i> Policy Advocacy</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Stronger representation for operators</li>
                            <li><i class="fas fa-check text-success"></i> Fairer business and operating standards</li>
                            <li><i class="fas fa-check text-success"></i> Better community engagement</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="membership-benefit">
                        <h4><i class="fas fa-chart-line"></i> Business Growth</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Shared market knowledge</li>
                            <li><i class="fas fa-check text-success"></i> Practical support for operators</li>
                            <li><i class="fas fa-check text-success"></i> Better opportunities for collaboration</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-12 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="membership-benefit">
                        <h4><i class="fas fa-user-shield"></i> Member Protection</h4>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Access to trusted guidance</li>
                            <li><i class="fas fa-check text-success"></i> Networking with like-minded operators</li>
                            <li><i class="fas fa-check text-success"></i> Support with compliance and service excellence</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="scripts/main.js?v=<?php echo filemtime(__DIR__ . '/scripts/main.js'); ?>"></script>

    <script>
        AOS.init();
    </script>

</body>
</html>
