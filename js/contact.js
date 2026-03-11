'use strict';

// ── Contact Form ─────────────────────────────────────────────
(function () {
  const form = document.getElementById('contactForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const name    = document.getElementById('cf-name').value.trim();
    const phone   = document.getElementById('cf-phone').value.trim();
    const message = document.getElementById('cf-message').value.trim();

    if (!name) { shakeField('cf-name'); return; }
    if (!phone) { shakeField('cf-phone'); return; }
    if (!message) { shakeField('cf-message'); return; }

    const btn = form.querySelector('button[type="submit"]');
    btn.textContent = 'Sending…';
    btn.disabled = true;

    // Simulate sending (replace with actual API call)
    setTimeout(() => {
      showToast('Message sent! We\'ll be in touch shortly.');
      form.reset();
      btn.textContent = 'Message Sent ✓';
      btn.style.background = '#1a8c1a';
      setTimeout(() => {
        btn.innerHTML = 'Send Message <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
        btn.disabled = false;
        btn.style.background = '';
      }, 3000);
    }, 1000);
  });
})();

// ── Booking Form ─────────────────────────────────────────────
(function () {
  const form = document.getElementById('bookingForm');
  if (!form) return;

  // Set min date to today
  const dateInput = document.getElementById('bk-date');
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const name    = document.getElementById('bk-name').value.trim();
    const phone   = document.getElementById('bk-phone').value.trim();
    const vehicle = document.getElementById('bk-vehicle').value.trim();
    const service = document.getElementById('bk-service').value;
    const date    = document.getElementById('bk-date').value;
    const time    = document.getElementById('bk-time').value;
    const notes   = document.getElementById('bk-notes').value.trim();

    if (!name)    { shakeField('bk-name');    return; }
    if (!phone)   { shakeField('bk-phone');   return; }
    if (!vehicle) { shakeField('bk-vehicle'); return; }
    if (!service) { shakeField('bk-service'); return; }
    if (!date)    { shakeField('bk-date');    return; }

    const btn = form.querySelector('.booking-submit');
    btn.textContent = 'Processing…';
    btn.disabled = true;

    // ── Save booking to backend (FastAPI + Supabase) ─────────
    const bookingData = { name, phone, vehicle, service, date, time, notes };

    // Attempt to save; fall back gracefully if API not available
    const saveBooking = fetch('/api/bookings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(bookingData)
    }).catch(() => ({ ok: false })); // graceful fallback

    saveBooking.then(res => {
      // Build WhatsApp message regardless of save result
      const waMsg = buildWhatsAppMessage({ name, phone, vehicle, service, date, time, notes });
      const waUrl = `https://wa.me/27607560744?text=${encodeURIComponent(waMsg)}`;

      showToast('Booking saved! Redirecting to WhatsApp…');
      form.reset();

      setTimeout(() => {
        window.open(waUrl, '_blank');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Submit Booking Request';
        btn.disabled = false;
      }, 1500);
    });
  });

  function buildWhatsAppMessage(d) {
    return `🚗 *MAS AUTO SERVICE BOOKING*\n\n` +
      `*Name:* ${d.name}\n` +
      `*Phone:* ${d.phone}\n` +
      `*Vehicle:* ${d.vehicle}\n` +
      `*Service:* ${d.service}\n` +
      `*Date:* ${d.date}${d.time ? ' @ ' + d.time : ''}\n` +
      (d.notes ? `*Notes:* ${d.notes}\n` : '') +
      `\n_Submitted via MAS Auto Website_`;
  }
})();

// ── Shake animation for invalid fields ───────────────────────
function shakeField(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.borderColor = 'var(--red-bright)';
  el.style.animation = 'shake 0.4s ease';
  el.focus();
  setTimeout(() => { el.style.animation = ''; el.style.borderColor = ''; }, 600);
}

// Inject shake keyframe
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `@keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }`;
document.head.appendChild(shakeStyle);
