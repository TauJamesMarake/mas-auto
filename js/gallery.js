/* ============================================================
   MAS AUTO – GALLERY.JS
   Filter + Lightbox
   ============================================================ */
'use strict';

// ── Filter ──────────────────────────────────────────────────
(function () {
  const btns = document.querySelectorAll('.filter-btn');
  const items = document.querySelectorAll('.gallery-item');

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      items.forEach(item => {
        if (filter === 'all' || item.dataset.cat === filter) {
          item.classList.remove('hidden');
        } else {
          item.classList.add('hidden');
        }
      });
    });
  });
})();

// ── Lightbox ─────────────────────────────────────────────────
(function () {
  const lightbox   = document.getElementById('lightbox');
  const backdrop   = document.getElementById('lightboxBackdrop');
  const imgEl      = document.getElementById('lightboxImg');
  const caption    = document.getElementById('lightboxCaption');
  const closeBtn   = document.getElementById('lightboxClose');
  const prevBtn    = document.getElementById('lightboxPrev');
  const nextBtn    = document.getElementById('lightboxNext');

  let currentIndex = 0;
  let visibleItems = [];

  function getVisible() {
    return [...document.querySelectorAll('.gallery-item:not(.hidden)')];
  }

  function openLightbox(index) {
    visibleItems = getVisible();
    currentIndex = index;
    showImage(currentIndex);
    lightbox.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
  }

  function showImage(index) {
    const item = visibleItems[index];
    if (!item) return;
    const imgDiv = item.querySelector('.gallery-img');
    const bg = imgDiv.style.backgroundImage;
    const title = item.querySelector('h4')?.textContent || '';
    const cat   = item.querySelector('.gallery-cat')?.textContent || '';
    imgEl.style.backgroundImage = bg;
    caption.textContent = `${cat} — ${title}`;
  }

  document.querySelectorAll('.gallery-item').forEach((item, i) => {
    item.addEventListener('click', () => {
      visibleItems = getVisible();
      const idx = visibleItems.indexOf(item);
      if (idx >= 0) openLightbox(idx);
    });
  });

  closeBtn.addEventListener('click', closeLightbox);
  backdrop.addEventListener('click', closeLightbox);
  prevBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    currentIndex = (currentIndex - 1 + visibleItems.length) % visibleItems.length;
    showImage(currentIndex);
  });
  nextBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    currentIndex = (currentIndex + 1) % visibleItems.length;
    showImage(currentIndex);
  });
  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') { currentIndex = (currentIndex - 1 + visibleItems.length) % visibleItems.length; showImage(currentIndex); }
    if (e.key === 'ArrowRight') { currentIndex = (currentIndex + 1) % visibleItems.length; showImage(currentIndex); }
  });
})();
