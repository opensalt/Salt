import { simplify, spinner, titleize } from '../util-salt';
import Papa from 'papaparse';

const SaltGithub = (function () {
    function getRepoList(page, perPage) {
        if ($('.js-github-list').length > 0) {
            $.get('/user/github/repos', {page: page, perPage: perPage}, function (data) {
                $('#repos').html('');

                $.each(data.data, function (i, e) {
                    $(".js-github-list .js-github-message-loading").hide();
                    $(".js-github-list #repos").append('<li class="list-group-item item" data-owner="' + e.owner.login + '" data-repo="'
                            + e.name + '" data-sha="" data-path=""><span class="glyphicon glyphicon-folder-close" aria-hidden="true"></span> ' + e.name + '</li>');
                    $('#repos').removeClass('hidden');
                });

                paginate(data.totalPages);
                itemListener('item', false);
            })
                    .fail(function () {
                        $(".js-github-list .js-github-message-loading").hide();
                        $(".js-github-list .js-github-message-error").show();
                    });
        }
    }

    function getFiles(evt, isFile) {
        let name = $(evt.target).attr('data-fname'),
                hasSubFolder = false;

        $.ajax({
            url: '/user/github/files',
            data: {
                owner: $(evt.target).attr('data-owner'),
                repo: $(evt.target).attr('data-repo'),
                sha: $(evt.target).attr('data-sha'),
                path: $(evt.target).attr('data-path')
            },
            type: 'get',
            dataType: 'json',
            success: function (response) {
                if (isFile) {
                    const content = window.atob(response.data.content);
                    if (name.endsWith('.csv')) {
                        Import.csv(content);
                    } else if (name.endsWith('.json')) {
                        Import.json(content);
                    }
                } else {
                    $(".js-github-list #files").html('<ul></ul>');
                    response.data.forEach(function (item) {
                        if (item.type === 'file') {
                            if (item.name.endsWith('.json') || item.name.endsWith('.csv') || item.name.endsWith('.md')) {

                                $(".js-github-list #files")
                                        .append('<li class="list-group-item file-item" data-owner="' + $(evt.target)
                                                .attr('data-owner') + '" data-repo="' + $(evt.target).attr('data-repo') + '" data-sha="' + item.sha
                                                + '" data-fname="' + item.name + '"><span class="glyphicon glyphicon-file" aria-hidden="true"></span> ' + item.name + '</li>');
                            }
                        } else if (item.type === 'dir') {
                            $(".js-github-list #files").append('<li class="list-group-item item" data-owner="' + $(evt.target).attr('data-owner')
                                    + '" data-repo="' + $(evt.target).attr('data-repo') + '" data-sha="" data-path="' + item.path
                                    + '"><span class="glyphicon glyphicon-folder-close" aria-hidden="true"></span> ' + item.name + '</li>');

                            hasSubFolder = true;
                        }
                    });

                    $('#repos').addClass('hidden');
                    $('#files').removeClass('hidden');
                    $('#pagination').addClass('hidden');
                    $('.repositories-list').removeClass('hidden');
                    $('.panel-title').html($(evt.target).attr('data-repo') + '/');
                    itemListener('file-item', true);

                    if (hasSubFolder) {
                        itemListener('item', false);
                    }

                    if ($(evt.target).attr('data-path').length > 0) {
                        let path = $(evt.target).attr('data-path'), back = '';
                        let split = path.split('/');

                        for (let i = 0; i < split.length - 1; i++) {
                            back += split[i] + '/';
                        }

                        $('.panel-title').html($(evt.target).attr('data-repo') + '/' + $(evt.target).attr('data-path') + '/');

                        $('.back').attr('data-owner', $(evt.target).attr('data-owner'));
                        $('.back').attr('data-repo', $(evt.target).attr('data-repo'));
                        $('.back').attr('data-path', back);
                    }

                    if ($(evt.target).attr('data-repo') + '/' == $('.panel-title').html()) {
                        $('.back').addClass('hidden');
                    } else {
                        $('.back').removeClass('hidden');
                    }
                }
            }
        });
    }

    function paginate(pages) {
        $('#pagination').twbsPagination({
            totalPages: pages,
            visiblePages: 5,
            onPageClick: function (event, page) {
                getRepoList(page, 30);
            }
        });
    }

    function itemListener(elementClass, isFile) {
        const $element = $('.' + elementClass);

        $element.on('click', function (evt) {
            getFiles(evt, isFile);
        });
    }

    return {
        getRepoList: getRepoList,
        getFiles: getFiles
    };
})();

