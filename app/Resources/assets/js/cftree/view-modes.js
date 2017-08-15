/* global apx */
window.apx = window.apx||{};

/* global empty */

/////////////////////////////////////////////////////
// TREE VIEW / ASSOCIATIONS VIEW MODES
apx.viewMode = {};

apx.viewMode.initialView = "tree";
apx.viewMode.currentView = "tree";
apx.viewMode.lastViewButtonPushed = "tree";

apx.viewMode.showTreeView = function(context) {
    apx.viewMode.currentView = "tree";

    // if the user clicked the button to show this view, or clicked an item from the associations table
    if (context == "button" || context == "avTable") {
        // if the user clicked the button and the last view button pushed wasn't tree...
        if (context == "button" && apx.viewMode.lastViewButtonPushed != "tree") {
            // then the user must have been in the assoc view, then clicked the button to go to the tree view, so push a history state
            apx.pushHistoryState();
        }
        // set viewMode.lastViewButtonPushed to "tree" (so if we got back to the tree view via clicking on an item from the assoc table, we "simulate" clicking the tree view button)
        apx.viewMode.lastViewButtonPushed = "tree";
    }

    // set buttons appropriately
    $("#displayAssocBtn").removeClass("btn-primary").addClass("btn-default").blur();
    $("#displayTreeBtn").addClass("btn-primary").removeClass("btn-default").blur();

    // hide the assocView and show the treeView
    $("#assocView").hide();
    $("#treeView").show();
};

