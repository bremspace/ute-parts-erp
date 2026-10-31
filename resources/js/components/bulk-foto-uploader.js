// Bulk Product Image Import Alpine Component
// Handles client-side pre-flight sorting, mobile-friendly multi-file pickers, fflate selective extraction, and chunk upload.

export function getBulkFotoUploader(wire) {
    return {
        sourceType: 'files',
        sourceName: '',
        rawFilesList: [],
        fileObjectsMap: new Map(),
        zipRawFile: null,

        isPreChecking: false,
        preCheckDone: false,
        totalScanned: 0,
        matchedCount: 0,
        skippedCount: 0,
        matchedList: [],
        skippedList: [],
        matchedSet: new Set(),
        showDetail: false,

        isUploading: false,
        isCancelled: false,
        sessionId: '',
        uploadedFiles: 0,
        percent: 0,
        statusText: '',
        chunkSize: 5,
        maxRetries: 3,

        init() {
            const wireTarget = wire || this.$wire;
            if (wireTarget && typeof wireTarget.$watch === 'function') {
                wireTarget.$watch('fotoMode', (newMode) => {
                    if (this.rawFilesList.length > 0) {
                        this.runPreCheck(newMode);
                    }
                });
                wireTarget.$watch('fotoSourceType', () => {
                    this.resetUploader();
                });
            }
        },

        onFilesSelected(event) {
            const files = Array.from(event.target.files || []);
            if (files.length === 0) return;

            this.sourceType = 'files';
            this.sourceName = files.length > 1 ? (files.length + ' berkas foto') : (files[0].name || 'Berkas foto');
            this.fileObjectsMap.clear();
            this.zipRawFile = null;

            this.rawFilesList = files.map(f => {
                const relPath = f.webkitRelativePath || f.name;
                this.fileObjectsMap.set(relPath, f);
                this.fileObjectsMap.set(f.name, f);
                return {
                    name: f.name,
                    relative_path: relPath,
                    size: f.size
                };
            });

            try { event.target.value = ''; } catch (_) {}

            const wireTarget = wire || this.$wire;
            const mode = (wireTarget && wireTarget.fotoMode) ? wireTarget.fotoMode : 'append';
            this.runPreCheck(mode);
        },

        async onZipSelected(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            this.sourceType = 'zip';
            this.sourceName = 'ZIP: ' + file.name + ' (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB)';
            this.zipRawFile = file;
            this.fileObjectsMap.clear();
            this.rawFilesList = [];

            this.isPreChecking = true;
            this.statusText = 'Membaca daftar file di dalam berkas ZIP...';

            try { event.target.value = ''; } catch (_) {}

            try {
                if (typeof window.fflate === 'undefined') {
                    throw new Error('Modul pembaca ZIP belum dimuat. Mohon refresh halaman.');
                }

                const arrayBuffer = await file.arrayBuffer();
                const uint8 = new Uint8Array(arrayBuffer);
                const entries = [];

                window.fflate.unzip(uint8, {
                    filter: function(entry) {
                        if (!entry.name.endsWith('/')) {
                            entries.push({
                                name: entry.name,
                                relative_path: entry.name,
                                size: entry.originalSize || 0
                            });
                        }
                        return false;
                    }
                }, (err) => {
                    if (err) {
                        console.error('Gagal membaca ZIP:', err);
                        alert('Gagal membaca arsip ZIP: ' + err.message);
                        this.resetUploader();
                        return;
                    }

                    this.rawFilesList = entries;
                    const wireTarget = wire || this.$wire;
                    const mode = (wireTarget && wireTarget.fotoMode) ? wireTarget.fotoMode : 'append';
                    this.runPreCheck(mode);
                });
            } catch (e) {
                console.error('ZIP Error:', e);
                alert('Terjadi kesalahan saat memproses file ZIP: ' + e.message);
                this.resetUploader();
            }
        },

        async runPreCheck(mode) {
            if (this.rawFilesList.length === 0) return;

            this.isPreChecking = true;
            this.preCheckDone = false;
            this.statusText = 'Menyortir & mencocokkan nama file ke SKU master produk...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const wireTarget = wire || this.$wire;
                const activeMode = mode || (wireTarget && wireTarget.fotoMode) || 'append';

                const res = await fetch('/app/wms/import-foto/pre-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        files: this.rawFilesList.slice(0, 10000),
                        mode: activeMode
                    })
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => null);
                    throw new Error((errData && errData.message) ? errData.message : 'HTTP Error ' + res.status);
                }

                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.message || 'Gagal menyortir daftar berkas');
                }

                this.totalScanned = data.total;
                this.matchedCount = data.matched_count;
                this.skippedCount = data.skipped_count;
                this.matchedList = data.matched || [];
                this.skippedList = data.skipped || [];

                this.matchedSet = new Set();
                this.matchedList.forEach(m => {
                    if (m.filename) this.matchedSet.add(m.filename);
                    if (m.relative_path) this.matchedSet.add(m.relative_path);
                });
                this.preCheckDone = true;
            } catch (e) {
                console.error('Pre-check error:', e);
                alert('Gagal menyortir berkas foto: ' + e.message);
            } finally {
                this.isPreChecking = false;
            }
        },

        resetUploader() {
            if (this.sessionId && this.uploadedFiles > 0) {
                this.callClearSession(this.sessionId);
            }
            this.sourceType = 'files';
            this.sourceName = '';
            this.rawFilesList = [];
            this.fileObjectsMap.clear();
            this.zipRawFile = null;
            this.preCheckDone = false;
            this.totalScanned = 0;
            this.matchedCount = 0;
            this.skippedCount = 0;
            this.matchedList = [];
            this.skippedList = [];
            this.matchedSet.clear();
            this.showDetail = false;
            this.isUploading = false;
            this.isCancelled = false;
            this.sessionId = '';
        },

        async cancelUpload() {
            this.isCancelled = true;
            this.statusText = 'Membatalkan upload...';
            await this.callClearSession(this.sessionId);
            this.resetUploader();
        },

        async callClearSession(sessId) {
            if (!sessId) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                await fetch('/app/wms/import-foto/clear-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ session_id: sessId })
                });
            } catch (e) {
                console.warn('Gagal membersihkan staging:', e);
            }
        },

        async startFilteredUpload() {
            if (this.matchedCount === 0) {
                alert('Tidak ada foto yang cocok dengan SKU katalog produk.');
                return;
            }

            this.isUploading = true;
            this.isCancelled = false;
            this.uploadedFiles = 0;
            this.percent = 0;
            this.sessionId = 'bulk_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
            this.statusText = 'Mempersiapkan pengunggahan berkas terfilter...';

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                if (this.sourceType === 'zip') {
                    await this.uploadZipSelectively(csrf);
                } else {
                    await this.uploadFilesSelectively(csrf);
                }

                if (this.isCancelled) return;

                this.statusText = 'Seluruh foto valid terunggah! Memindai & memetakan ke katalog produk...';
                const finalSessionId = this.sessionId;

                const livewireProxy = wire || this.$wire;
                if (livewireProxy && typeof livewireProxy.call === 'function') {
                    await livewireProxy.call('scanFromStagingSession', finalSessionId);
                } else if (livewireProxy && typeof livewireProxy.scanFromStagingSession === 'function') {
                    await livewireProxy.scanFromStagingSession(finalSessionId);
                } else {
                    const el = this.$el?.closest('[wire\\:id]');
                    const compId = el?.getAttribute('wire:id');
                    const comp = compId && window.Livewire ? window.Livewire.find(compId) : null;
                    if (comp) {
                        await comp.call('scanFromStagingSession', finalSessionId);
                    } else {
                        throw new Error('Komponen Livewire tidak ditemukan.');
                    }
                }
            } catch (e) {
                console.error('Gagal upload terfilter:', e);
                alert('Terjadi kesalahan saat mengunggah foto: ' + (e.message || e));
            } finally {
                this.isUploading = false;
            }
        },

        async uploadFilesSelectively(csrf) {
            const validFiles = [];
            for (const item of this.matchedList) {
                const fileObj = this.fileObjectsMap.get(item.relative_path) || this.fileObjectsMap.get(item.filename);
                if (fileObj) {
                    validFiles.push({ file: fileObj, relPath: item.relative_path || item.filename });
                }
            }

            const total = validFiles.length;
            for (let i = 0; i < total; i += this.chunkSize) {
                if (this.isCancelled) return;

                const chunk = validFiles.slice(i, i + this.chunkSize);
                const formData = new FormData();
                formData.append('session_id', this.sessionId);

                chunk.forEach(({ file, relPath }) => {
                    formData.append('files[]', file);
                    formData.append('paths[]', relPath);
                });

                await this.postChunkWithRetry(formData, csrf);
                this.uploadedFiles = Math.min(total, i + chunk.length);
                this.percent = Math.round((this.uploadedFiles / total) * 100);
                this.statusText = 'Mengunggah foto: ' + this.uploadedFiles + ' / ' + total + '...';
            }
        },

        async uploadZipSelectively(csrf) {
            this.statusText = 'Mengekstrak foto yang lolos sortir dari ZIP...';
            const arrayBuffer = await this.zipRawFile.arrayBuffer();
            const uint8 = new Uint8Array(arrayBuffer);

            const unzippedFiles = await new Promise((resolve, reject) => {
                window.fflate.unzip(uint8, {
                    filter: (entry) => this.matchedSet.has(entry.name)
                }, (err, data) => {
                    if (err) reject(err);
                    else resolve(data);
                });
            });

            const entries = Object.keys(unzippedFiles);
            const total = entries.length;

            for (let i = 0; i < total; i += this.chunkSize) {
                if (this.isCancelled) return;

                const chunkKeys = entries.slice(i, i + this.chunkSize);
                const formData = new FormData();
                formData.append('session_id', this.sessionId);

                chunkKeys.forEach(name => {
                    const bytes = unzippedFiles[name];
                    const lowerName = name.toLowerCase();
                    const mime = lowerName.endsWith('.png') ? 'image/png' : (lowerName.endsWith('.webp') ? 'image/webp' : 'image/jpeg');
                    const blob = new Blob([bytes], { type: mime });
                    formData.append('files[]', blob, name);
                    formData.append('paths[]', name);
                });

                await this.postChunkWithRetry(formData, csrf);
                this.uploadedFiles = Math.min(total, i + chunkKeys.length);
                this.percent = Math.round((this.uploadedFiles / total) * 100);
                this.statusText = 'Mengunggah foto: ' + this.uploadedFiles + ' / ' + total + '...';
            }
        },

        async postChunkWithRetry(formData, csrf) {
            let success = false;
            let attempt = 0;

            while (!success && attempt < this.maxRetries && !this.isCancelled) {
                attempt++;
                try {
                    const res = await fetch('/app/wms/import-foto/chunk', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    if (res.ok) {
                        success = true;
                    } else {
                        const errJson = await res.json().catch(() => null);
                        console.warn('Chunk retry ' + attempt + '/' + this.maxRetries + ':', errJson);
                        await new Promise(r => setTimeout(r, 1000 * attempt));
                    }
                } catch (err) {
                    console.warn('Chunk network error retry ' + attempt + '/' + this.maxRetries + ':', err);
                    await new Promise(r => setTimeout(r, 1000 * attempt));
                }
            }

            if (!success && !this.isCancelled) {
                throw new Error('Gagal mengunggah berkas setelah ' + this.maxRetries + 'x percobaan jaringan.');
            }
        }
    };
}

// Global registry for direct or Alpine access
window.bulkFotoUploader = getBulkFotoUploader;

if (typeof window !== 'undefined') {
    if (window.Alpine) {
        window.Alpine.data('bulkFotoUploader', (wire) => getBulkFotoUploader(wire));
    } else {
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('bulkFotoUploader', (wire) => getBulkFotoUploader(wire));
        });
    }
}
