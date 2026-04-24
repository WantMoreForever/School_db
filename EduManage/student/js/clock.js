// ÊµÊ±Ê±ÖÓ
function updateClock() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');

    const clock = document.getElementById('clock');
    if (!clock) return;

    clock.textContent =
        now.getFullYear() + '-' +
        pad(now.getMonth() + 1) + '-' +
        pad(now.getDate()) + ' ' +
        pad(now.getHours()) + ':' +
        pad(now.getMinutes()) + ':' +
        pad(now.getSeconds());
}

setInterval(updateClock, 1000);
updateClock();