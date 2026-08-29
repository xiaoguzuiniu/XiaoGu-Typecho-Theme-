(function () {
    const imageSelector = '.moment-content img, .page-content img';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mobileViewport = window.matchMedia('(max-width: 640px)');
    let lightbox = null;
    let figure = null;
    let previewImage = null;
    let caption = null;
    let counter = null;
    let closeButton = null;
    let previousButton = null;
    let nextButton = null;
    let previousFocus = null;
    let activeImages = [];
    let activeIndex = 0;
    let swipeStartX = 0;
    let swipeStartY = 0;
    let swipeStartTime = 0;
    let swipeDeltaX = 0;
    let swipeIntent = null;
    let swipeAnimating = false;
    let suppressPreviewClick = false;
    let animationTimers = [];

    function schedule(callback, delay) {
        const timer = window.setTimeout(callback, delay);
        animationTimers.push(timer);
        return timer;
    }

    function clearAnimationTimers() {
        animationTimers.forEach(window.clearTimeout);
        animationTimers = [];
    }

    function createLightbox() {
        if (lightbox) return;

        lightbox = document.createElement('div');
        lightbox.className = 'image-lightbox';
        lightbox.hidden = true;
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', '图片预览');
        lightbox.innerHTML = [
            '<button class="image-lightbox-close" type="button" aria-label="关闭图片预览">',
            '<span aria-hidden="true">&times;</span>',
            '</button>',
            '<span class="image-lightbox-counter" aria-live="polite"></span>',
            '<button class="image-lightbox-nav image-lightbox-prev" type="button" aria-label="上一张图片">‹</button>',
            '<figure class="image-lightbox-figure">',
            '<img class="image-lightbox-image" alt="">',
            '<figcaption class="image-lightbox-caption"></figcaption>',
            '</figure>',
            '<button class="image-lightbox-nav image-lightbox-next" type="button" aria-label="下一张图片">›</button>'
        ].join('');

        figure = lightbox.querySelector('.image-lightbox-figure');
        previewImage = lightbox.querySelector('.image-lightbox-image');
        caption = lightbox.querySelector('.image-lightbox-caption');
        counter = lightbox.querySelector('.image-lightbox-counter');
        closeButton = lightbox.querySelector('.image-lightbox-close');
        previousButton = lightbox.querySelector('.image-lightbox-prev');
        nextButton = lightbox.querySelector('.image-lightbox-next');

        closeButton.addEventListener('click', closeLightbox);
        previousButton.addEventListener('click', function () { showImage(activeIndex - 1); });
        nextButton.addEventListener('click', function () { showImage(activeIndex + 1); });
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) closeLightbox();
        });

        figure.addEventListener('touchstart', handleTouchStart, {passive: true});
        figure.addEventListener('touchmove', handleTouchMove, {passive: false});
        figure.addEventListener('touchend', handleTouchEnd);
        figure.addEventListener('touchcancel', cancelSwipe);
        figure.addEventListener('click', function () {
            if (!mobileViewport.matches) return;
            if (suppressPreviewClick || swipeAnimating) {
                suppressPreviewClick = false;
                return;
            }
            closeLightbox();
        });

        document.body.appendChild(lightbox);
    }

    function collectImages(image) {
        const scope = image.closest('.moment-content, .page-content');
        if (!scope) return [image];
        return Array.from(scope.querySelectorAll('img')).filter(function (item) {
            return Boolean(item.currentSrc || item.src);
        });
    }

    function preloadAdjacentImages() {
        if (activeImages.length < 2) return;
        [-1, 1].forEach(function (offset) {
            const index = (activeIndex + offset + activeImages.length) % activeImages.length;
            const source = activeImages[index].currentSrc || activeImages[index].src;
            if (!source) return;
            const image = new Image();
            image.src = source;
        });
    }

    function showImage(index) {
        if (!activeImages.length) return;
        activeIndex = (index + activeImages.length) % activeImages.length;
        const image = activeImages[activeIndex];
        const source = image.currentSrc || image.src;

        previewImage.src = source;
        previewImage.alt = image.alt || '';
        caption.textContent = image.alt || '';
        caption.hidden = !image.alt;
        counter.textContent = (activeIndex + 1) + ' / ' + activeImages.length;
        counter.hidden = activeImages.length < 2;
        previousButton.hidden = activeImages.length < 2;
        nextButton.hidden = activeImages.length < 2;
        preloadAdjacentImages();
    }

    function resetFigurePosition(animate) {
        figure.style.transition = animate && !reducedMotion.matches
            ? 'transform 260ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease'
            : 'none';
        figure.style.transform = 'translate3d(0, 0, 0)';
        figure.style.opacity = '1';
    }

    function finishSwipe(direction) {
        if (swipeAnimating) return;
        if (reducedMotion.matches) {
            showImage(activeIndex + direction);
            resetFigurePosition(false);
            return;
        }

        swipeAnimating = true;
        const exitX = direction > 0 ? -window.innerWidth : window.innerWidth;
        figure.style.transition = 'transform 180ms ease-in, opacity 180ms ease-in';
        figure.style.transform = 'translate3d(' + exitX + 'px, 0, 0)';
        figure.style.opacity = '0.2';

        schedule(function () {
            showImage(activeIndex + direction);
            figure.style.transition = 'none';
            figure.style.transform = 'translate3d(' + (-exitX * 0.32) + 'px, 0, 0)';
            figure.style.opacity = '0.15';

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    resetFigurePosition(true);
                    schedule(function () {
                        figure.removeAttribute('style');
                        swipeAnimating = false;
                    }, 280);
                });
            });
        }, 180);
    }

    function handleTouchStart(event) {
        if (swipeAnimating || activeImages.length < 2 || event.touches.length !== 1) return;
        suppressPreviewClick = false;
        swipeStartX = event.touches[0].clientX;
        swipeStartY = event.touches[0].clientY;
        swipeStartTime = Date.now();
        swipeDeltaX = 0;
        swipeIntent = null;
        figure.style.transition = 'none';
    }

    function handleTouchMove(event) {
        if (swipeAnimating || event.touches.length !== 1) {
            swipeIntent = 'cancelled';
            return;
        }

        const deltaX = event.touches[0].clientX - swipeStartX;
        const deltaY = event.touches[0].clientY - swipeStartY;
        if (swipeIntent === null && (Math.abs(deltaX) > 8 || Math.abs(deltaY) > 8)) {
            swipeIntent = Math.abs(deltaX) > Math.abs(deltaY) * 1.08 ? 'horizontal' : 'vertical';
        }
        if (swipeIntent !== 'horizontal') return;

        event.preventDefault();
        suppressPreviewClick = true;
        swipeDeltaX = deltaX;
        figure.style.transform = 'translate3d(' + deltaX + 'px, 0, 0)';
        figure.style.opacity = String(1 - Math.min(Math.abs(deltaX) / window.innerWidth * 0.34, 0.25));
    }

    function handleTouchEnd() {
        if (swipeAnimating) return;
        if (swipeIntent === 'horizontal') {
            const elapsed = Math.max(Date.now() - swipeStartTime, 1);
            const velocity = Math.abs(swipeDeltaX) / elapsed;
            const threshold = Math.min(90, Math.max(46, window.innerWidth * 0.13));
            if (Math.abs(swipeDeltaX) >= threshold || (Math.abs(swipeDeltaX) > 24 && velocity > 0.45)) {
                finishSwipe(swipeDeltaX < 0 ? 1 : -1);
            } else {
                resetFigurePosition(true);
            }
        } else if (swipeIntent === 'cancelled') {
            resetFigurePosition(true);
        }
        swipeIntent = null;
        swipeDeltaX = 0;
    }

    function cancelSwipe() {
        if (!swipeAnimating) resetFigurePosition(true);
        swipeIntent = null;
        swipeDeltaX = 0;
    }

    function openLightbox(image) {
        createLightbox();
        activeImages = collectImages(image);
        activeIndex = Math.max(activeImages.indexOf(image), 0);
        if (!activeImages.length) return;

        previousFocus = document.activeElement;
        showImage(activeIndex);
        lightbox.hidden = false;
        document.documentElement.classList.add('is-image-lightbox-open');
        document.body.classList.add('is-image-lightbox-open');
        closeButton.focus({preventScroll: true});
    }

    function closeLightbox() {
        if (!lightbox || lightbox.hidden) return;

        clearAnimationTimers();
        swipeAnimating = false;
        resetFigurePosition(false);
        figure.removeAttribute('style');
        lightbox.hidden = true;
        previewImage.removeAttribute('src');
        document.documentElement.classList.remove('is-image-lightbox-open');
        document.body.classList.remove('is-image-lightbox-open');

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus({preventScroll: true});
        }
        previousFocus = null;
        activeImages = [];
    }

    document.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const image = event.target.closest(imageSelector);
        if (!image) return;

        event.preventDefault();
        openLightbox(image);
    });

    document.addEventListener('keydown', function (event) {
        if (!lightbox || lightbox.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') showImage(activeIndex - 1);
        if (event.key === 'ArrowRight') showImage(activeIndex + 1);
    });
}());
