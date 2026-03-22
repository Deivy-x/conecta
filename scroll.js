/* ============================================================
   QuibdóConecta — Scroll Reveal Bidireccional (global)
   Cuando el elemento ENTRA al viewport: aparece (fadeUp)
   Cuando el elemento SALE del viewport hacia arriba: desaparece
   ============================================================ */
(function () {
  const CSS = `
    .reveal{opacity:0;transform:translateY(36px);transition:opacity .65s ease,transform .65s ease;}
    .reveal.visible{opacity:1;transform:translateY(0);}
  `;
  const style = document.createElement('style');
  style.textContent = CSS;
  document.head.appendChild(style);

  function initReveal() {
    const els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    const obs = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
        } else {
          // Solo desaparezca si el elemento está por ARRIBA del viewport (usuario subió)
          const rect = e.target.getBoundingClientRect();
          if (rect.top < 0) {
            e.target.classList.remove('visible');
          }
        }
      });
    }, { threshold: 0.12 });
    els.forEach(el => obs.observe(el));
  }
  document.addEventListener('DOMContentLoaded', initReveal);
})();
