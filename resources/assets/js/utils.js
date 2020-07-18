
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