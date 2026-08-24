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

    function showPhoto(index) {
        if (!visiblePhotos.length) return;
        activePhotoIndex = (index + visiblePhotos.length) % visiblePhotos.length;
        const photo = visiblePhotos[activePhotoIndex];
        previewImage.src = photo.original;
        previewImage.alt = photo.alt;
        previewTitle.textContent = photo.title;
        previewMeta.textContent = photo.meta || photo.album;
        previousButton.hidden = visiblePhotos.length < 2;
        nextButton.hidden = visiblePhotos.length < 2;
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
