import { router } from '@inertiajs/svelte';

function isUnmodifiedPrimaryClick(event: MouseEvent): boolean {
    return (
        event.button === 0 &&
        !event.defaultPrevented &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey
    );
}

function resolveAnchor(event: MouseEvent): HTMLAnchorElement | null {
    const target = event.target;
    if (target instanceof Element) {
        const closest = target.closest('a');
        if (closest instanceof HTMLAnchorElement) {
            return closest;
        }
    }

    if (typeof event.composedPath === 'function') {
        for (const node of event.composedPath()) {
            if (node instanceof HTMLAnchorElement) {
                return node;
            }
        }
    }

    return null;
}

function shouldHandle(anchor: HTMLAnchorElement): boolean {
    if (anchor.hasAttribute('download')) return false;
    if (anchor.dataset.inertia === 'false') return false;

    const target = anchor.getAttribute('target');
    if (target && target !== '_self') return false;

    const raw = anchor.getAttribute('href');
    if (
        !raw ||
        raw.startsWith('mailto:') ||
        raw.startsWith('tel:') ||
        raw.startsWith('javascript:')
    ) {
        return false;
    }

    if (raw === '#' || raw.startsWith('#')) return false;

    let url: URL;
    try {
        url = new URL(raw, window.location.href);
    } catch {
        return false;
    }

    if (url.origin !== window.location.origin) return false;

    return true;
}

export function installInertiaLinkDelegation(root: ParentNode = document): () => void {
    const onClick = (event: Event): void => {
        if (!(event instanceof MouseEvent) || !isUnmodifiedPrimaryClick(event)) {
            return;
        }

        const anchor = resolveAnchor(event);
        if (!anchor || !shouldHandle(anchor)) {
            return;
        }

        const url = new URL(anchor.href);

        if (
            url.pathname === window.location.pathname &&
            url.search === window.location.search &&
            url.hash !== ''
        ) {
            return;
        }

        event.preventDefault();
        router.visit(`${url.pathname}${url.search}${url.hash}`);
    };

    root.addEventListener('click', onClick);
    return () => root.removeEventListener('click', onClick);
}
