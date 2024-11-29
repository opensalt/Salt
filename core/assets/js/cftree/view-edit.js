import 'inline-attachment/src/inline-attachment.js';
import 'inline-attachment/src/codemirror-4.inline-attachment.js';
import render from './render-md';
import bootbox from 'bootbox';

import { empty } from './utils';

export default function (apx) {
    //////////////////////////////////////////////////////
    // EDIT THE DOCUMENT OR AN ITEM
    apx.edit = {};

    /** Edit the document data */
    apx.edit.prepareDocEditModal = function () {
        let $modal = $('#editDocModal');
        $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        $modal.on('shown.bs.modal', function (e) {
            $modal.data('mode', 'open');
            $modal.find('.modal-footer .btn-save').hide();
            $modal.find('.modal-body').load(
                apx.path.lsdoc_edit.replace('ID', apx.lsDocId),
                null,
                function (responseText, textStatus, jqXHR) {
                    $('#ls_doc_licence').select2entity({
                        dropdownParent: $('#ls_doc_licence').closest('div')
                    });
                    let $docSubjects = $('#ls_doc_subjects');
                    $docSubjects.select2entity({
                        dropdownParent: $docSubjects.closest('div')
                    });

                    if ($modal.find('form[name="ls_doc"]').length) {
                        $modal.find('.modal-footer .btn-save').show();
                    }
                }
            );
        }).on('hide.bs.modal', function (e) {
            $('#ls_doc_subjects').select2('destroy');
            $('#ls_doc_licence').select2('destroy');

            if ('open' === $modal.data('mode')) {
                $.ajax({
                    url: apx.path.lsdoc_unlock.replace('ID', apx.lsDocId),
                    method: 'POST'
                });
            }
            $modal.data('mode', 'close');
        }).on('hidden.bs.modal', function (e) {
            $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        });
        $modal.find('.btn-save').on('click', function (e) {
            $modal.data('mode', 'save');
            apx.spinner.showModal("Updating document");
            $.ajax({
                url: apx.path.lsdoc_edit.replace('ID', apx.lsDocId),
                method: 'POST',
                data: $modal.find('form[name=ls_doc]').serialize()
            }).done(function (data, textStatus, jqXHR) {
                $modal.modal('hide');
                // on successful update, reload the doc (removing any query params)
                let curHref = window.location.href;
                let newHref = curHref.split('?')[0];
                if (window.location.href === newHref) {
                    window.location.reload();
                } else {
                    window.location.href = newHref;
                }
                /*
                   let updatedData = {
                   "title": $("#ls_doc_title").val(),
                   "version": $("#ls_doc_version").val(),
                   "adoptionStatus": $("#ls_doc_adoptionStatus").val(),
                   };
                   */
            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $('#ls_doc_subjects').select2('destroy');
                $('#ls_doc_licence').select2('destroy');
                $modal.find('.modal-body').html(jqXHR.responseText);

                $('#ls_doc_licence').select2entity({
                    dropdownParent: $('#ls_doc_licence').closest('div')
                });
                let $docSubjects = $('#ls_doc_subjects');
                $docSubjects.select2entity({
                    dropdownParent: $docSubjects.closest('div')
                });
            });
        });
    };

    apx.edit.prepareDocDeleteModal = function () {
        let $modal = $('#deleteFrameworkModal');
        let $ack = $modal.find('#deleteFrameworkAcknowledgement');
        let isDelete = /^"?DELETE"?$/;
        let $btnDelete = $modal.find('.btn-delete');
        $modal.on('shown.bs.modal', function (e) {
            $ack.val('');
            $modal.find('.errors').html('');
            $btnDelete.addClass('btn-disabled').attr('disabled', 'disabled');
            $ack.on('change keyup', function (e) {
                if (isDelete.test($ack.val())) {
                    $btnDelete.removeClass('btn-disabled').removeAttr('disabled');
                } else {
                    $btnDelete.addClass('btn-disabled').attr('disabled', 'disabled');
                }
            });
            $btnDelete.on('click', function (e) {
                $.ajax({
                    url: apx.path.lsdoc_delete.replace('ID', apx.mainDoc.doc.id),
                    method: 'POST',
                    data: {
                        _method: 'DELETE',
                        token: $btnDelete.data('token')
                    },
                    dataType: 'json'
                }).done(function (data, textStatus, jqXHR) {
                    window.location.href = apx.path.doc_index;
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    $modal.find('.errors').html('<p class="text-danger">Error: ' + jqXHR.responseJSON.error.message + '</p>');
                });
            });
        }).on('hidden.bs.modal', function (e) {
            $ack.val('');
            $ack.off('change keyup');
            $btnDelete.off('click');
            $btnDelete.addClass('btn-disabled').attr('disabled', 'disabled');
        });
    };

    /** Edit an item */
    apx.edit.prepareItemEditModal = function () {
        let $modal = $('#editItemModal');
        let statementMde, notesMde;

        $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        $modal.on('shown.bs.modal', function (e) {
            $modal.data('mode', 'open');
            $modal.find('.modal-footer .btn-save').hide();
            $modal.find('.modal-body').load(
                apx.path.lsitem_edit.replace('ID', apx.mainDoc.currentItem.id),
                null,
                function (responseText, textStatus, jqXHR) {
                    if ($modal.find('form[name="ls_item"]').length) {
                        $modal.find('.modal-footer .btn-save').show();
                    }
                    $('#ls_item_educationalAlignment').multiselect({
                        optionLabel: function (element) {
                            return $(element).html() + ' - ' + $(element).data('title');
                        },
                        numberDisplayed: 20
                    });
                    $('#ls_item_itemType').select2entity({
                        dropdownParent: $('#ls_item_itemType').closest('div')
                    });

                    statementMde = render.mde($('#ls_item_fullStatement')[0]);
                    notesMde = render.mde($('#ls_item_notes')[0]);
                    const path = '/cfitem/' + apx.mainDoc.doc.id + '/upload_attachment';

                    inlineAttachment.editors.codemirror4.attach(
                        statementMde.codemirror, { uploadUrl: path }
                    );

                    inlineAttachment.editors.codemirror4.attach(
                        notesMde.codemirror, { uploadUrl: path }
                    );
                }
            );
        }).on('hide.bs.modal', function (e) {
            $('#ls_item_itemType').select2('destroy');

            if ('open' === $modal.data('mode')) {
                $.ajax({
                    url: apx.path.lsitem_unlock.replace('ID', apx.mainDoc.currentItem.id),
                    method: 'POST'
                });

                let id = apx.mainDoc.currentItem.id;
                if ("undefined" !== typeof apx.locks && "undefined" !== typeof apx.locks.mine && "undefined" !== typeof apx.locks.mine.items[id] && "number" === typeof apx.locks.mine.items[id].warning) {
                    clearTimeout(apx.locks.mine.items[id].warning);
                    apx.locks.mine.items[id].timeout = 0;
                    if ("undefined" !== typeof apx.locks.mine.warnings[id] && ("function" === typeof apx.locks.mine.warnings[id].close)) {
                        apx.locks.mine.warnings[id].close();
                    }
                }
            }
            $modal.data('mode', 'close');
        }).on('hidden.bs.modal', function (e) {
            $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
            if (null !== statementMde) {
                statementMde.toTextArea();
                statementMde = null;
                notesMde.toTextArea();
                notesMde = null;
            }
        });

        $modal.find('.btn-save').on('click', function (e) {
            $modal.data('mode', 'save');
            apx.spinner.showModal("Updating item");
            statementMde.toTextArea();
            statementMde = null;
            notesMde.toTextArea();
            notesMde = null;
            $.ajax({
                url: apx.path.lsitem_edit.replace('ID', apx.mainDoc.currentItem.id),
                method: 'POST',
                data: $modal.find('form[name=ls_item]').serialize()
            }).done(function (data, textStatus, jqXHR) {
                let id = apx.mainDoc.currentItem.id;
                if ("undefined" !== typeof apx.locks && "undefined" !== typeof apx.locks.mine && "undefined" !== typeof apx.locks.mine.items[id] && "number" === typeof apx.locks.mine.items[id].warning) {
                    clearTimeout(apx.locks.mine.items[id].warning);
                    apx.locks.mine.items[id].timeout = 0;
                    if ("undefined" !== typeof apx.locks.mine.warnings[id] && ("function" === typeof apx.locks.mine.warnings[id].close)) {
                        apx.locks.mine.warnings[id].close();
                    }
                }

                apx.spinner.hideModal();
                $modal.modal('hide');

                // on successful edit, update the item
                let item = apx.mainDoc.currentItem;

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

                // then re-render the tree and re-activate the item
                apx.treeDoc1.ftRender1();
                apx.treeDoc1.activateCurrentItem();
            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $('#ls_item_itemType').select2('destroy');
                $modal.find('.modal-body').html(jqXHR.responseText);

                $('#ls_item_educationalAlignment').multiselect({
                    optionLabel: function (element) {
                        return $(element).html() + ' - ' + $(element).data('title');
                    },
                    numberDisplayed: 20
                });
                $('#ls_item_itemType').select2entity({
                    dropdownParent: $('#ls_item_itemType').closest('div')
                });
                statementMde = render.mde($('#ls_item_fullStatement')[0]);
                notesMde = render.mde($('#ls_item_notes')[0]);
                const path = '/cfitem/' + apx.mainDoc.doc.id + '/upload_attachment';

                inlineAttachment.editors.codemirror4.attach(
                    statementMde.codemirror, { uploadUrl: path }
                );

                inlineAttachment.editors.codemirror4.attach(
                    notesMde.codemirror, { uploadUrl: path }
                );
            });
        });
    };

    /** Add a new child item, to the top level doc or to an item */
    apx.edit.prepareAddNewChildModal = function () {
        function getPath() {
            let path;
            if (apx.mainDoc.currentItem === apx.mainDoc.doc) {
                path = apx.path.lsitem_new.replace('DOC', apx.lsDocId);
            } else {
                path = apx.path.lsitem_new.replace('DOC', apx.lsDocId).replace('PARENT', apx.mainDoc.currentItem.id);
            }

            // if we have an assocGroup other than default selected, add that to the path
            if (apx.mainDoc.currentAssocGroup !== null) {
                path += "/" + apx.mainDoc.currentAssocGroup;
            }

            return path;
        }

        let statementMde,
            notesMde;
        let $modal = $('#addNewChildModal');
        $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        $modal.on('shown.bs.modal', function (e) {
            $modal.find('.modal-body').load(
                getPath(),
                null,
                function (responseText, textStatus, jqXHR) {
                    $('#ls_item_educationalAlignment').multiselect({
                        optionLabel: function (element) {
                            return $(element).html() + ' - ' + $(element).data('title');
                        },
                        numberDisplayed: 20
                    });
                    $('#ls_item_itemType').select2entity({
                        dropdownParent: $('#ls_item_itemType').closest('div')
                    });
                    statementMde = render.mde($('#ls_item_fullStatement')[0]);
                    notesMde = render.mde($('#ls_item_notes')[0]);
                    const path = '/cfitem/' + apx.mainDoc.doc.id + '/upload_attachment';

                    inlineAttachment.editors.codemirror4.attach(
                        statementMde.codemirror, { uploadUrl: path }
                    );

                    inlineAttachment.editors.codemirror4.attach(
                        notesMde.codemirror, { uploadUrl: path }
                    );
                }
            );
        }).on('hide.bs.modal', function (e) {
            $('#ls_item_itemType').select2('destroy');
        }).on('hidden.bs.modal', function (e) {
            $modal.find('.modal-body').html(apx.spinner.html("Loading Form"));
            if (null !== statementMde) {
                statementMde.toTextArea();
                statementMde = null;
                notesMde.toTextArea();
                notesMde = null;
            }
        });
        $modal.find('.btn-save').on('click', function (e) {
            apx.spinner.showModal("Creating item");
            if (statementMde) {
                statementMde.toTextArea();
            }
            statementMde = null;
            if (notesMde) {
                notesMde.toTextArea();
            }
            notesMde = null;
            $.ajax({
                url: getPath(),
                method: 'POST',
                data: $modal.find('form[name=ls_item]').serialize()
            }).done(function (data, textStatus, jqXHR) {
                apx.spinner.hideModal();
                $modal.modal('hide');

                apx.mainDoc.addNewItemData(data);

                // make sure the noItemsInstructions div is hidden
                $("#noItemsInstructions").hide();
            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $('#ls_item_itemType').select2('destroy');
                $modal.find('.modal-body').html(jqXHR.responseText);

                $('#ls_item_educationalAlignment').multiselect({
                    optionLabel: function (element) {
                        return $(element).html() + ' - ' + $(element).data('title');
                    },
                    numberDisplayed: 20
                });
                $('#ls_item_itemType').select2entity({
                    dropdownParent: $('#ls_item_itemType').closest('div')
                });
                statementMde = render.mde($('#ls_item_fullStatement')[0]);
                notesMde = render.mde($('#ls_item_notes')[0]);
                const path = '/cfitem/' + apx.mainDoc.doc.id + '/upload_attachment';

                inlineAttachment.editors.codemirror4.attach(
                    statementMde.codemirror, { uploadUrl: path }
                );

                inlineAttachment.editors.codemirror4.attach(
                    notesMde.codemirror, { uploadUrl: path }
                );
            });
        });
    };

    /** Delete one or more items */
    apx.edit.deleteItems = function (items) {
        let completed = 0;

        function itemDeleted() {
            // if we're done hide the spinner and re-render the tree
            ++completed;
            if (completed === items.length) {
                apx.spinner.hideModal();
                apx.treeDoc1.setCurrentItem({
                    "item": apx.mainDoc.doc
                });
                apx.treeDoc1.ftRender1();
                apx.treeDoc1.showCurrentItem();
                apx.pushHistoryState();
            }
        }

        function deleteItemsInternal(items) {
            // show "Deleting" spinner
            apx.spinner.showModal("Deleting");

            let lsItems = null;
            for (let i = 0; i < items.length; ++i) {
                let item = items[i];

                if (item !== null) {
                    // Check to see if the item exists in a different assocGroup than the assocGroup currently selected
                    let itemExistsInAnotherGroup = false;
                    let assocIdToDelete = null;

                    // go through all the assocs for this item
                    for (let j = 0; j < item.assocs.length; ++j) {
                        let a = item.assocs[j];
                        // when we find the ischildof...
                        if (a.type === "isChildOf" && a.inverse !== true) {
                            // then if it matches the currentAssocGroup...
                            if (a.groupId == apx.mainDoc.currentAssocGroup) {
                                // (Note that we want != here for assocGroup comparison so that null matches undefined)
                                // Record the association id, in case we need to delete this association
                                if (empty(lsItems)) {
                                    lsItems = {};
                                }
                                lsItems[item.id] = {
                                    "originalKey": "x", // not needed; this is legacy from old code
                                    "deleteChildOf": {
                                        "assocId": a.id
                                    }
                                };
                                assocIdToDelete = a.id;

                                // else this is an isChildOf association for a different group
                            } else {
                                itemExistsInAnotherGroup = true;
                            }
                        }
                    }

                    // if item exists in another group, use update_items service to delete the isChildOf association only
                    if (itemExistsInAnotherGroup) {
                        if (empty(lsItems)) {
                            console.log("Possible delete error: lsItems is empty");
                            itemDeleted();

                        } else {
                            // delete the assoc first, then make the ajax call
                            apx.mainDoc.deleteAssociation(assocIdToDelete);

                            $.ajax({
                                url: apx.path.doctree_update_items.replace('ID', apx.lsDocId),
                                method: 'POST',
                                data: {
                                    "lsItems": lsItems
                                }
                            }).done(function (data, textStatus, jqXHR) {
                                itemDeleted();

                            }).fail(function (jqXHR, textStatus, errorThrown) {
                                alert("An error occurred.");
                                // console.log(jqXHR.responseText);
                            });
                        }

                        // else use delete service to delete item
                    } else {
                        // delete all assocs for the item
                        for (let j = item.assocs.length - 1; j >= 0; --j) {
                            let a = item.assocs[j];
                            apx.mainDoc.deleteAssociation(a.id);
                        }

                        // find the item in mainDoc.items
                        for (let j = 0; j < apx.mainDoc.items.length; ++j) {
                            if (apx.mainDoc.items[j] === item) {
                                // delete it from itemHash and itemIdHash, and splice it from the items array
                                delete apx.mainDoc.itemHash[item.identifier];
                                delete apx.mainDoc.itemIdHash[item.id];
                                apx.mainDoc.items.splice(j, 1);
                                break;
                            }
                        }

                        $.ajax({
                            // for now at least, we always send "1" in for the "CHILDREN" parameter
                            url: apx.path.lsitem_tree_delete.replace('ID', item.id).replace('CHILDREN', 1),
                            method: 'POST'
                        }).done(function (data, textStatus, jqXHR) {
                            itemDeleted();

                        }).fail(function (jqXHR, textStatus, errorThrown) {
                            alert("An error occurred.");
                            // console.log(jqXHR.responseText);
                        });
                    }
                } else {
                    ++completed;
                }
            }
        }

        // if items isn't an array, use current item
        if (!Array.isArray(items)) {
            items = [apx.mainDoc.currentItem];
        }

        // make user confirm
        let modalId;
        if (items.length === 1) {
            if (items[0].ftNodeData.children.length > 0) {
                modalId = '#deleteItemAndChildrenModal';
            } else {
                modalId = '#deleteOneItemModal';
            }
        } else {
            // fill count of deleted items in to deleteMultipleItemsModalCount
            $("#deleteMultipleItemsModalCount").text(items.length);
            modalId = '#deleteMultipleItemsModal';
        }

        (new bootstrap.Modal(modalId)).show();
        $(modalId).one('click', '.btn-delete', function () {
            deleteItemsInternal(items);
            $(this).closest('.modal').modal('hide');
        });
    };

    /** Add an exemplar for an item */
    apx.edit.prepareExemplarModal = function () {
        let $exemplarModal = $('#addExemplarModal');
        $exemplarModal.on('show.bs.modal', function (e) {
            let title = apx.mainDoc.getItemTitle(apx.mainDoc.currentItem);
            $("#addExemplarOriginTitle").html(title);
            $exemplarModal.find('.modal-body .errors').removeClass('alert').removeClass('alert-danger').html('');
        });
        $exemplarModal.find('.btn-save').on('click', function (e) {
            let ajaxData = {
                exemplarUrl: $("#addExemplarFormUrl").val(),
                exemplarDescription: $("#addExemplarFormDescription").val(),
                associationType: "Exemplar",
                annotation: $('#addExemplarFormAnnotation').val()
            };

            if (ajaxData.exemplarUrl === "") {
                $exemplarModal.find('.modal-body .errors').addClass('alert').addClass('alert-danger').html("You must enter a URL to create an exemplar.");
                return;
            }

            if (ajaxData.exemplarUrl.length > 300) {
                $exemplarModal.find('.modal-body .errors').addClass('alert').addClass('alert-danger').html("The URL must be 300 characters or less.");
                return;
            }

            apx.spinner.showModal("Saving exemplar");

            // construct path for this association
            let path = apx.path.lsassociation_tree_new_exemplar;
            path = path.replace('ORIGIN_ID', apx.mainDoc.currentItem.id);

            $.ajax({
                url: path,
                method: 'POST',
                data: ajaxData
            }).done(function (data, textStatus, jqXHR) {
                apx.spinner.hideModal();
                $exemplarModal.modal('hide');

                // add the association
                apx.mainDoc.addAssociation({
                    "id": data.id,
                    "identifier": data.identifier,
                    "originItem": apx.mainDoc.currentItem,
                    "type": "exemplar",
                    "dest": {
                        "doc": "-",
                        "uri": ajaxData.exemplarUrl
                    }
                    // Note that exemplars are currently not added to association groups
                    //, "groupId": apx.mainDoc.currentAssocGroup
                });

                // clear form fields
                $("#addExemplarFormUrl").val("");
                $("#addExemplarFormDescription").val("");
                $exemplarModal.find('.modal-body .errors').removeClass('alert').removeClass('alert-danger').html('');

                // re-show current item
                apx.mainDoc.showCurrentItem();

            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $exemplarModal.find('.modal-body .errors').addClass('alert').addClass('alert-danger').html(jqXHR.responseJSON.error.message);
            });
        });
    };

    /** Add an association */
    apx.edit.prepareAssociateModal = function () {
        // add an option for each association type to the associationFormType select
        let lastType = '';
        let $associationFormType = $("#associationFormType");
        let $associationFormTypeForward = $associationFormType.clone();
        for (let i = 0; i < apx.assocTypes.length; ++i) {
            let curType = apx.assocTypes[i];
            if (null === curType) {
                continue;
            }

            if (0 === curType.lastIndexOf('-', 0)) {
                if ('' === lastType) {
                    continue;
                }

                $associationFormTypeForward.append('<option value="' + lastType + '|' + curType.substring(1) + '">&nbsp;&nbsp;&nbsp;' + curType.substring(1) + '</option>');

                continue;
            }

            lastType = curType;

            if (curType === "Exemplar" || curType === "Is Child Of") {
                lastType = '';

                continue;
            }

            $associationFormTypeForward.append('<option value="' + curType + '">' + curType + '</option>');
        }

        let lastForwardType = '';
        let $associationFormTypeReverse = $associationFormType.clone();
        for (let i = 0; i < apx.inverseAssocTypes.length; ++i) {
            let curType = apx.inverseAssocTypes[i];
            if (null === curType) {
                continue;
            }

            if (0 === curType.lastIndexOf('-', 0)) {
                if ('' === lastType) {
                    continue;
                }

                $associationFormTypeReverse.append('<option value="' + lastForwardType + '|' + curType.substring(1) + '">&nbsp;&nbsp;&nbsp;' + curType.substring(1) + '</option>');

                continue;
            }

            lastType = curType;
            lastForwardType = apx.assocTypes[i];

            if (lastForwardType === "Exemplar" || lastForwardType === "Is Child Of") {
                lastType = '';
                lastForwardType = '';

                continue;
            }


            $associationFormTypeReverse.append('<option value="' + lastForwardType + '">' + curType + '</option>');
        }

        $associationFormType.html($associationFormTypeForward.html());

        // prepare switch direction button
        $("#lsAssociationSwitchDirection").on('click', function () {
            let $dir = $("#lsAssociationDirection");
            let selected = $associationFormType.val();

            $dir.toggleClass("lsAssociationDirectionSwitched");
            if ($dir.hasClass("lsAssociationDirectionSwitched")) {
                $associationFormType.html($associationFormTypeReverse.html());
            } else {
                $associationFormType.html($associationFormTypeForward.html());
            }

            $associationFormType.val(selected);
        });

        let $associateModal = $('#associateModal');
        $associateModal.on('shown.bs.modal', function (e) {
            let originItem = apx.edit.createAssociationNodes.droppedNode.data.ref;
            let destItem = apx.edit.createAssociationNodes.draggedNodes[0].data.ref;

            // show the origin and destination statements
            let destination = apx.mainDoc.getItemTitle(destItem);
            if (apx.edit.createAssociationNodes.draggedNodes.length > 1) {
                destination += " <b>+" + (apx.edit.createAssociationNodes.draggedNodes.length - 1) + " additional item(s)</b>";
            }
            let origin = apx.mainDoc.getItemTitle(originItem);
            $("#lsAssociationDestinationDisplay").html(destination);
            $("#lsAssociationOriginDisplay").html(origin);
            $('#associationFormAnnotation').val('');

            // add association group menu if we have one and there's more than one item (the first item is always "default") in the menu
            let agMenu = $("#treeSideLeft").find(".assocGroupSelect");
            if (agMenu.find("option").length > 1) {
                agMenu = agMenu.clone();
                agMenu.attr("id", "associationFormGroup");
                $("#associationFormGroupHolder").html("").append(agMenu);
                $("#associationFormGroupHolderOuter").show();

                // if an assocGroup other than default is selected, select that group in the menu
                if (apx.mainDoc.currentAssocGroup != null) {
                    $("#associationFormGroup").val(apx.mainDoc.currentAssocGroup);
                }
            }
        });

        // when save button is clicked, create the association(s)
        $associateModal.find('.btn-save').on('click', function (e) {
            // let ajaxData = $associateModal.find('form[name=ls_association_tree]').serialize();

            apx.spinner.showModal("Saving association(s)");

            // go through all the draggedNodes
            let completed = 0;
            for (let i = 0; i < apx.edit.createAssociationNodes.draggedNodes.length; ++i) {
                let ajaxData = {
                    "type": $("#associationFormType").val(),
                    "annotation": $("#associationFormAnnotation").val()
                };

                // the "origin" refers to the node that's 'receiving' the association -- so this is the droppedNode
                // the "destination" refers to the node that's being associated with the origin node -- so this is the draggedNode
                let originItem = apx.edit.createAssociationNodes.droppedNode.data.ref;
                let destItem = apx.edit.createAssociationNodes.draggedNodes[i].data.ref;

                // ... that is, unless the user has clicked to switch directions, in which case we switch the items
                if ($("#lsAssociationDirection").hasClass("lsAssociationDirectionSwitched")) {
                    let temp = originItem;
                    originItem = destItem;
                    destItem = temp;
                }

                let ajaxItemData = function (item) {
                    if (item.doc.isExternalDoc()) {
                        return {
                            "identifier": item.identifier,
                            "uri": item.uri,
                            "externalDoc": item.doc.doc.identifier
                        };
                    }

                    if (!empty(item.id)) {
                        return {
                            "id": item.id
                        };
                    }

                    return {
                        "identifier": item.identifier,
                        "uri": item.uri
                    };
                };

                ajaxData.origin = ajaxItemData(originItem);
                ajaxData.dest = ajaxItemData(destItem);

                // if an assocGroup is selected via associationFormGroup and isn't default, add it
                let agMenu = $("#associationFormGroup");
                if (agMenu.length > 0 && agMenu.val() !== "default") {
                    ajaxData.assocGroup = agMenu.val();
                }

                $.ajax({
                    url: apx.path.lsassociation_tree_new,
                    method: 'POST',
                    data: ajaxData,
                    context: {
                        "origin": originItem,
                        "dest": destItem,
                        "type": ajaxData.type,
                        "annotation": ajaxData.annotation,
                        "assocGroup": ajaxData.assocGroup
                    }
                }).done(function (data, textStatus, jqXHR) {
                    // "this" will refer to context

                    // increment completed counter
                    ++completed;

                    // add new assoc object and its inverse
                    let types = this.type.split('|');
                    let type = types.shift();
                    this.type = type;
                    let subtype = types.join(' ');
                    type = apx.mainDoc.getAssociationTypeCondensed(this);
                    let atts = {
                        "id": data.id,
                        "identifier": data.identifier,
                        "origin": {
                            "doc": this.origin.doc.doc.identifier,
                            "item": this.origin.identifier,
                            "uri": this.origin.uri
                        },
                        "type": type,
                        "subtype": subtype,
                        "annotation": this.annotation,
                        "dest": {
                            "doc": this.dest.doc.doc.identifier,
                            "item": this.dest.identifier,
                            "uri": this.dest.uri
                        },
                        "groupId": this.assocGroup
                    };
                    let a = apx.mainDoc.addAssociation(atts);
                    apx.mainDoc.addInverseAssociation(a);

                    // if the origin item is currently showing in treeDoc1 and this wasn't a childOf assoc, show the association marker
                    if (type !== "isChildOf") {
                        let oi = apx.treeDoc1.itemHash[this.origin.identifier];
                        if (!empty(oi) && !empty(oi.identifier)) {
                            $(apx.treeDoc1.getFtNode(oi, 1).li).find(".treeHasAssociation").show();
                        }
                    }

                    // note that the assocView is no longer fresh, so that if the user clicks to view the association view it will refresh.
                    if (apx.viewMode.assocViewStatus !== "not_written") {
                        apx.viewMode.assocViewStatus = "stale";
                    }

                    // if all are completed, finish up
                    if (completed === apx.edit.createAssociationNodes.draggedNodes.length) {
                        apx.spinner.hideModal();
                        $associateModal.modal('hide');

                        // clear createAssociationNodes
                        apx.edit.createAssociationNodes = null;
                    }

                    // we don't need to update the item details here, because that will happen if/when the user clicks the toggle button to show the item details

                }).fail(function (jqXHR, textStatus, errorThrown) {
                    apx.spinner.hideModal();
                    alert("An error occurred when attempting to save the association.");
                });
            }

        });
    };

    /** edit an association */
    apx.edit.prepareEditAssociationModal = function () {
        // add an option for each association type to the associationFormType select
        let condenseType = function (type) {
            // apx.mainDoc.condenseType is not yet available
            return type[0].toLowerCase() + type.substr(1).replace(/ /g, "");    // convert type to camel case
        };
        let mappedTypes = {};
        let lastType = '';
        let $editAssociationFormType = $("#editAssociationFormType");
        let $editAssociationFormTypeForward = $editAssociationFormType.clone();
        for (let i = 0; i < apx.assocTypes.length; ++i) {
            let curType = apx.assocTypes[i];
            if (null === curType) {
                continue;
            }

            if (0 === curType.lastIndexOf('-', 0)) {
                if ('' === lastType) {
                    continue;
                }

                $editAssociationFormTypeForward.append('<option value="' + lastType + '|' + curType.substring(1) + '">&nbsp;&nbsp;&nbsp;' + curType.substring(1) + '</option>');

                continue;
            }

            lastType = curType;
            let condensed = condenseType(lastType);
            mappedTypes[condensed] = lastType;

            if (curType === "Exemplar" || curType === "Is Child Of") {
                lastType = '';

                continue;
            }

            $editAssociationFormTypeForward.append('<option value="' + curType + '">' + curType + '</option>');
        }

        let lastForwardType = '';
        let $editAssociationFormTypeReverse = $editAssociationFormType.clone();
        for (let i = 0; i < apx.inverseAssocTypes.length; ++i) {
            let curType = apx.inverseAssocTypes[i];
            if (null === curType) {
                continue;
            }

            if (0 === curType.lastIndexOf('-', 0)) {
                if ('' === lastType) {
                    continue;
                }

                $editAssociationFormTypeReverse.append('<option value="' + lastForwardType + '|' + curType.substring(1) + '">&nbsp;&nbsp;&nbsp;' + curType.substring(1) + '</option>');

                continue;
            }

            lastType = curType;
            lastForwardType = apx.assocTypes[i];

            if (lastForwardType === "Exemplar" || lastForwardType === "Is Child Of") {
                lastType = '';
                lastForwardType = '';

                continue;
            }


            $editAssociationFormTypeReverse.append('<option value="' + lastForwardType + '">' + curType + '</option>');
        }

        $editAssociationFormType.html($editAssociationFormTypeForward.html());

        // prepare switch direction button
        $("#editLsAssociationSwitchDirection").on('click', function () {
            let $dir = $("#editLsAssociationDirection");
            let selected = $editAssociationFormType.val();

            $dir.toggleClass("lsAssociationDirectionSwitched");
            if ($dir.hasClass("lsAssociationDirectionSwitched")) {
                $editAssociationFormType.html($editAssociationFormTypeReverse.html());
            } else {
                $editAssociationFormType.html($editAssociationFormTypeForward.html());
            }

            $editAssociationFormType.val(selected);
        });

        let $editAssociationModal = $('#editAssociationModal');
        $editAssociationModal.on('shown.bs.modal', function (e) {
            let oldAssocLink = $(e.relatedTarget);
            let oldIdentifier = oldAssocLink.data('association-identifier');
            let oldId = oldAssocLink.data('association-id');
            let oldAssoc = apx.mainDoc.assocHash[oldIdentifier];

            let originItem = apx.allItemsHash[oldAssoc.origin.item];
            let destItem = apx.allItemsHash[oldAssoc.dest.item];

            $editAssociationModal.data('association-identifier', oldIdentifier);
            $editAssociationModal.data('association-id', oldId);

            /*
            let originItem = apx.edit.createAssociationNodes.droppedNode.data.ref;
            let destItem = apx.edit.createAssociationNodes.draggedNodes[0].data.ref;
            */

            // show the origin and destination statements
            let destination = apx.mainDoc.getItemTitle(destItem);
            let origin = apx.mainDoc.getItemTitle(originItem);
            $("#editLsAssociationDestinationDisplay").html(destination);
            $("#editLsAssociationDestinationDisplay").data('identifier', destItem.identifier);
            $("#editLsAssociationOriginDisplay").html(origin);
            $("#editLsAssociationOriginDisplay").data('identifier', originItem.identifier);
            $('#editAssociationFormAnnotation').val(oldAssoc.annotation || '');

            let $dir = $("#editLsAssociationDirection");
            if ($dir.hasClass("lsAssociationDirectionSwitched")) {
                $dir.toggleClass("lsAssociationDirectionSwitched");
            }
            if (oldAssoc.inverse || false) {
                $dir.toggleClass("lsAssociationDirectionSwitched");
                $editAssociationFormType.html($editAssociationFormTypeReverse.html());
            } else {
                $editAssociationFormType.html($editAssociationFormTypeForward.html());
            }
            // TODO: Type/subtype
            let type = mappedTypes[oldAssoc.type];
            if ('' !== (oldAssoc.subtype || '')) {
                type = type + '|' + oldAssoc.subtype;
            }
            $editAssociationFormType.val(type);

            // add association group menu if we have one and there's more than one item (the first item is always "default") in the menu
            let agMenu = $("#treeSideLeft").find(".assocGroupSelect");
            if (agMenu.find("option").length > 1) {
                agMenu = agMenu.clone();
                agMenu.attr("id", "editAssociationFormGroup");
                $("#editAssociationFormGroupHolder").html("").append(agMenu);
                $("#editAssociationFormGroupHolderOuter").show();

                // if an assocGroup other than default is selected, select that group in the menu
                if (null !== (oldAssoc.groupId || null)) {
                    $("#editAssociationFormGroup").val(oldAssoc.groupId);
                }
            }
        });

        // when save button is clicked, create the association(s)
        $editAssociationModal.find('.btn-save').on('click', function (e) {
            // TODO: EDIT ASSOC - Needs to be updated
            // let ajaxData = $editAssociationModal.find('form[name=ls_association_tree]').serialize();

            // get the assocId from the modal
            let oldAssocId = $editAssociationModal.data('association-id');

            apx.spinner.showModal('Updating association');

            // go through all the draggedNodes
            let completed = 0;
            let ajaxData = {
                "type": $("#editAssociationFormType").val(),
                "annotation": $("#editAssociationFormAnnotation").val()
            };

            // the "origin" refers to the node that's 'receiving' the association -- so this is the droppedNode
            // the "destination" refers to the node that's being associated with the origin node -- so this is the draggedNode
            let originIdentifier = $("#editLsAssociationOriginDisplay").data('identifier');
            let destIdentifier = $("#editLsAssociationDestinationDisplay").data('identifier');
            let originItem = apx.allItemsHash[originIdentifier];
            let destItem = apx.allItemsHash[destIdentifier];

            // ... that is, unless the user has clicked to switch directions, in which case we switch the items
            if ($("#lsAssociationDirection").hasClass("lsAssociationDirectionSwitched")) {
                let temp = originItem;
                originItem = destItem;
                destItem = temp;
            }

            let ajaxItemData = function (item) {
                if (item.doc.isExternalDoc()) {
                    return {
                        "identifier": item.identifier,
                        "uri": item.uri,
                        "externalDoc": item.doc.doc.identifier
                    };
                }

                if (!empty(item.id)) {
                    return {
                        "id": item.id
                    };
                }

                return {
                    "identifier": item.identifier,
                    "uri": item.uri
                };
            };

            ajaxData.origin = ajaxItemData(originItem);
            ajaxData.dest = ajaxItemData(destItem);

            // if an assocGroup is selected via associationFormGroup and isn't default, add it
            let agMenu = $("#associationFormGroup");
            if (agMenu.length > 0 && agMenu.val() !== "default") {
                ajaxData.assocGroup = agMenu.val();
            }

            $.ajax({
                url: apx.path.lsassociation_tree_new,
                method: 'POST',
                data: ajaxData,
                context: {
                    "origin": originItem,
                    "dest": destItem,
                    "type": ajaxData.type,
                    "annotation": ajaxData.annotation,
                    "assocGroup": ajaxData.assocGroup
                }
            }).done(function (data, textStatus, jqXHR) {
                // "this" will refer to context

                // increment completed counter
                ++completed;

                // add new assoc object and its inverse
                let types = this.type.split('|');
                let type = types.shift();
                this.type = type;
                let subtype = types.join(' ');
                type = apx.mainDoc.getAssociationTypeCondensed(this);
                let atts = {
                    "id": data.id,
                    "identifier": data.identifier,
                    "origin": {
                        "doc": this.origin.doc.doc.identifier,
                        "item": this.origin.identifier,
                        "uri": this.origin.uri
                    },
                    "type": type,
                    "subtype": subtype,
                    "annotation": this.annotation,
                    "dest": {
                        "doc": this.dest.doc.doc.identifier,
                        "item": this.dest.identifier,
                        "uri": this.dest.uri
                    },
                    "groupId": this.assocGroup
                };
                let a = apx.mainDoc.addAssociation(atts);
                apx.mainDoc.addInverseAssociation(a);

                // if the origin item is currently showing in treeDoc1 and this wasn't a childOf assoc, show the association marker
                if (type !== "isChildOf") {
                    let oi = apx.treeDoc1.itemHash[this.origin.identifier];
                    if (!empty(oi) && !empty(oi.identifier)) {
                        $(apx.treeDoc1.getFtNode(oi, 1).li).find(".treeHasAssociation").show();
                    }
                }

                // note that the assocView is no longer fresh, so that if the user clicks to view the association view it will refresh.
                if (apx.viewMode.assocViewStatus !== "not_written") {
                    apx.viewMode.assocViewStatus = "stale";
                }

                // we don't need to update the item details here, because that will happen if/when the user clicks the toggle button to show the item details

                // call edit.deleteAssociation; on callback, re-show the current item
                apx.edit.doDeleteAssociation(oldAssocId, function () {
                    $editAssociationModal.modal('hide');
                    apx.treeDoc1.showCurrentItem();
                    apx.spinner.hideModal();
                });
            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                alert("An error occurred when attempting to save the association.");
            });
        });
    };

    apx.edit.doDeleteAssociation = function (assocId, callbackFn) {
        $.ajax({
            url: apx.path.lsassociation_remove.replace('ID', assocId),
            method: 'POST'
        }).done(function (data, textStatus, jqXHR) {
            apx.edit.performDeleteAssociation(assocId, callbackFn);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            if (callbackFn != null) {
                callbackFn();
            }
            alert("An error occurred.");
        });
    };

    apx.edit.deleteAssociation = function (assocId, callbackFn) {
        bootbox.confirm({
            title: "Remove association",
            message: "Are you sure you want to remove this association?<br/><strong>This cannot be undone.</strong>",
            buttons: {
                confirm: {
                    label: "Proceed",
                    className: "btn-danger"
                },
                cancel: {
                    label: "Cancel",
                    className: "btn-default"
                }
            },
            callback: function (result) {
                if (result) {
                    apx.edit.deleteAssociationConfirmed(assocId, callbackFn);
                }
            }
        });
    };

    apx.edit.deleteAssociationConfirmed = function (assocId, callbackFn) {
        apx.spinner.showModal("Removing association");
        let callback = function () {
            apx.spinner.hideModal();
            if (callbackFn != null) {
                callbackFn();
            }
        };

        apx.edit.doDeleteAssociation(assocId, callback);
    };

    apx.edit.performDeleteAssociation = function (assocId, callbackFn) {
        if ("undefined" === typeof apx.mainDoc.assocIdHash[assocId]) {
            // call callbackFn if specified
            if (callbackFn != null) {
                callbackFn();
            }

            return;
        }

        let identifier = apx.mainDoc.assocIdHash[assocId].origin.item;

        // after deletion, delete the association from the data structure
        apx.mainDoc.deleteAssociation(assocId);

        // if the origin item is currently showing in treeDoc1, hide the association marker if necessary
        let oi = apx.mainDoc.itemHash[identifier];
        if (!empty(oi)) {
            let showAssociationIcon = false;
            for (let i = 0; i < oi.assocs.length; ++i) {
                let a = oi.assocs[i];
                if (a.type !== "isChildOf") {
                    showAssociationIcon = true;
                    break;
                }
            }
            let $jq = $(apx.treeDoc1.getFtNode(apx.treeDoc1.itemHash[identifier], 1).li).find(".treeHasAssociation").first();
            if (showAssociationIcon) {
                $jq.show();
            } else {
                $jq.hide();
            }
        }

        // note that the assocView is no longer fresh, so that if the user clicks to view the association view it will refresh.
        if (apx.viewMode.assocViewStatus !== "not_written") {
            apx.viewMode.assocViewStatus = "stale";
        }

        if ('tree' === apx.viewMode.currentView) {
            apx.treeDoc1.ftRender1();
            apx.treeDoc1.activateCurrentItem();
            apx.mainDoc.showCurrentItem();
        }

        if ('assoc' === apx.viewMode.currentView) {
            apx.viewMode.showAssocView('refresh');
        }

        // then call callbackFn if specified
        if (callbackFn != null) {
            callbackFn();
        }
    };


    apx.edit.copyItems = function (draggedNodes, droppedNode, hitMode) {
        for (let j = 0; j < draggedNodes.length; ++j) {
            draggedNodes[j].copyTo(droppedNode, hitMode, function (n) {
                // temporarily add "copy" to the start of the key
                n.key = "copy-" + n.key;
            });
        }

        // now, after a few milliseconds to let the copyTo(s) complete...
        setTimeout(function () {
            // make sure droppedNode is expanded if hitMode is "over"
            if (hitMode === "over") {
                droppedNode.setExpanded(true);
                droppedNode.render();
            }

            // construct ajax call to insert the new item(s) and reorder their siblings
            let lsItems = {};
            // get siblings of the copy of the first dragged item (all the dragged items will be included in this siblings array)
            let siblings = apx.mainDoc.getFt(1).getNodeByKey("copy-" + draggedNodes[0].key).parent.children;
            for (let i = 0; i < siblings.length; ++i) {
                // get the key for this node
                let key = siblings[i].key;

                // start creating the object for the lsItems hash
                let o = {
                    "originalKey": key
                };

                // if this is a new node...
                if (key.indexOf("copy-") === 0) {
                    // get the copied item record
                    let copiedItem = siblings[i].data.ref;

                    // if we have an assocGroup other than default selected, add that
                    if (apx.mainDoc.currentAssocGroup != null) {
                        o.assocGroup = apx.mainDoc.currentAssocGroup;
                    }

                    // if we're copying from the same document...
                    if (apx.mainDoc.doc.id == apx.treeDoc2.doc.id) {
                        // If the *same* assocGroup is chosen on both sides, always create a new instance of the item
                        if (apx.mainDoc.currentAssocGroup == apx.treeDoc2.currentAssocGroup2) {
                            // set copyFromId flag so that updateItemAction will copy the item
                            o.copyFromId = copiedItem.id;
                            o.addCopyToTitle = "true";

                            // else *different* assocGroups are chosen on both sides, so:
                        } else {
                            // If the item already has an isChildOf association for the left-side assocGroup, create a new instance of the item
                            let assocs = apx.treeDoc2.getAssocsForItem(copiedItem, "isChildOf", apx.mainDoc.currentAssocGroup);
                            if (assocs.length > 0) {
                                // set copyFromId flag so that updateItemAction will copy the item
                                o.copyFromId = copiedItem.id;
                                o.addCopyToTitle = "true";

                                // Else the item does not have an isChildOf association for this assocGroup,
                                // so create a new isChildOf relationship for the assocGroup (as directed below), but do *not* create a new instance the item.
                            } else {
                                console.log("item doesn't exist");
                                // in this case we want to use copiedItem.id as the key for the object in the lsItems hash
                                key = copiedItem.id;

                                // TODO: in this case, it doesn't "copy" children of a "copied" folder...
                            }
                        }

                        // else if different documents, but the other document is on this server...
                    } else if (!copiedItem.doc.isExternalDoc()) {
                        // set copyFromId flag so that updateItemAction will copy the item
                        o.copyFromId = copiedItem.id;

                        // else different documents, and the treeDoc2 is on a different server...
                    } else {
                        // TODO: deal with copies from an external document??? In this case we would need to send in the full item, and we'd have to take care of copying children here
                        alert("You cannot currently copy an item from a document on another server.");
                        return;
                    }

                    // create a new childOf relationship regardless of whether or not we're actually creating a copy
                    o.newChildOf = {
                        "sequenceNumber": (i + 1)
                    };

                    // set parentId and parentType
                    // if parent is the document...
                    if (apx.mainDoc.isDocNode(siblings[i].parent)) {
                        // note the docId, and the fact that it's a document
                        o.newChildOf.parentId = apx.mainDoc.doc.id;
                        o.newChildOf.parentType = "doc";

                        // otherwise the parent is an item
                    } else {
                        o.newChildOf.parentId = siblings[i].parent.data.ref.id;
                        o.newChildOf.parentType = "item";
                    }

                    // else it's a sibling of the new item, so just update the sequenceNumber
                } else {
                    // here we want the key to be the item's lsItemId
                    key = siblings[i].data.ref.id;

                    // skip the item if it doesn't have an id (e.g. "orphaned items")
                    if (empty(key)) {
                        continue;
                    }

                    o.updateChildOf = {
                        "assocId": siblings[i].data.childOfAssocId,
                        "sequenceNumber": (i + 1)
                    };
                }

                // now add o to the lsItems hash with key
                lsItems[key] = o;
            }

            // ajax call to submit changes
            apx.spinner.showModal("Copying item(s)");
            $.ajax({
                url: apx.path.doctree_update_items.replace('ID', apx.lsDocId),
                method: 'POST',
                data: {
                    "lsItems": lsItems
                }
            }).done(function (data, textStatus, jqXHR) {
                // hide spinner
                apx.spinner.hideModal();
                apx.edit.updateItemsAjaxDone(data);

            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                alert("An error occurred.");
                console.log(jqXHR, textStatus, errorThrown);
            });
        }, 10); // end of anonymous setTimeout function
    };

    apx.moveEnabled = false;
    apx.edit.enableMove = function (cb) {
        apx.edit.moveEnabled = $(cb).is(':checked');
    };

    /** Move already-existing items in the tree structure */
    apx.edit.moveItems = function (draggedNodes, droppedNode, hitMode) {
        // make sure droppedNode is expanded if hitMode is "over"
        if (hitMode === "over") {
            droppedNode.setExpanded(true);
            droppedNode.render();
        }

        // go through each of the draggedNodes, constructing a hash with items to update
        let lsItems = {};
        for (let j = 0; j < draggedNodes.length; ++j) {
            let draggedNode = draggedNodes[j];

            // note original parent
            let originalParent = draggedNode.parent;

            // move the item in the tree
            draggedNode.moveTo(droppedNode, hitMode);

            let item = draggedNode.data.ref;

            // initialize the lsItems object for this item
            lsItems[item.id] = {
                "originalKey": item.identifier
            };

            // delete the old childOf relationship for the draggedNode
            lsItems[item.id].deleteChildOf = {
                "assocId": draggedNode.data.childOfAssocId
            };

            // and create a newChildOf attribute. if parent is the document...
            if (apx.mainDoc.isDocNode(draggedNode.parent)) {
                // note the docId, and the fact that it's a document
                lsItems[item.id].newChildOf = {
                    "parentId": apx.mainDoc.doc.id,
                    "parentType": "doc"
                }
            } else {
                // otherwise the parent is an item
                lsItems[item.id].newChildOf = {
                    "parentId": draggedNode.parent.data.ref.id,
                    "parentType": "item"
                }
            }
            // (we'll fill in the sequenceNumber for newChildOf below)

            // note: the draggedNode's original parent may now have a "hole" in its children's sequenceNumbers,
            // but that's fine; they will still be in the right order
        }

        // now go through *all* siblings of the dragged node(s) (including the dragged nodes themselves)...
        let siblings = draggedNodes[0].parent.children;
        for (let i = 0; i < siblings.length; ++i) {
            let item = siblings[i].data.ref;

            // skip the item if it doesn't have an id (e.g. "orphaned items")
            if (empty(item.id)) {
                continue;
            }

            // if this isn't a draggedNode...
            if (!(item.id in lsItems)) {
                // initialize the lsItems object
                lsItems[item.id] = {
                    "originalKey": item.identifier
                };

                // then we just have to update the sequenceNumber
                lsItems[item.id].updateChildOf = {
                    "assocId": siblings[i].data.childOfAssocId,
                    "sequenceNumber": (i + 1)
                };

                // else it's a draggedNode, so...
            } else {
                // set the proper sequence number for the newChildOf relationship
                lsItems[item.id].newChildOf.sequenceNumber = (i + 1);
            }

            // if we have an assocGroup other than default selected, add the assocGroup to the lsItems object
            if (apx.mainDoc.currentAssocGroup != null) {
                lsItems[item.id].assocGroup = apx.mainDoc.currentAssocGroup;
            }
        }

        // ajax call to submit changes
        apx.spinner.showModal("Reordering item(s)");
        $.ajax({
            url: apx.path.doctree_update_items.replace('ID', apx.lsDocId),
            method: 'POST',
            data: {
                "lsItems": lsItems
            }
        }).done(function (data, textStatus, jqXHR) {
            apx.spinner.hideModal();
            apx.edit.updateItemsAjaxDone(data);

        }).fail(function (jqXHR, textStatus, errorThrown) {
            apx.spinner.hideModal();
            alert("An error occurred.");
        });
    };

    apx.edit.updateItemsAjaxDone = function (data) {
        // remove stray tooltips
        setTimeout(function () {
            bootstrap.Tooltip.getOrCreateInstance('body').hide();
            bootstrap.Tooltip.getOrCreateInstance('#treeView').hide();
            bootstrap.Tooltip.getOrCreateInstance('#assocView').hide();
        }, 1000);

        let copiedItem = false;
        for (let i = 0; i < data.length; ++i) {
            let o = data[i];
            let n = apx.mainDoc.getFt(1).getNodeByKey(o.originalKey + '');
            if (n === null) {
                console.log("couldn't get node for " + o.originalKey);
            } else {
                let item;

                // if this is a copied item...
                if (o.originalKey.indexOf("copy-") === 0) {
                    copiedItem = true;

                    // then if the copied item had children -- which will have also been copied -- we need to refresh the mainDoc entirely,
                    // because we don't get back from the server any information about the copied children
                    if (!empty(n.children) && n.children.length > 0) {
                        apx.mainDoc.refreshFromServer();
                        return;
                    }

                    item = apx.mainDoc.itemIdHash[o.lsItemId];

                    // if the item was actually copied, make a copy of the item attached to the copied node and add it to mainDoc
                    if (empty(item)) {
                        item = apx.mainDoc.addItem({
                            // the first three attributes come back from the server
                            "id": o.lsItemId,
                            "identifier": o.lsItemIdentifier,
                            "fstmt": o.fullStatement,
                            // the rest come from the original item
                            "hcs": n.data.ref.hcs,
                            "le": n.data.ref.le,
                            "astmt": n.data.ref.astmt,
                            "ck": n.data.ref.ck,
                            "cku": n.data.ref.cku,
                            "notes": n.data.ref.notes,
                            "lang": n.data.ref.lang,
                            "el": n.data.ref.el,
                            "itp": n.data.ref.itp
                        });
                    }

                } else {
                    item = n.data.ref;
                }

                // if we got back deleteChildOf, it's the assocId of the deleted association; delete it
                if (!empty(o.deleteChildOf)) {
                    apx.mainDoc.deleteAssociation(o.deleteChildOf);
                }

                // if we got back sequenceNumber, we added or updated an isChildOf association; we should always get o.assocId as well
                if (!empty(o.sequenceNumber)) {
                    let existingAssoc = apx.mainDoc.assocIdHash[o.assocId];
                    if (empty(existingAssoc)) {
                        let atts = {
                            "id": o.assocId,
                            "seq": o.sequenceNumber * 1,
                            "originItem": item,
                            "type": "isChildOf",
                            "destItem": n.parent.data.ref, // parent item is the node's parent's ref
                            "groupId": apx.mainDoc.currentAssocGroup
                        };
                        let a = apx.mainDoc.addAssociation(atts);
                        apx.mainDoc.addInverseAssociation(a);

                    } else {
                        existingAssoc.seq = o.sequenceNumber * 1;
                    }
                }
            }
        }

        // re-render the tree
        apx.treeDoc1.ftRender1();
        // unless we just copied an item, re-activate the current item
        if (!copiedItem) {
            apx.treeDoc1.activateCurrentItem();
        }
    };


    /////////////////////////////////////////////////////
    // ASSOCIATION GROUP EDITING
    apx.edit.initializeManageAssocGroupButtons = function () {
        // initialize buttons in association group modal
        $('#manageAssocGroupsModal')
            .off('click', ".assocgroup-edit-btn").on('click', ".assocgroup-edit-btn", function () {
                apx.edit.editAssocGroup(this);
            })
            .off('click', ".assocgroup-delete-btn").on('click', ".assocgroup-delete-btn", function () {
                apx.edit.deleteAssocGroup(this);
            });
    };

    apx.edit.prepareAddAssocGroupModal = function () {
        let $addAssocGroupModal = $('#addAssocGroupModal');
        let $manageAssocGroupsModal = $("#manageAssocGroupsModal");
        $addAssocGroupModal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        $addAssocGroupModal.on('show.bs.modal', function (e) {
            $manageAssocGroupsModal.modal('hide');
        }).on('shown.bs.modal', function (e) {
            $('#addAssocGroupModal').find('.modal-body').load(
                apx.path.lsdef_association_grouping_new,
                null,
                function (responseText, textStatus, jqXHR) {
                    // select this document from the document select menu, then hide the menu
                    $("#ls_def_association_grouping_lsDoc").val(apx.lsDocId);
                    $("#ls_def_association_grouping_lsDoc").closest(".form-group").hide();
                }
            )
        }).on('hidden.bs.modal', function (e) {
            $('#addAssocGroupModal').find('.modal-body').html(apx.spinner.html("Loading Form"));
        });
        $addAssocGroupModal.find('.btn-save').on('click', function (e) {
            apx.spinner.showModal("Creating item");
            $.ajax({
                url: apx.path.lsdef_association_grouping_new,
                method: 'POST',
                data: $addAssocGroupModal.find('form[name=ls_def_association_grouping]').serialize()
            }).done(function (data, textStatus, jqXHR) {
                // returned data will be the new item id

                apx.spinner.hideModal();

                // on successful add, add the item to the assocGroups list
                let newAssocGroupId = data;
                let ag = {
                    "id": newAssocGroupId,
                    "title": $("#ls_def_association_grouping_title").val(),
                    "description": $("#ls_def_association_grouping_description").val(),
                    "lsDocId": apx.mainDoc.doc.id
                };
                apx.mainDoc.assocGroups.push(ag);
                apx.mainDoc.assocGroupIdHash[ag.id] = ag;

                // and add it to the manage groups modal
                let html = '<tr data-assocgroupid="' + newAssocGroupId + '">';
                html += '<td>' + render.escaped(ag.title) + '</td>';
                html += '<td>';
                html += '<button class="assocgroup-edit-btn btn btn-default btn-sm float-end">Edit</button>';
                html += '<button class="assocgroup-delete-btn btn btn-default btn-sm float-end" style="margin-right:5px">Delete</button>';
                html += '<span class="assocgroup-description">' + render.escaped(ag.description) + '</span>';
                html += '</td>';
                html += '</tr>';
                $manageAssocGroupsModal.find("tbody").append(html);

                // re-render the select menu(s)
                apx.mainDoc.renderAssocGroupMenu($("#treeSideLeft").find(".assocGroupSelect"), 1);
                if (apx.mainDoc == apx.treeDoc2) {
                    apx.mainDoc.renderAssocGroupMenu($("#treeSideRight").find(".assocGroupSelect"), 2);
                }

                // hide the add modal and show the manage modal
                $addAssocGroupModal.modal('hide');
                $manageAssocGroupsModal.modal('show');

            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $addAssocGroupModal.find('.modal-body').html(jqXHR.responseText);
            });
        });

        // if you cancel the new assoc group modal, re-open the manage modal
        $addAssocGroupModal.find('.modal-footer .btn-default').on('click', function (e) {
            $("#manageAssocGroupsModal").modal('show');
        });
    };

    apx.edit.editAssocGroup = function (btn) {
        // get assocGroup to delete
        const assocGroupId = $(btn).closest("[data-assocgroupid]").attr("data-assocgroupid");

        // hide the manage modal
        $("#manageAssocGroupsModal").modal('hide');

        const $editAssocGroupModal = $('#editAssocGroupModal');
        $editAssocGroupModal.find('.modal-body').html(apx.spinner.html("Loading Form"));
        $editAssocGroupModal.modal('show').on('shown.bs.modal', function (e) {
            $('#editAssocGroupModal').find('.modal-body').load(
                apx.path.lsdef_association_grouping_edit.replace('ID', assocGroupId),
                null,
                function (responseText, textStatus, jqXHR) {
                    // select this document from the document select menu, then hide the menu
                    $("#ls_def_association_grouping_lsDoc").val(apx.lsDocId);
                    $("#ls_def_association_grouping_lsDoc").closest(".form-group").hide();
                }
            )
        }).on('hidden.bs.modal', function (e) {
            $('#editAssocGroupModal').find('.modal-body').html(apx.spinner.html("Loading Form"));
        });
        $editAssocGroupModal.find('.btn-save').off().on('click', function (e) {
            apx.spinner.showModal("Updating group");
            $.ajax({
                url: apx.path.lsdef_association_grouping_edit.replace('ID', assocGroupId),
                method: 'POST',
                data: $editAssocGroupModal.find('form[name=ls_def_association_grouping]').serialize()
            }).done(function (data, textStatus, jqXHR) {
                apx.spinner.hideModal();
                // on successful edit, update the item...
                const title = $("#ls_def_association_grouping_title").val();
                let description = $("#ls_def_association_grouping_description").val();
                if (description == "") description = "—";

                // in the modal
                const $tr = $("tr[data-assocgroupid=" + assocGroupId + "]");
                $tr.find("td").first().html(title);
                $tr.find(".assocgroup-description").html(description);

                // and in the mainDoc assocGroups array
                apx.mainDoc.assocGroupIdHash[assocGroupId].title = title;
                apx.mainDoc.assocGroupIdHash[assocGroupId].description = description;

                // re-render the select menu(s)
                apx.mainDoc.renderAssocGroupMenu($("#treeSideLeft").find(".assocGroupSelect"), 1);
                if (apx.mainDoc == apx.treeDoc2) {
                    apx.mainDoc.renderAssocGroupMenu($("#treeSideRight").find(".assocGroupSelect"), 2);
                }

                // hide assoc group edit modal; show manage modal
                $editAssocGroupModal.modal('hide');
                $("#manageAssocGroupsModal").modal('show');

            }).fail(function (jqXHR, textStatus, errorThrown) {
                apx.spinner.hideModal();
                $editAssocGroupModal.find('.modal-body').html(jqXHR.responseText);
            });
        });

        // if you cancel the edit assoc group modal, re-open the manage modal
        $editAssocGroupModal.find('.modal-footer .btn-default').on('click', function (e) {
            $("#manageAssocGroupsModal").modal('show');
        });
    };

    apx.edit.deleteAssocGroup = function (btn) {
        // get assocGroup to delete
        const assocGroupId = $(btn).closest("[data-assocgroupid]").attr("data-assocgroupid");

        // hide the manage modal
        $("#manageAssocGroupsModal").modal('hide');

        // show confirmation modal
        $("#deleteAssocGroupModal").modal()
            .one('click', '.btn-delete', function () {
                $(this).closest('.modal').modal('hide');

                // show "Deleting" spinner
                apx.spinner.showModal("Deleting");

                $.ajax({
                    url: apx.path.lsdef_association_grouping_tree_delete.replace('ID', assocGroupId),
                    method: 'POST'
                }).done(function (data, textStatus, jqXHR) {
                    // hide the spinner
                    apx.spinner.hideModal();

                    // remove from the assocGroups array/hash
                    for (let i = 0; i < apx.mainDoc.assocGroups.length; ++i) {
                        if (apx.mainDoc.assocGroups[i].id == assocGroupId) {
                            apx.mainDoc.assocGroups.splice(i, 1);
                            break;
                        }
                    }
                    delete apx.mainDoc.assocGroupIdHash[assocGroupId];

                    // re-render the assocGroup menu(s) (this will hide them if necessary)
                    apx.mainDoc.renderAssocGroupMenu($("#treeSideLeft").find(".assocGroupSelect"), 1);
                    if (apx.mainDoc == apx.treeDoc2) {
                        apx.mainDoc.renderAssocGroupMenu($("#treeSideRight").find(".assocGroupSelect"), 2);
                    }

                    // remove from the manage modal, then reshow it
                    $("tr[data-assocgroupid=" + assocGroupId + "]").remove();
                    $("#manageAssocGroupsModal").modal('show');

                }).fail(function (jqXHR, textStatus, errorThrown) {
                    alert(jqXHR.responseJSON.error.message);
                    apx.spinner.hideModal();
                    $("#manageAssocGroupsModal").modal('show');
                });
            }).one('hidden.bs.modal', function (e) {
                $(this).off('click', '.btn-delete');
            });
    };
}
