(function () {
    const s = document.getElementById('sidebar'), b = document.getElementById('backdrop'), m = document.getElementById('menuBtn');
    if (m) { const t = () => { s.classList.toggle('open'); b.classList.toggle('show') }; m.onclick = t; b.onclick = t; }

    if (s) {
        // Debounced save during user scrolling
        let scrollTimer = null;
        s.addEventListener('scroll', function () {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                try {
                    sessionStorage.setItem('sidebar_scroll', s.scrollTop);
                } catch (e) {}
            }, 60);
        }, { passive: true });

        // Immediate save when user clicks any link in sidebar
        s.addEventListener('click', function (e) {
            const a = e.target.closest('a');
            if (a) {
                try {
                    sessionStorage.setItem('sidebar_scroll', s.scrollTop);
                } catch (e) {}
            }
        });

        // Save right before page transition
        window.addEventListener('beforeunload', function () {
            try {
                sessionStorage.setItem('sidebar_scroll', s.scrollTop);
            } catch (e) {}
        });
    }
})();
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault() }));