const UpdateFramework = (function () {
    const frameworkToAssociateSelector = '#js-framework-to-association-on-update',
            pathToUpdateFramework = "/cfdoc/doc/" + getCurrentCfDocId();

    function init() {
        $('body').on('click', '.btn.btn--updater', function () {
            SaltLocal.handleFile($(this).data('update-action'));
        });
    }

    function getRequestParams(fileContent) {
        fileData = Import.csv(fileContent, true);
        return {
            content: window.btoa(encodeURIComponent(fileContent).replace(/%([0-9A-F]{2})/g,
                    function toSolidBytes(match, p1) {
                        return String.fromCharCode('0x' + p1);
                    })),
            cfItemKeys: fileData.cfItemKeys,
            frameworkToAssociate: $(frameworkToAssociateSelector).val()
        };
    }

    function getCurrentCfDocId() {
        return $('#lsDocId').val();
    }

    function derivative(fileContent) {
        $.post(pathToUpdateFramework + "/derive", getRequestParams(fileContent), function (data) {
            window.location.href = "/cftree/doc/" + data.new_doc_id;
        });
    }

    function update(fileContent) {
        $.post(pathToUpdateFramework + "/update", getRequestParams(fileContent), function () {
            location.reload();
        });
    }

    return {init: init, derivative: derivative, update: update};
})();

