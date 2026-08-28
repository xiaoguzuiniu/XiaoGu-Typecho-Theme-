(function () {
    const source = document.querySelector('[data-gallery-source]');
    const grid = document.querySelector('[data-gallery-grid]');
    const filters = document.querySelector('[data-gallery-filters]');
    const albumList = document.querySelector('[data-gallery-albums]');
    const count = document.querySelector('[data-gallery-count]');
    const empty = document.querySelector('[data-gallery-empty]');
    const lightbox = document.querySelector('[data-gallery-lightbox]');

    if (!source || !grid || !filters || !albumList || !count || !empty || !lightbox) return;

    const photos = [];
    const albums = new Map();
    const walker = document.createTreeWalker(source, NodeFilter.SHOW_ELEMENT);
    let currentAlbum = '生活片刻';
    let node;

    function cleanText(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function photoMeta(image) {
        const owner = image.closest('[data-date], [data-location]');
        const date = cleanText(image.dataset.date || (owner && owner.dataset.date));
        const location = cleanText(image.dataset.location || (owner && owner.dataset.location));
        return [location, date].filter(Boolean).join(' · ');
    }

    while ((node = walker.nextNode())) {
        if (/^H[23]$/.test(node.tagName)) {
            currentAlbum = cleanText(node.textContent) || '生活片刻';
            continue;
        }

        if (node.tagName !== 'IMG') continue;

        const figure = node.closest('figure');
        const link = node.closest('a');
        const figureCaption = figure && figure.querySelector('figcaption');
        const album = cleanText(node.dataset.album) || currentAlbum;
        const title = cleanText(node.alt || node.title || (figureCaption && figureCaption.textContent))
            || '生活片刻 ' + String(photos.length + 1).padStart(2, '0');
        const preview = node.currentSrc || node.src;
        const original = link && link.href ? link.href : preview;

        if (!preview) continue;

        const photo = {
            album: album,
            title: title,
            meta: photoMeta(node),
            preview: preview,
            original: original,
            alt: cleanText(node.alt) || title,
            srcset: node.getAttribute('srcset') || '',
            sizes: node.getAttribute('sizes') || ''
        };

        photos.push(photo);
        if (!albums.has(album)) albums.set(album, []);
        albums.get(album).push(photo);
    }

    if (!photos.length) {
        empty.hidden = false;
        albumList.innerHTML = '<p class="gallery-album-loading">相册正在整理中。</p>';
        return;
    }

    let activeAlbum = 'all';
    let visiblePhotos = photos.slice();
    let activePhotoIndex = 0;
    let previousFocus = null;

    function makeImage(photo) {
        const image = document.createElement('img');
        image.src = photo.preview;
        image.alt = photo.alt;
        image.loading = 'lazy';
        image.decoding = 'async';
        if (photo.srcset) image.srcset = photo.srcset;
        if (photo.sizes) image.sizes = photo.sizes;
        return image;
    }

    function updateCount() {
        const number = count.querySelector('strong');
        number.textContent = String(visiblePhotos.length);
        count.hidden = false;
    }

    function renderGrid() {
        grid.replaceChildren();
        visiblePhotos.forEach(function (photo, index) {
            const item = document.createElement('button');
            const caption = document.createElement('span');
            const title = document.createElement('strong');
            const meta = document.createElement('small');

            item.type = 'button';
            item.className = 'gallery-photo gallery-photo-pattern-' + (index % 8);
            item.dataset.galleryPhotoIndex = String(index);
            item.setAttribute('aria-label', '查看照片：' + photo.title);
            title.textContent = photo.title;
            caption.className = 'gallery-photo-caption';
            caption.appendChild(title);
            if (photo.meta) {
                meta.textContent = photo.meta;
                caption.appendChild(meta);
            }
            item.append(makeImage(photo), caption);
            grid.appendChild(item);
        });
        grid.hidden = false;
        updateCount();
    }

    function setAlbum(album) {
        activeAlbum = album;
        visiblePhotos = album === 'all' ? photos.slice() : (albums.get(album) || []).slice();

        document.querySelectorAll('[data-gallery-filter]').forEach(function (button) {
            const selected = button.dataset.galleryFilter === album;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        document.querySelectorAll('[data-gallery-album]').forEach(function (button) {
            const selected = button.dataset.galleryAlbum === album;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-current', selected ? 'true' : 'false');
        });

        renderGrid();
    }

    function createFilter(label, album, amount) {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.galleryFilter = album;
        button.setAttribute('aria-pressed', 'false');
        button.textContent = label;
        if (amount !== null) {
            const number = document.createElement('span');
            number.textContent = String(amount);
            button.appendChild(number);
        }
        button.addEventListener('click', function () { setAlbum(album); });
        filters.appendChild(button);
    }

    function createAlbumRow(label, album, albumPhotos) {
        const button = document.createElement('button');
        const thumb = makeImage(albumPhotos[0]);
        const copy = document.createElement('span');
        const title = document.createElement('strong');
        const subtitle = document.createElement('small');
        const amount = document.createElement('b');

        button.type = 'button';
        button.className = 'gallery-album-row';
        button.dataset.galleryAlbum = album;
        title.textContent = label;
        subtitle.textContent = album === 'all' ? '所有生活片段' : '收藏的光影片段';
        amount.textContent = String(albumPhotos.length);
        copy.append(title, subtitle);
        button.append(thumb, copy, amount);
        button.addEventListener('click', function () { setAlbum(album); });
        albumList.appendChild(button);
    }

    filters.replaceChildren();
    createFilter('全部', 'all', photos.length);
    albums.forEach(function (albumPhotos, album) {
        createFilter(album, album, albumPhotos.length);
    });
    filters.hidden = false;

    albumList.replaceChildren();
    createAlbumRow('全部照片', 'all', photos);
    albums.forEach(function (albumPhotos, album) {
        createAlbumRow(album, album, albumPhotos);
    });

    const previewImage = lightbox.querySelector('[data-gallery-lightbox-image]');
    const previewTitle = lightbox.querySelector('[data-gallery-lightbox-title]');
    const previewMeta = lightbox.querySelector('[data-gallery-lightbox-meta]');
    const previousButton = lightbox.querySelector('.gallery-lightbox-prev');
    const nextButton = lightbox.querySelector('.gallery-lightbox-next');
    const closeButton = lightbox.querySelector('.gallery-lightbox-close');
    const previewFigure = lightbox.querySelector('.gallery-lightbox-figure');
    const previewCounter = lightbox.querySelector('[data-gallery-lightbox-counter]');
    let swipeStartX = 0;
    let swipeStartY = 0;
    let swipeDeltaX = 0;
    let swipeIntent = null;
    let swipeAnimating = false;

    function preloadAdjacentPhotos() {
        if (visiblePhotos.length < 2) return;
        [-1, 1].forEach(function (offset) {
            const index = (activePhotoIndex + offset + visiblePhotos.length) % visiblePhotos.length;
            const image = new Image();
            image.src = visiblePhotos[index].original;
        });
    }

    function showPhoto(index) {
        if (!visiblePhotos.length) return;
        activePhotoIndex = (index + visiblePhotos.length) % visiblePhotos.length;
        const photo = visiblePhotos[activePhotoIndex];
        previewImage.src = photo.original;
        previewImage.alt = photo.alt;
        previewTitle.textContent = photo.title;
        previewMeta.textContent = photo.meta || photo.album;
        previewCounter.textContent = (activePhotoIndex + 1) + ' / ' + visiblePhotos.length;
        previousButton.hidden = visiblePhotos.length < 2;
        nextButton.hidden = visiblePhotos.length < 2;
        preloadAdjacentPhotos();
    }

    function resetSwipePosition(animate) {
        previewFigure.style.transition = animate
            ? 'transform 260ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease'
            : 'none';
        previewFigure.style.transform = 'translate3d(0, 0, 0)';
        previewFigure.style.opacity = '1';
    }

    function finishSwipe(direction) {
        if (swipeAnimating) return;
        swipeAnimating = true;
        const exitX = direction > 0 ? -window.innerWidth : window.innerWidth;

        previewFigure.style.transition = 'transform 180ms ease-in, opacity 180ms ease-in';
        previewFigure.style.transform = 'translate3d(' + exitX + 'px, 0, 0)';
        previewFigure.style.opacity = '0.25';

        window.setTimeout(function () {
            showPhoto(activePhotoIndex + direction);
            previewFigure.style.transition = 'none';
            previewFigure.style.transform = 'translate3d(' + (-exitX * 0.32) + 'px, 0, 0)';
            previewFigure.style.opacity = '0.2';

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    resetSwipePosition(true);
                    window.setTimeout(function () {
                        previewFigure.removeAttribute('style');
                        swipeAnimating = false;
                    }, 280);
                });
            });
        }, 180);
    }

    function openLightbox(index, trigger) {
        previousFocus = trigger || document.activeElement;
        showPhoto(index);
        lightbox.hidden = false;
        document.documentElement.classList.add('is-gallery-lightbox-open');
        document.body.classList.add('is-gallery-lightbox-open');
        closeButton.focus({preventScroll: true});
    }

    function closeLightbox() {
        if (lightbox.hidden) return;
        lightbox.hidden = true;
        previewImage.removeAttribute('src');
        document.documentElement.classList.remove('is-gallery-lightbox-open');
        document.body.classList.remove('is-gallery-lightbox-open');
        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus({preventScroll: true});
        }
        previousFocus = null;
    }

    grid.addEventListener('click', function (event) {
        const item = event.target.closest('[data-gallery-photo-index]');
        if (!item) return;
        openLightbox(Number(item.dataset.galleryPhotoIndex), item);
    });
    closeButton.addEventListener('click', closeLightbox);
    previousButton.addEventListener('click', function () { showPhoto(activePhotoIndex - 1); });
    nextButton.addEventListener('click', function () { showPhoto(activePhotoIndex + 1); });
    previewFigure.addEventListener('touchstart', function (event) {
        if (swipeAnimating || visiblePhotos.length < 2 || event.touches.length !== 1) return;
        swipeStartX = event.touches[0].clientX;
        swipeStartY = event.touches[0].clientY;
        swipeDeltaX = 0;
        swipeIntent = null;
        previewFigure.style.transition = 'none';
    }, {passive: true});
    previewFigure.addEventListener('touchmove', function (event) {
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
        swipeDeltaX = deltaX;
        previewFigure.style.transform = 'translate3d(' + deltaX + 'px, 0, 0)';
        previewFigure.style.opacity = String(1 - Math.min(Math.abs(deltaX) / window.innerWidth * 0.32, 0.24));
    }, {passive: false});
    previewFigure.addEventListener('touchend', function () {
        if (swipeAnimating) return;
        if (swipeIntent === 'horizontal') {
            const threshold = Math.min(90, Math.max(48, window.innerWidth * 0.14));
            if (Math.abs(swipeDeltaX) >= threshold) {
                finishSwipe(swipeDeltaX < 0 ? 1 : -1);
            } else {
                resetSwipePosition(true);
            }
        } else if (swipeIntent === 'cancelled') {
            resetSwipePosition(true);
        }
        swipeIntent = null;
        swipeDeltaX = 0;
    });
    previewFigure.addEventListener('touchcancel', function () {
        if (!swipeAnimating) resetSwipePosition(true);
        swipeIntent = null;
        swipeDeltaX = 0;
    });
    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', function (event) {
        if (lightbox.hidden) return;
        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') showPhoto(activePhotoIndex - 1);
        if (event.key === 'ArrowRight') showPhoto(activePhotoIndex + 1);
    });

    setAlbum(activeAlbum);
}());
