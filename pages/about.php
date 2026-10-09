<?php
$basePath = '../';
$activePage = 'about';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Hotspot Operators Union</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../css/mainstyles.css" rel="stylesheet">
    <link href="../css/responsive.css" rel="stylesheet">
    <link href="../css/about.css" rel="stylesheet">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center" data-aos="fade-up">
                    <h1>About Us</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">About Us</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <section class="our-story-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="story-content">
                        <h3>Our Story</h3>
                        <p class="lead">A united voice for hotspot operators across Uganda</p>
                        <p>The Hotspot Operators Union was formed to give local connectivity entrepreneurs a stronger voice in business, policy, and community growth. We believe operators deserve support, recognition, and practical guidance as they build service networks that keep communities connected.</p>
                        <p>What began as a grassroots effort to bring operators together has grown into a movement focused on professionalism, inclusion, and sustainable digital access across urban and peri-urban communities.</p>

                        <div class="story-stats">
                            <div class="story-stat">
                                <span class="story-stat-value">2024</span>
                                <span class="story-stat-label">Founded</span>
                            </div>
                            <div class="story-stat">
                                <span class="story-stat-value">500+</span>
                                <span class="story-stat-label">Members</span>
                            </div>
                            <div class="story-stat">
                                <span class="story-stat-value">24/7</span>
                                <span class="story-stat-label">Support</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
                    <div class="story-image">
                        <img src="../images/history.jpg" alt="Hotspot Operators Union Story" class="img-fluid rounded">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="zoom-in">
                    <div class="coverage-map text-center">
                        <i class="fas fa-map-marked-alt" style="font-size: 5rem; margin-bottom: 20px;"></i>
                        <h3>Building a Stronger Sector</h3>
                        <p>We bring together hotspot operators, community leaders, and local business advocates to strengthen service quality and collective action.</p>
                        <div class="row mt-4">
                            <div class="col-6">
                                <h4>500+</h4>
                                <p>Members Connected</p>
                            </div>
                            <div class="col-6">
                                <h4>24/7</h4>
                                <p>Community Support</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-flex justify-content-center">
                    <div class="about-content text-center w-100 px-3 px-md-5">
                        <h2>Our Mission</h2>
                        <p style="text-align:justify;">To protect, support, and elevate hotspot operators by creating a united platform for advocacy, skills development, business growth, and ethical service delivery across Uganda.</p>
                        <p style="text-align:justify;">We believe connectivity should not only be accessible, but also responsibly managed, fairly regulated, and sustainably grown.</p>
                        <div class="row justify-content-center">
                            <div class="col-12 col-md-6 mb-4">
                                <div class="feature-item">
                                    <i class="fas fa-lightbulb feature-icon"></i>
                                    <h4>Innovation</h4>
                                    <p>Encouraging smarter, more resilient operator models.</p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-4">
                                <div class="feature-item">
                                    <i class="fas fa-heart feature-icon"></i>
                                    <h4>Community</h4>
                                    <p>Building trust between operators and the communities they serve.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../scripts/main.js"></script>
</body>

</html>
