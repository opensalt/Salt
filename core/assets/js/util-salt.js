export function simplify(string) {
    return string.match(/[a-zA-Z]*/g).join("").toLowerCase();
}

export function capitalize(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

export function titleize(string) {
    return capitalize(string.replace(/([A-Z]+)/g, " $1").replace(/([A-Z][a-z])/g, " $1"));
}

export function spinner(msg) {
    return '<div class="spinnerOuter"><span class="glyphicon glyphicon-cog spinning spinnerCog"></span><span class="spinnerText">' + msg + '</span></div>';
}
