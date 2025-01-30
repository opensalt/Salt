import underline from 'markdown-it-underline';
import mk from 'markdown-it-katex';
import markdown from 'markdown-it';
import sanitizeHtml from 'sanitize-html';

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

    return render;
})();

export default render;
