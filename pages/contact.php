<?php
$basePath = '../';
$activePage = 'contact';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/hou_logo.png?v=<?php echo filemtime(__DIR__ . '/../images/hou_logo.png'); ?>">
    <link rel="apple-touch-icon" href="../images/hou_logo.png?v=<?php echo filemtime(__DIR__ . '/../images/hou_logo.png'); ?>">
    <title>Contact Us - Hotspot Operators Union</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../css/mainstyles.css" rel="stylesheet">
    <link href="../css/responsive.css" rel="stylesheet">
    <link href="../css/contact.css" rel="stylesheet">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center" data-aos="fade-up">
                    <h1>Contact Us</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-info-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="contact-info-card contact-call-card text-center h-100">
                        <div class="call-badge">
                            <div class="call-badge-ring"></div>
                            <div class="call-badge-inner">
                                <i class="fas fa-headset"></i>
                                <span class="call-badge-247">24/7</span>
                                <span class="call-badge-support">SUPPORT</span>
                            </div>
                        </div>
                        <h3>Call Us</h3>
                        <p class="call-number"><i class="fab fa-whatsapp"></i><a href="https://wa.me/256700000000" target="_blank">+256 700 000 000</a></p>
                        <p class="call-number"><i class="fas fa-phone-alt"></i><a href="tel:+256700000000">+256 700 000 000</a></p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="contact-info-card text-center h-100">
                        <div class="icon-wrapper mb-3">
                            <i class="fas fa-map-marker-alt fa-3x text-primary"></i>
                        </div>
                        <h3>Our Location</h3>
                        <p>Kampala, Uganda</p>
                        <a href="https://maps.google.com/?q=Kampala+Uganda" target="_blank" class="btn btn-outline-primary mt-2">Get Directions</a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="contact-info-card text-center h-100">
                        <div class="icon-wrapper mb-3">
                            <i class="fas fa-envelope fa-3x text-primary"></i>
                        </div>
                        <h3>Email Us</h3>
                        <p>General Inquiries:<br><a href="mailto:info@hotspotoperatorsunion.org">info@hotspotoperatorsunion.org</a></p>
                        <a href="mailto:info@hotspotoperatorsunion.org" class="btn btn-outline-primary mt-2">Send Us a Message</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>FAQs</h2>
                <p>Quick answers to what people usually ask us</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="contactFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Who can join the union?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    The union is open to hotspot operators, connectivity entrepreneurs, community network managers, and dedicated stakeholders working to strengthen local digital access.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What does the union do?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    We advocate for better standards, share knowledge, build professional networks, and support members with practical guidance on growing reliable and responsible hotspot businesses.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    How do I become a member?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    Reach out through our contact channels and let us know your business, experience, and interest in joining the union. We will guide you through the process.
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
