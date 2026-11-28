// The CV photo: frame it at 3:4 by dragging and zooming, then upload a 600 × 800 JPEG.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('photoCropper', () => ({
        width: 240,
        height: 320,
        source: null,
        image: null,
        zoom: 1,
        cover: 1,
        x: 0,
        y: 0,
        drag: null,
        busy: false,
        failed: false,

        choose(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            this.failed = false;
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = () => {
                this.image = image;
                this.source = url;
                // The smallest size that still fills the frame.
                this.cover = Math.max(this.width / image.naturalWidth, this.height / image.naturalHeight);
                this.zoom = 1;
                this.x = (this.width - image.naturalWidth * this.scale) / 2;
                this.y = (this.height - image.naturalHeight * this.scale) / 2;
            };
            image.onerror = () => {
                URL.revokeObjectURL(url);
                this.failed = true;
            };
            image.src = url;
        },

        get scale() {
            return this.cover * this.zoom;
        },

        get imageStyle() {
            if (!this.image) return '';
            return `left: ${this.x}px; top: ${this.y}px; width: ${this.image.naturalWidth * this.scale}px; height: ${this.image.naturalHeight * this.scale}px`;
        },

        // Keeps the photo covering the whole frame.
        clamp() {
            this.x = Math.min(0, Math.max(this.width - this.image.naturalWidth * this.scale, this.x));
            this.y = Math.min(0, Math.max(this.height - this.image.naturalHeight * this.scale, this.y));
        },

        // Zooms around the middle of the frame.
        setZoom(zoom) {
            const middleX = (this.width / 2 - this.x) / this.scale;
            const middleY = (this.height / 2 - this.y) / this.scale;
            this.zoom = zoom;
            this.x = this.width / 2 - middleX * this.scale;
            this.y = this.height / 2 - middleY * this.scale;
            this.clamp();
        },

        start(event) {
            this.drag = { x: event.clientX - this.x, y: event.clientY - this.y };
            event.currentTarget.setPointerCapture(event.pointerId);
        },

        move(event) {
            if (!this.drag) return;
            this.x = event.clientX - this.drag.x;
            this.y = event.clientY - this.drag.y;
            this.clamp();
        },

        end() {
            this.drag = null;
        },

        nudge(x, y) {
            this.x += x;
            this.y += y;
            this.clamp();
        },

        cancel() {
            if (this.source) URL.revokeObjectURL(this.source);
            this.source = null;
            this.image = null;
        },

        save() {
            const canvas = document.createElement('canvas');
            canvas.width = 600;
            canvas.height = 800;
            const factor = canvas.width / this.width;
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(this.image, this.x * factor, this.y * factor, this.image.naturalWidth * this.scale * factor, this.image.naturalHeight * this.scale * factor);

            this.busy = true;
            canvas.toBlob((blob) => {
                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                this.$wire.upload(
                    'photo',
                    file,
                    () => this.$wire.save().then(() => {
                        this.busy = false;
                        this.cancel();
                    }),
                    () => {
                        this.busy = false;
                    },
                );
            }, 'image/jpeg', 0.9);
        },
    }));
});

// The CV preview: the PDF the CV downloads as, drawn page by page with pdf.js, so its pages
// and page breaks are exactly the file's. Drawn again after every save.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cvPreview', (url, pageLabel) => ({
        pages: null,
        error: null,
        loading: false,
        // Counts draws, so a slower earlier one never replaces a newer one.
        draws: 0,

        init() {
            this.draw();
        },

        get target() {
            return Number(this.$wire.pages);
        },

        async draw() {
            const draw = ++this.draws;
            this.loading = true;
            try {
                // Loaded on the first draw only, so other pages don't carry pdf.js.
                const [pdfjs, worker] = await Promise.all([import('pdfjs-dist'), import('pdfjs-dist/build/pdf.worker.min.mjs?url')]);
                pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

                const response = await fetch(url, { cache: 'no-store' });
                if (!response.ok) throw new Error(await response.text());
                const task = pdfjs.getDocument({ data: await response.arrayBuffer() });
                const pdf = await task.promise;
                const count = pdf.numPages;
                // Drawn at the box's width in screen pixels, so the text stays sharp.
                const width = Math.max(this.$refs.pages.clientWidth, 320) * window.devicePixelRatio;
                const canvases = [];
                for (let number = 1; number <= count; number++) {
                    const page = await pdf.getPage(number);
                    const viewport = page.getViewport({ scale: width / page.getViewport({ scale: 1 }).width });
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.floor(viewport.width);
                    canvas.height = Math.floor(viewport.height);
                    canvas.className = 'block w-full rounded-sm bg-white shadow-sm';
                    canvas.setAttribute('role', 'img');
                    canvas.setAttribute('aria-label', pageLabel.replace(':page', number).replace(':pages', count));
                    await page.render({ canvas, viewport }).promise;
                    canvases.push(canvas);
                }
                await task.destroy();
                if (draw !== this.draws) return;
                this.$refs.pages.replaceChildren(...canvases);
                this.pages = count;
                this.error = null;
            } catch (error) {
                if (draw === this.draws) this.error = error.message;
            } finally {
                if (draw === this.draws) this.loading = false;
            }
        },
    }));
});

