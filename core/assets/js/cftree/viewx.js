import { empty } from './utils';
import ApxDocument from './view-documentclass';
import render from './render-md';

//////////////////////////////////////////////////
// INITIALIZE WEB APP

let apx = window.apx || {};

apx.locks = apx.locks || {
    docs: [],
    items: [],
    mine: {
        docs: {},
        items: {},
        warnings: {}
    }
};

export default function (apx) {
    apx.initialize = function () {
        // prepare edit modals/functions
        apx.edit.prepareDocEditModal();
        apx.edit.prepareDocDeleteModal();
        apx.edit.prepareItemEditModal();
        apx.edit.prepareAddNewChildModal();
        apx.edit.prepareExemplarModal();
        apx.edit.prepareAssociateModal();
        apx.edit.prepareEditAssociationModal();

        // prepare assocGroup modals/functions
        apx.edit.prepareAddAssocGroupModal();
        apx.edit.initializeManageAssocGroupButtons();

        apx.markLogsAsRead();
        apx.copyFramework.init();

        bootstrap.Tooltip.getOrCreateInstance('#treeView', {
            'selector': '.fancytree-title',
            // "content": content,  // this is for popover
            "title": function (e) {
                return ""; // TODO: Fix this, "this" seems to be null now in next line
                let node = $.ui.fancytree.getNode(e);
                return apx.treeDoc1.tooltipContent(node);
            },
            "delay": { "show": 200, "hide": 100 },
            "placement": "top",
            "html": true,
            "container": "body",
            "trigger": "hover"   // this is for popover
        });

        // tooltips for associations with titles
        bootstrap.Tooltip.getOrCreateInstance('#treeSideRight', {
            selector: '.itemDetailsAssociationTitle.annotated',
            "title": function () { return $(this).html(); },
            "delay": { "show": 200, "hide": 100 },
            "placement": "top",
            "html": true,
            "container": "body"
        });

        // right-side buttongroup
        $("#rightSideItemDetailsBtn").on('click', function () { apx.setRightSideMode("itemDetails"); });
        $("#rightSideCopyItemsBtn").on('click', function () { apx.setRightSideMode("copyItem"); });
        $("#rightSideCreateAssociationsBtn").on('click', function () { apx.setRightSideMode("addAssociation"); });

        let treeSideLeft = $("#treeSideLeft");
        let treeSideRight = $("#treeSideRight");

        // Tree checkboxes/menus
        treeSideLeft.find(".treeCheckboxControlBtn").on('click', function (e) { apx.treeDoc1.treeCheckboxToggleAll(null, 1); e.stopPropagation(); });
        treeSideLeft.find(".treeCheckboxMenuItem").on('click', function () { apx.treeDoc1.treeCheckboxMenuItemSelected($(this), 1); });
        treeSideRight.find(".treeCheckboxControlBtn").on('click', function (e) { apx.treeDoc2.treeCheckboxToggleAll(null, 2); e.stopPropagation(); });
        treeSideRight.find(".treeCheckboxMenuItem").on('click', function () { apx.treeDoc2.treeCheckboxMenuItemSelected($(this), 2); });

        // popovers on export modal
        $('#exportModal').find('[data-bs-toggle="popover"]').popover();

        // change event on assocGroup menus
        treeSideLeft.find(".assocGroupSelect").off().on('change', function () { apx.treeDoc1.assocGroupSelected(this, 1); });
        treeSideRight.find(".assocGroupSelect").off().on('change', function () { apx.treeDoc2.assocGroupSelected(this, 2); });

        // links/buttons on item info card
        // enable more info link
        $(".lsItemDetailsMoreInfoLink a").on('click', function () { apx.toggleMoreInfo(); });

        // enable deleteItem button
        $("#deleteItemBtn").on('click', apx.edit.deleteItems);

        // enable toggleFolder button
        $("#toggleFolderBtn").on('click', function () { apx.treeDoc1.toggleFolders(); });

        // doc view/tree view buttongroup
        $("#displayTreeBtn").on('click', function () { apx.viewMode.showTreeView("button"); });
        $("#displayAssocBtn").on('click', function () { apx.viewMode.showAssocView("button"); });
        $("#displayLogBtn").on('click', function () { apx.viewMode.showLogView("button"); });

        // implement enableMoveCheckbox
        $("#enableMoveCheckbox").on('click', function () { apx.edit.enableMove(this); });

        // make sure initialAssocGroup is a number if it's not null
        if (!empty(apx.initialAssocGroup)) {
            apx.initialAssocGroup *= 1;
        }

        // parse query string
        apx.query = {};
        let arr = document.location.search.substr(1).split("&");
        for (let i = 0; i < arr.length; ++i) {
            let line = arr[i].split("=");
            apx.query[line[0]] = line[1];
        }

        // if we're in chooserMode, initialize
        if (apx.chooserMode.active()) {
            apx.chooserMode.initialize();
        } else {
            // else show docTitleRow, header, and footer
            $("header, #docTitleRow, footer").show();
        }

        ///////////////////////////////////////////////////////////////////////////////
        // MAINDOC

        // lsDocId could be an integer, in which case it's a SALT database ID; or we could be loading by url
        if (apx.lsDocId === 'url') {
            // if we're loading by url, the url should be in the search string, i.e. "url=http://example.com"
            apx.mainDoc = new ApxDocument({ "url": decodeURIComponent(apx.query.url) }, apx);
        } else {
            apx.mainDoc = new ApxDocument({ "id": apx.lsDocId }, apx);
        }

        // establish and load the main document -- apx.mainDoc
        apx.spinner.showModal("Loading document");
        apx.mainDoc.load(function () {
            apx.spinner.hideModal();

            // show the treeView div now that the document has been loaded
            $("#treeView").show();

            // fill in document title, in case we loaded from url
            $("#docTitle").html(apx.mainDoc.doc.title);
            window.document.title = apx.mainDoc.doc.title;

            // Prepare menus for choosing documents on each side (we have to do this after we've gotten mainDoc.associatedDocs)
            apx.prepareDocumentMenus();

            // go through each provided "associatedDoc"
            for (let identifier in apx.mainDoc.associatedDocs) {
                let ed = apx.mainDoc.associatedDocs[identifier];
                // and start loading now any associatedDocs that have the "autoLoad" flag set to "true" (unless we've already loaded it)
                // we have to do this because associations to items in these docs don't specify the doc id
                if (ed.autoLoad === "true" && !(identifier in apx.allDocs)) {
                    console.log("loading doc " + ed.title);
                    apx.allDocs[identifier] = "loading";
                    new ApxDocument({ "identifier": identifier }, apx).load();
                }
            }

            // find any other docs referenced by associations in mainDoc
            apx.mainDoc.findAssociatedDocs();

            // if we got an initialLsItemId, set it (the document will be selected by default)
            if (!empty(apx.initialLsItemId)) {
                let item = apx.mainDoc.itemIdHash[apx.initialLsItemId];
                if (!empty(item)) {
                    apx.mainDoc.setCurrentItem({ "identifier": item.identifier });

                    // If an item is initially selected, get appropriate initialAssocGroup
                    // first get all assocGroups for isChildOf relationships for this item
                    let assocGroups = apx.mainDoc.getAssocGroupsForItem(apx.mainDoc.currentItem, "isChildOf");

                    // if initialAssocGroup is empty (null, meaning the default group) OR it isn't one of the available isChildOf relationships for this item...
                    if (empty(apx.initialAssocGroup) || $.inArray(apx.initialAssocGroup, assocGroups) === -1) {
                        // then if the item has no isChildOf relationship or has an isChildOf relationship for the default group, use default
                        if (assocGroups.length === 0 || $.inArray(null, assocGroups) > -1) {
                            apx.initialAssocGroup = null;

                            // else use the first-listed assocGroup
                        } else {
                            apx.initialAssocGroup = assocGroups[0];
                        }
                    }
                }
            }

            // set the initialAssocGroup
            apx.mainDoc.setCurrentAssocGroup(apx.initialAssocGroup);

            // Now, by default we show mainDoc's item tree on the left side, so initialize treeDoc1 now
            apx.treeDoc1 = apx.mainDoc;
            apx.treeDocLoadCallback1();

            // if the url ends with "av", toggle to association view
            if (window.location.href.search(/\/av$/) > -1) {
                apx.viewMode.showAssocView("pageLoaded");
                apx.viewMode.lastViewButtonPushed = "assoc";
                apx.viewMode.initialView = "assocView";
            } else if (window.location.href.search(/\/lv$/) > -1) {
                apx.viewMode.showLogView("pageLoaded");
                apx.viewMode.lastViewButtonPushed = "log";
                apx.viewMode.initialView = "logView";
            } else {
                apx.viewMode.initialView = "treeView";

                if (Number.parseInt(apx.query.edit) === 1) {
                    $('#editDocModal').modal('show');
                }
            }
        });

        /** Sometimes we need to refresh the mainDoc entirely from the server... */
        apx.mainDoc.refreshFromServer = function () {
            apx.spinner.showModal("Refreshing document");
            let currentItemIdentifier = apx.mainDoc.currentItem.identifier;
            let currentAssocGroup = apx.mainDoc.currentAssocGroup;
            apx.mainDoc.load(function () {
                // if we're showing mainDoc on the left side, refresh it now
                if (apx.mainDoc === apx.treeDoc1) {
                    apx.treeDoc1.setCurrentItem({ "identifier": currentItemIdentifier });
                    apx.treeDoc1.setCurrentAssocGroup(currentAssocGroup);
                    apx.treeDoc1.ftRender1();
                    apx.treeDoc1.activateCurrentItem();
                    apx.treeDoc1.showCurrentItem();
                }
                apx.spinner.hideModal();
            });
        };

        if (apx.enableEdit) {
            apx.initializeNotification();
        }
    };

    //////////////////////////////////////////////////////
    /**
     * Using Mercure to synchronize changes
     */
    apx.initializeNotification = function () {
        if ('undefined' !== typeof EventSource) {
            const url = new URL(window.location.protocol + '//' + window.location.host + '/.well-known/mercure');
            url.searchParams.append('topic', 'doc-updates/' + apx.lsDocId);

            const eventSource = new EventSource(url);
            eventSource.onmessage = event => {
                apx.notifications.notify(JSON.parse(event.data));
            }
        }

        let enabled = apx.notifications.isEnabled();
        $('#notifications-switch-location').html('<a id="notifications-switch" href="#" class="btn"><i class="fa-bell'+(enabled ? ' fa-solid' : '-slash fa-regular')+'"></i></a>');
        $('#notifications-switch').on('click', apx.notifications.toggle);
        apx.notifications.enableMonitor();

        $.each(apx.locks.docs || [], function (id, tm) {
            if ("number" !== typeof tm || tm < apx.startTime) {
                return;
            }
            apx.locks.docs[id] = setTimeout(function (id) {
                delete apx.locks.docs[id];
                let changes = {};
                changes[id] = 'x';
                apx.notifications.notify({
                    msgId: 'LockTimeout',
                    msg: 'The document is no longer being edited',
                    at: (new Date()).getTime(),
                    by: '',
                    changes: {
                        'doc-ul': changes
                    }
                });
            }, tm - apx.startTime, id);
        });
        $.each(apx.locks.items || [], function (id, tm) {
            if ("number" !== typeof tm || tm < apx.startTime) {
                return;
            }
            apx.locks.items[id] = setTimeout(function (id) {
                delete apx.locks.items[id];
                let changes = {};
                changes[id] = 'x';
                let item;
                if ("undefined" === typeof apx.mainDoc.itemIdHash[id]) {
                    item = apx.mainDoc.itemIdHash[id].fstmt;
                } else {
                    item = "unknown";
                }
                let itemName = render.inline(item.substr(0, 60));
                apx.notifications.notify({
                    msgId: 'LockTimeout',
                    msg: 'The item "' + itemName + '" is no longer being edited',
                    at: (new Date()).getTime(),
                    by: '',
                    changes: {
                        'item-ul': changes
                    }
                });
            }, tm - apx.startTime, id);
        });
        apx.locks.mine = {
            docs: {},
            items: {},
            warnings: {}
        };
    };

    apx.refreshPage = function () {
        if ("tree" === apx.viewMode.currentView) {
            apx.treeDoc1.ftRender1();
            apx.treeDoc1.activateCurrentItem();
            apx.mainDoc.showCurrentItem();
        }

        if ('assoc' === apx.viewMode.currentView) {
            apx.viewMode.showAssocView('refresh');
        }
    };

    apx.notifications = apx.notifications || {};
    apx.notifyCheck = apx.notifyCheck || {};

    $.notifyDefaults({
        type: 'info',
        mouse_over: 'pause',
        z_index: 1075,
        url_target: '_self'
    });

    apx.notifications.isEnabled = function () {
        let cookie = document.cookie.match(new RegExp('(^| )n=([^;]+)'));
        if (cookie) {
            if ("n" === cookie[2]) {
                return false;
            }
        }

        return true;
    };

    apx.notifications.enableMonitor = function () {
        let current = apx.notifications.isEnabled();

        setInterval(function () {
            if (current !== apx.notifications.isEnabled()) {
                current = !current;
                apx.notifications.displayToggle(current);
            }
        }, 500);
    };

    apx.notifications.displayToggle = function (isEnabled) {
        const enabled = apx.notifications.isEnabled();
        $('#notifications-switch').html('<i class="fa-bell'+(enabled ? ' fa-solid' : '-slash fa-regular')+'"></i>');
    };

    apx.notifications.toggle = function (e) {
        e.stopPropagation();
        e.preventDefault();

        let enabled = apx.notifications.isEnabled();
        enabled = !enabled;

        if (enabled) {
            document.cookie = "n=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/";
        } else {
            document.cookie = "n=n; path=/";
        }

        apx.notifications.displayToggle(enabled);
    };

    $.extend(apx.notifications, {
        'assoc-a': function (list) {
            $.each(list, function (id, identifier) {
                apx.notifications.loadAssociation(id);
            });
        },

        'assoc-u': function (list) {
            let load = function (data) {
                let a = apx.mainDoc.assocIdHash[data.id];
                // most common (only?) change is sequence number
                if ("undefined" !== typeof a) {
                    a.seq = data.seq;
                    a.mod = data.mod;
                }

                apx.refreshPage();
            };

            $.each(list, function (id, identifier) {
                if ("undefined" !== apx.notifications.updates['assocs'][id]) {
                    load(apx.notifications.updates['assocs'][id]);
                } else {
                    $.ajax({
                        url: apx.path.doc_tree_association_json.replace('ID', id),
                        method: 'GET'
                    }).done(function (data, textStatus, jqXHR) {
                        load(data);
                    }).fail(function (jqXHR, textStatus, errorThrown) {
                        // Ignore for now
                    });
                }
            });
        },

        'assoc-d': function (list) {
            $.each(list, function (id, identifier) {
                apx.edit.performDeleteAssociation(id);
            });

            apx.refreshPage();
        },

        'item-l': function (list, msg) {
            if ("undefined" === typeof apx.locks) {
                // If locks are disabled then ignore
                return;
            }

            let timeout = 300000;
            let warningTime = 60000;

            $.each(list, function (id, identifier) {
                if (msg.by === apx.me) {
                    apx.locks['items'][id] = false;

                    let warning = setTimeout(function (id) {
                        let item;
                        if ("undefined" !== typeof apx.mainDoc.itemIdHash[id]) {
                            item = apx.mainDoc.itemIdHash[id].fstmt;
                        } else {
                            item = "unknown";
                        }
                        let title = render.inline(item.substr(0, 60));

                        apx.locks.mine.warnings[id] = apx.notifications.notify({
                            msgId: 'LockTimeoutWarning',
                            msg: 'Your lock for item "' + title + '" is about to expire<br/>&nbsp;&nbsp;&nbsp;&nbsp;<strong>Click here to extend the lock</strong>',
                            at: (new Date()).getTime(),
                            by: '',
                            changes: {
                            },
                            delay: warningTime,
                            msgType: 'warning',
                            icon: 'fa fa-exclamation-triangle',
                            url: 'javascript:apx.notifications.relockItem(' + id + ');',
                            onClose: function (e) {
                                if (0 === apx.locks.mine.items[id].timeout) {
                                    return;
                                }
                                if (apx.locks.mine.items[id].timeout > (new Date()).getTime()) {
                                    return;
                                }

                                delete apx.locks.items[id];

                                $('#editItemModal').find('.modal-footer .btn-save').hide();

                                let changes = {};
                                changes[id] = 'x';
                                apx.notifications.notify({
                                    msgId: 'LockTimeout',
                                    msg: 'Your lock for item "' + title + '" has expired',
                                    at: (new Date()).getTime(),
                                    by: '',
                                    changes: {
                                        'item-ul': changes
                                    },
                                    delay: 0,
                                    msgType: 'danger',
                                    icon: 'fa fa-exclamation-triangle'
                                });
                            }
                        });
                    }, timeout - warningTime, id);

                    apx.locks.mine.items[id] = {
                        warning: warning,
                        timeout: msg.at + timeout + apx.timeDiff - 2000
                    };
                } else {
                    if ("number" === typeof apx.locks.items[id]) {
                        // remove any existing timer
                        clearTimeout(apx.locks.items[id]);
                    }

                    apx.locks.items[id] = setTimeout(function (id) {
                        delete apx.locks.items[id];

                        let item;
                        if ("undefined" !== typeof apx.mainDoc.itemIdHash[id]) {
                            item = apx.mainDoc.itemIdHash[id].fstmt;
                        } else {
                            item = "unknown";
                        }
                        let title = render.inline(item.substr(0, 60));

                        let changes = {};
                        changes[id] = 'x';
                        apx.notifications.notify({
                            msgId: 'LockTimeout',
                            msg: 'The item "' + title + '" is no longer being edited',
                            at: (new Date()).getTime(),
                            by: '',
                            changes: {
                                'item-ul': changes
                            }
                        });
                    }, timeout, id);
                }
            });

            apx.refreshPage();
        },

        'item-ul': function (list) {
            if ("undefined" === typeof apx.locks) {
                // If locks are disabled then ignore
                return;
            }

            $.each(list, function (id, identifier) {
                if ("number" === typeof apx.locks['items'][id]) {
                    clearTimeout(apx.locks['items'][id]);
                    delete apx.locks['items'][id];
                }
                if ("undefined" !== typeof apx.locks.mine.items[id] && "number" === typeof apx.locks.mine.items[id].warning) {
                    clearTimeout(apx.locks.mine.items[id].warning);
                    apx.locks.mine.items[id].timeout = 0;
                    if ("undefined" !== typeof apx.locks.mine.warnings[id] && ("function" === typeof apx.locks.mine.warnings[id].close)) {
                        apx.locks.mine.warnings[id].close();
                    }
                }
            });

            apx.refreshPage();
        },

        relockItem: function (id) {
            // set new lock first
            apx.locks.mine.items[id].timeout = (new Date()).getTime() + 5000;
            apx.locks.mine.warnings[id].close();
            $.ajax({
                url: apx.path.lsitem_lock.replace('ID', id),
                method: 'POST'
            }).done(function (data, textStatus, jqXHR) {
            });
        },

        'item-a': function (list) {
            $.each(list, function (id, identifier) {
                apx.notifications.loadItem(id);
            });
        },

        'item-u': function (list) {
            $.each(list, function (id, identifier) {
                $.ajax({
                    url: apx.path.lsitem_tree_json.replace('ID', id),
                    method: 'GET'
                }).done(function (data, textStatus, jqXHR) {
                    let item = apx.mainDoc.itemIdHash[id];

                    // first delete existing attributes (in case they were cleared)
                    for (let key in item) {
                        if (key !== "nodeType" && key !== "assocs" && key !== "setToParent") {
                            delete item[key];
                        }
                    }
                    // then (re-)set attributes
                    for (let key in data) {
                        item[key] = data[key];
                    }

                    apx.refreshPage();
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    // Ignore for now
                });
            });
        },

        'item-d': function (list) {
            $.each(list, function (id, identifier) {
                let item = apx.mainDoc.itemIdHash[id];

                if ('undefined' !== typeof item && "object" === typeof item.assocs) {
                    $.each(item.assocs, function (i, assoc) {
                        if (true !== assoc.inverse) {
                            apx.edit.performDeleteAssociation(assoc.id);
                        }
                    });
                }

                // find the item in mainDoc.items
                for (let i = 0; i < apx.mainDoc.items.length; ++i) {
                    if (apx.mainDoc.items[i] === item) {
                        // delete it from itemHash and itemIdHash, and splice it from the items array
                        delete apx.mainDoc.itemHash[item.identifier];
                        delete apx.mainDoc.itemIdHash[item.id];
                        apx.mainDoc.items.splice(i, 1);
                        break;
                    }
                }
            });

            apx.refreshPage();
        },

        'doc-l': function (list, msg) {
            if ("undefined" === typeof apx.locks) {
                // If locks are disabled then ignore
                return;
            }

            let timeout = 300000;
            let warningTime = 60000;

            $.each(list, function (id, identifier) {
                if (apx.lsDocId.toString() !== id.toString()) {
                    return;
                }

                if (msg.by === apx.me) {
                    apx.locks['docs'][id] = false;

                    let warning = setTimeout(function (id) {
                        apx.locks.mine.warnings[id] = apx.notifications.notify({
                            msgId: 'LockTimeoutWarning',
                            msg: 'Your lock for the document is about to expire<br/>&nbsp;&nbsp;&nbsp;&nbsp;<strong>Click here to extend the lock</strong>',
                            at: (new Date()).getTime(),
                            by: '',
                            changes: {
                            },
                            delay: warningTime,
                            msgType: 'warning',
                            icon: 'fa fa-exclamation-triangle',
                            url: 'javascript:apx.notifications.relockDoc(' + id + ');',
                            onClose: function (e) {
                                if (0 === apx.locks.mine.docs[id].timeout) {
                                    return;
                                }
                                if (apx.locks.mine.docs[id].timeout > (new Date()).getTime()) {
                                    return;
                                }

                                delete apx.locks.docs[id];

                                $('#editDocModal').find('.modal-footer .btn-save').hide();

                                let changes = {};
                                changes[id] = 'x';
                                apx.notifications.notify({
                                    msgId: 'LockTimeout',
                                    msg: 'Your lock for the document has expired',
                                    at: (new Date()).getTime(),
                                    by: '',
                                    changes: {
                                        'doc-ul': changes
                                    },
                                    delay: 0,
                                    msgType: 'danger',
                                    icon: 'fa fa-exclamation-triangle'
                                });
                            }
                        });
                    }, timeout - warningTime, id);

                    apx.locks.mine.docs[id] = {
                        warning: warning,
                        timeout: msg.at + timeout + apx.timeDiff - 2000
                    };
                } else {
                    if ("number" === typeof apx.locks.docs[id]) {
                        // remove any existing timer
                        clearTimeout(apx.locks.docs[id]);
                    }

                    apx.locks.docs[id] = setTimeout(function (id) {
                        delete apx.locks.docs[id];

                        let changes = {};
                        changes[id] = 'x';
                        apx.notifications.notify({
                            msgId: 'LockTimeout',
                            msg: 'The document is no longer being edited',
                            at: (new Date()).getTime(),
                            by: '',
                            changes: {
                                'doc-ul': changes
                            }
                        });
                    }, timeout, id);
                }
            });

            apx.refreshPage();
        },

        'doc-ul': function (list) {
            if ("undefined" === typeof apx.locks) {
                // If locks are disabled then ignore
                return;
            }

            $.each(list, function (id, identifier) {
                if (apx.lsDocId.toString() === id.toString()) {
                    if ("number" === typeof apx.locks['docs'][id]) {
                        clearTimeout(apx.locks['docs'][id]);
                        delete apx.locks['docs'][id];
                    }
                    if ("undefined" !== typeof apx.locks.mine.docs[id] && "number" === typeof apx.locks.mine.docs[id].warning) {
                        clearTimeout(apx.locks.mine.docs[id].warning);
                        apx.locks.mine.docs[id].timeout = 0;
                        if ("undefined" !== typeof apx.locks.mine.warnings[id] && ("function" === typeof apx.locks.mine.warnings[id].close)) {
                            apx.locks.mine.warnings[id].close();
                        }
                    }
                }
            });

            apx.refreshPage();
        },

        relockDoc: function (id) {
            // set new lock first
            apx.locks.mine.docs[id].timeout = (new Date()).getTime() + 5000;
            apx.locks.mine.warnings[id].close();
            $.ajax({
                url: apx.path.lsdoc_lock.replace('ID', id),
                method: 'POST'
            }).done(function (data, textStatus, jqXHR) {
            });
        },

        'doc-a': function (list) {
            // Document Add should not occur
        },

        'doc-u': function (list) {
            $.each(list, function (id, identifier) {
                $.ajax({
                    url: apx.path.lsdoc_tree_json.replace('ID', id),
                    method: 'GET'
                }).done(function (data, textStatus, jqXHR) {
                    $.each([
                        'title',
                        'officialSourceURL',
                        'creator',
                        'publisher',
                        'description',
                        'language',
                        'adoptionStatus',
                        'statusStart',
                        'statusEnd',
                        'note',
                        'version',
                        'lastChangeDateTime'
                    ], function (i, key) {
                        if ("undefined" !== typeof data[key] && null !== data[key]) {
                            apx.mainDoc.doc[key] = data[key];
                        } else {
                            delete apx.mainDoc.doc[key];
                        }
                    });

                    apx.refreshPage();
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    // Ignore for now
                });
            });
        },

        'doc-d': function (list) {
            $.each(list, function (id, identifier) {
                // Means we can't do any editing anymore....
                if (id === apx.mainDoc.doc.id) {
                    window.location.replace('/');
                }
            });
        },

        reload: function (list) {
            window.location.reload(true);
        },

        redirect: function (list) {
            window.location.replace(list);
        },

        displayNotification: function (msg) {
            // Do not display messages to yourself
            if ('string' === typeof msg.by && msg.by === apx.me) {
                return;
            }

            if ('boolean' === typeof msg.show && msg.show === false) {
                return;
            }

            if (!apx.notifications.isEnabled()) {
                return;
            }

            if ('function' === typeof apx.notifyCheck[msg.msgId]) {
                if (false === apx.notifyCheck[msg.msgId](msg)) {
                    return;
                }
            }

            if (msg.url && "LockTimeoutWarning" !== msg.msgId) {
                msg.msg += '<span class="notification-url-indicator">Show</span>';
            }

            return $.notify({
                message: msg.msg,
                url: msg.url ? msg.url : null,
                icon: msg.icon ? msg.icon : null,
                target: msg.target ? msg.target : '_self'
            }, {
                delay: ('number' === typeof msg.delay) ? msg.delay : 30000,
                allow_dismiss: ('boolean' === typeof msg.dismissible) ? !msg.dismissible : true,
                type: ('string' === typeof msg.msgType) ? msg.msgType : 'info',
                onShow: msg.onShow ? msg.onShow : null,
                onShown: msg.onShown ? msg.onShown : null,
                onClose: msg.onClose ? msg.onClose : null,
                onClosed: msg.onClosed ? msg.onClosed : null
            });
        },

        notify: function (msg) {
            let notification = apx.notifications.displayNotification(msg);

            if ("object" === typeof msg.changes) {
                let changed = {};
                $.each(msg.changes, function (key, list) {
                    let type = key.replace(/-.*/, '');
                    if ("undefined" === typeof changed[type]) {
                        changed[type] = [];
                    }
                    $.each(list, function (id, identifier) {
                        changed[type].push(id);
                    });
                });

                $.ajax({
                    url: apx.path.doc_tree_multi_changes.replace('ID', apx.mainDoc.doc.id),
                    method: 'POST',
                    data: changed
                }).done(function (data, textStatus, jqXHR) {

                    apx.notifications.updates = data;

                    $.each(msg.changes, function (key, list) {
                        if (("function" === typeof apx.notifications[key])) {
                            apx.notifications[key](list, msg);
                        } else {
                            console.log("function not found", key, list);
                        }
                    });
                });
            }

            return notification;
        },

        loadAssociation: function (assocId) {
            // Get association info
            let load = function (data) {
                if ("undefined" !== typeof apx.mainDoc.assocIdHash[data.id]) {
                    // Already exists
                    return;
                }

                let a = apx.mainDoc.addAssociation(data);
                apx.mainDoc.addInverseAssociation(a);

                apx.refreshPage();
            };

            if ("undefined" !== apx.notifications.updates['assocs'][assocId]) {
                load(apx.notifications.updates['assocs'][assocId]);
            } else {
                $.ajax({
                    url: apx.path.doc_tree_association_json.replace('ID', assocId),
                    method: 'GET'
                }).done(function (data, textStatus, jqXHR) {
                    load(data);
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    // Ignore for now
                });
            }
        },

        loadItem: function (itemId) {
            if ("undefined" === typeof apx.mainDoc.itemIdHash[itemId]) {
                $.ajax({
                    url: apx.path.lsitem_tree_json.replace('ID', itemId),
                    method: 'GET'
                }).done(function (data, textStatus, jqXHR) {
                    apx.mainDoc.addNewItemData(data);

                    apx.refreshPage();
                });
            }
        }
    });

    $.extend(apx.notifyCheck, {
        // See if the lock/unlock message should be shown
        /** @return {boolean} */
        'D04': function (msg) {
            // document lock
            let display = false;
            $.each(msg.changes['doc-l'], function (id, identifier) {
                if ("number" !== typeof apx.locks['docs'][id] && false !== apx.locks['docs'][id]) {
                    display = true;
                }
            });

            return display;
        },
        /** @return {boolean} */
        'D05': function (msg) {
            // document unlock
            let display = false;
            $.each(msg.changes['doc-ul'], function (id, identifier) {
                if ("number" === typeof apx.locks['docs'][id]) {
                    display = true;
                }
            });

            return display;
        },
        /** @return {boolean} */
        'I06': function (msg) {
            // item lock
            let display = false;
            $.each(msg.changes['item-l'], function (id, identifier) {
                if ("number" !== typeof apx.locks['items'][id] && false !== apx.locks['items'][id]) {
                    msg.url = apx.path.lsItem.replace('ID', id);
                    display = true;
                }
            });

            return display;
        },
        /** @return {boolean} */
        'I07': function (msg) {
            // item unlock
            let display = false;
            $.each(msg.changes['item-ul'], function (id, identifier) {
                if ("number" === typeof apx.locks['items'][id]) {
                    msg.url = apx.path.lsItem.replace('ID', id);
                    display = true;
                }
            });

            return display;
        },

        // Add links to items
        /** @return {boolean} */
        'I01': function (msg) {
            // item add
            let display = false;
            $.each(msg.changes['item-a'], function (id, identifier) {
                msg.url = apx.path.lsItem.replace('ID', id);
                display = true;
            });

            return display;
        },

        /** @return {boolean} */
        'I03': function (msg) {
            // item copied
            let display = false;
            $.each(msg.changes['item-a'], function (id, identifier) {
                msg.url = apx.path.lsItem.replace('ID', id);
                display = true;
            });

            return display;
        },

        /** @return {boolean} */
        'I08': function (msg) {
            // item modified
            let display = false;
            $.each(msg.changes['item-u'], function (id, identifier) {
                if ("undefined" !== typeof apx.mainDoc.itemIdHash[id]) {
                    msg.url = apx.path.lsItem.replace('ID', id);
                    display = true;
                }
            });

            return display;
        },
    });

    //////////////////////////////////////////////////////
    /**
     * "Spinner" for indicating when something is loading
     */
    apx.spinner = {};
    apx.spinner.html = function (msg) {
        return '<div class="spinnerOuter"><i class="fa fa-cog spinning spinnerCog"></i><span class="spinnerText">' + msg + '</span></div>';
    };

    apx.spinner.showModal = function (msg) {
        $("#modalSpinnerMessage").html(apx.spinner.html(msg));
        $("#modalSpinner").show();
    };

    apx.spinner.hideModal = function () {
        $("#modalSpinner").hide();
    };

    //////////////////////////////////////////////////////
    // BROWSER HISTORY MAINTENANCE

    apx.popStateActivate = false;

    // set onpopstate event to restore state when user clicks the browser back/forward button
    window.onpopstate = function (event) {
        // set popStateActivate so we don't re-push this history state
        apx.popStateActivate = true;

        let lsItemId, assocGroup, view;
        // if event.state is null, we're back to the initial values...
        if (event.state == null) {
            lsItemId = apx.initialLsItemId;
            assocGroup = apx.initialAssocGroup;
            view = apx.viewMode.initialView;
        } else {
            lsItemId = event.state.lsItemId;
            assocGroup = event.state.assocGroup;
            view = event.state.view;
        }

        // now if we're moving to assocView, show it
        if (view === "assoc") {
            apx.viewMode.showAssocView("history");
        } else if (view === "log") {
            apx.viewMode.showLogView("history");
        } else {
            // else assume tree view
            apx.viewMode.showTreeView("history");
            if ('undefined' !== typeof apx.treeDoc1) {
                // restore assocGroup if necessary
                if (assocGroup != apx.treeDoc1.currentAssocGroup) {
                    apx.treeDoc1.setCurrentAssocGroup(assocGroup);
                    apx.treeDoc1.ftRender();
                }
                // set and activate the current item
                apx.treeDoc1.setCurrentItem({ "lsItemId": lsItemId });
                apx.treeDoc1.activateCurrentItem();
            }
        }
    };

    /** Function to update the history state */
    apx.pushHistoryState = function () {
        // no history if we loaded the mainDoc from a url, or if we're in chooser mode
        if (apx.mainDoc.loadedFromUrl() || apx.query.mode === "chooser") {
            return;
        }

        // if we just called this after the user clicked back or forward, though, don't push a new state
        if (apx.popStateActivate !== true) {
            // For now, at least, if we're not showing the mainDoc on the left side, don't push a new state
            if (apx.mainDoc !== apx.treeDoc1) {
                return;
            }

            let path;
            let state = {
                "view": apx.viewMode.currentView
            };

            // if currentItem is the document...
            if (apx.treeDoc1.currentItem === apx.treeDoc1.doc) {
                path = apx.path.lsDoc.replace('ID', apx.lsDocId);
                if (apx.viewMode.currentView === "assoc") {
                    // add "/av" to path if the association view
                    path += "/av";
                }
                if (apx.viewMode.currentView === "log") {
                    // add "/lv" to path if the log view
                    path += "/lv";
                }
                state.lsItemId = null;
            } else {
                // else the currentItem is an item
                path = apx.path.lsItem.replace('ID', apx.treeDoc1.currentItem.id);
                state.lsItemId = apx.treeDoc1.currentItem.id;
            }

            // add assocGroup to path if necessary
            if (apx.treeDoc1.currentAssocGroup != null) {
                if (apx.viewMode.currentView !== "assoc"
                    && apx.viewMode.currentView !== "log") {
                    path += "/" + apx.treeDoc1.currentAssocGroup;
                }
                state.assocGroup = apx.treeDoc1.currentAssocGroup;
            } else {
                state.assocGroup = null;
            }

            window.history.pushState(state, "Competency Framework", path);
        }
        // clear popStateActivate
        apx.popStateActivate = false;
    };

    apx.markLogsAsRead = function () {
        $('.modal#seeImportLogs button.btn-link#mark-logs-as-read').on('click', function () {
            $.post('/cfdoc/' + apx.lsDocId + '/import_logs/mark_as_read')
                .done(function (data) {
                    $('.modal#seeImportLogs .modal-body .list-group').fadeOut();
                    $(this).attr('disabled', 'disabled');
                    $('#seeImportLogs').modal('toggle');
                });
        });
    };
}
