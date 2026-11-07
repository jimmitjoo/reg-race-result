// The text block written in place (#115): Tiptap with only what a text block may hold, saved as a draft
// while typing. Loaded on the editor page only.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';

const richText = (id, content, placeholder, askLink) => {
    // Outside Alpine's reactive data: a proxied editor breaks ProseMirror.
    let editor;

    return {
        focused: false,
        tick: 0,
        init() {
            let timer;
            const save = () => this.$wire.setField(id, 'body', editor.isEmpty ? '' : editor.getHTML());
            editor = new Editor({
                element: this.$refs.body,
                content,
                extensions: [
                    StarterKit.configure({ heading: { levels: [3] }, code: false, codeBlock: false, horizontalRule: false, strike: false }),
                    Link.configure({ openOnClick: false, autolink: true, protocols: ['mailto', 'tel'] }),
                    Placeholder.configure({ placeholder }),
                ],
                onUpdate: () => { clearTimeout(timer); timer = setTimeout(save, 500); },
                onTransaction: () => this.tick++,
                onFocus: () => (this.focused = true),
                onBlur: () => { this.focused = false; clearTimeout(timer); save(); },
            });
        },
        destroy() {
            editor?.destroy();
        },
        active(name, attributes = {}) {
            this.tick;
            return editor?.isActive(name, attributes) ?? false;
        },
        toggle(command, ...args) {
            editor.chain().focus()[command](...args).run();
        },
        link() {
            const previous = editor.getAttributes('link').href ?? '';
            const href = window.prompt(askLink, previous || 'https://');
            if (href === null) return;
            const chain = editor.chain().focus().extendMarkRange('link');
            href.trim() === '' || href.trim() === 'https://' ? chain.unsetLink().run() : chain.setLink({ href: href.trim() }).run();
        },
    };
};

const register = () => window.Alpine.data('richText', richText);
window.Alpine ? register() : document.addEventListener('alpine:init', register);
