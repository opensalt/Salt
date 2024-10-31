import jQuery from 'jquery';
let $ = jQuery;
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

//import DataTable from 'datatables.net-bs5';
// Just load fixedheader instead -- not yet sure how to install multiple plugins
import DataTable from 'datatables.net-fixedheader-bs5';

// This shouldn't be necessary...
$.fn.dataTable = DataTable;
$.fn.dataTableSettings = DataTable.settings;
$.fn.dataTableExt = DataTable.ext;
DataTable.$ = $;

$.fn.DataTable = function ( opts ) {
    return $(this).dataTable( opts ).api();
};

//import FixedHeader from 'datatables.net-fixedheader/js/dataTables.fixedHeader.mjs';
//FixedHeader(window, $);
//import Scroller from 'datatables.net-scroller/js/dataTables.scroller.mjs';
//Scroller(window, $);
//import Select from 'datatables.net-select/js/dataTables.select.mjs';
//Select(window, $);


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
