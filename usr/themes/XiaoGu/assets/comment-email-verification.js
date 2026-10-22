(function () {
    'use strict';

    function mail(form) {
        return (form.querySelector('[name="mail"]')?.value || '').trim().toLowerCase();
    }

    function state(form, message, error) {
        const target = form.querySelector('.comment-mail-state');
        if (!target) return;
        target.textContent = message;
        target.classList.toggle('is-error', !!error);
    }

    async function request(form, action, extra) {
        const body = new URLSearchParams({mail: mail(form), _: form.dataset.mailVerifyToken || ''});
        if (extra) Object.entries(extra).forEach(([key, value]) => body.set(key, value));
        const response = await fetch((form.dataset.mailVerifyUrl || '') + action, {
            method: 'POST', body, credentials: 'same-origin', cache: 'no-store'
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || '邮箱验证失败');
        return result;
    }

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('.comment-mail-send, .comment-mail-check');
        if (!button) return;
        const form = button.closest('form[data-mail-verification-required="1"]');
        if (!form) return;
        const address = mail(form);
        const mailInput = form.querySelector('[name="mail"]');
        if (!mailInput?.validity.valid || !address) {
            state(form, '请先填写正确的邮箱', true);
            mailInput?.focus();
            return;
        }
        button.disabled = true;
        state(form, '请稍候…', false);
        try {
            if (button.matches('.comment-mail-send')) {
                const result = await request(form, 'mail_code');
                state(form, result.message, false);
                form.querySelector('.comment-mail-code')?.focus();
            } else {
                const code = (form.querySelector('.comment-mail-code')?.value || '').trim();
                if (!/^\d{6}$/.test(code)) throw new Error('请输入邮件中的 6 位验证码');
                const result = await request(form, 'mail_verify', {code});
                if (mail(form) !== address) throw new Error('邮箱已改变，请重新验证');
                document.querySelectorAll('form[data-mail-verification-required="1"]').forEach(function (other) {
                    if (mail(other) === address) {
                        other.dataset.verifiedMail = address;
                        state(other, result.message, false);
                    }
                });
                state(form, result.message, false);
            }
        } catch (error) {
            state(form, error.message || '邮箱验证失败，请稍后重试', true);
        } finally {
            button.disabled = false;
        }
    });

    document.addEventListener('input', function (event) {
        const input = event.target.closest('form[data-mail-verification-required="1"] [name="mail"]');
        if (!input) return;
        const form = input.closest('form');
        if (form.dataset.verifiedMail !== mail(form)) {
            form.dataset.verifiedMail = '';
            state(form, '邮箱有变化，请重新验证', false);
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-mail-verification-required="1"]');
        if (!form || !form.matches('#comment-form, .moment-comment-form')) return;
        if (form.dataset.verifiedMail === mail(form) && mail(form)) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        state(form, '请先收取验证码并验证邮箱，再发布评论或留言', true);
        form.querySelector('.comment-mail-send')?.focus();
    }, true);
}());
