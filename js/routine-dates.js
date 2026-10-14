(() => {
    const script = document.currentScript;
    const serverTime = Number(script.dataset.serverTime);
    const started = Date.now();
    const names = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Argentina/Buenos_Aires',
        year: 'numeric', month: '2-digit', day: '2-digit'
    });
    function updateDates() {
        const now = new Date(serverTime + Date.now() - started);
        const parts = Object.fromEntries(formatter.formatToParts(now).map(part => [part.type, part.value]));
        const today = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
        const weekday = today.getUTCDay() || 7;
        const monday = new Date(today);
        monday.setUTCDate(today.getUTCDate() + (weekday === 7 ? 1 : 1 - weekday));
        document.querySelectorAll('[data-routine-day]').forEach(element => {
            const day = Number(element.dataset.routineDay);
            const date = new Date(monday);
            date.setUTCDate(monday.getUTCDate() + day - 1);
            element.textContent = names[day] + ' ' + date.getUTCDate()
                + (element.hasAttribute('data-show-today') && weekday === day ? ' · Hoy' : '');
            element.title = date.toLocaleDateString('es-AR', { timeZone: 'UTC' });
        });
    }
    updateDates();
    setInterval(updateDates, 1000);
    document.addEventListener('visibilitychange', updateDates);
})();
