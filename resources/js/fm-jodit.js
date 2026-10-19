/**
 * Jodit integration for vm-engine/file-manager.
 *
 * Adds two toolbar buttons that open the Livewire file picker
 * (<livewire:filemanager::file-picker /> must be on the page) and insert the
 * chosen file: an <img> for "fmImage", a link for "fmFile".
 *
 * Usage:
 *   import fmJoditConfig from '.../fm-jodit.js';
 *   Jodit.make('#editor', { ...fmJoditConfig });
 *
 * Or keep your own toolbar and append ours:
 *   Jodit.make('#editor', {
 *     buttons: [...yourButtons, 'fmImage', 'fmFile'],
 *     extraButtons: fmJoditConfig.extraButtons,
 *   });
 */

/** Editor waiting for a picked file: { editor, kind: 'image' | 'file' }. */
let pending = null;

// Dispatched by the FilePicker Livewire component once a file is chosen.
document.addEventListener('fm:file-selected', (event) => {
    const url = event.detail?.url;

    if (!pending || !url) return;

    const { editor, kind } = pending;
    pending = null;

    editor.s.restore();

    if (kind === 'image') {
        editor.s.insertImage(url);

        return;
    }

    // Built as a node (not an HTML string) so odd filenames can't inject markup.
    const link = editor.createInside.element('a', { href: url });
    link.textContent = decodeURIComponent(url.split('/').pop() || url);
    editor.s.insertNode(link);
});

function openPicker(editor, kind) {
    editor.s.save();
    pending = { editor, kind };

    const accept = kind === 'image' ? 'image/*' : '*';

    if (window.Livewire) {
        window.Livewire.dispatch('fm:open-picker', { accept });
    } else {
        document.dispatchEvent(new CustomEvent('fm:open-picker', { detail: { accept }, bubbles: true }));
    }
}

const fmJoditConfig = {
    extraButtons: [
        {
            name: 'fmImage',
            icon: 'image',
            tooltip: 'Insert image from File Manager',
            exec: (editor) => openPicker(editor, 'image'),
        },
        {
            name: 'fmFile',
            icon: 'file',
            tooltip: 'Insert file link from File Manager',
            exec: (editor) => openPicker(editor, 'file'),
        },
    ],
};

export default fmJoditConfig;
