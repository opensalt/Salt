import underline from 'markdown-it-underline';
import mk from 'markdown-it-katex';
import markdown from 'markdown-it';
import sanitizeHtml from 'sanitize-html';
import SimpleMDE from 'simplemde';

const render = (function() {
    const
        md = markdown('default', {
            html: true,
            breaks: true,
            linkify: false
        }).use(underline).use(mk, {
            "throwOnError": false,
            "errorColor": " #cc0000"
        }),
        mdInline = markdown('default', {
            html: true,
            breaks: false,
            linkify: false
        }).use(underline).use(mk, {
            "throwOnError": false,
            "errorColor": " #cc0000"
        }),
        mdInlineLinked = markdown('default', {
            html: true,
            breaks: false,
            linkify: true
        }).use(underline).use(mk, {
            "throwOnError": false,
            "errorColor": " #cc0000"
        });

    function sanitizerBlock(dirty) {
        return sanitizeHtml(dirty, {
            allowedTags: [
                'ul', 'ol', 'li',
                'u', 'b', 'i',
                'br', 'p',
                'sup', 'sub',
                'img'
            ],
            allowedAttributes: {
                'ol': ['type'],
                'img': ['src', 'alt', 'title']
            }
        });
    }

    function sanitizerInline(dirty) {
        return sanitizeHtml(dirty, {
            allowedTags: ['img'],
            allowedAttributes: {'img': ['alt', 'title']}
        });
    }

    const render = {
        block: function(value) {
            return md.render(sanitizerBlock(value));
        },

        inline: function(value) {
            // Remove images and replace with alt text or "[image]"
            let wrap = $('<div>').html(mdInline.renderInline(sanitizerInline(value)));
            wrap.find('img').replaceWith(function() {
                return '[' + (this.alt || this.title || 'image') + ']';
            });
            return wrap.html();
        },

        inlineLinked: function(value) {
            // Remove images and replace with alt text or "[image]"
            let wrap = $('<div>').html(mdInlineLinked.renderInline(sanitizerInline(value)));
            wrap.find('img').replaceWith(function() {
                return '[' + (this.alt || this.title || 'image') + ']';
            });
            return wrap.html();
        },

        escaped: function(value) {
            let entityMap = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            };

            return String(value).replace(/[&<>"'`=\/]/g, function(s) {
                return entityMap[s];
            });
        }
    };

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
        var cm = editor.codemirror;
        var output = "";
        var selectedText = cm.getSelection();
        var text = selectedText || 'placeholder';

        output = "_" + text + "_";
        cm.replaceSelection(output);
    }

    function mathText(editor) {
        var cm = editor.codemirror;
        var output = "";
        var selectedText = cm.getSelection();
        var text = selectedText;

        output = "$" + text + "$";
        cm.replaceSelection(output);
    }

    render.mde = function(element) {
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

    return render;
})();

export default render;
