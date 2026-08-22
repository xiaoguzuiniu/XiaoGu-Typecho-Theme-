(function () {
    'use strict';

    const formSelector = '#comment-form, .moment-comment-form';

    function statusElement(form) {
        return form.querySelector('.comment-form-status, .moment-comment-status');
    }

    function clearField(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
    }

    function clearValidation(form) {
        form.querySelectorAll('.is-invalid').forEach(clearField);
        const status = statusElement(form);
        if (!status || status.dataset.validationError !== 'true') return;
        status.textContent = '';
        status.hidden = true;
        status.classList.remove('is-error');
        delete status.dataset.validationError;
    }

    function validationError(form) {
        const author = form.querySelector('[name="author"]');
        const mail = form.querySelector('[name="mail"]');
        const url = form.querySelector('[name="url"]');
        const text = form.querySelector('[name="text"]');

        if (author && author.value.trim() === '') {
            return {field: author, message: '请先填写你的昵称'};
        }
        if (mail && mail.value.trim() === '') {
            return {field: mail, message: '请填写常用邮箱'};
        }
        if (mail && !mail.validity.valid) {
            return {field: mail, message: '邮箱格式不正确，请检查后重试'};
        }
        if (url && url.value.trim() !== '' && !url.validity.valid) {
            return {field: url, message: '网址格式不正确，请以 http:// 或 https:// 开头'};
        }
        if (text && text.value.trim() === '') {
            return {field: text, message: '写点内容再发送吧'};
        }

        return null;
    }

    function showValidation(form, error) {
        clearValidation(form);
        error.field.classList.add('is-invalid');
        error.field.setAttribute('aria-invalid', 'true');

        const status = statusElement(form);
        if (status) {
            status.textContent = error.message;
            status.hidden = false;
            status.classList.add('is-error');
            status.dataset.validationError = 'true';
        }

        try {
            error.field.focus({preventScroll: true});
        } catch (focusError) {
            error.field.focus();
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest(formSelector);
        if (!form) return;

        const error = validationError(form);
        if (!error) {
            clearValidation(form);
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        showValidation(form, error);
    }, true);

    document.addEventListener('input', function (event) {
        const field = event.target.closest(
            '#comment-form input, #comment-form textarea, '
            + '.moment-comment-form input, .moment-comment-form textarea'
        );
        if (!field) return;

        const form = field.closest(formSelector);
        clearField(field);
        const status = statusElement(form);
        if (status && status.dataset.validationError === 'true') {
            status.textContent = '';
            status.hidden = true;
            status.classList.remove('is-error');
            delete status.dataset.validationError;
        }
    });
}());
