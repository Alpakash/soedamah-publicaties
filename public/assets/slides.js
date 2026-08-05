/**
 * Ingesloten presentatie op de boekpagina: een lichte diaviewer waar de bezoeker
 * doorheen kan klikken. Geen externe library nodig, dus werkt binnen de strikte
 * Content-Security-Policy (script-src 'self'). Zonder JavaScript toont de pagina
 * gewoon alle dia's onder elkaar als terugval — deze code verbetert dat tot een
 * klikbare viewer met vorige/volgende, toetsenbord, vegen en volledig scherm.
 */
(function () {
  'use strict';

  function initDeck(viewer) {
    var slides = Array.prototype.slice.call(viewer.querySelectorAll('.deck-slide'));
    if (slides.length === 0) {
      return;
    }

    var stage = viewer.querySelector('.deck-stage');
    var prevBtn = viewer.querySelector('.deck-prev');
    var nextBtn = viewer.querySelector('.deck-next');
    var fullBtn = viewer.querySelector('.deck-full');
    var current = viewer.querySelector('.deck-current');
    var index = 0;

    // JavaScript werkt: schakel over van de gestapelde terugval naar de viewer.
    viewer.classList.add('deck--ready');

    function show(next) {
      index = (next + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        var active = i === index;
        slide.classList.toggle('is-active', active);
        // Alvast de buurdia's laden zodat bladeren soepel voelt.
        if ((i === index + 1 || i === index - 1) && slide.getAttribute('loading')) {
          slide.removeAttribute('loading');
        }
      });
      if (current) {
        current.textContent = String(index + 1);
      }
    }

    function next() { show(index + 1); }
    function prev() { show(index - 1); }

    if (nextBtn) {
      nextBtn.addEventListener('click', next);
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', prev);
    }

    // Klikken op de dia bladert vooruit (achterkant met Shift = terug).
    stage.addEventListener('click', function (e) {
      if (e.shiftKey) {
        prev();
      } else {
        next();
      }
    });

    // Toetsenbord: pijltjes bladeren zodra de viewer focus of hover heeft.
    viewer.setAttribute('tabindex', '0');
    viewer.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === 'PageDown') {
        e.preventDefault();
        next();
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.key === 'PageUp') {
        e.preventDefault();
        prev();
      } else if (e.key === 'Home') {
        e.preventDefault();
        show(0);
      } else if (e.key === 'End') {
        e.preventDefault();
        show(slides.length - 1);
      }
    });

    // Vegen op aanraakschermen.
    var touchX = null;
    stage.addEventListener('touchstart', function (e) {
      touchX = e.changedTouches[0].clientX;
    }, { passive: true });
    stage.addEventListener('touchend', function (e) {
      if (touchX === null) {
        return;
      }
      var dx = e.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 40) {
        if (dx < 0) { next(); } else { prev(); }
      }
      touchX = null;
    });

    // Volledig scherm (waar de browser dit ondersteunt).
    if (fullBtn) {
      var canFullscreen = viewer.requestFullscreen || viewer.webkitRequestFullscreen;
      if (!canFullscreen) {
        fullBtn.hidden = true;
      } else {
        fullBtn.addEventListener('click', function () {
          var doc = document;
          var isFull = doc.fullscreenElement || doc.webkitFullscreenElement;
          if (isFull) {
            (doc.exitFullscreen || doc.webkitExitFullscreen).call(doc);
          } else {
            (viewer.requestFullscreen || viewer.webkitRequestFullscreen).call(viewer);
          }
        });
      }
    }

    show(0);
  }

  document.querySelectorAll('[data-deck]').forEach(initDeck);
})();
