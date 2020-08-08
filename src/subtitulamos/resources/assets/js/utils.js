
export function dateDiff(a, b) {
    let utcA = Date.UTC(
        a.getFullYear(),
        a.getMonth(),
        a.getDate(),
        a.getHours(),
        a.getMinutes(),
        a.getSeconds()
    );
    let utcB = Date.UTC(
        b.getFullYear(),
        b.getMonth(),
        b.getDate(),
        b.getHours(),
        b.getMinutes(),
        b.getSeconds()
    );

    return Math.floor(utcB - utcA);
}

export function eById(id) {
    return document.getElementById(id);
}

export function onDomReady(callback) {
    // See if DOM is already available
    if (document.readyState === "complete" || document.readyState === "interactive") {
        // call on next available tick
        setTimeout(callback, 1);
    } else {
        document.addEventListener("DOMContentLoaded", callback);
    }
}

export function raiseFetchErrors(response) {
    if (!response.ok) {
        throw {
            error: true,
            response: response
        };
    }
    return response;
}

export function naiveDeepClone(obj) {
    // A naive deep clone. Doesn't work well with circular references or complex objects
    return JSON.parse(JSON.stringify(obj));
}

export function easyFetch(url, baseOpts) {
    const opts = baseOpts ? naiveDeepClone(baseOpts) : {};
    const method = opts.method ? opts.method.toUpperCase() : "";
    if (method === "POST" && opts.rawBody instanceof Object) {
        opts.body = JSON.stringify(opts.rawBody);
        if (!opts.headers) {
            opts.headers = {};
        }

        if (!opts.headers['Content-Type']) {
            opts.headers['Content-Type'] = 'application/json';
        }
    } else if ((method === "GET" || !method) && opts.params) {
        url += "?" + new URLSearchParams(opts.params).toString();
    }

    return fetch(url, opts).then(raiseFetchErrors);
}
