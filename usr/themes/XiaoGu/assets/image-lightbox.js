(function () {
    const imageSelector = '.moment-content img, .page-content img';
    let lightbox = null;
    let previewImage = null;
    let caption = null;
    let closeButton = null;
    let previousFocus = null;

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
            '<figure class="image-lightbox-figure">',
            '<img class="image-lightbox-image" alt="">',
            '<figcaption class="image-lightbox-caption"></figcaption>',
            '</figure>'
        ].join('');

        previewImage = lightbox.querySelector('.image-lightbox-image');
        caption = lightbox.querySelector('.image-lightbox-caption');
        closeButton = lightbox.querySelector('.image-lightbox-close');

        closeButton.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) closeLightbox();
        });

        document.body.appendChild(lightbox);
    }

    function openLightbox(image) {
        createLightbox();

        const source = image.currentSrc || image.src;
        if (!source) return;

        previousFocus = document.activeElement;
        previewImage.src = source;
        previewImage.alt = image.alt || '';
        caption.textContent = image.alt || '';
        caption.hidden = !image.alt;
        lightbox.hidden = false;
        document.documentElement.classList.add('is-image-lightbox-open');
        document.body.classList.add('is-image-lightbox-open');
        closeButton.focus({preventScroll: true});
    }

    function closeLightbox() {
        if (!lightbox || lightbox.hidden) return;

        lightbox.hidden = true;
        previewImage.removeAttribute('src');
        document.documentElement.classList.remove('is-image-lightbox-open');
        document.body.classList.remove('is-image-lightbox-open');

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus({preventScroll: true});
        }
        previousFocus = null;
    }

    document.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const image = event.target.closest(imageSelector);
        if (!image) return;

        event.preventDefault();
        openLightbox(image);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeLightbox();
    });
}());
