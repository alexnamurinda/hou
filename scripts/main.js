(function () {
    'use strict';

    // Document ready function
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize AOS (Animate on Scroll)
        AOS.init({
            duration: 800,
            once: false, // Keep animations active on scroll up/down
            offset: 100,
            easing: 'ease-in-out',
        });

        // Refresh AOS on scroll to re-trigger animations
        window.addEventListener('scroll', AOS.refresh);

        // Navbar scroll
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Back to top button
        const backToTopButton = document.querySelector('.back-to-top');
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('active');
            } else {
                backToTopButton.classList.remove('active');
            }
        });

        backToTopButton.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Counter Up
        $('.counter').counterUp({
            delay: 1,
            time: 1
        });

        // Rating system
        const ratingStars = document.querySelectorAll('.rating-star');
        if (ratingStars.length > 0) {
            ratingStars.forEach(star => {
                star.addEventListener('mouseover', function () {
                    const rating = parseInt(this.dataset.rating);

                    // Reset all stars
                    ratingStars.forEach(s => s.classList.remove('active'));

                    // Highlight stars up to the hovered one
                    ratingStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.classList.add('active');
                        }
                    });
                });

                star.addEventListener('click', function () {
                    const rating = parseInt(this.dataset.rating);
                    document.getElementById('reviewRating').value = rating;
                });
            });
        }

        // Submit review form
        const submitReviewBtn = document.getElementById('submitReview');
        if (submitReviewBtn) {
            submitReviewBtn.addEventListener('click', function () {
                const form = document.getElementById('reviewForm');
                const formData = {
                    name: document.getElementById('reviewName').value,
                    email: document.getElementById('reviewEmail').value,
                    rating: document.getElementById('reviewRating').value,
                    service: document.getElementById('reviewService').value,
                    message: document.getElementById('reviewMessage').value
                };

                // Validate form
                let isValid = true;

                if (!formData.name) {
                    document.getElementById('reviewName').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('reviewName').classList.remove('is-invalid');
                }

                if (!formData.email || !validateEmail(formData.email)) {
                    document.getElementById('reviewEmail').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('reviewEmail').classList.remove('is-invalid');
                }

                if (!formData.service) {
                    document.getElementById('reviewService').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('reviewService').classList.remove('is-invalid');
                }

                if (!formData.message) {
                    document.getElementById('reviewMessage').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('reviewMessage').classList.remove('is-invalid');
                }

                if (isValid) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<strong>Thank you for your review!</strong> Your feedback has been submitted successfully.';

                    const modalBody = document.querySelector('.modal-body');
                    modalBody.prepend(alertDiv);

                    form.reset();
                    ratingStars.forEach(s => s.classList.remove('active'));
                    ratingStars.forEach((s, index) => {
                        if (index < 5) {
                            s.classList.add('active');
                        }
                    });

                    setTimeout(() => {
                        alertDiv.remove();
                        $('#reviewModal').modal('hide');
                    }, 3000);
                }
            });
        }

        // Contact form submission
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = {
                    name: document.getElementById('contactName').value,
                    email: document.getElementById('contactEmail').value,
                    subject: document.getElementById('contactSubject').value,
                    message: document.getElementById('contactMessage').value
                };

                let isValid = true;

                if (!formData.name) {
                    document.getElementById('contactName').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('contactName').classList.remove('is-invalid');
                }

                if (!formData.email || !validateEmail(formData.email)) {
                    document.getElementById('contactEmail').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('contactEmail').classList.remove('is-invalid');
                }

                if (!formData.subject) {
                    document.getElementById('contactSubject').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('contactSubject').classList.remove('is-invalid');
                }

                if (!formData.message) {
                    document.getElementById('contactMessage').classList.add('is-invalid');
                    isValid = false;
                } else {
                    document.getElementById('contactMessage').classList.remove('is-invalid');
                }

                if (isValid) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<strong>Thank you for contacting us!</strong> Your message has been sent successfully. We will get back to you soon.';

                    contactForm.prepend(alertDiv);
                    contactForm.reset();

                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                }
            });
        }

        // Initialize dropdowns
        var dropdowns = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        dropdowns.map(function (dropdown) {
            return new bootstrap.Dropdown(dropdown);
        });

        // Animate service cards
        document.querySelectorAll('.service-card').forEach((card, index) => {
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-delay', (index + 1) * 100);
        });

        // Property filter
        const propertyFilter = document.getElementById('propertyFilter');
        if (propertyFilter) {
            propertyFilter.addEventListener('change', function () {
                const value = this.value;
                const propertyCards = document.querySelectorAll('.property-card');

                propertyCards.forEach(card => {
                    if (value === 'all' || card.getAttribute('data-type') === value) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Initialize modals
        var modals = [].slice.call(document.querySelectorAll('[data-bs-toggle="modal"]'));
        modals.map(function (modal) {
            return new bootstrap.Modal(modal);
        });
    });

    // Email validation function
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
})();

// Carousel initialization (outside DOMContentLoaded)
const myCarousel = document.querySelector('#propertyCarousel');
if (myCarousel) {
    const carousel = new bootstrap.Carousel(myCarousel, {
        interval: 6000,
        ride: 'carousel'
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const navLinks = document.querySelectorAll('.nav-link');
    const navbarCollapse = document.getElementById('navbarNav');
    const bsCollapse = new bootstrap.Collapse(navbarCollapse, { toggle: false });

    // Collapse on nav-link click
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                bsCollapse.hide();
            }
        });
    });

    // Collapse when clicking outside navbar
    document.addEventListener('click', function (event) {
        const isClickInside = navbarCollapse.contains(event.target) || event.target.classList.contains('navbar-toggler');
        if (!isClickInside && navbarCollapse.classList.contains('show')) {
            bsCollapse.hide();
        }
    });
});