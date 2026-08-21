(function (window, document) {
    'use strict';

    const desktop = window.matchMedia('(min-width: 901px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const controllers = new WeakMap();

    function isEnabled() {
        return desktop.matches && !reducedMotion.matches;
    }

    function normalizeDelta(event, element) {
        let delta = event.deltaY;
        if (event.deltaMode === 1) {
            delta *= 16;
        } else if (event.deltaMode === 2) {
            delta *= element.clientHeight;
        }
        return delta;
    }

    function nestedScrollableCanMove(target, boundary, delta) {
        let element = target instanceof Element ? target : null;

        while (element && element !== boundary) {
            const style = window.getComputedStyle(element);
            const scrollable = /auto|scroll/.test(style.overflowY)
                && element.scrollHeight > element.clientHeight + 1;

            if (scrollable) {
                const max = element.scrollHeight - element.clientHeight;
                if ((delta < 0 && element.scrollTop > 0) || (delta > 0 && element.scrollTop < max)) {
                    return true;
                }
            }
            element = element.parentElement;
        }

        return false;
    }

    function create(element, options) {
        if (!element) return null;
        if (controllers.has(element)) return controllers.get(element);

        const settings = Object.assign({
            listenWheel: true,
            easing: 0.16,
            threshold: 0.35
        }, options || {});
        let current = element.scrollTop;
        let target = current;
        let frame = 0;
        let lastApplied = current;

        function maxScroll() {
            return Math.max(0, element.scrollHeight - element.clientHeight);
        }

        function clamp(value) {
            return Math.max(0, Math.min(maxScroll(), value));
        }

        function sync() {
            current = element.scrollTop;
            target = current;
            lastApplied = current;
        }

        function cancel() {
            if (frame) {
                window.cancelAnimationFrame(frame);
                frame = 0;
            }
            sync();
        }

        function animate() {
            target = clamp(target);
            const distance = target - current;

            if (Math.abs(distance) <= settings.threshold) {
                current = target;
                lastApplied = current;
                element.scrollTop = current;
                frame = 0;
                return;
            }

            current += distance * settings.easing;
            lastApplied = current;
            element.scrollTop = current;
            frame = window.requestAnimationFrame(animate);
        }

        function start() {
            if (!frame) frame = window.requestAnimationFrame(animate);
        }

        function addDelta(delta) {
            if (!delta) return false;

            if (!isEnabled()) {
                const previous = element.scrollTop;
                element.scrollTop += delta;
                sync();
                return element.scrollTop !== previous;
            }

            if (!frame) sync();
            const next = clamp(target + delta);
            if (Math.abs(next - target) < 0.01) return frame !== 0;

            target = next;
            start();
            return true;
        }

        function scrollTo(value, immediate) {
            target = clamp(value);
            if (immediate || !isEnabled()) {
                cancel();
                element.scrollTop = target;
                sync();
                return;
            }
            if (!frame) current = element.scrollTop;
            start();
        }

        function onWheel(event) {
            if (!isEnabled() || event.ctrlKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

            const delta = normalizeDelta(event, element);
            if (nestedScrollableCanMove(event.target, element, delta)) return;
            if (addDelta(delta)) event.preventDefault();
        }

        function onScroll() {
            if (frame && Math.abs(element.scrollTop - lastApplied) < 1.5) return;
            sync();
        }

        if (settings.listenWheel) {
            element.addEventListener('wheel', onWheel, { passive: false });
        }
        element.addEventListener('scroll', onScroll, { passive: true });

        const controller = {
            addDelta: addDelta,
            cancel: cancel,
            getTarget: function () {
                return frame ? target : element.scrollTop;
            },
            scrollTo: scrollTo
        };
        controllers.set(element, controller);
        return controller;
    }

    function autoInit(root) {
        const scope = root || document;
        scope.querySelectorAll('[data-damped-scroll]').forEach(function (element) {
            create(element);
        });
    }

    window.XiaoGuDampedScroll = {
        autoInit: autoInit,
        create: create,
        normalizeDelta: normalizeDelta
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            autoInit(document);
        });
    } else {
        autoInit(document);
    }
}(window, document));
