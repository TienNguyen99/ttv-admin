function tvFetch(url, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.timeout = 60000; // 60 seconds timeout

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const json = JSON.parse(xhr.responseText);
                    callback(json, null);
                } catch (e) {
                    console.error("TV JSON parse error:", e);
                    callback(null, e);
                }
            } else {
                callback(null, new Error(`HTTP ${xhr.status}`));
            }
        }
    };

    xhr.onerror = function () {
        callback(null, new Error("Network error"));
    };

    xhr.ontimeout = function () {
        callback(null, new Error("Request timeout"));
    };

    xhr.send();
}
