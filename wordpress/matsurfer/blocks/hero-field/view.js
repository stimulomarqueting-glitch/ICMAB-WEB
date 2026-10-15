/**
 * Home hero (HeroField.astro) — plate tilt + vapour.
 *  - pointer over the section → tilt (±7° / ±10°), sheen and mesh follow;
 *    leaving eases the plate back;
 *  - vapour: a slow, sparse plume inside the flask in the photo. Particles
 *    rise from the base of the lower chamber, cool from coral to violet,
 *    pass behind the opaque collar and fade out under the dome. Everything
 *    is simulated in the photo's own pixel space (1100 × 619) and clipped
 *    to the flask outline, so it stays on the glass at any card size.
 * Motion is progressive enhancement: no JS → static plate and copy;
 * prefers-reduced-motion → no tilt, one still frame of vapour. The loop
 * pauses off-screen and when the tab is hidden. Works for any number of
 * hero sections on the page (each one is set up on its own).
 */
(function () {
  'use strict';

  /* ── the flask, in photo pixels ───────────────────────────────── */
  var IMG_W = 1100;
  var IMG_H = 619;
  var CHAMBER = { x: 726, y: 346, w: 98, h: 114 }; // clear lower chamber
  var DOME = { cx: 775, cy: 300, rx: 47, ry: 64 }; // upper bell, above the O-ring
  var SOURCE_Y = 452; // where the material sits
  var TOP_Y = 244; // where the vapour thins out under the dome
  var COLLAR = { top: 304, bottom: 346 }; // opaque band between the two
  var N = 70;

  var CORAL = [240, 83, 47];
  var MAGENTA = [206, 66, 98];
  var VIOLET = [108, 18, 248];

  function mix(a, b, t) {
    return a.map(function (v, i) {
      return Math.round(v + (b[i] - v) * t);
    });
  }

  function colour(t) {
    return t < 0.5 ? mix(CORAL, MAGENTA, t / 0.5) : mix(MAGENTA, VIOLET, (t - 0.5) / 0.5);
  }

  /* rounded rectangle with per-corner radii [tl, tr, br, bl]; roundRect() when available */
  function roundRect(ctx, x, y, w, h, r) {
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(x, y, w, h, r);
      return;
    }
    ctx.moveTo(x + r[0], y);
    ctx.lineTo(x + w - r[1], y);
    ctx.arcTo(x + w, y, x + w, y + r[1], r[1]);
    ctx.lineTo(x + w, y + h - r[2]);
    ctx.arcTo(x + w, y + h, x + w - r[2], y + h, r[2]);
    ctx.lineTo(x + r[3], y + h);
    ctx.arcTo(x, y + h, x, y + h - r[3], r[3]);
    ctx.lineTo(x, y + r[0]);
    ctx.arcTo(x, y, x + r[0], y, r[0]);
    ctx.closePath();
  }

  function initTilt(root) {
    root.addEventListener('pointermove', function (e) {
      var r = root.getBoundingClientRect();
      if (!r.width || !r.height) return;
      var nx = (e.clientX - r.left) / r.width - 0.5;
      var ny = (e.clientY - r.top) / r.height - 0.5;
      root.style.setProperty('--px', String(nx + 0.5));
      root.style.setProperty('--py', String(ny + 0.5));
      root.style.setProperty('--ry', (nx * 10).toFixed(2) + 'deg');
      root.style.setProperty('--rx', (-ny * 7).toFixed(2) + 'deg');
    });
    root.addEventListener('pointerleave', function () {
      root.style.setProperty('--rx', '0deg');
      root.style.setProperty('--ry', '0deg');
    });
  }

  function initVapour(root, canvas, photo, reduce) {
    var ctx = canvas.getContext && canvas.getContext('2d');
    if (!ctx) return;
    var DPR = Math.min(window.devicePixelRatio || 1, 2);

    var flaskPath = function () {
      ctx.beginPath();
      roundRect(ctx, CHAMBER.x, CHAMBER.y, CHAMBER.w, CHAMBER.h, [3, 3, 24, 24]);
      ctx.ellipse(DOME.cx, DOME.cy, DOME.rx, DOME.ry, 0, Math.PI, Math.PI * 2);
      ctx.closePath();
    };

    /* maps photo pixels onto the card, following object-fit: cover */
    var W = 0;
    var H = 0;
    var layout = function () {
      var r = canvas.getBoundingClientRect();
      W = Math.max(1, Math.round(r.width));
      H = Math.max(1, Math.round(r.height));
      canvas.width = W * DPR;
      canvas.height = H * DPR;
      var pos = String(getComputedStyle(photo).objectPosition || '').split(' ').map(function (v) {
        return parseFloat(v) / 100;
      });
      var ox = pos[0];
      var oy = pos[1];
      var s = Math.max(W / IMG_W, H / IMG_H);
      var offX = (W - IMG_W * s) * (isNaN(ox) ? 0.5 : ox);
      var offY = (H - IMG_H * s) * (isNaN(oy) ? 0.5 : oy);
      ctx.setTransform(DPR * s, 0, 0, DPR * s, DPR * offX, DPR * offY);
    };

    var ps = [];
    var spawn = function (p) {
      p.x = CHAMBER.x + 14 + Math.random() * (CHAMBER.w - 28);
      p.y = SOURCE_Y - Math.random() * 6;
      p.vy = -(0.16 + Math.random() * 0.2); // photo px per frame: ~12–20s to the dome
      p.s = 1.3 + Math.random() * 1.6;
      p.ph = Math.random() * Math.PI * 2;
      p.amp = 0.08 + Math.random() * 0.1;
      p.age = 0;
      return p;
    };

    var t0 = performance.now();
    var step = function (now) {
      var dt = Math.min(2, (now - t0) / 16.67);
      t0 = now;
      for (var i = 0; i < ps.length; i++) {
        var p = ps[i];
        p.age += dt;
        p.y += p.vy * dt;
        p.x += Math.sin(now / 1400 + p.ph) * p.amp * dt;
        /* the walls: narrow gently as the dome closes in */
        var halfW = p.y < COLLAR.top
          ? DOME.rx * Math.sqrt(Math.max(0, 1 - Math.pow((DOME.cy - p.y) / DOME.ry, 2))) - 6
          : CHAMBER.w / 2 - 8;
        var cx = CHAMBER.x + CHAMBER.w / 2;
        if (p.x < cx - halfW) p.x = cx - halfW;
        if (p.x > cx + halfW) p.x = cx + halfW;
        if (p.y < TOP_Y || halfW <= 2) spawn(p);
      }
    };

    var draw = function (now) {
      ctx.save();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.restore();

      ctx.save();
      flaskPath();
      ctx.clip();

      /* a warm glow where the material heats, breathing slowly */
      var pulse = 0.28 + 0.12 * Math.sin(now / 1800);
      var glow = ctx.createRadialGradient(775, SOURCE_Y + 4, 2, 775, SOURCE_Y + 4, 58);
      glow.addColorStop(0, 'rgba(240,83,47,' + pulse.toFixed(3) + ')');
      glow.addColorStop(1, 'rgba(240,83,47,0)');
      ctx.fillStyle = glow;
      ctx.fillRect(CHAMBER.x, CHAMBER.y + 40, CHAMBER.w, CHAMBER.h);

      ctx.globalCompositeOperation = 'lighter';
      var span = SOURCE_Y - TOP_Y;
      for (var i = 0; i < ps.length; i++) {
        var p = ps[i];
        if (p.y > COLLAR.top && p.y < COLLAR.bottom) continue; // behind the collar
        var rise = Math.max(0, Math.min(1, (SOURCE_Y - p.y) / span));
        var c = colour(rise);
        var fadeIn = Math.min(1, p.age / 40);
        var fadeOut = rise > 0.75 ? Math.max(0, 1 - (rise - 0.75) / 0.25) : 1;
        var a = (0.55 - 0.2 * rise) * fadeIn * fadeOut;
        ctx.fillStyle = 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a.toFixed(3) + ')';
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.s, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.restore();
    };

    layout();
    for (var i = 0; i < N; i++) {
      var p = spawn({ x: 0, y: 0, vy: 0, s: 1, ph: 0, amp: 0, age: 0 });
      p.y = SOURCE_Y - Math.random() * (SOURCE_Y - TOP_Y); // pre-fill the plume
      p.age = 60;
      ps.push(p);
    }

    var raf = 0;
    var running = false;
    var visible = true;
    var loop = function (now) {
      step(now);
      draw(now);
      raf = requestAnimationFrame(loop);
    };
    var start = function () {
      if (!running && visible && !document.hidden) {
        running = true;
        t0 = performance.now();
        raf = requestAnimationFrame(loop);
      }
    };
    var stop = function () {
      running = false;
      cancelAnimationFrame(raf);
    };

    var still = function () {
      draw(performance.now());
    };
    if (reduce) {
      if (photo.complete) still();
      else photo.addEventListener('load', still, { once: true });
    } else {
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(
          function (entries) {
            visible = entries[0].isIntersecting;
            if (visible) start();
            else stop();
          },
          { threshold: 0.05 }
        ).observe(root);
      } else {
        start();
      }
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop();
        else start();
      });
    }

    var rt = 0;
    window.addEventListener('resize', function () {
      window.clearTimeout(rt);
      rt = window.setTimeout(function () {
        layout();
        if (reduce) still();
      }, 150);
    });
  }

  function init(root) {
    if (root.hasAttribute('data-field-ready')) return;
    root.setAttribute('data-field-ready', '');
    var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    if (!reduce) initTilt(root);

    var canvas = root.querySelector('canvas[data-vapour]');
    var photo = root.querySelector('img.plate__img');
    if (canvas && photo) initVapour(root, canvas, photo, reduce);
  }

  function boot() {
    /* the hero section only: other elements on the home page also carry data-field */
    var roots = document.querySelectorAll('section.field[data-field]');
    for (var i = 0; i < roots.length; i++) init(roots[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
