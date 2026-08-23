(() => {
    const viewport = document.getElementById('bracketViewport');
    const stage = document.getElementById('bracketStage');

    if (!viewport || !stage) return;

    let scale = 1;
    let panX = 0;
    let panY = 0;
    let panning = false;
    let startX = 0;
    let startY = 0;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    function applyTransform() {
        stage.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
        const resetButton = document.querySelector('[data-bracket-action="reset"]');
        if (resetButton) resetButton.textContent = `${Math.round(scale * 100)}%`;
    }

    function zoom(delta) {
        scale = clamp(scale + delta, 0.55, 1.65);
        applyTransform();
    }

    document.querySelectorAll('[data-bracket-action]').forEach(button => {
        button.addEventListener('click', () => {
            const action = button.dataset.bracketAction;
            if (action === 'zoom-in') zoom(0.1);
            if (action === 'zoom-out') zoom(-0.1);
            if (action === 'reset') {
                scale = 1;
                panX = 0;
                panY = 0;
                applyTransform();
                viewport.scrollTo({left: 0, top: 0, behavior: 'smooth'});
            }
        });
    });

    viewport.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        panning = true;
        startX = event.clientX - panX;
        startY = event.clientY - panY;
        viewport.classList.add('is-panning');
        viewport.setPointerCapture?.(event.pointerId);
    });

    viewport.addEventListener('pointermove', event => {
        if (!panning) return;
        panX = event.clientX - startX;
        panY = event.clientY - startY;
        applyTransform();
    });

    const stopPan = event => {
        panning = false;
        viewport.classList.remove('is-panning');
        if (event?.pointerId !== undefined && viewport.hasPointerCapture?.(event.pointerId)) {
            viewport.releasePointerCapture(event.pointerId);
        }
    };

    viewport.addEventListener('pointerup', stopPan);
    viewport.addEventListener('pointercancel', stopPan);

    viewport.addEventListener('wheel', event => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        zoom(event.deltaY < 0 ? 0.1 : -0.1);
    }, { passive: false });

    applyTransform();
})();
