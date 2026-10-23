// The "Position your photo" dialog. Resolves with the framed JPEG, or null when cancelled.

// Profile photos (<input data-crop>) are framed by the student before upload: the frame has the shape of
// a directory card (4:3, see welcome.blade.php), and round avatars show its middle, so the guide oval marks
// where the face should go. Only the framed part is uploaded.
const CROP_ASPECT = 4 / 3;
const CROP_MAX_WIDTH = 1200;
const CROP_MAX_ZOOM = 4;

export function cropPhoto(bitmap) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'm-auto w-[min(100%-2rem,28rem)] rounded-2xl bg-white p-4 sm:p-5 shadow-2xl backdrop:bg-slate-950/70';
        dialog.innerHTML = `
            <h2 class="text-lg font-bold text-slate-900">Position your photo</h2>
            <p class="mt-1 text-sm text-slate-500">Drag to move. Zoom with the slider or pinch. This is how it looks in the alumni directory.</p>
            <div data-crop-frame class="relative mt-3 aspect-[4/3] w-full cursor-grab touch-none select-none overflow-hidden rounded-xl bg-slate-900 active:cursor-grabbing">
                <canvas class="absolute inset-0 h-full w-full"></canvas>
                <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 h-[80%] w-[45%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-2 border-dashed border-white/80"></div>
            </div>
            <label class="mt-4 flex items-center gap-3 text-sm text-slate-600">
                <span aria-hidden="true">➖</span>
                <input data-crop-zoom type="range" min="1" max="${CROP_MAX_ZOOM}" step="0.01" value="1" class="w-full accent-brand-600" aria-label="Zoom">
                <span aria-hidden="true">➕</span>
            </label>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" data-crop-cancel class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" data-crop-done class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Use photo</button>
            </div>`;
        document.body.append(dialog);

        const frame = dialog.querySelector('[data-crop-frame]');
        const canvas = frame.querySelector('canvas');
        const zoomInput = dialog.querySelector('[data-crop-zoom]');
        const context = canvas.getContext('2d');

        // Image position (top-left corner, in frame pixels) and scale; the image always covers the frame.
        let frameWidth = 0;
        let coverScale = 1;
        let zoom = 1;
        let x = 0;
        let y = 0;

        const frameHeight = () => frameWidth / CROP_ASPECT;
        const scale = () => coverScale * zoom;
        const clamp = () => {
            x = Math.min(0, Math.max(frameWidth - bitmap.width * scale(), x));
            y = Math.min(0, Math.max(frameHeight() - bitmap.height * scale(), y));
        };
        const draw = () => {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(frameWidth * ratio);
            canvas.height = Math.round(frameHeight() * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.fillStyle = '#fff';
            context.fillRect(0, 0, frameWidth, frameHeight());
            context.drawImage(bitmap, x, y, bitmap.width * scale(), bitmap.height * scale());
        };
        const layout = () => {
            const previousWidth = frameWidth;
            frameWidth = frame.clientWidth;
            coverScale = Math.max(frameWidth / bitmap.width, frameHeight() / bitmap.height);

            if (previousWidth) {
                x *= frameWidth / previousWidth;
                y *= frameWidth / previousWidth;
            } else {
                // Start centred, a little above the middle where faces usually are.
                x = (frameWidth - bitmap.width * scale()) / 2;
                y = (frameHeight() - bitmap.height * scale()) * 0.3;
            }

            clamp();
            draw();
        };
        // Zoom around a point of the frame (its centre for the slider, the fingers for a pinch).
        const zoomTo = (newZoom, originX = frameWidth / 2, originY = frameHeight() / 2) => {
            newZoom = Math.min(CROP_MAX_ZOOM, Math.max(1, newZoom));
            const factor = newZoom / zoom;
            x = originX - (originX - x) * factor;
            y = originY - (originY - y) * factor;
            zoom = newZoom;
            zoomInput.value = String(zoom);
            clamp();
            draw();
        };

        const pointers = new Map();
        let pinchDistance = 0;
        const distance = () => {
            const [a, b] = [...pointers.values()];
            return Math.hypot(a.x - b.x, a.y - b.y);
        };

        frame.addEventListener('pointerdown', (event) => {
            frame.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            pinchDistance = pointers.size === 2 ? distance() : 0;
        });
        frame.addEventListener('pointermove', (event) => {
            const previous = pointers.get(event.pointerId);
            if (!previous) {
                return;
            }

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2 && pinchDistance) {
                const box = frame.getBoundingClientRect();
                const [a, b] = [...pointers.values()];
                const newDistance = distance();
                zoomTo(zoom * (newDistance / pinchDistance), (a.x + b.x) / 2 - box.left, (a.y + b.y) / 2 - box.top);
                pinchDistance = newDistance;
            } else if (pointers.size === 1) {
                x += event.clientX - previous.x;
                y += event.clientY - previous.y;
                clamp();
                draw();
            }
        });
        const release = (event) => {
            pointers.delete(event.pointerId);
            pinchDistance = 0;
        };
        frame.addEventListener('pointerup', release);
        frame.addEventListener('pointercancel', release);
        frame.addEventListener('wheel', (event) => {
            event.preventDefault();
            const box = frame.getBoundingClientRect();
            zoomTo(zoom * (event.deltaY < 0 ? 1.1 : 1 / 1.1), event.clientX - box.left, event.clientY - box.top);
        }, { passive: false });
        zoomInput.addEventListener('input', () => zoomTo(Number(zoomInput.value)));
        window.addEventListener('resize', layout);

        const finish = (file) => {
            window.removeEventListener('resize', layout);
            dialog.close();
            dialog.remove();
            resolve(file);
        };

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(null);
        });
        dialog.querySelector('[data-crop-cancel]').addEventListener('click', () => finish(null));
        dialog.querySelector('[data-crop-done]').addEventListener('click', () => {
            // Export the framed part at up to CROP_MAX_WIDTH wide, never larger than the original pixels.
            const sourceWidth = frameWidth / scale();
            const output = document.createElement('canvas');
            output.width = Math.round(Math.min(CROP_MAX_WIDTH, sourceWidth));
            output.height = Math.round(output.width / CROP_ASPECT);
            const outputContext = output.getContext('2d');
            outputContext.fillStyle = '#fff';
            outputContext.fillRect(0, 0, output.width, output.height);
            outputContext.drawImage(bitmap, -x / scale(), -y / scale(), sourceWidth, sourceWidth / CROP_ASPECT, 0, 0, output.width, output.height);
            output.toBlob((blob) => finish(blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }) : null), 'image/jpeg', 0.85);
        });

        dialog.showModal();
        layout();
    });
}