const Import = (function () {

    let file = "";
    let cfItemKeys = {};

    function csvImporter(content, disableRequest) {
        file = content;

        let csv = Papa.parse(file);

        let fields = CfItem.fields;
        let columns = csv.data[0];
        let index = null, field = null, column = null;

        for (let i = 0; i < fields.length; i++) {
            field = fields[i];
            for (let j = 0; j < columns.length; j++) {
                column = columns[j];
                if (column.length > 0) {
                    if (simplify(field) === simplify(column)) {
                        cfItemKeys[field] = column.replace(/"/g, '');

                        index = fields.indexOf(field);
                        if (index >= 0) {
                            fields.splice(index, 1);
                        }

                        index = columns.indexOf(column);
                        if (index >= 0) {
                            columns.splice(index, 1);
                        }

                        i--;
                        break;
                    }
                }
            }
        }

        if (disableRequest) {
            return {cfItemKeys: cfItemKeys, fields: fields};
        }

        $('.missing-fields').html('');
        let validatedValues = validateContent(csv);

        if (fields.length > 0) {
            fields.forEach(function (field) {
                CfItem.missingField(field);
            });

            $('#import-div').addClass('hidden');
            $('#errors').removeClass('hidden');
        }

        $('.file-loading .row .col-md-12').html(spinner('Loading file'));
        $('.file-loading').removeClass('hidden');

        index = fields.indexOf('humanCodingScheme');

        if (index < 0 && validatedValues) {
            sendData();
        } else {
            $('.file-loading').addClass('hidden');
            $('#import-div').removeClass('hidden');
        }
    }

    function validateContent(csv) {
        let sw = true;
        csv.data.forEach(function (row, i) {
            if (row[3]) {
                if (row[3].length > 60) {
                    sw = false;
                    CfItem.errorValue('Line ' + i + 1, 'Abbreviated statement can not be longer than 60 characters.', 'warning');
                }
            }
        });

        return sw;
    }

    function jsonImporter(file) {
        let json = JSON.parse(file);
        let keys = Object.keys(json);
        let subKey = [];

        keys.forEach(function (subJson) {
            subKey = Object.keys(subJson);
        });
    }

    function sendData() {
        let dataRequest = {
            content: window.btoa(encodeURIComponent(file).replace(/%([0-9A-F]{2})/g,
                    function toSolidBytes(match, p1) {
                        return String.fromCharCode('0x' + p1);
                    })),
            cfItemKeys: cfItemKeys,
            lsDocId: $('#lsDocId').val(),
            frameworkToAssociate: $('#js-framework-to-association').val(),
            missingFieldsLog: CfItem.getErrorsLog()
        };

        $.ajax({
            url: '/cf/github/import',
            type: 'post',
            data: dataRequest,
            success: function (response) {
                location.reload();
            }
        });
    }

    function asnStructure(content) {
        $.ajax({
            url: '/cf/asn/import',
            type: 'post',
            data: {
                fileUrl: $('#asn-url').val()
            },
            success: function (response) {
                location.reload();
            },
            error: function () {
                $('.asn-error-msg').html('<strong>Error!</strong> Something went wrong.').removeClass('hidden');
            }
        });
    }

    function caseImporter(file) {
        $('.tab-content').addClass('hidden');
        $('.file-loading .row .col-md-12').html(spinner('Loading file'));
        $('.file-loading').removeClass('hidden');
        $('.case-error-msg').addClass('hidden');

        $.ajax({
            url: '/salt/case/import',
            type: 'post',
            data: {
                fileContent: window.btoa(encodeURIComponent(file).replace(/%([0-9A-F]{2})/g,
                        function toSolidBytes(match, p1) {
                            return String.fromCharCode('0x' + p1);
                        }))
            },
            success: function (response) {
                location.reload();
            },
            error: function () {
                $('.tab-content').removeClass('hidden');
                $('.case-error-msg').html('Error while importing the file');
                $('.case-error-msg').removeClass('hidden');
                $('.file-loading').addClass('hidden');
            }
        });
    }

    return {
        csv: csvImporter,
        json: jsonImporter,
        send: sendData,
        fromAsn: asnStructure,
        case: caseImporter
    };
})();

const SaltLocal = (function () {

    function handleFileSelect(fileType, input) {
        let files = document.getElementById(input).files;
        let json = '', f;

        if (fileType === 'update' || fileType === 'derivative') {
            files = document.getElementById('file-for-update').files;
        } else {
            files = document.getElementById('file-url').files;
        }

        if (window.File && window.FileReader && window.FileList && window.Blob) {
            for (let i = 0; f = files[i]; i++) {

                let reader = new FileReader();
                if (isTypeValid(f.name)) {
                    reader.onload = (function (theFile) {
                        return function (e) {
                            let file = e.target.result;
                            switch (fileType) {
                                case 'local':
                                    Import.csv(file);
                                    break;
                                case 'case':
                                    Import.case(file);
                                    break;
                                case 'derivative':
                                    UpdateFramework.derivative(file);
                                    break;
                                case 'update':
                                    UpdateFramework.update(file);
                                    break;
                            }
                        };
                    })(f);

                    reader.readAsText(f);
                } else {
                    $('.tab-content').removeClass('hidden');
                    $('.case-error-msg').html('File type not allowed');
                    $('.case-error-msg').removeClass('hidden');
                    $('.file-loading').addClass('hidden');
                }
            }
        } else {
            console.error('The FILE APIs are not fully supported in this broswer');
        }
    }

    function handleExcelFile() {
        let files = document.getElementById('excel-url').files;
        let file;
        let data = new FormData();

        if (window.File && window.FileReader && window.FileList && window.Blob) {
            let file = files[0];
            if (isTypeValid(file.name)) {

                $('.tab-content').addClass('hidden');
                $('.file-loading .row .col-md-12').html(spinner('Loading file'));
                $('.file-loading').removeClass('hidden');
                $('.case-error-msg').addClass('hidden');

                data.append('file', file);
                $.ajax({
                    url: '/salt/excel/import',
                    data: data,
                    cache: false,
                    contentType: false,
                    processData: false,
                    type: 'POST',
                    success: function (response) {
                        location.reload();
                    },
                    error: function () {
                        $('.tab-content').removeClass('hidden');
                        $('.case-error-msg').html("We're sorry, we cannot load this document. Please ensure this document is not already on the server, or see the Spreadsheet loading guide at docs.opensalt.org");
                        $('.case-error-msg').removeClass('hidden');
                        $('.file-loading').addClass('hidden');
                    }
                });
            } else {
                $('.tab-content').removeClass('hidden');
                $('.case-error-msg').html('File type not allowed');
                $('.case-error-msg').removeClass('hidden');
                $('.file-loading').addClass('hidden');
            }
        }
    }

    function isTypeValid(file) {
        const types = ['xls', 'xlsx', 'json', 'csv'];
        const filename = file.split('.').pop();

        if (types.indexOf(filename) >= 0) {
            return true;
        }

        return false;
    }

    return {
        handleFile: handleFileSelect,
        handleExcelFile: handleExcelFile
    };
})();

if (document.getElementById("toggleRight")) {
    document.getElementById("toggleRight").onclick = function () {
        toggleDivRight()
    };

    function toggleDivRight() {
        const rightWindow = document.getElementById("treeSideRight");
        if (rightWindow.style.display === "none") {
            rightWindow.style.display = "block";
            document.getElementById("treeSideLeft").setAttribute("style", "width:50%");
            document.getElementById("treeSideRight").setAttribute("style", "width:50%");
            document.getElementById("toggleRight").setAttribute("class", "fa fa-chevron-circle-right");
        } else {
            rightWindow.style.display = "none";
            document.getElementById("treeSideLeft").setAttribute("style", "width:100%");
            document.getElementById("toggleRight").setAttribute("class", "fa fa-chevron-circle-left");
        }
    }
}

if (document.getElementById("toggleLeft")) {
    document.getElementById("toggleLeft").onclick = function () {
        toggleDivLeft()
    };

    function toggleDivLeft() {
        const leftWindow = document.getElementById("treeSideLeft");
        if (leftWindow.style.display === "none") {
            leftWindow.style.display = "block";
            document.getElementById("treeSideRight").setAttribute("style", "width:50%");
            document.getElementById("treeSideLeft").setAttribute("style", "width:50%");
            document.getElementById("toggleLeft").setAttribute("class", "fa fa-chevron-circle-left");
        } else {
            leftWindow.style.display = "none";
            document.getElementById("treeSideRight").setAttribute("style", "width:100%");
            document.getElementById("toggleLeft").setAttribute("class", "fa fa-chevron-circle-right");
        }
    }
}
const CfItem = (function () {

    let missingFieldsErrorMessages = [];

    let fields = [
        'identifier',
        'fullStatement',
        'humanCodingScheme',
        'abbreviatedStatement',
        'conceptKeywords',
        'notes',
        'language',
        'educationLevel',
        'cfItemType',
        'license',
        'isChildOf',
        'isPartOf',
        'replacedBy',
        'exemplar',
        'precedes',
        'isPeerOf',
        'hasSkillLevel',
        'isRelatedTo',
        'sequenceNumber'
    ];

    function generateDropdowns(arrData, type) {
        let mandatoryClass = "";
        let panelType = "default";
        arrData.chunk(2).forEach(function (dropdownGrouped) {
            $('.dropdowns.' + type).append('<div class="row"></div>');
            dropdownGrouped.forEach(function (dropdown) {
                if (dropdown[1] === 'M') {
                    mandatoryClass = "mandatory-class";
                    panelType = "primary"
                }
                $('.dropdowns.' + type + ' .row').last().append('<div class="col-xs-6"><div class="panel panel-' + panelType + '"><div class="panel-body ' + mandatoryClass + '"></div></div></div>');
                $('.dropdowns.' + type + ' .row .panel-body').last().append('<div class="col-xs-6"><div class="form-group"><label>' + dropdown[0].titleize() + '</label><select name="' + dropdown[0] + '" class="form-control select"><option>Choose one option</option></select></div></div>');
                $('.dropdowns.' + type + ' .row .panel-body').last().append('<div class="col-xs-6"><div class="form-group"><label>Enter default value if needed</label><input name="' + dropdown[0] + '_default_value" type="text" class="form-control"/></div></div>');
                mandatoryClass = "";
                panelType = "default";
            });
        });
    }

    function validDropdowns(formMatchedSelector) {
        let missingRequiredFiles = false;

        $(formMatchedSelector).find("select").each(function (i, e) {
            if ($(e).val().length < 1) {
                missingRequiredFiles = true;
            }
        });

        if (missingRequiredFiles) {
            $(".js-alert-missing-fields").removeClass("hidden");
        } else {
            $(".js-alert-missing-fields").addClass("hidden");
        }

        return !missingRequiredFiles;
    }

    function missingField(field) {
        let alert = '<div class="alert alert-warning js-alert-missing-fields" role="alert">';
        alert += '<a href="#" class="close" data-dismiss="alert" aria-label="close">x</a>';
        alert += '<div class="js-error-message-missing-field">';
        alert += '<strong>Missing field "' + titleize(field) + '"</strong>, if you did not list a column ' + field + ' in your CSV ignore this message! ';
        alert += 'if you meant to, please take a look at the import template and try again!';
        alert += '</div>';
        alert += '</div>';

        missingFieldsErrorMessages.push($(alert).find(".js-error-message-missing-field").text());
        $('.missing-fields').append(alert);
    }

    function errorValue(err, msg, alertType) {
        let alert = '<div class="alert alert-' + alertType + ' js-alert-missing-fields" role="alert">';
        alert += '<a href="#" class="close" data-dismiss="alert" aria-label="close">x</a>';
        alert += '<div class="js-error-message-missing-field">';
        alert += '<strong>Error:</strong> ' + err + ', ' + msg;
        alert += '</div>';
        alert += '</div>';
        $('.missing-fields').prepend(alert);
    }

    function getErrorsLog() {
        return missingFieldsErrorMessages;
    }

    return {
        fields: fields,
        validDropdowns: validDropdowns,
        missingField: missingField,
        getErrorsLog: getErrorsLog,
        errorValue: errorValue
    };
})();

const SanitizeData = (function () {
    function matchedFields(formSelector) {
        let sanitizedData = {},
                tempData = {},
                formData = $(formSelector).serializeArray();

        formData.forEach(function (e) {
            tempData[e.name] = e.value;
        });

        return tempData;
    }

    return {
        matchedFields: matchedFields
    };
})();

function listRepositories() {
    $('#files').addClass('hidden');
    $('#repos').removeClass('hidden');
    $('#pagination').removeClass('hidden');
    $('.repositories-list').addClass('hidden');
    $('.panel-title').html('Repositories list');
    $('#back').html('');
}

$(function () {
    $('.github-tab').on('click', function () {
        SaltGithub.getRepoList(1, 30);
        listRepositories();
    });

    $('.btn-import-asn').on('click', function () {
        Import.fromAsn();
    });

    UpdateFramework.init();
});

// Used from page-level javascript
globalThis.SaltLocal = SaltLocal;
globalThis.SaltGithub = SaltGithub;
globalThis.listRepositories = listRepositories;

const dragbar = $("#dragbar");

dragbar.on('mousedown', function (upperEvent) {
    upperEvent.preventDefault();
    let treeSideLeft = $('#treeSideLeft'),
            treeSideRight = $('#treeSideRight'),
            treeView = $("#treeView"),
            treeViewOffsetLeft = treeView.offset().left,
            treeViewOffsetRight = treeViewOffsetLeft + treeView.width(),
            threshold = 330;

    $(document).on('mousemove', dragBar);

    $(document).one('mouseup', function (e) {
        $(document).off('mousemove', dragBar);
    });

    function dragBar(e) {
        let cursorX = e.clientX,
                cursorFromOffsetLeft = cursorX - treeViewOffsetLeft,
                cursorFromOffsetRight = treeViewOffsetRight - cursorX;
        if (cursorFromOffsetLeft < threshold || cursorFromOffsetRight < threshold) {
            return false;
        }

        treeSideLeft.css("width", cursorFromOffsetLeft);
        treeSideRight.css("width", cursorFromOffsetRight);
    }
});

const adjustWindow = function (e) {
    if ($('#treeView').width() <= 768)
    {
        $('#treeSideLeft').width('100%');
        $('#treeSideRight').width('100%');
        $(".treeSideRightInner").hide();
        $(".rightTreeSideLeftInner").hide();
        $("#dragbar").hide();
    }
    else {
        $('#treeSideLeft').width('50%');
        $('#treeSideRight').width('50%');
        $(".treeSideRightInner").show();
        $(".rightTreeSideLeftInner").show();
        $("#dragbar").show();
    }
};
$(adjustWindow);

$(window).on('resize', adjustWindow);

$(function () {
    let table = $('#datatable').DataTable();
    $('#search_form_organization').on('keyup', function () {
        table
            .columns(1)
            .search(this.value)
            .draw();
    });

});