apx.viewMode.avFilters = {
    "avShowChild": false,
    "avShowExact": true,
    "avShowExemplar": true,
    "avShowIsRelatedTo": true,
    "avShowprecedes": true,
    "avShowreplacedBy": false,
    "avShowhasSkillLevel": false,
    "avShowisPeerOf": false,
    "avShowisPartOf": false,
    "groups": []
};
apx.viewMode.assocViewStatus = "not_written";
apx.viewMode.showAssocView = function(context) {
    // can't show the assocView until all docs have been loaded
    for (var identifier in apx.allDocs) {
        if (apx.allDocs[identifier] == "loading") {
            apx.spinner.showModal("Loading associated document(s)");
            setTimeout(function() { apx.viewMode.showAssocView(context); }, 1000);
            return;
        }
    }
    apx.spinner.hideModal();

    apx.viewMode.currentView = "assoc";

    // currentItem is always the doc in assocView
    apx.mainDoc.setCurrentItem({"item": apx.mainDoc.doc});

    // if we're refreshing the view
    if (context == "refresh") {
        // set viewMode.assocViewStatus to "stale" so we make sure to reload it
        apx.viewMode.assocViewStatus = "stale";

    // else if the user clicked the button to load this view
    } else if (context == "button") {
        // unless the user has now clicked the Associations button twice in a row, push a history state
        if (apx.viewMode.lastViewButtonPushed != "assoc") {
            apx.pushHistoryState();
        }

        // note that this was the last button pushed
        apx.viewMode.lastViewButtonPushed = "assoc";
    }

    // if viewMode.assocViewStatus isn't "current", re-write the table
    if (apx.viewMode.assocViewStatus != "current") {
        // destroy previous table if we already created it
        if (apx.viewMode.assocViewStatus != "not_written") {
            $("#assocViewTable").DataTable().destroy();
        }

        // make sure viewMode.avFilters.groups is set up to use included groups
        var gft = [];
        for (var i = 0; i < apx.mainDoc.assocGroups.length; ++i) {
            var group = apx.mainDoc.assocGroups[i];
            if (!empty(apx.viewMode.avFilters.groups[group.id])) {
                gft[group.id] = apx.viewMode.avFilters.groups[group.id];
            } else {
                gft[group.id] = true;
            }
        }

        // add a value for the default group; item 0
        if (!empty(apx.viewMode.avFilters.groups[0])) {
            gft[0] = apx.viewMode.avFilters.groups[0];
        } else {
            gft[0] = true;
        }
        apx.viewMode.avFilters.groups = gft;

        function avGetItemCell(a, key) {
            // set default title
            var title;
            if (!empty(a[key].uri)) {
                title = a[key].uri;
            } else if (!empty(a[key].item)) {
                title = a[key].item;
            } else if (!empty(a[key].title)) {
                title = a[key].title;
            } else {
                title = key;
            }
            var doc = null;

            // for the dest of an exemplar, we just use .uri
            if (a.type == "exemplar") {
                title = a[key].uri;

            // else see if the "item" is actually a document
            } else if (!empty(apx.allDocs[a[key].item]) && typeof(apx.allDocs[a[key].item]) != "string") {
                title = "Document: " + apx.allDocs[a[key].item].doc.title;

            // else if we know about this item via allItemsHash...
            } else if (!empty(apx.allItemsHash[a[key].item])) {
                var destItem = apx.allItemsHash[a[key].item];
                title = apx.mainDoc.getItemTitle(destItem, true);
                doc = destItem.doc;

            // else we don't (currently at least) know about this item...
            } else {
                if (a[key].doc != "?") {
                    // look for document in allDocs
                    doc = apx.allDocs[a[key].doc];

                    // if we tried to load this document and failed, note that
                    if (doc == "loaderror") {
                        title += " (document could not be loaded)";

                    // else if we know we're still in the process of loading that doc, note that
                    } else if (doc == "loading") {
                        title += " (loading document...)";

                    // else we have the doc -- this shouldn't normally happen, because if we know about the doc,
                    // we should have found the item in apx.allItemsHash above
                    } else if (typeof(doc) == "object") {
                        title += " (item not found in document)";
                    }
                }
            }

            // if item comes from another doc, note that
            if (!empty(doc) && typeof(doc) == "object" && doc != apx.mainDoc) {
                var docTitle = doc.doc.title;
                if (docTitle.length > 30) {
                    docTitle = docTitle.substr(0, 35);
                    docTitle = docTitle.replace(/\w+$/, "");
                    docTitle += "…";
                }
                title += ' <span style="color:red">' + docTitle + '</span>';
            }

            var html = '<div data-association-id="' + a.id + '" data-association-identifier="' + a.identifier + '" data-association-item="' + key + '" class="assocViewTitle">'
                + title
                + '</div>'
            ;

            return html;
        }

        // compose datatables data array
        var dataSet = [];
        for (var i = 0; i < apx.mainDoc.assocs.length; ++i) {
            var assoc = apx.mainDoc.assocs[i];

            // skip associations (probably inverse associations) from other docs
            if (assoc.assocDoc != apx.mainDoc.doc.identifier) {
                continue;
            }

            // skip types if filters dictate
            if (assoc.type == "isChildOf") {
                if (!apx.viewMode.avFilters.avShowChild) {
                    continue;
                }
            } else if (assoc.type == "exactMatchOf") {
                if (!apx.viewMode.avFilters.avShowExact) {
                    continue;
                }
            } else if (assoc.type == "exemplar") {
                if (!apx.viewMode.avFilters.avShowExemplar) {
                    continue;
                }
              } else if (assoc.type == "isRelatedTo") {
                if (!apx.viewMode.avFilters.avShowIsRelatedTo) {
                    continue;
                  }
                } else if (assoc.type == "precedes") {
                    if (!apx.viewMode.avFilters.avShowprecedes) {
                        continue;
                    }
                  } else if (assoc.type == "replacedBy") {
                      if (!apx.viewMode.avFilters.avShowreplacedBy) {
                          continue;
                      }
                    } else if (assoc.type == "hasSkillLevel") {
                        if (!apx.viewMode.avFilters.avShowhasSkillLevel) {
                            continue;
                        }
                      } else if (assoc.type == "isPeerOf") {
                          if (!apx.viewMode.avFilters.avShowisPeerOf) {
                              continue;
                          }
                        } else if (assoc.type == "isPartOf") {
                            if (!apx.viewMode.avFilters.avShowisPartOf) {
                                continue;
                            }
            }

            // skip groups if filters dictate
            if ("groupId" in assoc) {
                if (!apx.viewMode.avFilters.groups[assoc.groupId]) {
                    continue;
                }
            } else {
                if (!apx.viewMode.avFilters.groups[0]) {
                    continue;
                }
            }

            // determine groupForLinks
            var groupForLinks = "default";
            if ("groupId" in assoc) {
                groupForLinks = assoc.groupId;
            }

            // get text to show in origin and destination column
            var origin = avGetItemCell(assoc, "origin");
            var dest = avGetItemCell(assoc, "dest");

            // get type cell, with remove association button (only for editors)
            var type = apx.mainDoc.getAssociationTypePretty(assoc) + $("#associationRemoveBtn").html();

            // construct array for row
            var arr = [origin, type, dest];

            // add group to row array if we have any groups
            if (apx.mainDoc.assocGroups.length > 0) {
                if ("groupId" in assoc) {
                    arr.push(apx.mainDoc.assocGroupIdHash[assoc.groupId].title);
                } else {
                    arr.push("– Default –");
                }
            }

            // push row array onto dataSet array
            dataSet.push(arr);
        }

        // set up columns
        var columns = [
            { "title": "Origin", "className": "avTitleCell" },
            { "title": "Association Type", "className": "avTypeCell" },
            { "title": "Destination", "className": "avTitleCell" }
        ];
        // add group if we have any
        if (apx.mainDoc.assocGroups.length > 0) {
            columns.push({"title": "Association Group", "className": "avGroupCell"});
        }

        // populate the table
        $("#assocViewTable").DataTable({
            "data": dataSet,
            "columns": columns,
            "stateSave": true,
            "lengthMenu": [ [ 25, 100, 500, -1 ], [25, 100, 500, "All"]],
            "pageLength": 100,
            //"select": true
        });

        // add filters
        $("#assocViewTable_wrapper .dataTables_length").prepend($("#assocViewTableFilters").html());

        // enable type filters
        for (var filter in apx.viewMode.avFilters) {
            $("#assocViewTable_wrapper input[data-filter=" + filter + "]").prop("checked", apx.viewMode.avFilters[filter])
                .on('change', function() {
                    apx.viewMode.avFilters[$(this).attr("data-filter")] = $(this).is(":checked");
                    apx.viewMode.showAssocView("refresh");
                    // TODO: save this value in localStorage?
                });
        }

        // enable group filters if we have any groups
        if (apx.mainDoc.assocGroups.length > 0) {
            $gf = $("#assocViewTable_wrapper .assocViewTableGroupFilters");
            for (var groupId in apx.viewMode.avFilters.groups) {
                if (groupId != 0) {
                    $gf.append('<label class="avGroupFilter"><input type="checkbox" data-group-id="' + groupId + '"> ' + apx.mainDoc.assocGroupIdHash[groupId].title + '</label><br>');
                }
                $("#assocViewTable_wrapper .avGroupFilter input[data-group-id=" + groupId + "]").prop("checked", apx.viewMode.avFilters.groups[groupId])
                    .on('change', function() {
                        apx.viewMode.avFilters.groups[$(this).attr("data-group-id")] = $(this).is(":checked");
                        apx.viewMode.showAssocView("refresh");
                        // TODO: save this value in localStorage?
                    });
            }
            $gf.css("display", "inline-block");
        }

        // enable remove buttons
        $("#assocViewTable_wrapper .btn-remove-association").on('click', function(e) {
            e.preventDefault();
            var assocId = $(this).closest("tr").find("[data-association-id]").attr("data-association-id");
            console.log("delete " + assocId);

            apx.edit.deleteAssociation(assocId, function() {
                // refresh the table after deleting the association
                apx.viewMode.showAssocView("refresh");
            });
            return false;
        });

        // tooltips for items with titles
        $(".assocViewTitle").each(function() {
            var content = $(this).html();
            $(this).tooltip({
                "title": content,
                "delay": { "show": 200, "hide": 100 },
                "placement": "bottom",
                "html": true,
                "container": "body"
            });
        });

        // click on items to open them
        $(".assocViewTitle").on('click', function(e) {
            // if openAssociationItem returns true, it means that we opened an item in this document
            if (apx.mainDoc.openAssociationItem(this, true)) {
                // so switch to tree view mode
                apx.viewMode.showTreeView("avTable");
            }
        });

        apx.viewMode.assocViewStatus = "current";

    // end of code for writing table
    }

    // set mode toggle buttons appropriately
    $("#displayTreeBtn").removeClass("btn-primary").addClass("btn-default").blur();
    $("#displayAssocBtn").addClass("btn-primary").removeClass("btn-default").blur();

    // hide the treeView and show the assocView
    $("#treeView").hide();
    $("#assocView").show();
};

