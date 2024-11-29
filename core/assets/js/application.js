import $ from './_jquerySetup';

$.fn.select2.defaults.set('theme', 'bootstrap-5');

//$.fn.modal.Constructor.prototype.enforceFocus = function() {};
// OR try when adding the select2....
//  $("#select2insidemodal").select2({
//    dropdownParent: $("#myModal")
//  });
bootstrap.Tooltip.getOrCreateInstance('body', {
    container: 'body',
    selector: '[data-bs-toggle="tooltip"]'
});
