// 4:5 crop editor for nominee portraits. The frame shows exactly what will be saved; drag (mouse or one
// finger) to move the photo, the slider or +/- to zoom, arrow keys to nudge. The crop is sent to Livewire as
// {x, y, width}, fractions of the original image, and the server re-encodes from the original.
export function registerCropEditor(Alpine) {
    Alpine.data('cropEditor', (crop, src) => ({
        crop,
        src,
        natural: { width: 0, height: 0 },
        frameWidth: 0,
        zoom: 0,
        dragging: null,

        init() {
            this.$watch('src', () => this.load());
            this.load();
            new ResizeObserver(() => this.measure()).observe(this.$refs.frame);
        },

        load() {
            if (!this.src) return;
            const image = new Image();
            image.onload = () => {
                this.natural = { width: image.naturalWidth, height: image.naturalHeight };
                this.measure();
                if (!this.crop || typeof this.crop.width !== 'number') {
                    this.zoom = 0;
                    this.applyZoom(true);
                } else {
                    this.zoom = Math.round((1 - this.crop.width / this.maxWidth()) / 0.8 * 100);
                }
            };
            image.src = this.src;
        },

        measure() {
            this.frameWidth = this.$refs.frame.clientWidth;
        },

        maxWidth() {
            const { width, height } = this.natural;
            return width && height ? Math.min(1, (height * 0.8) / width) : 1;
        },

        heightFraction(width) {
            return (width * this.natural.width) / 0.8 / this.natural.height;
        },

        clamp(next) {
            const width = Math.max(this.maxWidth() * 0.2, Math.min(this.maxWidth(), next.width));
            const height = this.heightFraction(width);
            return {
                width: +width.toFixed(5),
                x: +Math.max(0, Math.min(1 - width, next.x)).toFixed(5),
                y: +Math.max(0, Math.min(1 - height, next.y)).toFixed(5),
            };
        },

        applyZoom(centre = false) {
            const width = this.maxWidth() * (1 - (this.zoom / 100) * 0.8);
            const current = this.crop && typeof this.crop.width === 'number' ? this.crop : { x: 0, y: 0, width: this.maxWidth() };
            // Keep the centre of the frame where it is while zooming.
            const cx = centre ? 0.5 : current.x + current.width / 2;
            const cy = centre ? 0.5 : current.y + this.heightFraction(current.width) / 2;
            this.crop = this.clamp({ width, x: cx - width / 2, y: cy - this.heightFraction(width) / 2 });
        },

        nudge(dx, dy) {
            this.crop = this.clamp({ ...this.crop, x: this.crop.x + dx * this.crop.width, y: this.crop.y + dy * this.heightFraction(this.crop.width) });
        },

        start(event) {
            this.dragging = { x: event.clientX, y: event.clientY, crop: { ...this.crop } };
            event.target.setPointerCapture(event.pointerId);
        },

        move(event) {
            if (!this.dragging || !this.frameWidth) return;
            const scale = this.frameWidth / (this.dragging.crop.width * this.natural.width); // px on screen per source px
            const dx = (event.clientX - this.dragging.x) / scale / this.natural.width;
            const dy = (event.clientY - this.dragging.y) / scale / this.natural.height;
            this.crop = this.clamp({ ...this.dragging.crop, x: this.dragging.crop.x - dx, y: this.dragging.crop.y - dy });
        },

        end() {
            this.dragging = null;
        },

        imageStyle() {
            if (!this.crop || !this.natural.width || !this.frameWidth) return 'visibility:hidden';
            const scale = this.frameWidth / (this.crop.width * this.natural.width);
            return `width:${this.natural.width * scale}px;height:${this.natural.height * scale}px;`
                + `transform:translate(${-this.crop.x * this.natural.width * scale}px,${-this.crop.y * this.natural.height * scale}px)`;
        },
    }));
}
