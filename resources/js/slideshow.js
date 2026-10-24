// Memory photo slideshows (resources/views/components/memory-slides.blade.php).
//
// How it works, in short:
// 1. Every [data-slideshow] on the page gets its own slideshow (setUpSlideshow, at the bottom).
// 2. show(index) makes one photo visible and updates the counter, dots and Full size link.
// 3. A timer calls show(next) every data-interval ms. The timer only runs while isPlaying() is true:
//    not paused with the button, no mouse over / keyboard focus inside, on screen, and the browser tab visible.
// 4. Visitors who ask their device for less motion get no autoplay; arrows, dots, swipe and ← → keys always work.

const SWIPE_DISTANCE_PX = 40;
const DEFAULT_INTERVAL_MS = 4000;
const prefersLessMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function setUpSlideshow(root) {
    const slides = [...root.querySelectorAll('[data-slide]')];
    const dots = [...root.querySelectorAll('[data-slide-dot]')];
    const counter = root.querySelector('[data-slide-number]');
    const progressBar = root.querySelector('[data-slide-progress]');
    const fullSizeLink = root.querySelector('[data-slide-open]');
    const pauseButton = root.querySelector('[data-slide-pause]');
    const intervalMs = Number(root.dataset.interval) || DEFAULT_INTERVAL_MS;

    // Everything that can change while the page is open.
    const state = {
        current: 0,
        timer: null,
        pausedByButton: prefersLessMotion,
        visitorIsLooking: false, // mouse over it, or keyboard focus inside it
        onScreen: false,
    };

    const isPlaying = () => !state.pausedByButton && !state.visitorIsLooking && state.onScreen && !document.hidden;

    // ---- Showing a photo -------------------------------------------------

    function show(index) {
        // Wrap around: after the last photo comes the first, before the first comes the last.
        state.current = (index + slides.length) % slides.length;

        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === state.current);
            slide.toggleAttribute('aria-hidden', i !== state.current);
        });
        dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === state.current)));

        if (counter) {
            counter.textContent = state.current + 1;
        }
        if (fullSizeLink) {
            const photo = slides[state.current];
            fullSizeLink.href = photo.currentSrc || photo.src;
            fullSizeLink.hidden = 'broken' in photo.dataset; // nothing to open when the photo is missing (photo-fallback.js)
        }

        restartTimer();
    }

    const showNext = () => show(state.current + 1);
    const showPrevious = () => show(state.current - 1);

    // ---- Timer and progress bar -----------------------------------------

    // Called after every change: starts counting again from zero, or stays stopped while not playing.
    function restartTimer() {
        clearTimeout(state.timer);
        resetProgressBar();

        if (isPlaying()) {
            fillProgressBar();
            state.timer = setTimeout(showNext, intervalMs);
        }
    }

    function resetProgressBar() {
        if (progressBar) {
            progressBar.style.transition = 'none';
            progressBar.style.width = '0%';
        }
    }

    function fillProgressBar() {
        if (progressBar) {
            progressBar.getBoundingClientRect(); // makes the browser apply width 0% before animating to 100%
            progressBar.style.transition = `width ${intervalMs}ms linear`;
            progressBar.style.width = '100%';
        }
    }

    // ---- Buttons and keyboard -------------------------------------------

    root.querySelector('[data-slide-prev]')?.addEventListener('click', showPrevious);
    root.querySelector('[data-slide-next]')?.addEventListener('click', showNext);
    dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));

    root.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            showNext();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            showPrevious();
        }
    });

    function updatePauseButton() {
        if (pauseButton) {
            pauseButton.setAttribute('aria-pressed', String(state.pausedByButton));
            pauseButton.textContent = state.pausedByButton ? '▶ Play' : '❚❚ Pause';
        }
    }

    pauseButton?.addEventListener('click', () => {
        state.pausedByButton = !state.pausedByButton;
        updatePauseButton();
        restartTimer();
    });

    // ---- Hold the photo while the visitor is looking ---------------------

    function setVisitorIsLooking(looking) {
        state.visitorIsLooking = looking;
        restartTimer();
    }

    // Only a real mouse counts as hovering: on phones a tap would otherwise pause it until the next tap elsewhere.
    root.addEventListener('pointerenter', (event) => event.pointerType === 'mouse' && setVisitorIsLooking(true));
    root.addEventListener('pointerleave', (event) => event.pointerType === 'mouse' && setVisitorIsLooking(false));
    root.addEventListener('focusin', () => setVisitorIsLooking(true));
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            setVisitorIsLooking(false);
        }
    });

    // Play only while at least half of the slideshow is on screen, and never in a hidden browser tab.
    new IntersectionObserver(([entry]) => {
        state.onScreen = entry.isIntersecting;
        restartTimer();
    }, { threshold: 0.5 }).observe(root);

    document.addEventListener('visibilitychange', restartTimer);

    // ---- Swipe on touch screens -----------------------------------------

    let swipeStartX = null;
    let justSwiped = false;

    root.addEventListener('pointerdown', (event) => {
        swipeStartX = event.clientX;
        justSwiped = false;
    });

    root.addEventListener('pointerup', (event) => {
        if (swipeStartX === null) {
            return;
        }

        const distance = event.clientX - swipeStartX;
        swipeStartX = null;

        if (Math.abs(distance) >= SWIPE_DISTANCE_PX) {
            justSwiped = true;
            distance < 0 ? showNext() : showPrevious(); // finger moved left = next photo
        }
    });

    // On a card the photo is a link to the post: a swipe must change the photo, not open the post.
    root.addEventListener('click', (event) => {
        if (justSwiped) {
            event.preventDefault();
            justSwiped = false;
        }
    }, true);

    // ---- Start ------------------------------------------------------------

    updatePauseButton();
    show(0);
}

document.querySelectorAll('[data-slideshow]').forEach(setUpSlideshow);
