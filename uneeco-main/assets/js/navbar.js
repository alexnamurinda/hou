// Navbar scroll effect
const navbar = document.querySelector('.navbar');
const topBanner = document.querySelector('.top-banner');
const heroStack = document.querySelector('.hero-stack');
const topBannerHeight = topBanner ? topBanner.offsetHeight : 50;

if (document.readyState !== 'loading') {
    if (heroStack) heroStack.classList.add('animate');
} else {
    document.addEventListener('DOMContentLoaded', function () {
        if (heroStack) heroStack.classList.add('animate');
    });
}

window.addEventListener('scroll', function () {
    if (window.scrollY > topBannerHeight) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

window.addEventListener('scroll', function () {
    if (window.scrollY > topBannerHeight) {
        navbar.classList.add('scrolled');
        // Keep navbar below banner on small screens
        if (window.innerWidth < 768) {
            //navbar.style.top = topBanner ? topBanner.offsetHeight + 'px' : '0';
        }
    } else {
        navbar.classList.remove('scrolled');
        if (window.innerWidth < 768) {
           // navbar.style.top = topBanner ? topBanner.offsetHeight + 'px' : '0';
        }
    }
});

// Mobile navbar auto-close on link click
const navbarCollapse = document.getElementById('navbarNav');
if (navbarCollapse) {
    const bsCollapse = new bootstrap.Collapse(navbarCollapse, { toggle: false });
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                bsCollapse.hide();
            }
        });
    });
}

// Search functionality
const searchBtn = document.getElementById('searchBtn');
const navLinks = document.getElementById('navLinks');
const searchBar = document.getElementById('searchBar');
const searchInput = document.getElementById('searchInput');
const searchIcon = searchBtn ? searchBtn.querySelector('i') : null;

if (searchBtn && navLinks && searchBar && searchInput && searchIcon) {
    searchBtn.addEventListener('click', function () {
        if (searchBar.style.display === 'none' || searchBar.style.display === '') {
            navLinks.style.display = 'none';
            searchBar.style.display = 'block';
            searchIcon.className = 'fas fa-times';
            searchInput.focus();
        } else {
            navLinks.style.display = 'flex';
            searchBar.style.display = 'none';
            searchIcon.className = 'fas fa-search';
            searchInput.value = '';
        }
    });

    // Handle enter key for search
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const query = searchInput.value.trim();
            if (query) {
                const isInPages = window.location.pathname.includes('/pages/');
                const searchPath = isInPages ? 'search.html' : 'pages/search.html';
                window.location.href = `${searchPath}?q=${encodeURIComponent(query)}`;
            }
        }
    });
}

// Cart functionality (placeholder)
const cartBtn = document.getElementById('cartBtn');
if (cartBtn) {
    cartBtn.addEventListener('click', function () {
        window.location.reload();
    });
}

// Mobile Sidebar Navigation
(function () {
    const navToggler = document.getElementById('navToggler');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileSidebarOverlay = document.getElementById('mobileSidebarOverlay');
    const mobileSidebarClose = document.getElementById('mobileSidebarClose');

    if (!navToggler || !mobileSidebar) return;

    // Inject search bar between header and nav
    const sidebarNav = mobileSidebar.querySelector('.mobile-sidebar-nav');
    if (sidebarNav && !mobileSidebar.querySelector('.mobile-sidebar-search')) {
        const searchBlock = document.createElement('div');
        searchBlock.className = 'mobile-sidebar-search';
        searchBlock.innerHTML = `
            <div class="mobile-search-wrap">
                <i class="fas fa-search mobile-search-icon"></i>
                <input type="text" class="mobile-search-input" id="mobileSidebarSearchInput"
                    placeholder="Search products…" autocomplete="off" spellcheck="false">
                <button class="mobile-search-clear" id="mobileSidebarSearchClear" aria-label="Clear" style="display:none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
        mobileSidebar.insertBefore(searchBlock, sidebarNav);

        const input = document.getElementById('mobileSidebarSearchInput');
        const clearBtn = document.getElementById('mobileSidebarSearchClear');

        input.addEventListener('input', function () {
            clearBtn.style.display = this.value ? 'flex' : 'none';
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            clearBtn.style.display = 'none';
            input.focus();
        });

        function doMobileSearch() {
            const q = input.value.trim();
            if (!q) return;
            const inPages = window.location.pathname.includes('/pages/');
            window.location.href = (inPages ? 'search.html' : 'pages/search.html')
                + '?q=' + encodeURIComponent(q);
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') doMobileSearch();
        });
    }

    function openSidebar() {
        mobileSidebar.classList.add('open');
        if (mobileSidebarOverlay) mobileSidebarOverlay.classList.add('open');
        mobileSidebar.setAttribute('aria-hidden', 'false');
        navToggler.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        // Focus the search input after the slide-in animation
        const input = document.getElementById('mobileSidebarSearchInput');
        if (input) setTimeout(() => input.focus(), 360);
    }

    function closeSidebar() {
        mobileSidebar.classList.remove('open');
        if (mobileSidebarOverlay) mobileSidebarOverlay.classList.remove('open');
        mobileSidebar.setAttribute('aria-hidden', 'true');
        navToggler.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    navToggler.addEventListener('click', openSidebar);

    if (mobileSidebarClose) mobileSidebarClose.addEventListener('click', closeSidebar);
    if (mobileSidebarOverlay) mobileSidebarOverlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.mobile-sidebar-link').forEach(link => {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });
})();
