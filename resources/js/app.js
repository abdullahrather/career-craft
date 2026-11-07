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
