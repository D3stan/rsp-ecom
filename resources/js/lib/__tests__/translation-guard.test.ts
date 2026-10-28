import { beforeAll, beforeEach, describe, expect, it } from 'vitest';

class FakeNode {
    parentNode: FakeNode | null = null;
    children: FakeNode[] = [];

    removeChild(child: FakeNode): FakeNode {
        if (child.parentNode !== this) {
            throw new Error('NotFoundError');
        }
        this.children = this.children.filter((c) => c !== child);
        child.parentNode = null;

        return child;
    }

    insertBefore(newNode: FakeNode, referenceNode: FakeNode | null): FakeNode {
        if (referenceNode && referenceNode.parentNode !== this) {
            throw new Error('NotFoundError');
        }
        const index = referenceNode ? this.children.indexOf(referenceNode) : this.children.length;
        this.children.splice(index, 0, newNode);
        newNode.parentNode = this;

        return newNode;
    }
}

const page = { lang: 'en', classes: [] as string[], translatedNodes: false };

const fakeDocument = {
    documentElement: {
        get lang() {
            return page.lang;
        },
        classList: { contains: (name: string) => page.classes.includes(name) },
    },
    querySelector: () => (page.translatedNodes ? {} : null),
};

function domError(): Error {
    const error = new Error("Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.");
    error.name = 'NotFoundError';

    return error;
}

function movedNode(): FakeNode {
    const node = new FakeNode();
    new FakeNode().insertBefore(node, null);

    return node;
}

let guard: typeof import('../translation-guard');

beforeAll(async () => {
    (globalThis as { Node?: unknown }).Node = FakeNode;
    (globalThis as { document?: unknown }).document = fakeDocument;
    // Imported after the fakes exist, since the module reads the original <html lang> on load
    guard = await import('../translation-guard');
    guard.guardAgainstBrowserTranslation();
});

beforeEach(() => {
    page.lang = 'en';
    page.classes = [];
    page.translatedNodes = false;
});

describe('guardAgainstBrowserTranslation', () => {
    it('removes a real child as before', () => {
        const parent = new FakeNode();
        const child = new FakeNode();
        parent.insertBefore(child, null);

        parent.removeChild(child);

        expect(parent.children).toEqual([]);
    });

    it('still throws for a moved node when the page is not translated', () => {
        expect(() => new FakeNode().removeChild(movedNode())).toThrow('NotFoundError');
        expect(() => new FakeNode().insertBefore(new FakeNode(), movedNode())).toThrow('NotFoundError');
    });

    it.each([
        ['Safari or Firefox changed the lang', () => (page.lang = 'hu')],
        ['Chrome added its class', () => (page.classes = ['translated-ltr'])],
        ['Edge or another translator tagged nodes', () => (page.translatedNodes = true)],
    ])('ignores removing a moved node when %s', (_, translate) => {
        translate();
        const moved = movedNode();

        expect(new FakeNode().removeChild(moved)).toBe(moved);
    });

    it('appends when the reference node was moved on a translated page', () => {
        page.lang = 'hu';
        const parent = new FakeNode();
        const existing = new FakeNode();
        const incoming = new FakeNode();
        parent.insertBefore(existing, null);

        parent.insertBefore(incoming, movedNode());

        expect(parent.children).toEqual([existing, incoming]);
    });
});

describe('isTranslatedPageError', () => {
    it('keeps DOM errors from untranslated pages for Sentry', () => {
        expect(guard.isTranslatedPageError(domError())).toBe(false);
    });

    it('drops DOM errors from translated pages', () => {
        page.lang = 'hu';

        expect(guard.isTranslatedPageError(domError())).toBe(true);
    });

    it('keeps other errors on translated pages', () => {
        page.lang = 'hu';

        expect(guard.isTranslatedPageError(new Error('boom'))).toBe(false);
    });
});
