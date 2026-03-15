'use strict';

// ── Utility: Shake invalid field ──────────────────────────────
// Visually shakes a form field and turns its border red when
// validation fails, then resets after 600ms.
function shakeField(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.borderColor = 'var(--red-bright)';
    el.style.animation   = 'shake 0.4s ease';
    el.focus();
    setTimeout(() => {
        el.style.animation   = '';
        el.style.borderColor = '';
    }, 600);
}

// Inject shake keyframe into <head> once on load
const shakeStyle       = document.createElement('style');
shakeStyle.textContent = `@keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }`;
document.head.appendChild(shakeStyle);


// Utility: Toast notification
// Shows a small floating message at the bottom of the screen.
// Used to confirm form submissions without reloading the page.
function showToast(msg) {
    let toast = document.getElementById('mas-toast');

    // Create the toast element if it doesn't exist yet
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'mas-toast';
        toast.style.cssText = `
            position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(40px);
            background:#1a1a1a; color:#fff; padding:12px 24px; border-radius:4px;
            font-size:14px; opacity:0; transition:all 0.3s ease; z-index:9999;
            border-left:4px solid var(--red, #C8102E); white-space:nowrap;
        `;
        document.body.appendChild(toast);
    }

    toast.textContent = msg;

    // Slide up and show
    setTimeout(() => {
        toast.style.opacity   = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
    }, 10);

    // Slide down and hide after 3.5 seconds
    setTimeout(() => {
        toast.style.opacity   = '0';
        toast.style.transform = 'translateX(-50%) translateY(40px)';
    }, 3500);
}


// Contact Form
// Handles the general enquiry form on contact.html.
// Sends data to /api/contact.php via JSON POST.
(function () {
    const form = document.getElementById('contactForm');
    if (!form) return; // Exit if form not on this page

    form.addEventListener('submit', async function (e) {
        e.preventDefault(); // Stop default HTML form submission (page reload)

        // Read and trim field values
        const name    = document.getElementById('cf-name').value.trim();
        const phone   = document.getElementById('cf-phone').value.trim();
        const email   = (document.getElementById('cf-email')?.value || '').trim();
        const message = document.getElementById('cf-message').value.trim();

        // Client-side validation — catches empty fields before hitting the server
        if (!name)    { shakeField('cf-name');    return; }
        if (!phone)   { shakeField('cf-phone');   return; }
        if (!message) { shakeField('cf-message'); return; }

        const btn = form.querySelector('button[type="submit"]');
        const originalHTML = btn.innerHTML;
        btn.textContent = 'Sending…';
        btn.disabled    = true;

        try {
            // POST to the PHP contact endpoint as JSON
            const res  = await fetch('/api/contact.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ name, phone, email, message })
            });

            const json = await res.json();

            if (res.ok && json.success) {
                showToast('Message sent! We\'ll be in touch shortly.');
                form.reset();
                btn.textContent = 'Message Sent';
                btn.style.background = '#1a8c1a';

                setTimeout(() => {
                    btn.innerHTML        = originalHTML;
                    btn.disabled         = false;
                    btn.style.background = '';
                }, 3000);

            } else {
                // Server returned a validation error or DB error
                showToast(json.message || 'Something went wrong. Please try again.');
                btn.innerHTML = originalHTML;
                btn.disabled  = false;
            }

        } catch (err) {
            // Network failure — server unreachable
            showToast('Could not send message. Please check your connection.');
            btn.innerHTML = originalHTML;
            btn.disabled  = false;
        }
    });
})();


// Booking Form
// Handles the service booking form on contact.html.
// Saves to MySQL via /api/booking.php, then opens WhatsApp
// with a pre-filled message as a secondary confirmation channel.
(function () {
    const form = document.getElementById('bookingForm');
    if (!form) return; // Exit if form not on this page

    // Set minimum selectable date to today so past dates can't be chosen
    const dateInput = document.getElementById('bk-date');
    if (dateInput) {
        dateInput.min = new Date().toISOString().split('T')[0];
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Read all field values
        const name    = document.getElementById('bk-name').value.trim();
        const phone   = document.getElementById('bk-phone').value.trim();
        const vehicle = document.getElementById('bk-vehicle').value.trim();
        const service = document.getElementById('bk-service').value;
        const date    = document.getElementById('bk-date').value;
        const time    = document.getElementById('bk-time').value;
        const notes   = document.getElementById('bk-notes').value.trim();
        const email   = (document.getElementById('bk-email')?.value || '').trim();

        // Client-side validation
        if (!name)    { shakeField('bk-name');    return; }
        if (!phone)   { shakeField('bk-phone');   return; }
        if (!vehicle) { shakeField('bk-vehicle'); return; }
        if (!service) { shakeField('bk-service'); return; }
        if (!date)    { shakeField('bk-date');    return; }

        const btn = form.querySelector('.booking-submit');
        btn.textContent = 'Processing…';
        btn.disabled    = true;

        // Build WhatsApp message, always sent regardless of API result
        // This is the primary communication channel for MAS Auto
        const waMsg = buildWhatsAppMessage({ name, phone, vehicle, service, date, time, notes });
        const waUrl = `https://wa.me/27607560744?text=${encodeURIComponent(waMsg)}`;

        try {
            // POST booking to PHP backend → MySQL
            const res  = await fetch('/api/booking.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ name, phone, vehicle, service, date, time, notes, email })
            });

            const json = await res.json();

            if (res.ok && json.success) {
                // Booking saved: show toast, open WhatsApp
                showToast('Booking saved! Redirecting to WhatsApp…');
                form.reset();

                setTimeout(() => {
                    window.open(waUrl, '_blank');
                    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Submit Booking Request';
                    btn.disabled  = false;
                }, 1500);

            } else {
                // API returned an error — still open WhatsApp as fallback
                // so the customer doesn't lose their booking
                showToast('Redirecting to WhatsApp to complete your booking…');
                setTimeout(() => {
                    window.open(waUrl, '_blank');
                    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Submit Booking Request';
                    btn.disabled  = false;
                }, 1500);
            }

        } catch (err) {
            // Network failure — fall back to WhatsApp silently
            // Customer still gets through to MAS Auto
            showToast('Redirecting to WhatsApp…');
            setTimeout(() => {
                window.open(waUrl, '_blank');
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Submit Booking Request';
                btn.disabled  = false;
            }, 1500);
        }
    });


    /**
     * buildWhatsAppMessage()
     * Constructs the pre-filled WhatsApp message using booking data.
     * This message opens in WhatsApp Web / app ready to send to MAS Auto.
     *
     * @param {Object} d  Booking fields
     * @returns {string}  Formatted message string
     */
    function buildWhatsAppMessage(d) {
        return `*MAS AUTO SERVICE BOOKING*\n\n` +
            `*Name:* ${d.name}\n` +
            `*Phone:* ${d.phone}\n` +
            `*Vehicle:* ${d.vehicle}\n` +
            `*Service:* ${d.service}\n` +
            `*Date:* ${d.date}${d.time ? ' @ ' + d.time : ''}\n` +
            (d.notes ? `*Notes:* ${d.notes}\n` : '') +
            `\n_Submitted via MAS Auto Website_`;
    }

})();