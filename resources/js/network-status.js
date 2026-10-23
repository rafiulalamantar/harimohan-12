// Connection notices: a bar at the bottom while offline, a short "back online" when it returns, and forms
// are held back while offline so nothing is lost to the browser's error page. public/sw.js serves
// public/offline.html when a whole page can't load.
export const OFFLINE_MESSAGE = "📡 You're offline. Check your Wi-Fi or mobile data. We'll let you know when you're back.";
const networkStatus = document.querySelector('[data-network-status]');
const networkStatusBaseClass = networkStatus?.className ?? '';
const networkTones = {
    offline: 'bg-slate-900 text-white',
    online: 'bg-emerald-600 text-white',
};
let networkStatusTimer;

export function showNetworkMessage(message, tone) {
    if (!networkStatus) {
        return;
    }

    clearTimeout(networkStatusTimer);
    networkStatus.textContent = message;
    networkStatus.className = `${networkStatusBaseClass} ${networkTones[tone]}`;
    networkStatus.hidden = false;

    if (tone === 'online') {
        networkStatusTimer = setTimeout(() => {
            networkStatus.hidden = true;
        }, 3000);
    }
}

window.addEventListener('offline', () => showNetworkMessage(OFFLINE_MESSAGE, 'offline'));
window.addEventListener('online', () => showNetworkMessage("✅ You're back online.", 'online'));

if (!navigator.onLine) {
    showNetworkMessage(OFFLINE_MESSAGE, 'offline');
}

// Capture phase, so an offline submit is stopped before any "Are you sure?" prompt.
document.addEventListener('submit', (event) => {
    if (!navigator.onLine) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showNetworkMessage("📡 You're offline, so nothing was sent. What you typed is still here. Try again once you're connected.", 'offline');
    }
}, true);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
