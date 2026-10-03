import { completeLines, readerName, nextOffset } from './timing/lines.js';

// RFIDServer writes one file per reader into the race folder. A file only appears when its reader
// gets its first read, and sometimes only one reader ever does, so each tick reads whatever is there.
// Nothing is buffered here: the file is the buffer. An offset only moves after the server confirmed,
// and after a reload everything is sent again (the server skips lines it already has).

const POLL_MS = 1000;
const MAX_BATCH_BYTES = 256 * 1024;

function store(mode, action) {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('timing', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('folders');
        open.onerror = () => reject(open.error);
        open.onsuccess = () => {
            try {
                const request = action(open.result.transaction('folders', mode).objectStore('folders'));
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            } catch (error) {
                reject(error);
            }
        };
    });
}

// Remembering the folder is a convenience: if it fails, the folder is simply chosen again after a reload.
const saveFolder = (key, handle) => store('readwrite', (folders) => folders.put(handle, key)).catch(() => {});
const loadFolder = (key) => store('readonly', (folders) => folders.get(key)).catch(() => null);
const countLines = (text) => text.split('\n').length - 1;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('timing', ({ eventId, eventFolder, url, csrf, t }) => ({
        t,
        supported: 'showDirectoryPicker' in window,
        folder: null,
        savedFolder: null,
        prepared: false,
        online: true,
        readers: {},

        async init() {
            window.addEventListener('beforeunload', (event) => {
                if (this.waiting() > 0) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            if (!this.supported) {
                return;
            }

            const handle = await loadFolder(eventId);
            if (handle && (await handle.queryPermission({ mode: 'read' })) === 'granted') {
                this.use(handle);
            } else {
                this.savedFolder = handle ?? null;
            }
        },

        async chooseFolder() {
            if (this.savedFolder && (await this.savedFolder.requestPermission({ mode: 'read' })) === 'granted') {
                return this.use(this.savedFolder);
            }

            const handle = await window.showDirectoryPicker({ id: 'timing', mode: 'read' });
            await saveFolder(eventId, handle);
            this.use(handle);
        },

        async prepare() {
            const parent = await window.showDirectoryPicker({ id: 'timing', mode: 'readwrite' });
            const handle = await parent.getDirectoryHandle(eventFolder, { create: true });
            await saveFolder(eventId, handle);
            this.prepared = true;
            this.use(handle);
        },

        use(handle) {
            this.folder = handle;
            this.savedFolder = null;
            this.timer ??= setInterval(() => this.tick(), POLL_MS);
            this.tick();
        },

        waiting() {
            return Object.values(this.readers).reduce((sum, reader) => sum + reader.waiting, 0);
        },

        readerNames() {
            return Object.keys(this.readers).sort();
        },

        async tick() {
            if (this.busy) {
                return;
            }
            this.busy = true;

            try {
                for await (const [name, entry] of this.folder.entries()) {
                    const reader = entry.kind === 'file' ? readerName(name) : null;
                    if (reader) {
                        await this.sync(reader, await entry.getFile());
                    }
                }
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    this.savedFolder = this.folder;
                    this.folder = null;
                }
            } finally {
                this.busy = false;
            }
        },

        async sync(reader, file) {
            this.readers[reader] ??= { offset: 0, sent: 0, waiting: 0 };
            const state = this.readers[reader]; // Alpine's reactive proxy, so the page updates
            state.offset = nextOffset(state.offset, file.size);

            const unsent = completeLines(new Uint8Array(await file.slice(state.offset).arrayBuffer()));
            state.waiting = countLines(unsent.text);

            const batch = completeLines(new Uint8Array(await file.slice(state.offset, state.offset + MAX_BATCH_BYTES).arrayBuffer()));
            if (batch.length === 0) {
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ reader, lines: batch.text }),
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch {
                this.online = false;

                return;
            }

            this.online = true;
            state.offset += batch.length;
            state.sent += countLines(batch.text);
            state.waiting -= countLines(batch.text);
        },
    }));
});
