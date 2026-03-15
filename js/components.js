'use strict';

/**
 * components.js — MAS Auto Component Loader
 * ─────────────────────────────────────────
 * PURPOSE: Fetches navbar.html and footer.html from /components/ and injects
 *          them into every page. After injection it wires up all interactive
 *          behaviour so the rest of the codebase (global.js, page scripts)
 *          can assume the navbar/footer DOM already exists.
 *
 * EXECUTION ORDER (guaranteed):
 *   1. loadComponents()   — fetch & inject HTML for both components
 *   2. setActiveLink()    — mark the current page link as .active
 *   3. initNavScroll()    — add .scrolled class to navbar on scroll
 *   4. initHamburger()    — toggle mobile drawer + animate hamburger icon
 *   5. initFooterYear()   — write current year into copyright line
 *
 * HOW ACTIVE DETECTION WORKS:
 *   We compare the browser's current pathname against each link's href.
 *   The root path "/" matches the Home link.
 *   All other links match by checking if the pathname ends with the
 *   filename in the href (e.g. "about.html"). This means the same
 *   navbar.html works whether the page is at /pages/about.html or
 *   hosted at a CDN path — no hardcoding needed.
 */

(async function () {

    /* ─── 1. RESOLVE COMPONENT BASE PATH ───────────────────────────────────
     *
     * Because index.html lives at the root and pages/*.html live one level
     * deeper, we need to resolve where /components/ actually is relative to
     * the current page when served from a file:// protocol (local dev).
     *
     * On a real web server (HTTP/HTTPS) with root-relative paths (/components/)
     * this is irrelevant — the server handles it. But for local file:// opens
     * we calculate the path manually so the fetch() doesn't 404.
     *
     * Strategy: count how many path segments deep we are and prepend "../"
     * for each segment beyond the root.
     */
    function resolveBase() {
        const protocol = window.location.protocol;
        if (protocol === 'http:' || protocol === 'https:') {
            // On a real server, always use root-relative paths.
            return '/components/';
        }
        // file:// — calculate relative depth
        const depth = window.location.pathname.split('/').filter(Boolean).length;
        // index.html at root = depth 1, pages/about.html = depth 2
        const prefix = depth <= 1 ? '' : '../'.repeat(depth - 1);
        return prefix + 'components/';
    }

    const BASE = resolveBase();

    /* ─── 2. FETCH HELPER ───────────────────────────────────────────────────
     *
     * fetchHTML(filename) → returns the raw HTML string from a component file.
     * Returns an empty string on failure so the page still loads without the
     * component rather than throwing an uncaught error.
     */
    async function fetchHTML(filename) {
        try {
            const res = await fetch(BASE + filename);
            if (!res.ok) throw new Error(`HTTP ${res.status} for ${filename}`);
            return await res.text();
        } catch (err) {
            console.error(`[MAS Auto] Could not load component "${filename}":`, err);
            return '';
        }
    }

    async function loadComponents() {
        const [navHTML, footHTML] = await Promise.all([
            fetchHTML('navbar.html'),
            fetchHTML('footer.html')
        ]);

        const navSlot = document.getElementById('navbar-placeholder');
        const footSlot = document.getElementById('footer-placeholder');

        if (navSlot) navSlot.innerHTML = navHTML;
        if (footSlot) footSlot.innerHTML = footHTML;
    }

    /* ACTIVE LINK DETECTION*/
    function setActiveLink() {
        const path = window.location.pathname;

        // Normalise to just the filename: "/pages/about.html" → "about.html"
        // Root path "/" or "/index.html" → "index.html"
        const filename = path === '/' || path === ''
            ? 'index.html'
            : path.split('/').pop() || 'index.html';

        const allNavLinks = document.querySelectorAll(
            '.nav-links a, .nav-mobile a'
        );

        allNavLinks.forEach(link => {
            link.classList.remove('active');

            // Get just the filename from the link's href attribute
            // e.g. "../pages/about.html" → "about.html", "index.html" → "index.html"
            const linkFile = (link.getAttribute('href') || '')
                .split('/').pop()
                .split('#')[0]; // strip any #anchor suffix

            if (linkFile === filename) {
                link.classList.add('active');
            }
        });
    }

    /* NAVBAR SCROLL BEHAVIOUR*/
    function initNavScroll() {
        const navbar = document.getElementById('navbar');
        if (!navbar) return;

        const THRESHOLD = 60; // pixels from top before .scrolled activates

        function onScroll() {
            navbar.classList.toggle('scrolled', window.scrollY > THRESHOLD);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); // run once immediately in case page loads mid-scroll
    }

    /* MOBILE MENU */
    function initHamburger() {
        const hamburger = document.getElementById('hamburger');
        const mobileNav = document.getElementById('mobileNav');
        if (!hamburger || !mobileNav) return;

        function openMenu() {
            mobileNav.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            const [s0, s1, s2] = hamburger.querySelectorAll('span');
            s0.style.transform = 'rotate(45deg) translate(5px, 5px)';
            s1.style.opacity = '0';
            s2.style.transform = 'rotate(-45deg) translate(5px, -5px)';
        }

        function closeMenu() {
            mobileNav.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            const [s0, s1, s2] = hamburger.querySelectorAll('span');
            s0.style.transform = '';
            s1.style.opacity = '';
            s2.style.transform = '';
        }

        function toggleMenu() {
            mobileNav.classList.contains('open') ? closeMenu() : openMenu();
        }

        // Click to toggle
        hamburger.addEventListener('click', toggleMenu);

        // Keyboard accessibility — Enter or Space activates the button
        hamburger.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMenu();
            }
        });

        // Close when any mobile nav link is clicked
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });

        // Close when clicking outside the nav area
        document.addEventListener('click', e => {
            if (
                mobileNav.classList.contains('open') &&
                !mobileNav.contains(e.target) &&
                !hamburger.contains(e.target)
            ) {
                closeMenu();
            }
        });
    }

    /* FOOTER YEAR */
    function initFooterYear() {
        const yearEl = document.getElementById('footer-year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    }

    await loadComponents();
    setActiveLink();
    initNavScroll();
    initHamburger();
    initFooterYear();

})();