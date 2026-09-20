const EVENT_NAMESPACE = '.App\\Events\\';

function connection() {
    return window.Echo?.connector?.pusher?.connection ?? null;
}

/**
 * NFR09: re-fetch state on reconnect. Only fires after a real drop, so the
 * first connect does not duplicate the page's server-rendered state.
 */
export function onReconnect(callback) {
    const socket = connection();
    if (!socket) {
        return;
    }

    let wasDropped = false;

    socket.bind('unavailable', () => {
        wasDropped = true;
    });
    socket.bind('disconnected', () => {
        wasDropped = true;
    });
    socket.bind('connected', () => {
        if (wasDropped) {
            wasDropped = false;
            callback();
        }
    });
}

/** Subscribes to one channel and runs `callback` for every named event. */
export function listenOn(channel, events, callback) {
    if (!channel) {
        return;
    }

    events.forEach((event) => channel.listen(EVENT_NAMESPACE + event, callback));
}

export function privateChannel(name) {
    return window.Echo ? window.Echo.private(name) : null;
}

/** 07.8: 'menu' is public, so no auth request is made for it. */
export function publicChannel(name) {
    return window.Echo ? window.Echo.channel(name) : null;
}

/** Fetches a page's own /state endpoint and hands the JSON to `render`. */
export function refreshFrom(url, render) {
    return fetch(url, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : null))
        .then((state) => {
            if (state) {
                render(state);
            }
        })
        .catch(() => {});
}
