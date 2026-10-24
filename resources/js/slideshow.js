// Memory photo slideshows (resources/views/components/memory-slides.blade.php).
// Photos change by themselves every data-interval ms, and pause while the visitor is looking closely:
// mouse over or keyboard focus inside, the slideshow scrolled out of view, or the browser tab hidden.
// Visitors who ask their device for less motion get no autoplay; arrows, dots, swipe and ← → keys always work.
const SWIPE_DISTANCE = 40;
const prefersLessMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function setUpSlideshow(root) {
    const slides = [...root.querySelectorAll('[data-slide]')];
    const dots = [...root.querySelectorAll('[data-slide-dot]')];
    const number = root.querySelector('[data-slide-number]');
    const progress = root.querySelector('[data-slide-progress]');
    const openLink = root.querySelector('[data-slide-open]');
    const pauseButton = root.querySelector('[data-slide-pause]');
    const interval = Number(root.dataset.interval) || 4000;

    let current = 0;
    let timer = null;
    let pausedByVisitor = prefersLessMotion; // the Pause button (or the device setting)
    let hovered = false;
    let visible = false;

    function show(index) {
        current = (index + slides.length) % slides.length;

        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === current);
            slide.toggleAttribute('aria-hidden', i !== current);
        });
        dots.forEach((dot, i) => dot.setAttribute('aria-current', i === current ? 'true' : 'false'));

        if (number) {
            number.textContent = current + 1;
        }
        if (openLink) {
            openLink.href = slides[current].currentSrc || slides[current].src;
        }

        restart();
    }

    // Starts the timer (and the progress bar) from zero, or stops both while paused.
    function restart() {
        clearTimeout(timer);
        const playing = !pausedByVisitor && !hovered && visible && !document.hidden;

        if (progress) {
            progress.style.transition = 'none';
            progress.style.width = '0%';
        }

        if (!playing) {
            return;
        }

        if (progress) {
            progress.getBoundingClientRect(); // apply width 0 before animating to 100%
            progress.style.transition = `width ${interval}ms linear`;
            progress.style.width = '100%';
        }

        timer = setTimeout(() => show(current + 1), interval);
    }

    root.querySelector('[data-slide-prev]')?.addEventListener('click', () => show(current - 1));
    root.querySelector('[data-slide-next]')?.addEventListener('click', () => show(current + 1));
    dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));

    function updatePauseButton() {
        pauseButton?.setAttribute('aria-pressed', String(pausedByVisitor));
        if (pauseButton) {
            pauseButton.textContent = pausedByVisitor ? '▶ Play' : '❚❚ Pause';
        }
    }

    pauseButton?.addEventListener('click', () => {
        pausedByVisitor = !pausedByVisitor;
        updatePauseButton();
        restart();
    });

    root.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            show(current + (event.key === 'ArrowRight' ? 1 : -1));
        }
    });

    // Mouse over or keyboard focus inside = the visitor is looking, so hold the photo.
    // Only a real mouse counts: on phones a tap would otherwise pause the slideshow until the next tap elsewhere.
    root.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'mouse') {
            hovered = true;
            restart();
        }
    });
    root.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'mouse') {
            hovered = false;
            restart();
        }
    });
    root.addEventListener('focusin', () => { hovered = true; restart(); });
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            hovered = false;
            restart();
        }
    });

    // Swipe left / right on touch screens. A swipe must not also open the post the card links to.
    let swipeStartX = null;
    let swiped = false;
    root.addEventListener('pointerdown', (event) => {
        swipeStartX = event.clientX;
        swiped = false;
    });
    root.addEventListener('pointerup', (event) => {
        if (swipeStartX === null) {
            return;
        }
        const distance = event.clientX - swipeStartX;
        swipeStartX = null;

        if (Math.abs(distance) >= SWIPE_DISTANCE) {
            swiped = true;
            show(current + (distance < 0 ? 1 : -1));
        }
    });
    root.addEventListener('click', (event) => {
        if (swiped) {
            event.preventDefault();
            swiped = false;
        }
    }, true);

    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        restart();
    }, { threshold: 0.5 }).observe(root);

    document.addEventListener('visibilitychange', restart);

    updatePauseButton();
    show(0);
}

document.querySelectorAll('[data-slideshow]').forEach(setUpSlideshow);
