document.addEventListener('DOMContentLoaded', function () {
    const backToTop = document.getElementById('backToTop');

    window.addEventListener('scroll', function () {
        if (window.scrollY > 300) {
            backToTop.classList.add('active');
        } else {
            backToTop.classList.remove('active');
        }
    });

    backToTop.addEventListener('click', function (e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#') return;

        const target = document.querySelector(href);
        if (target) {
            e.preventDefault();
            const offset = document.querySelector('.navbar').offsetHeight;
            const top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
});

// Talk to Us button functionality
document.addEventListener('DOMContentLoaded', function () {
    const chatToggle = document.getElementById('chatToggle');
    const chatOptions = document.getElementById('chatOptions');
    const chatLabel = document.getElementById('chatLabel');
    const chatIcon = document.getElementById('chatIcon');

    chatToggle.addEventListener('click', () => {
        chatOptions.classList.toggle('open');
        const isOpen = chatOptions.classList.contains('open');
        chatLabel.textContent = isOpen ? 'Hide' : 'Talk to us';
        chatIcon.className = isOpen ? 'fas fa-times' : 'fab fa-whatsapp';
    });

    closeChat.addEventListener('click', () => {
        chatOptions.classList.remove('open');
        chatLabel.textContent = 'Talk to us';
        chatIcon.className = 'fab fa-whatsapp';
    });
});