(function () {
    'use strict';

    var config = window.XiaoGuGalleryAdminConfig || {};
    var textarea = document.getElementById('text');
    var form = document.forms.write_page;
    if (!textarea || !form || !config.uploadUrl) return;

    var toolbar = document.querySelector('.editor');
    var editArea = document.getElementById('wmd-editarea') || textarea.parentElement;
    var photos = parseMarkdown(textarea.value);
    var uploadCount = 0;
    var sourceMode = false;

    var panel = document.createElement('section');
    panel.className = 'xiaogu-gallery-admin';
    panel.innerHTML = [
        '<header class="xiaogu-gallery-admin-head">',
        '  <div><h2>相册图片</h2><p>选择多张照片后会自动上传、排版并写入页面。</p></div>',
        '  <button type="button" class="btn btn-s" data-gallery-source-toggle>源码编辑</button>',
        '</header>',
        '<div class="xiaogu-gallery-toolbar">',
        '  <label><span>上传到</span><input type="text" data-gallery-album-input value="生活片刻" maxlength="60" placeholder="相册名称"></label>',
        '  <div class="xiaogu-gallery-upload-actions">',
        '    <button type="button" class="btn primary" data-gallery-pick>选择照片</button>',
        '    <button type="button" class="btn" data-gallery-camera>拍照</button>',
        '  </div>',
        '  <input type="file" accept="image/*" multiple data-gallery-files hidden>',
        '  <input type="file" accept="image/*" capture="environment" data-gallery-camera-file hidden>',
        '</div>',
        '<div class="xiaogu-gallery-dropzone" data-gallery-dropzone tabindex="0" role="button">',
        '  <span class="xiaogu-gallery-dropzone-icon" aria-hidden="true">＋</span>',
        '  <strong>点击选择或把照片拖到这里</strong>',
        '  <small>支持一次上传多张图片，手机端可直接选择相册或拍照</small>',
        '</div>',
        '<div class="xiaogu-gallery-status" data-gallery-status role="status"></div>',
        '<div class="xiaogu-gallery-items" data-gallery-items></div>',
        '<div class="xiaogu-gallery-admin-empty" data-gallery-admin-empty>',
        '  <strong>还没有添加照片</strong>',
        '  <span>选择照片后会在这里直接预览。</span>',
        '</div>'
    ].join('');

    var mount = toolbar || editArea;
    mount.parentNode.insertBefore(panel, mount);

    var albumInput = panel.querySelector('[data-gallery-album-input]');
    var fileInput = panel.querySelector('[data-gallery-files]');
    var cameraInput = panel.querySelector('[data-gallery-camera-file]');
    var dropzone = panel.querySelector('[data-gallery-dropzone]');
    var items = panel.querySelector('[data-gallery-items]');
    var empty = panel.querySelector('[data-gallery-admin-empty]');
    var status = panel.querySelector('[data-gallery-status]');
    var sourceToggle = panel.querySelector('[data-gallery-source-toggle]');

    function parseMarkdown(markdown) {
        var references = {};
        var result = [];
        var currentAlbum = '生活片刻';
        var lines = String(markdown || '').split(/\r?\n/);

        lines.forEach(function (line) {
            var reference = line.match(/^\s*\[([^\]]+)\]:\s*(\S+)/);
            if (reference) references[reference[1]] = reference[2];
        });

        lines.forEach(function (line) {
            var heading = line.match(/^\s*#{2,3}\s+(.+?)\s*#*\s*$/);
            if (heading) {
                currentAlbum = cleanValue(heading[1]) || '生活片刻';
                return;
            }

            var inlinePattern = /!\[([^\]]*)\]\((\S+?)(?:\s+["'][^"']*["'])?\)/g;
            var referencePattern = /!\[([^\]]*)\]\[([^\]]+)\]/g;
            var match;

            while ((match = inlinePattern.exec(line))) {
                result.push(makePhoto(match[1], match[2], currentAlbum));
            }
            while ((match = referencePattern.exec(line))) {
                if (references[match[2]]) {
                    result.push(makePhoto(match[1], references[match[2]], currentAlbum));
                }
            }
        });

        return result;
    }

    function cleanValue(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function makePhoto(title, url, album) {
        return {
            id: 'gallery-' + Date.now() + '-' + Math.random().toString(36).slice(2),
            title: cleanValue(title) || fileTitle(url),
            album: cleanValue(album) || '生活片刻',
            url: String(url || '').trim(),
            previewUrl: String(url || '').trim(),
            cid: 0,
            state: 'ready',
            error: ''
        };
    }

    function fileTitle(value) {
        var path = String(value || '').split(/[?#]/)[0];
        var name = path.split('/').pop() || '未命名照片';
        try { name = decodeURIComponent(name); } catch (error) {}
        return name;
    }

    function escapeMarkdown(value) {
        return cleanValue(value).replace(/([\\\[\]])/g, '\\$1');
    }

    function serialize() {
        var groups = [];
        var groupMap = new Map();

        photos.filter(function (photo) {
            return photo.state === 'ready' && photo.url;
        }).forEach(function (photo) {
            var album = cleanValue(photo.album) || '生活片刻';
            if (!groupMap.has(album)) {
                groupMap.set(album, []);
                groups.push(album);
            }
            groupMap.get(album).push(photo);
        });

        textarea.value = groups.map(function (album) {
            var imageLines = groupMap.get(album).map(function (photo) {
                return '![' + escapeMarkdown(photo.title || fileTitle(photo.url)) + '](' + photo.url + ')';
            });
            return '## ' + album + '\n\n' + imageLines.join('\n\n');
        }).join('\n\n');
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    }

    function setStatus(message, type) {
        status.textContent = message || '';
        status.className = 'xiaogu-gallery-status' + (type ? ' is-' + type : '');
    }

    function addAttachmentInput(cid) {
        if (!cid || form.querySelector('input[name="attachment[]"][value="' + cid + '"]')) return;
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'attachment[]';
        input.value = cid;
        form.appendChild(input);
    }

    function createCard(photo, index) {
        var card = document.createElement('article');
        var imageWrap = document.createElement('div');
        var image = document.createElement('img');
        var state = document.createElement('span');
        var fields = document.createElement('div');
        var titleLabel = document.createElement('label');
        var titleInput = document.createElement('input');
        var albumLabel = document.createElement('label');
        var cardAlbumInput = document.createElement('input');
        var actions = document.createElement('div');

        card.className = 'xiaogu-gallery-item is-' + photo.state;
        card.dataset.galleryItem = photo.id;
        imageWrap.className = 'xiaogu-gallery-item-preview';
        image.src = photo.previewUrl || photo.url;
        image.alt = '';
        state.className = 'xiaogu-gallery-item-state';
        state.textContent = photo.state === 'uploading' ? '上传中…' : (photo.state === 'error' ? '上传失败' : '');
        imageWrap.append(image, state);

        fields.className = 'xiaogu-gallery-item-fields';
        titleLabel.innerHTML = '<span>照片标题</span>';
        titleInput.type = 'text';
        titleInput.value = photo.title;
        titleInput.maxLength = 120;
        titleInput.placeholder = '照片标题';
        titleInput.disabled = photo.state === 'uploading';
        titleInput.addEventListener('input', function () {
            photo.title = titleInput.value;
            serialize();
        });
        titleLabel.appendChild(titleInput);

        albumLabel.innerHTML = '<span>所属相册</span>';
        cardAlbumInput.type = 'text';
        cardAlbumInput.value = photo.album;
        cardAlbumInput.maxLength = 60;
        cardAlbumInput.placeholder = '生活片刻';
        cardAlbumInput.disabled = photo.state === 'uploading';
        cardAlbumInput.addEventListener('input', function () {
            photo.album = cardAlbumInput.value;
            serialize();
        });
        albumLabel.appendChild(cardAlbumInput);
        fields.append(titleLabel, albumLabel);

        actions.className = 'xiaogu-gallery-item-actions';
        actions.append(
            actionButton('↑', '向前移动', index === 0 || photo.state === 'uploading', function () { movePhoto(index, -1); }),
            actionButton('↓', '向后移动', index === photos.length - 1 || photo.state === 'uploading', function () { movePhoto(index, 1); }),
            actionButton('删除', '删除照片', photo.state === 'uploading', function () { removePhoto(index); }, true)
        );
        card.append(imageWrap, fields, actions);

        if (photo.error) {
            var error = document.createElement('p');
            error.className = 'xiaogu-gallery-item-error';
            error.textContent = photo.error;
            card.appendChild(error);
        }
        return card;
    }

    function actionButton(text, label, disabled, handler, danger) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-s' + (danger ? ' xiaogu-gallery-delete' : '');
        button.textContent = text;
        button.title = label;
        button.setAttribute('aria-label', label);
        button.disabled = disabled;
        button.addEventListener('click', handler);
        return button;
    }

    function render() {
        items.replaceChildren();
        photos.forEach(function (photo, index) {
            items.appendChild(createCard(photo, index));
        });
        empty.hidden = photos.length > 0;
        items.hidden = photos.length === 0;
    }

    function movePhoto(index, direction) {
        var target = index + direction;
        if (target < 0 || target >= photos.length) return;
        var item = photos.splice(index, 1)[0];
        photos.splice(target, 0, item);
        render();
        serialize();
    }

    function removePhoto(index) {
        var photo = photos[index];
        if (!photo || photo.state === 'uploading') return;
        if (photo.previewUrl && photo.previewUrl.indexOf('blob:') === 0) {
            URL.revokeObjectURL(photo.previewUrl);
        }
        photos.splice(index, 1);
        render();
        serialize();
    }

    function validateFile(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return '“' + (file && file.name ? file.name : '该文件') + '”不是图片文件';
        }
        if (config.maxUploadSize && file.size > config.maxUploadSize) {
            return '“' + file.name + '”超过服务器上传大小限制';
        }
        return '';
    }

    function canvasBlob(canvas, type, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(resolve, type, quality);
        });
    }

    async function decodeImage(file) {
        if (typeof createImageBitmap === 'function') {
            try {
                var bitmap = await createImageBitmap(file, {imageOrientation: 'from-image'});
                return {
                    source: bitmap,
                    width: bitmap.width,
                    height: bitmap.height,
                    close: function () { bitmap.close(); }
                };
            } catch (error) {}
        }

        return new Promise(function (resolve, reject) {
            var objectUrl = URL.createObjectURL(file);
            var image = new Image();
            image.onload = function () {
                resolve({
                    source: image,
                    width: image.naturalWidth,
                    height: image.naturalHeight,
                    close: function () { URL.revokeObjectURL(objectUrl); }
                });
            };
            image.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('无法读取图片'));
            };
            image.src = objectUrl;
        });
    }

    async function prepareImage(file) {
        var compressible = /^image\/(jpeg|jpg|webp)$/i.test(file.type || '');
        if (!compressible) return file;

        var decoded;
        try {
            decoded = await decodeImage(file);
        } catch (error) {
            return file;
        }

        var maxSide = 2560;
        var scale = Math.min(1, maxSide / Math.max(decoded.width, decoded.height));
        var shouldResize = scale < 1;
        var shouldCompress = Boolean(config.maxUploadSize && file.size > config.maxUploadSize * 0.88);
        if (!shouldResize && !shouldCompress) {
            decoded.close();
            return file;
        }

        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(decoded.width * scale));
        canvas.height = Math.max(1, Math.round(decoded.height * scale));
        var context = canvas.getContext('2d', {alpha: false});
        context.drawImage(decoded.source, 0, 0, canvas.width, canvas.height);
        decoded.close();

        var limit = config.maxUploadSize ? config.maxUploadSize * 0.88 : Infinity;
        var qualities = [0.86, 0.76, 0.66, 0.56];
        var blob = null;
        for (var index = 0; index < qualities.length; index++) {
            blob = await canvasBlob(canvas, 'image/jpeg', qualities[index]);
            if (!blob || blob.size <= limit) break;
        }
        if (!blob) return file;

        var baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';
        return new File([blob], baseName + '.jpg', {
            type: 'image/jpeg',
            lastModified: file.lastModified || Date.now()
        });
    }

    async function uploadFile(file, album) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            setStatus('请选择图片文件。', 'error');
            return;
        }

        if (config.maxUploadSize && file.size > config.maxUploadSize * 0.88) {
            setStatus('正在优化大图“' + file.name + '”…', 'uploading');
        }
        file = await prepareImage(file);
        var validationError = validateFile(file);
        if (validationError) {
            setStatus(validationError, 'error');
            return;
        }

        var photo = makePhoto(file.name, '', album);
        photo.previewUrl = URL.createObjectURL(file);
        photo.state = 'uploading';
        photos.push(photo);
        uploadCount++;
        render();
        setStatus('正在上传 ' + uploadCount + ' 张照片…', 'uploading');

        try {
            var targetUrl = new URL(config.uploadUrl, window.location.href);
            targetUrl.searchParams.set('cid', String(config.cid || ''));
            var body = new FormData();
            body.append('file', file);
            var response = await fetch(targetUrl.toString(), {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('服务器返回 ' + response.status);
            var result = await response.json();
            var attachment = Array.isArray(result) ? result[1] : null;
            if (!attachment || !attachment.isImage || !attachment.url) {
                throw new Error('上传结果无效');
            }

            photo.url = attachment.url;
            photo.cid = Number(attachment.cid) || 0;
            photo.title = cleanValue(attachment.title) || photo.title;
            photo.state = 'ready';
            photo.error = '';
            if (photo.previewUrl && photo.previewUrl.indexOf('blob:') === 0) {
                URL.revokeObjectURL(photo.previewUrl);
            }
            photo.previewUrl = photo.url;
            addAttachmentInput(photo.cid);
            serialize();
        } catch (error) {
            photo.state = 'error';
            photo.error = '上传失败：' + error.message;
        } finally {
            uploadCount--;
            render();
            if (uploadCount > 0) {
                setStatus('正在上传 ' + uploadCount + ' 张照片…', 'uploading');
            } else if (photos.some(function (item) { return item.state === 'error'; })) {
                setStatus('部分照片上传失败，请删除失败项后重新选择。', 'error');
            } else {
                setStatus('照片已上传，点击“发布页面”保存更改。', 'success');
            }
        }
    }

    async function addFiles(fileList) {
        var files = Array.from(fileList || []);
        if (!files.length) return;
        var album = cleanValue(albumInput.value) || '生活片刻';
        for (var index = 0; index < files.length; index++) {
            await uploadFile(files[index], album);
        }
        fileInput.value = '';
        cameraInput.value = '';
    }

    function setSourceMode(enabled) {
        sourceMode = enabled;
        panel.classList.toggle('is-source-mode', enabled);
        [toolbar, editArea].forEach(function (element) {
            if (element) element.classList.toggle('xiaogu-gallery-source-visible', enabled);
        });
        sourceToggle.textContent = enabled ? '返回相册编辑' : '源码编辑';
        if (!enabled) {
            photos = parseMarkdown(textarea.value);
            render();
        } else {
            serialize();
        }
    }

    panel.querySelector('[data-gallery-pick]').addEventListener('click', function () { fileInput.click(); });
    panel.querySelector('[data-gallery-camera]').addEventListener('click', function () { cameraInput.click(); });
    fileInput.addEventListener('change', function () { addFiles(fileInput.files); });
    cameraInput.addEventListener('change', function () { addFiles(cameraInput.files); });
    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });
    ['dragenter', 'dragover'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });
    ['dragleave', 'drop'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });
    dropzone.addEventListener('drop', function (event) { addFiles(event.dataTransfer.files); });
    sourceToggle.addEventListener('click', function () { setSourceMode(!sourceMode); });

    form.addEventListener('submit', function (event) {
        if (uploadCount > 0) {
            event.preventDefault();
            setStatus('请等待照片上传完成后再保存。', 'error');
            panel.scrollIntoView({behavior: 'smooth', block: 'start'});
            return;
        }
        if (!sourceMode) serialize();
    });

    [toolbar, editArea].forEach(function (element) {
        if (element) element.classList.add('xiaogu-gallery-source');
    });
    render();
    serialize();
}());
