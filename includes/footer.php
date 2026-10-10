<?php $basePath = $basePath ?? ''; ?>
<footer class="footer">
    <div class="container">
        <div class="row text-center text-md-start gy-4">
            <div class="col-md-3">
                <div class="footer-info">
                    <div class="footer-logo mb-3">
                        <h3>HOU <i class="fas fa-wifi"></i></h3>
                    </div>
                    <p>Advocating for fair policy, stronger networks, and better opportunities for hotspot operators across Uganda.</p>
                    <div class="social-links d-flex justify-content-center justify-content-md-start">
                        <a href="https://www.facebook.com" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://x.com" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://wa.me/256700000000" target="_blank" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?= $basePath ?>index.php">Home</a></li>
                        <li><a href="<?= $basePath ?>pages/about.php">About Us</a></li>
                        <li><a href="<?= $basePath ?>pages/contact.php">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-3">
                <div class="footer-links">
                    <h4>Our Services</h4>
                    <ul>
                        <li>Membership Support</li>
                        <li>Advocacy</li>
                        <li>Training &amp; Capacity Building</li>
                        <li>Compliance Guidance</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-3">
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Kampala, Uganda</p>
                    <p><i class="fas fa-phone"></i> <a href="tel:+256700000000" style="color:inherit;">+256 700 000 000</a></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:info@hotspotoperatorsunion.org" style="color:inherit;">info@hotspotoperatorsunion.org</a></p>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom mt-4">
        <div class="container">
            <div class="row align-items-center justify-content-between text-center text-md-start">
                <div class="col-md-6">
                    <p class="copyright mb-0">© <?= date('Y') ?> Hotspot Operators Union. All Rights Reserved.</p>
                </div>
                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                    <div class="footer-links-bottom">
                        <a href="<?= $basePath ?>pages/contact.php">Privacy Policy</a>
                        <a href="<?= $basePath ?>pages/contact.php">Terms of Service</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
