// Lazy-load CSS background images — elements use data-bg="url(...)" instead of inline style
(function () {
    function applyBg(el) {
        el.style.backgroundImage = el.dataset.bg;
        el.removeAttribute('data-bg');
    }
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    applyBg(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '300px 0px' });
        document.querySelectorAll('[data-bg]').forEach(function (el) {
            observer.observe(el);
        });
    } else {
        document.querySelectorAll('[data-bg]').forEach(applyBg);
    }
}());

// Initialize AOS (Animate On Scroll)
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({
        duration: 800,
        once: true,
        offset: 80,
        easing: 'ease-out-cubic'
    });

    // Clients slider: shows 4 logos, slides one off left / one in from right
    (function () {
        var wrapper = document.querySelector('.clients-wrapper');
        var track   = document.getElementById('clientsTrack');
        if (!track || !wrapper) return;

        // Clone all items once for seamless infinite loop
        track.innerHTML += track.innerHTML;
        var items     = Array.from(track.children);
        var origCount = items.length / 2;  // number of real (non-clone) items
        var current   = 0;
        var busy      = false;
        var SLIDE_MS  = 550;

        var ITEM_MARGIN = 40; // 20px each side (matches CSS margin: 0 20px)

        function getVisible() {
            var vw = wrapper.offsetWidth;
            if (vw >= 992) return 4;   // desktop  — 4 logos, 1 always off-screen
            if (vw >= 600) return 3;   // tablet
            return 2;                  // mobile
        }

        function slotWidth() {
            return wrapper.offsetWidth / getVisible();
        }

        function applyWidths() {
            var w = slotWidth() - ITEM_MARGIN;
            items.forEach(function (item) { item.style.flexBasis = w + 'px'; });
        }
        applyWidths();

        function advance() {
            if (busy) return;
            busy = true;
            current++;
            var w = slotWidth();
            track.style.transition = 'transform ' + SLIDE_MS + 'ms cubic-bezier(0.4,0,0.2,1)';
            track.style.transform  = 'translateX(-' + (current * w) + 'px)';

            setTimeout(function () {
                if (current >= origCount) {
                    // Silently jump back to start (clones make this invisible)
                    track.style.transition = 'none';
                    current = 0;
                    track.style.transform  = 'translateX(0)';
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () { busy = false; });
                    });
                } else {
                    busy = false;
                }
            }, SLIDE_MS + 50);
        }

        setInterval(advance, 2800);

        window.addEventListener('resize', function () {
            applyWidths();
            track.style.transition = 'none';
            track.style.transform  = 'translateX(-' + (current * slotWidth()) + 'px)';
        });
    }());

    // Partner dissolve rotator — cycles .partner-slide and .partner-img as one sequence
    document.querySelectorAll('.partner-sheet').forEach(function(sheet, sheetIdx) {
        var slides = Array.from(sheet.querySelectorAll('.partner-slide, .partner-img'));
        if (!slides.length) return;
        var current = slides.findIndex(function(s) {
            return s.classList.contains('active');
        });
        if (current < 0) current = 0;

        setInterval(function() {
            slides[current].classList.remove('active');
            current = (current + 1) % slides.length;
            slides[current].classList.add('active');
        }, 3500 + sheetIdx * 1200);
    });
});
