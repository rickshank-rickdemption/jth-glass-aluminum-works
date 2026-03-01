(() => {
    const BLOCKED_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.svg', '.avif'];

    const isImageUrl = (url) => {
        const value = String(url || '').toLowerCase();
        return BLOCKED_EXTENSIONS.some((ext) => value.includes(ext));
    };

    const elementHasImage = (node) => {
        if (!node || !(node instanceof Element)) return false;
        if (node.tagName === 'IMG' || node.tagName === 'PICTURE') return true;
        const style = window.getComputedStyle(node);
        const bg = style?.backgroundImage || '';
        return bg.includes('url(') && isImageUrl(bg);
    };

    const isImageTarget = (node) => {
        if (!node || !(node instanceof Element)) return false;
        let cur = node;
        while (cur && cur !== document.documentElement) {
            if (elementHasImage(cur)) return true;
            cur = cur.parentElement;
        }
        return false;
    };

    const lockImages = () => {
        document.querySelectorAll('img').forEach((img) => {
            img.setAttribute('draggable', 'false');
            img.style.userSelect = 'none';
            img.style.webkitUserDrag = 'none';
            img.style.webkitTouchCallout = 'none';
        });
    };

    document.addEventListener('contextmenu', (e) => {
        e.preventDefault();
    }, true);

    document.addEventListener('dragstart', (e) => {
        if (isImageTarget(e.target)) {
            e.preventDefault();
        }
    }, true);

    document.addEventListener('keydown', (e) => {
        const k = String(e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && (k === 's' || k === 'u')) {
            e.preventDefault();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) {
            e.preventDefault();
        }
    }, true);

    const observer = new MutationObserver(() => lockImages());
    document.addEventListener('DOMContentLoaded', () => {
        lockImages();
        observer.observe(document.documentElement, { childList: true, subtree: true });
    });
})();