////////////////////////////////////////////////
// "CHOOSER" MODE

apx.chooserMode = {};
apx.chooserMode.active = function() {
    // we're in chooser mode if "mode=chooser" is in the query string
    return (apx.query.mode == "chooser");
};

apx.chooserMode.initialize = function() {
    // hide header, footer, docTitleRow, instructions, and some other things
    $("header").hide();
    $("footer").hide();
    $("#docTitleRow").hide();
    $("#tree1Instructions").hide();
    $("#treeRightSideMode").hide();
    $("#itemOptionsWrapper").hide();

    // unless we have "associations=true" in the query string, hide associations from the item details
    if (apx.query.associations != "true") {
        $(".lsItemAssociations").hide();
    }

    // set treeSideLeft to class col-sm-12 instead of col-sm-6
    $("#treeSideLeft").removeClass("col-sm-6").addClass("chooserModeDocTree");

    // for treeSideRight, remove class col-sm6 and add class chooserModeItemDetails
    $("#treeSideRight").removeClass("col-sm-6").addClass("chooserModeItemDetails");

    // click event on chooserModeTreeSideRightBackground
    $("#chooserModeTreeSideRightBackground").on("click", function() { apx.chooserMode.hideDetails(); });

    // show and enable chooserModeButtons
    $("#chooserModeButtons").show();
    $("#chooserModeItemDetailsChooseBtn").on("click", function() { apx.chooserMode.choose(); });
    $("#chooserModeItemDetailsCloseDetailsBtn").on("click", function() { apx.chooserMode.hideDetails(); });
};

