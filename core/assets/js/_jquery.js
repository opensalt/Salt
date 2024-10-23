import $ from 'jquery';
window.$ = window.jQuery = $;

import migrate from 'jquery-migrate';
migrate($, window);

// $.browser is needed by jquery-comments but has been deprecated and removed a long time ago
jQuery.uaMatch = function( ua ) {
    ua = ua.toLowerCase();
    var match = /(chrome)[ \/]([\w.]+)/.exec( ua ) ||
        /(webkit)[ \/]([\w.]+)/.exec( ua ) ||
        /(opera)(?:.*version|)[ \/]([\w.]+)/.exec( ua ) ||
        /(msie) ([\w.]+)/.exec( ua ) ||
        ua.indexOf("compatible") < 0 && /(mozilla)(?:.*? rv:([\w.]+)|)/.exec( ua ) || [];
    return {
        browser: match[ 1 ] || "",
        version: match[ 2 ] || "0"
    };
};
if ( !jQuery.browser ) {
    var
    matched = jQuery.uaMatch( navigator.userAgent ),
    browser = {};
    if ( matched.browser ) {
        browser[ matched.browser ] = true;
        browser.version = matched.version;
    }
    // Chrome is Webkit, but Webkit is also Safari.
    if ( browser.chrome ) {
        browser.webkit = true;
    } else if ( browser.webkit ) {
        browser.safari = true;
    }
    jQuery.browser = browser;
}

import select2 from 'select2/dist/js/select2.full';
select2(window, $);

//import 'jquery-comments';
import jqComments from 'jquery-comments';
jqComments(window, $);

import DataTable from 'datatables.net';
new DataTable(window, $);
import DataTablesBS from 'datatables.net-bs';
import DataTablesFixedHeader from 'datatables.net-fixedheader';
import DataTablesScroller from 'datatables.net-scroller';
import DataTablesSelect from 'datatables.net-select';


function defineJQueryPlugin(plugin) {
  const name = plugin.NAME;
  const JQUERY_NO_CONFLICT = $.fn[name];
  $.fn[name] = plugin.jQueryInterface;
  $.fn[name].Constructor = plugin;
  $.fn[name].noConflict = () => {
    $.fn[name] = JQUERY_NO_CONFLICT;
    return plugin.jQueryInterface;
  }
}

//defineJQueryPlugin();

export default $;