// The cover letter preview: an A4 page scaled to fit, reloaded after every change.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('letterPreview', (url) => ({
        version: 0,
        scale: 1,
        height: 1123,

        init() {
            this.fit();
            this.observer = new ResizeObserver(() => this.fit());
            this.observer.observe(this.$refs.box);
        },

        // Leaving the page with wire:navigate removes the preview; its watcher goes with it.
        destroy() {
            this.observer.disconnect();
        },

        get src() {
            return `${url}?v=${this.version}`;
        },

        reload() {
            this.version++;
        },

        fit() {
            this.scale = Math.min(1, this.$refs.box.clientWidth / 794);
        },

        loaded() {
            const page = this.$refs.frame.contentDocument?.documentElement;
            if (page) this.height = page.scrollHeight;
        },
    }));
});

// A spoken answer in a mock interview, added to the answer box: recorded and sent to the
// app's Whisper server when there is one, or else turned into text by the browser itself.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('spokenAnswer', (whisper, language, messages) => ({
        listening: false,
        busy: false,
        error: '',
        recognition: null,
        recorder: null,

        get supported() {
            return whisper
                ? Boolean(navigator.mediaDevices?.getUserMedia && window.MediaRecorder)
                : Boolean(window.SpeechRecognition || window.webkitSpeechRecognition);
        },

        destroy() {
            this.recognition?.abort();
            if (this.recorder?.state === 'recording') {
                this.recorder.onstop = null;
                this.recorder.stop();
            }
            this.recorder?.stream.getTracks().forEach((track) => track.stop());
        },

        toggle() {
            this.error = '';
            if (this.listening) {
                whisper ? this.recorder.stop() : this.recognition.stop();

                return;
            }
            whisper ? this.record() : this.recognise();
        },

        // The browser's own speech recognition, writing into the box as the person speaks.
        recognise() {
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const box = this.$refs.answer;
            const before = box.value.trim();
            this.recognition = new Recognition();
            this.recognition.lang = language;
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.onresult = (event) => {
                const spoken = Array.from(event.results, (result) => result[0].transcript).join('');
                box.value = [before, spoken.trim()].filter(Boolean).join(' ');
                box.dispatchEvent(new Event('input'));
            };
            this.recognition.onerror = (event) => {
                this.error = event.error === 'not-allowed' ? messages.denied : messages.failed;
            };
            this.recognition.onend = () => {
                this.listening = false;
            };
            this.recognition.start();
            this.listening = true;
        },

        // A recording for the Whisper server, which the app adds to the box.
        async record() {
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch {
                this.error = messages.denied;

                return;
            }
            const chunks = [];
            this.recorder = new MediaRecorder(stream);
            this.recorder.ondataavailable = (event) => chunks.push(event.data);
            this.recorder.onstop = () => {
                stream.getTracks().forEach((track) => track.stop());
                this.listening = false;
                const type = this.recorder.mimeType.split(';')[0] || 'audio/webm';
                const file = new File(chunks, `answer.${type.split('/')[1]}`, { type });
                this.busy = true;
                // Typed text goes to the server first, so the spoken words are added to it.
                this.$wire.answer = this.$refs.answer.value;
                this.$wire.upload(
                    'recording',
                    file,
                    () => this.$wire.transcribe().finally(() => {
                        this.busy = false;
                    }),
                    () => {
                        this.busy = false;
                        this.error = messages.failed;
                    },
                );
            };
            this.recorder.start();
            this.listening = true;
        },
    }));
});

// The theme toggle in the header: switch, and remember the choice.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) return;

    const dark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch {
        // Without storage the choice lasts for this page only.
    }
});