/** Buttons to show next to each item's title in the fancytree */
apx.chooserMode.treeItemButtons = function() {
    return '<div class="treeItemButtons" style="display:none">'
        + '<button class="chooserModeShowDetailsBtn btn btn-default btn-xs"><span class="glyphicon glyphicon-search" title="Show details"></span></button>'
        + ' <button class="chooserModeChooseBtn btn btn-default btn-xs">Choose</button>'
        + '</div>'
        ;
}

/** Enable item chooser buttons; this will be called each time an item is activated in the fancytree */
apx.chooserMode.enableTreeItemButtons = function(node) {
    if (apx.query.mode == "chooser") {
        // hide and disable all buttons
        $(".treeItemButtons").hide().find("button").off("click");
        // then show and enable this item's buttons
        $(node.li).find(".treeItemButtons").first().show();
        $(node.li).find(".treeItemButtons").first().find(".chooserModeShowDetailsBtn").on("click", function() { apx.chooserMode.showDetails(); });
        $(node.li).find(".treeItemButtons").first().find(".chooserModeChooseBtn").on("click", function() { apx.chooserMode.choose(); });
    }
};

/** User clicked to show details for an item */
apx.chooserMode.showDetails = function() {
    $("#chooserModeTreeSideRightBackground").show();
    $("#treeSideRight").animate({"right": "10px"}, 200);

    // remove stray tooltips
    setTimeout(function() { $(".tooltip").remove(); }, 100);
};

/** Hide details */
apx.chooserMode.hideDetails = function() {
    $("#chooserModeTreeSideRightBackground").hide();
    $("#treeSideRight").animate({"right": "-600px"}, 200);
};

/** Item is chosen... */
apx.chooserMode.choose = function() {
    // compose data to send back about chosen item
    var i = apx.mainDoc.currentItem;
    var data = {
        "item": {
            "identifier": i.identifier,
            "saltId": i.id,
            "fullStatement": i.fstmt,
            "abbreviatedStatement": i.astmt,
            "humanCodingScheme": i.hcs,
            "listEnumInSource": i.le,
            "conceptKeywords": i.ck,
            "conceptKeywordsURI": i.cku,
            "notes": i.notes,
            "language": i.lang,
            "educationalAlignment": i.el,
            "itemType": i.itp,
            "lastChangeDateTime": i.mod
        }
    };

    // append a token if provided
    if (!empty(apx.query.choosercallbacktoken)) {
        data.token = apx.query.choosercallbacktoken;
    }

    console.log(data);

    apx.spinner.showModal("Item chosen");

    // if a callback url is given in the query string, send the chosen item back to that url
    if (!empty(apx.query.choosercallbackurl)) {
        var url = apx.query.choosercallbackurl + "?data=" + encodeURIComponent(JSON.stringify(data));
        window.location = url;
        /*
        $.ajax({
            url: apx.query.choosercallbackurl,
            method: 'GET',
            data: data
        }).done(function(data, textStatus, jqXHR) {
            console.log("OpenSALT item chooser callback function executed.");
            apx.spinner.hideModal();

        }).fail(function(jqXHR, textStatus, errorThrown){
            apx.spinner.hideModal();
            console.log(errorThrown);
            alert("Error submitting chosen item.");
        });
        */

        return;

    // else if a callback function is given, try to call it
    } else if (!empty(apx.query.choosercallbackfn)) {
        try {
            apx.query.choosercallbackfn(data);
        } catch(e) {
            apx.spinner.hideModal();
            console.log(e);
            alert("Callback function “" + apx.query.choosercallbackfn + "” did not execute.");
        }
        return;
    }

    apx.spinner.hideModal();
    alert("Item chosen: " + itemData.fullStatement + "\n\nTo send items to a callback URL or function, provide a “choosercallbackurl” or “choosercallbackfn” in the query string.");
};
