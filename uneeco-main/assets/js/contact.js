// ── Hours badge: show/hide based on current time ──
(function () {
    const badge = document.querySelector('.hours-badge');
    if (!badge) return;
    const now  = new Date();
    const day  = now.getDay();
    const t    = now.getHours() + now.getMinutes() / 60;

    let isOpen = false;
    if (day >= 1 && day <= 5 && t >= 8  && t < 18) isOpen = true;
    if (day === 6              && t >= 9  && t < 14) isOpen = true;

    if (!isOpen) {
        badge.textContent   = 'Closed';
        badge.style.background   = 'rgba(255,80,80,0.15)';
        badge.style.color        = '#ff7070';
        badge.style.borderColor  = 'rgba(255,80,80,0.25)';
    }
})();

// ── Contact Form submit ──
const form = document.getElementById('contactForm');
if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const email    = document.getElementById('cf-email').value.trim();
        const whatsapp = document.getElementById('cf-whatsapp').value.trim();
        const subject  = document.getElementById('cf-subject').value;
        const message  = document.getElementById('cf-message').value.trim();

        if (!email || !whatsapp || !subject || !message) {
            ['cf-email', 'cf-whatsapp', 'cf-subject', 'cf-message'].forEach(function (id) {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.style.borderColor = '#e05';
                    el.addEventListener('input', function () { el.style.borderColor = ''; }, { once: true });
                }
            });
            return;
        }

        if (typeof grecaptcha !== 'undefined' && !grecaptcha.getResponse()) {
            const rc = document.querySelector('.cf-recaptcha');
            if (rc) {
                rc.style.outline      = '2px solid #e05';
                rc.style.borderRadius = '8px';
                setTimeout(function () { rc.style.outline = ''; }, 2000);
            }
            return;
        }

        const btn = form.querySelector('.cf-submit');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
        btn.disabled  = true;

        const formData = new FormData();
        formData.append('email',     email);
        formData.append('whatsapp',  whatsapp);
        formData.append('subject',   subject);
        formData.append('message',   message);
        if (typeof grecaptcha !== 'undefined') {
            formData.append('recaptcha', grecaptcha.getResponse());
        }

        fetch('../send-mail.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    document.getElementById('formView').style.display = 'none';
                    var success = document.getElementById('formSuccess');
                    success.style.display = 'block';
                    success.style.opacity = 0;
                    requestAnimationFrame(function () {
                        success.style.transition = 'opacity 0.5s ease';
                        success.style.opacity    = 1;
                    });
                } else {
                    btn.innerHTML = 'Send Message <i class="fas fa-arrow-right"></i>';
                    btn.disabled  = false;
                    alert(data.message || 'Something went wrong. Please try again.');
                    if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
                }
            })
            .catch(function () {
                btn.innerHTML = 'Send Message <i class="fas fa-arrow-right"></i>';
                btn.disabled  = false;
                alert('Network error. Please check your connection and try again.');
                if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
            });
    });
}

function resetForm() {
    document.getElementById('formView').style.display   = 'block';
    document.getElementById('formSuccess').style.display = 'none';
    document.getElementById('contactForm').reset();
    var btn = document.querySelector('.cf-submit');
    btn.innerHTML = 'Send Message <i class="fas fa-arrow-right"></i>';
    btn.disabled  = false;
    if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
}
