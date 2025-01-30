import SimpleMDE from 'simplemde';
import render from "./render-md";

const mde = (function() {
    let createLevelOneAlphaList = function(listStartCount, startPoint,
                                           endPoint, cm, callbackFunction) {
        let levelOneEndpoint = endPoint;
        if (undefined !== callbackFunction) {
            levelOneEndpoint = startPoint + 25;
        }
        let counter = 0;
        for (let i = startPoint; i <= levelOneEndpoint; i++) {
            let text = cm.getLine(i);
            if (text.substring(0, 2) === String.fromCharCode(listStartCount +
                    counter) +
                ".") {
                text = text.slice(3);
            } else {
                text = String.fromCharCode(listStartCount + counter) + ". " +
                    text;
            }
            counter++;
            cm.replaceRange(text, {
                line: i,
                ch: 0
            }, {
                line: i,
                ch: 99999999999999
            });
        }
        if (undefined !== callbackFunction) {
            callbackFunction(listStartCount, startPoint, endPoint, cm);
        }
    };

    let createLevelTwoAlphaList = function(listStartCount, startPoint,
                                           endPoint, cm) {
        let lineCounter = parseInt(startPoint + 26);
        outerloop: for (let outerCount = 0; outerCount < 26; outerCount++) {
            for (let innerCounter = 0; innerCounter < 26; innerCounter++) {
                if (lineCounter > endPoint) {
                    break outerloop;
                }
                let text = cm.getLine(lineCounter);
                if (text.substring(0, 3) === String.fromCharCode(listStartCount +
                        outerCount) +
                    String.fromCharCode(listStartCount + innerCounter) +
                    ".") {
                    text = text.slice(4);
                } else {
                    text = String.fromCharCode(listStartCount + outerCount) +
                        String
                            .fromCharCode(listStartCount + innerCounter) +
                        ". " + text;
                }
                cm.replaceRange(text, {
                    line: lineCounter,
                    ch: 0
                }, {
                    line: lineCounter,
                    ch: 99999999999999
                });
                lineCounter++;
            }
        }
    };

    let createLevelThreeAlphaList = function(listStartCount, startPoint,
                                             endPoint, cm) {
        let lineCounter = 0;
        outerloop: for (let outerMostCount = 0; outerMostCount < 26; outerMostCount++) {
            for (let outerCount = 0; outerCount < 26; outerCount++) {
                for (let innerCounter = 0; innerCounter < 26; innerCounter++) {
                    if (lineCounter > endPoint) {
                        break outerloop;
                    }
                    let text = cm.getLine(lineCounter);
                    if (text.substring(0, 4) === String
                            .fromCharCode(listStartCount + outerMostCount) +
                        String.fromCharCode(listStartCount + outerCount) +
                        String
                            .fromCharCode(listStartCount + innerCounter) +
                        ".") {
                        text = text.slice(5);
                    } else {
                        text = String.fromCharCode(listStartCount +
                                outerMostCount) +
                            String.fromCharCode(listStartCount +
                                outerCount) +
                            String.fromCharCode(listStartCount +
                                innerCounter) + ". " + text;
                    }
                    cm.replaceRange(text, {
                        line: lineCounter,
                        ch: 0
                    }, {
                        line: lineCounter,
                        ch: 99999999999999
                    });
                    lineCounter++;
                }
            }
        }
    };

    function alphaList(editor) {
        let cm = editor.codemirror;
        if (cm.getSelection()) {
            let startPoint = cm.getCursor("start");
            let endPoint = cm.getCursor("end");
            let listStartCount = 97;
            if (endPoint.line - startPoint.line > 675 + 25) {
                createLevelThreeAlphaList(listStartCount, startPoint.line,
                    endPoint.line, cm);
            } else {
                if (endPoint.line - startPoint.line > 25) {
                    createLevelOneAlphaList(listStartCount, startPoint.line,
                        endPoint.line, cm, createLevelTwoAlphaList);
                } else {
                    createLevelOneAlphaList(listStartCount, startPoint.line,
                        endPoint.line, cm);
                }
            }
            cm.trigger('focus');
        }
    }

    function underlineText(editor) {
        const cm = editor.codemirror;
        const text = cm.getSelection() || 'placeholder';
        let output = "_" + text + "_";
        cm.replaceSelection(output);
    }

    function mathText(editor) {
        const cm = editor.codemirror;
        const text = cm.getSelection();
        let output = "$" + text + "$";
        cm.replaceSelection(output);
    }

    const mde = function(element) {
        return new SimpleMDE({
            element: element,
            toolbar: [{
                name: "underlineText",
                action: underlineText,
                className: "fa fa-underline",
                title: "Underline text",
            },
                "bold", "italic", "heading", "|",
                {
                    name: "mathText",
                    action: mathText,
                    className: "fa si-sigma",
                    title: "Math text",
                },
                "quote",
                "unordered-list", "ordered-list", {
                    name: "AlphabeticalList",
                    action: alphaList,
                    className: "fa fa-sort-alpha-asc",
                    title: "Alphabetical List",
                }, "|",
                {
                    name: "image",
                    action: SimpleMDE.drawImage,
                    className: "fa fa-picture-o",
                    title: "Insert image via URL or drag and drop an image into the field below",
                },
                "table", "horizontal-rule", "|", "preview",
                "side-by-side", "fullscreen"
            ],
            previewRender: render.block
        });
    };

    return mde;
})();

export default mde;
