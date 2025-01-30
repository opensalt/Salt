import { empty } from './utils';
import ApxDocument from './view-documentclass';
import render from './render-md';

export default function (apx) {
    /** Prepare menus for selecting documents for the left- and right-side trees */
    apx.prepareDocumentMenus = function () {
        // The original menu will be rendered into div #ls_doc_list and select #ls_doc_list_lsDoc, on the right side

        // Mark this document in the menu
        let docList = $("#ls_doc_list_lsDoc");
        let $opt = docList.find("[value=" + apx.mainDoc.doc.id + "]");
        $opt.html($opt.html() + " (• DOCUMENT BEING EDITED •)");

        // add item to menu for loading from another server
        docList.append('<optgroup class="externalDocsOptGroup" label="EXTERNAL DOCUMENTS"><option value="url">Load an “external” document by url…</option></optgroup>');

        // go through each provided "associatedDoc" and add it to the "externalDocsOptGroup" option group if it's an external doc
        if (!empty(apx.mainDoc.associatedDocs)) {
            for (let identifier in apx.mainDoc.associatedDocs) {
                let ad = apx.mainDoc.associatedDocs[identifier];
                // non-external docs have urls that start with "local"
                if (ad.url.search(/local/) !== 0) {
                    apx.addDocToMenus(identifier, ad.url, ad.title);
                }
            }
        }

        // now get the div and update the id's
        let $rightDiv = $("#ls_doc_list");
        $rightDiv.addClass("ls_doc_list");
        $rightDiv.find("[type=hidden]").remove();
        $rightDiv.attr("id", "ls_doc_list_right");
        $rightDiv.find("select").attr("id", "ls_doc_list_lsDoc_right");

        // clone the div for the left side, update the id's there, and insert it in place
        let $leftDiv = $rightDiv.clone();
        $leftDiv.attr("id", "ls_doc_list_left");
        $leftDiv.find("select").attr("id", "ls_doc_list_lsDoc_left");
        $("#tree1SelectorDiv").find(".row").append($leftDiv);

        // enable the select menus
        $("#ls_doc_list_lsDoc_left").on('change', function () { apx.docSelectedForTree(this, 1); });
        $("#ls_doc_list_lsDoc_right").on('change', function () { apx.docSelectedForTree(this, 2); });

        // change tree buttons
        $(".changeTree1DocumentBtn").on('click', function () { apx.tree1ChangeButtonClicked(); });
        $(".changeTree2DocumentBtn").on('click', function () { apx.tree2ChangeButtonClicked(); });

        // prepare the modal for loading an external document
        let $modal = $('#loadExternalDocumentModal');
        $modal.find('.btn-save').on('shown.bs.modal', function (e) {
            $("#loadExternalDocumentUrlInput").trigger('focus').trigger('select');
        }).on('click', function (e) {
            let url = $("#loadExternalDocumentUrlInput").val();
            $modal.modal('hide');
            if (!empty(url)) {
                apx.docSelectedForTree(url);
            }
        });
    };

    /** Add a document loaded from an external server to the document select menus */
    apx.addDocToMenus = function (identifier, url, title) {
        // add to mainDoc.associatedDocs if necessary
        if (empty(apx.mainDoc.associatedDocs[identifier])) {
            apx.mainDoc.associatedDocs[identifier] = { "url": url, "title": title, "autoLoad": "false" };
        }

        // and add to menus if necessary
        if ($(".externalDocsOptGroup [value=" + identifier + "]").length === 0) {
            $(".externalDocsOptGroup").prepend('<option value="' + identifier + '">' + apx.mainDoc.associatedDocs[identifier].title + ' (' + identifier + ')</option>');
        }
    };

    apx.docSelectedForTree = function (menuOrUrl, side) {
        let lsDocId, initializationKey;

        // if menuOrUrl is a string, its a URL that the user entered
        if (typeof (menuOrUrl) === "string") {
            initializationKey = "url";
            lsDocId = menuOrUrl;

            // retrieve stashed side
            side = apx.docSelectedForTreeSide;

        } else {
            initializationKey = "id";
            // get the selected document id
            lsDocId = $(menuOrUrl).val();

            // if user selects to load a new document by URL, get the URL now
            if (lsDocId === "url") {
                $("#loadExternalDocumentModal").modal('show');
                $(menuOrUrl).val("");

                // stash side so we can retrieve it if the user chooses a URL
                apx.docSelectedForTreeSide = side;
                return;

                // if user selects the blank item in the menu, go back to the currently-loaded document
            } else if (lsDocId === "") {
                $(menuOrUrl).val(apx["treeDoc" + side].doc.id);
                return;

                // else if lsDocId isn't a number, it's an identifier for an external document
            } else if (isNaN(lsDocId * 1)) {
                initializationKey = "identifier";
            }
        }

        // if we get to here we should have an initializationKey and an lsDocId

        // destroy previus ft if there
        try {
            apx["treeDoc" + side]["ft" + side].fancytree("destroy");
        } catch (e) {
            // Ignore error
        }
        $('#viewmode_tree' + side).html("");

        // check to see if we already have the document info; if so we don't need to reload
        for (let identifier in apx.allDocs) {
            let d = apx.allDocs[identifier];

            // if this document errored when loading, continue through the loop; if this is what the user is trying to load now, let them retry
            if (d === "loaderror") {
                continue;
            }

            // if *any* documents are still autoloading, make the user wait, because the document they're requesting here might be the one that's loading
            if (d === "loading") {
                apx.spinner.showModal("Loading document");
                setTimeout(function () { apx.docSelectedForTree(menuOrUrl, side); }, 1000);
                return;
            }

            apx.spinner.hideModal();

            // if we found the document that was requested here...
            if ((initializationKey === "identifier" && identifier === lsDocId)
                || (initializationKey === "id" && !d.isExternalDoc() && d.doc.id === lsDocId)) {
                // set treeDoc1 or treeDoc2
                apx["treeDoc" + side] = d;
                // and call the side's treeDocLoadCallback function
                apx["treeDocLoadCallback" + side]();
                return;
            }
        }

        // if we get to here, initialize and load the document
        let o = {};
        o[initializationKey] = lsDocId;
        apx["treeDoc" + side] = new ApxDocument(o, apx);

        // load the document
        apx.spinner.showModal("Loading document");
        apx["treeDoc" + side].load(function () {
            apx["treeDocLoadCallback" + side]();
            apx.spinner.hideModal();
        });

    };

    /** Define a function that will "synch" association destinations as documents get loaded when the application is starting up */
    apx.unknownAssocsShowing = null;
    apx.checkUnknownAssociationDestinationsInterval = setInterval(function () {
        // if unknownAssocsShowing hasn't been set yet, just return
        if (apx.unknownAssocsShowing === null) {
            return;
        }

        // go through any current unknownAssocsShowing
        for (let id in apx.unknownAssocsShowing) {
            const assoc = apx.unknownAssocsShowing[id];
            // if we know about this association's destination item now
            if (!empty(apx.allItemsHash[assoc.dest.item])) {
                const title = apx.treeDoc1.associationDestItemTitle(assoc);
                $("[data-association-id=" + id + "] .itemDetailsAssociationTitle").html(title);
            }
        }

        // if all documents have been loaded, clear the interval
        let allLoaded = true;
        for (let identifier in apx.allDocs) {
            if (apx.allDocs[identifier] === "loading") {
                allLoaded = false;
                break;
            }
        }
        if (allLoaded) {
            clearInterval(apx.checkUnknownAssociationDestinationsInterval);
        }

    }, 1000);


    ///////////////////////////////////////////////////////////////////////////////
    // LEFT-SIDE TREE
    apx.treeDoc1 = null;

    apx.tree1ChangeButtonClicked = function () {
        // clear viewmode_tree1 and hide tree1SectionControls and assocGroupFilter
        $("#viewmode_tree1").html("");
        $("#tree1SectionControls").hide();
        $("#treeSideLeft").find(".assocGroupFilter").hide();

        // clear selection in ls_doc_list_lsDoc_left
        $("#ls_doc_list_lsDoc_left").val("");

        // and show tree1SelectorDiv
        $("#tree1SelectorDiv").show();

        // reset instructions
        $("#tree1InitialInstructions").show();
        $("#tree1SectionThisDocInstructions").hide();
        $("#tree1SectionOtherDocInstructions").hide();

        // make sure the noItemsInstructions div is hidden
        $("#noItemsInstructions").hide();
    };


    // define function to run when treeDoc1 is loaded
    apx.treeDocLoadCallback1 = function () {
        // define treeDoc1's ftRender function
        apx.treeDoc1.ftRender1 = function () {
            // first process the tree, using treeDoc1's current association group
            let ftData = apx.treeDoc1.createTree(apx.treeDoc1.currentAssocGroup, 1);

            let viewmodeTree1 = $('#viewmode_tree1');
            // destroy the existing tree if necessary
            if (viewmodeTree1.children().length > 0) {
                viewmodeTree1.fancytree("destroy");
            }

            // establish the fancytree widget
            apx.treeDoc1.ft1 = viewmodeTree1.fancytree({
                extensions: ['filter', 'dnd'],
                source: ftData,
                quicksearch: true,
                filter: {
                    autoApply: true,  // Re-apply last filter if lazy data is loaded
                    counter: true,  // Show a badge with number of matching child nodes near parent icons
                    fuzzy: false,  // Match single characters in order, e.g. 'fb' will match 'FooBar'
                    hideExpandedCounter: true,  // Hide counter badge, when parent is expanded
                    highlight: true,  // Highlight matches by wrapping inside <mark> tags
                    mode: "hide"  // Grayout unmatched nodes (pass "hide" to remove unmatched node instead)
                },

                /*
                renderTitle: function(event, data) {
                    console.log(event, data);
                    title = render.inline(apx.treeDoc1.getItemTitle(data.node.data.ref, true));
                    return title;
                },
                */

                // function called after the node is rendered
                renderNode: function (event, data) {
                    let $span = $(data.node.span),
                        $title = $span.find('> span.fancytree-title'),
                        ref = data.node.data.ref
                        ;
                    let title = '';

                    if ('undefined' === typeof ref) {
                        return;
                    }

                    if (ref.title) {
                        title = render.inline(ref.title);
                    } else if (ref.astmt) {
                        title = render.inline(ref.astmt);
                    } else if (ref.fstmt) {
                        title = render.inline(ref.fstmt);
                    }

                    if (ref.hcs) {
                        title = '<span class="item-humanCodingScheme">' + render.escaped(ref.hcs) + '</span> ' + title;
                    }

                    $title.html(title);
                },

                click: function (event, data) {
                    // if we're in chooser mode, show display to allow this item to be chosen
                    // (this won't have any effect if we're not in chooser mode)
                    apx.chooserMode.itemClicked(data.node);
                },

                // when item is activated (user clicks on it or activateKey() is called), show details for the item
                activate: function (event, data) {
                    let item = data.node.data.ref;

                    // if this isn't already the currentItem...
                    if (item !== apx.treeDoc1.currentItem) {
                        // setCurrentItem
                        apx.treeDoc1.setCurrentItem({ "identifier": item.identifier });
                        // push history state...
                        apx.pushHistoryState();
                    }

                    // launching comment system depending of the item id.
                    if ('undefined' !== typeof (CommentSystem)) {
                        CommentSystem.init(item);
                    }

                    // hide tree2 and show the item details section; this will call showCurrentItem
                    apx.setRightSideMode("itemDetails");
                },

                // if user doubleclicks on a node, open the node, then simulate clicking the "Edit" button for it
                dblclick: function (event, data) {
                    if (apx.treeDoc1.isDocNode(data.node)) {
                        $(".btn[data-bs-target='#editDocModal']").trigger('click')
                    } else {
                        $(".btn[data-bs-target='#editItemModal']").trigger('click')
                    }

                    // return false to cancel default processing (i.e. opening folders)
                    return false;
                },

                expand: function () {
                    apx.treeDoc1.recordExpandedFolders(1);
                },

                // drag-and-drop functionality - https://github.com/mar10/fancytree/wiki/ExtDnd
                dnd: {
                    dragExpand: function () { return false; },   // don't autoexpand folders when you drag over them; this makes things confusing

                    smartRevert: true,

                    // this function seems to need to be defined for the dnd functionality to work
                    dragStart: function (node, data) {
                        // don't allow dragging when we can't edit
                        if (!apx.enableEdit) {
                            return false;
                        }

                        // and don't allow drag if edit.moveEnabled is false
                        if (true !== apx.edit.moveEnabled) {
                            return false;
                        }

                        // don't allow the document to be dragged
                        if (true === apx.treeDoc1.isDocNode(node)) {
                            return false;
                        }

                        // don't allow the orphan directory to be dragged
                        if ('orphans' === node.key) {
                            return false;
                        }

                        // disable tooltips
                        $('#treeView').on('show.bs.tooltip', function () { return false; });
                    },

                    dragStop: function (node, data) {
                        // re-enable tooltip
                        $('#treeView').off('show.bs.tooltip');
                    },

                    initHelper: function (node, data) {
                        // Helper was just created: modify markup
                        let helper = data.ui.helper;
                        let tree = node.tree;
                        let sourceNodes = data.tree.getSelectedNodes();

                        // Store a list of active + all selected nodes
                        if (!node.isSelected()) {
                            sourceNodes.unshift(node);
                        }
                        helper.data("sourceNodes", sourceNodes);

                        // Mark selected nodes also as drag source (active node is already)
                        $(".fancytree-active,.fancytree-selected", tree.$container).addClass("fancytree-drag-source");

                        // Add a counter badge to helper if dragging more than one node
                        if (sourceNodes.length > 1) {
                            helper.append($("<span class='fancytree-childcounter'/>").text("+" + (sourceNodes.length - 1)));
                        }
                    },

                    dragEnter: function (droppedNode, data) {
                        let draggedNode = data.otherNode;

                        // determine if this is inter- or intra-tree drag
                        let treeDraggedFrom = "tree1";
                        if (droppedNode.tree !== draggedNode.tree) {
                            treeDraggedFrom = "tree2";
                        }

                        // Do not allow dropping in Orphaned Items folder
                        let parent = droppedNode;
                        while (null !== parent) {
                            if ('orphans' === parent.key) {
                                return false;
                            }
                            parent = parent.getParent();
                        }

                        // intra-tree drag
                        if (treeDraggedFrom === "tree1") {
                            // Don't allow dropping *over* a non-folder node (this would make it too easy to accidentally create a child).
                            if (true === droppedNode.folder) {
                                // also don't allow dropping before or after the document -- only "over" allowed in this case
                                if (apx.treeDoc1.isDocNode(droppedNode)) {
                                    return "over";
                                } else {
                                    return true;
                                }
                            } else {
                                return ["before", "after"];     // , "over"
                            }

                            // drag from tree2 to tree1
                        } else {
                            // if we're in associate mode, only allow drags *over*, not between, items
                            if (apx.rightSideMode === "addAssociation") {
                                // and don't allow any drops onto the document
                                if (apx.treeDoc1.isDocNode(droppedNode)) {
                                    return 'over';
                                } else {
                                    return 'over';
                                }
                                // else we're in copy mode; use same thing here as moving within the tree
                            } else {
                                // don't allow dropping before or after the document -- only "over" allowed in this case
                                if (apx.treeDoc1.isDocNode(droppedNode)) {
                                    return "over";
                                } else if (droppedNode.folder === true) {
                                    return true;
                                } else {
                                    return ["before", "after"];
                                }
                            }
                        }
                    },

                    dragDrop: function (droppedNode, data) {
                        // determine if this is inter- or intra-tree drag
                        let treeDraggedFrom = "tree1";
                        if (droppedNode.tree !== data.otherNode.tree) {
                            treeDraggedFrom = "tree2";
                        }

                        let draggedNodes = data.ui.helper.data("sourceNodes");

                        // intra-tree drag - move the item(s) in the tree
                        if (treeDraggedFrom === "tree1") {
                            apx.edit.moveItems(draggedNodes, droppedNode, data.hitMode);

                            // inter-tree drag (from tree2)
                        } else {
                            // if we're in associate mode, show choice for what type of association to add
                            if (apx.rightSideMode === "addAssociation") {
                                // remember dragged and dropped nodes while we make the call to open the form
                                apx.edit.createAssociationNodes = {
                                    "draggedNodes": draggedNodes,
                                    "droppedNode": droppedNode
                                };

                                // then open the modal form to allow the user to choose the association type
                                $('#associateModal').modal('show');

                                // else if we're in copy mode; copy node(s) to new tree
                            } else if (apx.rightSideMode === "copyItem") {
                                // apx.edit.copyItem(draggedNode, droppedNode, data.hitMode);
                                apx.edit.copyItems(draggedNodes, droppedNode, data.hitMode);
                            }
                        }
                    }   // end of dragDrop function
                }
            });
            // end of fancytree widget initialization

            // if we're not showing mainDoc on the left, set a background color to indicate that
            if (apx.treeDoc1 !== apx.mainDoc) {
                $("#tree1Section").addClass("otherDoc");
            } else {
                $("#tree1Section").removeClass("otherDoc");
            }

            // restore checkbox state
            apx.treeDoc1.treeCheckboxRestoreCheckboxes(1);

            // if this document is also showing on the right side, re-render there too
            if (apx.treeDoc1 === apx.treeDoc2 && !empty(apx.treeDoc2.ftRender2)) {
                apx.treeDoc2.ftRender2();
            }
        };
        // end of ftRender1 definition

        ///////////////////////////////////
        // Things we need to do when a new doc is loaded for treeDoc1

        // hide tree1SelectorDiv
        $("#tree1SelectorDiv").hide();

        // render treeDoc1's assocGroup menu
        apx.treeDoc1.renderAssocGroupMenu($("#treeSideLeft").find(".assocGroupSelect"));

        // render the fancytree for treeDoc1
        apx.treeDoc1.ftRender1();

        // initialize tree search bar
        apx.treeDoc1.initializeTreeFilter(1);

        // show instructions and controls properly
        $("#tree1InitialInstructions").hide();

        // if we're showing the mainDoc...
        if (apx.treeDoc1 === apx.mainDoc) {
            $("#tree1SectionThisDocInstructions").show();
            $("#tree1SectionOtherDocInstructions").hide();

            // In the right-side control group, enable the "Copy Items" button
            $("#rightSideCopyItemsBtn").attr("disabled", false);

            // show the buttons (e.g. delete, create new) that aren't operable when viewing a different doc
            $("[data-alt-document-disabled]").show();

            // show the tree1SectionBulkControls div
            $("#tree1SectionBulkControls").show();

            // activate the current (initial) item (which might be the document)
            apx.treeDoc1.activateCurrentItem();

            // we also have to call showCurrentItem, because if the current item is the document it's already active
            apx.treeDoc1.showCurrentItem();

            // else we're showing a different doc...
        } else {
            $("#tree1SectionOtherDocInstructions").show();
            $("#tree1SectionThisDocInstructions").hide();

            // In the right-side control group, disable the "Copy Items" buttons
            $("#rightSideCopyItemsBtn").attr("disabled", true);

            // hide the buttons (e.g. delete, create new) that aren't operable when viewing a different doc
            $("[data-alt-document-disabled]").hide();

            // hide the tree1SectionBulkControls div; you can't use these controls when viewing a different doc
            $("#tree1SectionBulkControls").hide();
        }

        // call setRightSideMode
        apx.setRightSideMode("itemDetails");

        // if this is the mainDoc and it contains no items, show the noItemsInstructions
        if (apx.treeDoc1 == apx.mainDoc && apx.mainDoc.items.length === 0) {
            $("#noItemsInstructions").show();
        } else {
            $("#noItemsInstructions").hide();
        }
    };

    /** Toggle more item details in item details display */
    // @param {*} [arg] - if true or false, set to that value; if "restore", restore last-used value; otherwise toggle
    apx.moreInfoShowing = false;
    apx.toggleMoreInfo = function (arg) {
        if (arg == null) {
            apx.moreInfoShowing = !apx.moreInfoShowing;
        } else if (arg !== "restore") {
            apx.moreInfoShowing = arg;
        }

        if (apx.moreInfoShowing) {
            $(".lsItemDetailsExtras").slideDown(100);
            $(".lsItemDetailsMoreInfoLink a").text("Less Info");
        } else {
            $(".lsItemDetailsExtras").slideUp(100);
            $(".lsItemDetailsMoreInfoLink a").text("More Info");
        }
    };



    ///////////////////////////////////////////////////////////////////////////////
    // RIGHT-SIDE TREE

    apx.rightSideMode = "itemDetails";
    apx.setRightSideMode = function (newMode) {
        if (newMode === "itemDetails") {
            $("#tree2SectionControls").hide();
            $("#tree2Section").hide();
            $("#itemSection").show();
            $(".js-comments-container").show();

            $("#rightSideItemDetailsBtn").addClass("btn-primary").removeClass("btn-default");
            $("#rightSideCopyItemsBtn").removeClass("btn-primary").addClass("btn-default");
            $("#rightSideCreateAssociationsBtn").removeClass("btn-primary").addClass("btn-default");

            // re-show the item details in case anything got updated
            apx.treeDoc1.showCurrentItem();

        } else {
            if (!empty(apx.treeDoc2)) {
                $("#tree2SectionControls").show();
                $("#tree2InitialInstructions").hide();
            } else {
                $("#tree2SectionControls").hide();
                $("#tree2InitialInstructions").show();
            }
            $("#tree2Section").show();
            $("#itemSection").hide();
            $(".js-comments-container").hide();

            if (newMode === "addAssociation") {
                if (!empty(apx.treeDoc2)) {
                    $("#tree2SectionCopyInstructions").hide();
                    $("#tree2SectionRelationshipInstructions").show();
                }

                $("#rightSideItemDetailsBtn").removeClass("btn-primary").addClass("btn-default");
                $("#rightSideCopyItemsBtn").removeClass("btn-primary").addClass("btn-default");
                $("#rightSideCreateAssociationsBtn").addClass("btn-primary").removeClass("btn-default");

            } else {    // newMode == "copyItem"
                if (!empty(apx.treeDoc2)) {
                    $("#tree2SectionCopyInstructions").show();
                    $("#tree2SectionRelationshipInstructions").hide();
                }

                $("#rightSideItemDetailsBtn").removeClass("btn-primary").addClass("btn-default");
                $("#rightSideCopyItemsBtn").addClass("btn-primary").removeClass("btn-default");
                $("#rightSideCreateAssociationsBtn").removeClass("btn-primary").addClass("btn-default");
            }
        }

        apx.rightSideMode = newMode;
    };

    apx.tree2ChangeButtonClicked = function () {
        // clear viewmode_tree2 and hide tree2SectionControls and assocGroupFilter
        $("#viewmode_tree2").html("");
        $("#tree2SectionControls").hide();
        $("#treeSideRight").find(".assocGroupFilter").hide();

        // clear selection in ls_doc_list_lsDoc_right
        $("#ls_doc_list_lsDoc_right").val("");

        // and show tree2SelectorDiv
        $("#tree2SelectorDiv").show();

        // reset instructions
        $("#tree2InitialInstructions").show();
        $("#tree2SectionCopyInstructions").hide();
        $("#tree2SectionRelationshipInstructions").hide();
    };

    // document showing on the right side
    apx.treeDoc2 = null;

    // define function to run when treeDoc2 is loaded
    apx.treeDocLoadCallback2 = function () {
        // define treeDoc2's ftRender function
        apx.treeDoc2.ftRender2 = function () {
            // process the tree, using current association group
            let ftData = apx.treeDoc2.createTree(apx.treeDoc2.currentAssocGroup2, 2);

            let viewmodeTree2 = $('#viewmode_tree2');

            // make sure viewmode_tree2 is cleared and showing
            if (viewmodeTree2.find(".ui-fancytree").length > 0) {
                viewmodeTree2.fancytree("destroy");
            }
            viewmodeTree2.html("").show();

            // then initialize (or re-initialize) fancytree
            apx.treeDoc2.ft2 = viewmodeTree2.fancytree({
                extensions: ['filter', 'dnd'],
                source: ftData,
                quicksearch: true,
                filter: {
                    autoApply: true,  // Re-apply last filter if lazy data is loaded
                    counter: true,  // Show a badge with number of matching child nodes near parent icons
                    fuzzy: false,  // Match single characters in order, e.g. 'fb' will match 'FooBar'
                    hideExpandedCounter: true,  // Hide counter badge, when parent is expanded
                    highlight: true,  // Highlight matches by wrapping inside <mark> tags
                    mode: "hide"  // Grayout unmatched nodes (pass "hide" to remove unmatched node instead)
                },

                renderNode: function (event, data) {
                },

                expand: function () {
                    apx.treeDoc2.recordExpandedFolders(2);
                },

                click: function () {
                    // if we're in copy mode and treeDoc2 is an external doc, stop the user right here; no copying allowed from external docs at this time
                    if (apx.rightSideMode === "copyItem" && apx.treeDoc2.isExternalDoc()) {
                        alert("You cannot currently copy an item from a document on another server.");
                        return false;
                    }
                },

                // drag-and-drop functionality - https://github.com/mar10/fancytree/wiki/ExtDnd
                dnd: {
                    // focusOnClick: true,
                    dragExpand: function () { return false; },   // don't autoexpand folders when you drag over them; this makes things confusing

                    // modify default jQuery draggable options
                    draggable: {
                        // disable auto-scrolling, though I'm not sure this does much good
                        scroll: false,
                        // append the draggable helper item to the body, so that you'll see it when you drag over tree2
                        appendTo: "body"
                    },

                    // define dragStart on tree2 to allow dragging from this tree
                    dragStart: function (node, data) {
                        // when we start dragging, activate the key so it'll be highlighted
                        apx.treeDoc2.getFt(2).activateKey(node.key);

                        // don't allow the document to be dragged
                        if (true === apx.treeDoc2.isDocNode(node)) {
                            return false;
                        }

                        // don't allow the orphan directory to be dragged
                        if ('orphans' === node.key) {
                            return false;
                        }

                        // disable tooltips
                        $('#treeView').on('show.bs.tooltip', function () { return false; });
                    },

                    initHelper: function (node, data) {
                        // Helper was just created: modify markup
                        let helper = data.ui.helper;
                        let tree = node.tree;
                        let sourceNodes = data.tree.getSelectedNodes();

                        // Store a list of active + all selected nodes
                        if (!node.isSelected()) {
                            sourceNodes.unshift(node);
                        }
                        helper.data("sourceNodes", sourceNodes);

                        // Mark selected nodes also as drag source (active node is already)
                        $(".fancytree-active,.fancytree-selected", tree.$container).addClass("fancytree-drag-source");

                        // Add a counter badge to helper if dragging more than one node
                        if (sourceNodes.length > 1) {
                            helper.append($("<span class='fancytree-childcounter'/>").text("+" + (sourceNodes.length - 1)));
                        }
                    },

                    dragStop: function (node, data) {
                        // re-enable tooltip
                        $('#treeView').off('show.bs.tooltip');
                    },

                    // this function needs to be defined for the dnd functionality to work...
                    dragEnter: function (node, data) {
                        // but you can't drag from tree2 to tree2, so return false here to prevent this
                        // the logic for dragging into tree1 is in the other fancytree definer
                        return false;
                    },
                    dragDrop: function (node, data) {
                        // we should never get here, because we only allow drags from tree2 to tree1
                        console.log('tree2 dragDrop (' + apx.rightSideMode + '): ' + draggedItemId + ' to ' + hitMode + ' ' + droppedItemId);
                    }
                }
            });

            // restore checkbox state
            apx.treeDoc2.treeCheckboxRestoreCheckboxes(2);
        };
        // end of ftRender function

        // show instructions properly by re-calling setRightSideMode
        if ($("#rightSideCopyItemsBtn").hasClass("btn-primary")) {
            apx.setRightSideMode("copyItem");
        } else {
            apx.setRightSideMode("addAssociation");
        }

        // and hide tree2SelectorDiv
        $("#tree2SelectorDiv").hide();

        // set the currentAssocGroup
        apx.treeDoc2.setCurrentAssocGroup(null, 2);

        // render the treeDoc2's assocGroup menu
        apx.treeDoc2.renderAssocGroupMenu($("#treeSideRight").find(".assocGroupSelect"), 2);

        // process the tree and render
        apx.treeDoc2.ftRender2();

        // initialize tree search bar
        apx.treeDoc2.initializeTreeFilter(2);
    };
}
