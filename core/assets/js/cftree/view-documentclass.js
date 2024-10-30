import { empty, op } from './utils';
import render from './render-md';

/////////////////////////////////////////////////////////////////////////////

/**
 * Class for representing/manipulating/using a document
 *
 * @class
 */

function ApxDocument(initializer, apx) {
    let self = this;

    // record the initializer for use when the document is loaded
    self.initializer = initializer;

    // keep track of the current association group chosen for the document, which could be different on sides 1 (left) and 2 (right)
    self.currentAssocGroup = null;
    self.currentAssocGroup2 = null;
    self.setCurrentAssocGroup = function(assocGroup, side) {
        let $agm;
        if (empty(side) || side === 1) {
            self.currentAssocGroup = assocGroup;
            $agm = self.$assocGroupMenu;
        } else {
            self.currentAssocGroup2 = assocGroup;
            $agm = self.$assocGroupMenu2;
        }

        // set the assoc group menu properly
        if (!empty($agm)) {
            if (assocGroup == null) {
                $agm.val("default");
            } else {
                $agm.val(assocGroup);
            }
        }
    };

    /** keep track of the currently-selected item for the document (which could be the doc itself)
     *  Input can be an item, an identifier, or an lsItemId; in the latter case, null represents the document */
    self.currentItem = null;
    self.setCurrentItem = function(o) {
        self.currentItem = null;

        if (!empty(o.item)) {
            self.currentItem = o.item;
        } else if (!empty(o.identifier)) {
            if (o.identifier === self.doc.identifier) {
                self.currentItem = self.doc;
            } else {
                self.currentItem = self.itemHash[o.identifier];
            }
        } else if (!empty(o.lsItemId)) {
            self.currentItem = self.itemIdHash[o.lsItemId];
        } else {
            self.currentItem = self.doc;
        }

        // to check to see if the document is currently selected, use
        // (d.currentItem == d.doc)
    };

    /** Load the document via an ajax call */
    self.load = function(callbackFn) {
        let path = apx.path.doctree_retrieve_document.replace('ID', apx.lsDocId);
        let ajaxData = {};
        if (!empty(self.initializer.id)) {
            ajaxData.id = self.initializer.id;
        } else if (!empty(self.initializer.identifier)) {
            ajaxData.identifier = self.initializer.identifier;
        } else if (!empty(self.initializer.url)) {
            ajaxData.url = self.initializer.url;
        }

        console.log("loading document", self.initializer);

        $.ajaxq('docFetchQ', {
            url: path,
            method: 'GET',
            data: ajaxData
        }).done(function(data, textStatus, jqXHR) {
            if (empty(data) || typeof(data) !== "object" || empty(data.CFDocument)) {
                self.loadError(data);
                return;
            }

            // extract the pieces of the returned document

            // CFDocument comes in standard CASE format
            self.doc = data.CFDocument;

            // store this ApxDocument in apx.allDocs
            apx.allDocs[self.doc.identifier] = self;

            // some SALT-specific top-level things...

            // document id
            self.doc.id = data.lsDocId;

            // "baseDoc" -- normally "self", but if this is a "pure cross-walk" document, this will be a different document identifier
            self.baseDoc = data.baseDoc;

            // documents referenced by associations in this document
            self.associatedDocs = data.associatedDocs;
            if (empty(self.associatedDocs)) {
                self.associatedDocs = {};
            }

            // note that doc is a document
            self.doc.nodeType = "document";

            // extract uriBase from doc.uri
            //self.doc.uriBase = self.doc.uri.replace(/\/[^\/]+$/, "/");
            //self.doc.uriBase = self.doc.uri.replace(/CFDocuments\/$/, "CFItems/");
            // @todo: this assumes an OpenSALT instance on the other end that can use /uri/... URLs
            self.doc.uriBase = self.doc.uri.replace(/(https?:\/\/[^\/]+).*/, '$1/uri/');

            // add an assocs array for the document
            self.doc.assocs = [];

            self.items = op(data, "CFItems");
            if (empty(self.items)) {
                self.items = [];
            }

            self.assocs = op(data, "CFAssociations");
            if (empty(self.assocs)) {
                self.assocs = [];
            }

            self.itemTypes = op(data, "CFDefinitions", "CFItemTypes");
            self.subjects = op(data, "CFDefinitions", "CFSubjects");
            self.concepts = op(data, "CFDefinitions", "CFConcepts");
            self.licenses = op(data, "CFDefinitions", "CFLicenses");
            self.assocGroups = op(data, "CFDefinitions", "CFAssociationGroupings");
            // create hashes for assocGroups
            self.assocGroupHash = {};
            self.assocGroupIdHash = {};
            if (empty(self.assocGroups)) {
                self.assocGroups = [];
            } else {
                for (let i = 0; i < self.assocGroups.length; ++i) {
                    self.assocGroupHash[self.assocGroups[i].identifier] = self.assocGroups[i];
                    self.assocGroupIdHash[self.assocGroups[i].id] = self.assocGroups[i];
                }
            }

            // if we didn't get a "condensed" file (via the doctree's export function)...
            if (data.condensed !== true) {
                // then convert package data for items and assocs
                self.convertPackageData();
            }

            // process items
            // create hashes so we can reference items via their id's or identifiers
            self.itemHash = {};
            self.itemIdHash = {};
            let originalItems = self.items;
            self.items = [];
            for (let i = 0; i < originalItems.length; ++i) {
                self.addItem(originalItems[i]);
            }

            // add the document to the itemHash; this makes some other things more convenient
            self.itemHash[self.doc.identifier] = self.doc;

            // process associations
            // create hashes for assocs too, and tie assocs to their items
            self.assocHash = {};
            self.assocIdHash = {};
            let originalAssocLength = self.assocs.length;   // we will likely add new associations below, so length will increase
            for (let i = 0; i < originalAssocLength; ++i) {
                let a = self.assocs[i];
                self.assocHash[a.identifier] = a;
                if (!empty(a.id)) {
                    self.assocIdHash[a.id] = a;
                }

                // tie assoc to item
                if (!empty(a.origin.item)) {
                    // if we already know about this item, add it to the item's assocs array
                    if (!empty(self.itemHash[a.origin.item])) {
                        self.itemHash[a.origin.item].assocs.push(a);

                    // else...
                    } else {
                        // create an "assocsOnly" item to hold the assoc, so we can easily look it up later
                        self.itemHash[a.origin.item] = {
                            "assocsOnly": true,
                            assocs: []
                        };
                        self.itemHash[a.origin.item].assocs.push(a);
                    }
                }
                // note that this assoc is "owned" by this doc
                a.assocDoc = self.doc.identifier;

                // Construct inverse (e.g. "isParentOf") associations
                self.addInverseAssociation(a);
            }

            // when doc first loads, doc is selected
            self.setCurrentItem({"identifier": self.doc.identifier});

            // if we initialized by url, add to the document menus
            if (!empty(self.initializer.url)) {
                apx.addDocToMenus(self.doc.identifier, self.initializer.url, self.doc.title);
            }

            // if this isn't mainDoc,
            if (self !== apx.mainDoc) {
                // check to see if this doc is a reference (origin or dest) for any of mainDoc's
                self.updateMainDocAssocs();
            }

            if (!empty(callbackFn)) {
                callbackFn();
            }

            // if we're showing the association view, refresh it now, in case we just finished loading a document referred to in an association
            if (apx.viewMode.currentView === "assoc") {
                apx.viewMode.showAssocView("refresh");
            }

        }).fail(function(jqXHR, textStatus, errorThrown){
            self.loadError();
        });
    };

    self.loadError = function(data) {
        console.log("error loading document", self.initializer);
        if (!empty(data)) {
            console.log("data returned:", data);
        }

        // if the document was initialized via an identifier, note that we got an error loading the doc
        // TODO: we need to be able to keep track of documents opened via urls here too...
        if (!empty(self.initializer.identifier)) {
            apx.allDocs[self.initializer.identifier] = "loaderror";
        }

        apx.spinner.hideModal();
    };

    /** Convert a original package file from the standard CASE format into the condensed format we work with in the OpenSALT code */
    self.convertPackageData = function() {
        function changeKey(item, oldKey, newKey) {
            if (oldKey in item) {
                item[newKey] = item[oldKey];
                delete item[oldKey];
            }
        }

        // TODO: we may need to do some converting for assocGroups
        // if we have any assocGroups where we don't have an id, create an arbitrary id to use in the client
        // use negative id's (?)
        let lastNewId = 0;
        for (let i = 0; i < self.assocGroups.length; ++i) {
            let ag = self.assocGroups[i];
            if (empty(ag.id)) {
                --lastNewId;
                ag.id = lastNewId;
                self.assocGroupIdHash[ag.id] = ag;
            }
        }

        // items: some field names are abbreviated
        for (let i = 0; i < self.items.length; ++i) {
            let item = self.items[i];
            changeKey(item, "fullStatement", "fstmt");
            changeKey(item, "abbreviatedStatement", "astmt");
            changeKey(item, "humanCodingScheme", "hcs");
            changeKey(item, "listEnumInSource", "le");
            changeKey(item, "conceptKeywords", "ck");
            changeKey(item, "conceptKeywordsURI", "cku");
            changeKey(item, "language", "lang");
            changeKey(item, "educationalAlignment", "el");
            changeKey(item, "itemType", "itp");
            changeKey(item, "lastChangeDateTime", "mod");
            // things that remain the same: identifier, notes
            // the condensed format will also include an id field
        }

        // CFAssociations: define a function for converting origin and dest data
        function assocTarget(assoc, oldKey, newKey) {
            // we don't know the document
            assoc[newKey] = {"doc": "?"};

            // put the "identifier" in "item"
            if (!empty(assoc[oldKey].identifier)) {
                assoc[newKey].item = assoc[oldKey].identifier;
            }

            // add the title if we got it (we should)
            if (!empty(assoc[oldKey].title)) {
                assoc[newKey].title = assoc[oldKey].title;
            }

            delete assoc[oldKey];
        }

        // then go through each association
        for (let i = 0; i < self.assocs.length; ++i) {
            let assoc = self.assocs[i];

            // if we get a CFAssociationGroupingURI, that should have a "title" and "identifier" for the group
            if (!empty(assoc.CFAssociationGroupingURI)) {
                let ag;
                let ago = assoc.CFAssociationGroupingURI;
                // if we at least have an identifier...
                if (!empty(ago) && typeof(ago) === "object" && !empty(ago.identifier)) {
                    ag = self.assocGroupHash[ago.identifier];

                    // if the group didn't already exist, create it now
                    if (empty(ag)) {
                        ag = {
                            "identifier": ago.identifier,
                            "id": ago.id,
                            "title": ago.title
                        };

                        // get a new id if necessary
                        if (empty(ag.id)) {
                            --lastNewId;
                            ag.id = lastNewId;
                        }

                        // generate a title if necessary
                        if (empty(ag.title)) {
                            ag.title = "Association Group " + (ag.id*1);
                        }

                        self.assocGroups.push(ag);
                        self.assocGroupHash[ag.identifier] = ag;
                        self.assocGroupIdHash[ag.id] = ag;
                    }

                    // now record the id as assoc.groupId
                    assoc.groupId = ag.id;
                }

                delete assoc.CFAssociationGroupingURI;
            }
            changeKey(assoc, "associationType", "type");
            changeKey(assoc, "sequenceNumber", "seq");
            changeKey(assoc, "lastChangeDateTime", "mod");
            assocTarget(assoc, "originNodeURI", "origin");
            assocTarget(assoc, "destinationNodeURI", "dest");

            // things that remain the same: identifier
            // the condensed format will also include an id field
            // TODO: it would be good to put the sequenceNumber field in OpenSALT exports
        }
    };

    /** Find, and try to load, any docs referenced by associations in this doc */
    self.findAssociatedDocs = function() {
        for (let i = 0; i < self.assocs.length; ++i) {
            let a = self.assocs[i];
            if (a.origin.doc !== "-" && a.origin.doc !== "?" && !(a.origin.doc in apx.allDocs)) {
                apx.allDocs[a.origin.doc] = "loading";
                new ApxDocument({"identifier": a.origin.doc}, apx).load();
            }
            if (a.dest.doc !== a.origin.doc && a.dest.doc !== "-" && a.dest.doc !== "?" && !(a.dest.doc in apx.allDocs)) {
                apx.allDocs[a.dest.doc] = "loading";
                new ApxDocument({"identifier": a.dest.doc}, apx).load();
            }
        }
    };

    /** If the mainDoc has any associations that reference items in this doc, update the assoc items */
    self.updateMainDocAssocs = function() {
        for (let i = 0; i < apx.mainDoc.assocs.length; ++i) {
            let a = apx.mainDoc.assocs[i];
            if (a.origin.doc === "?" && !empty(self.itemHash[a.origin.item])) {
                a.origin.doc = self.doc.identifier;
            }
            if (a.type !== "exemplar" && a.dest.doc === "?" && !empty(self.itemHash[a.dest.item])) {
                a.dest.doc = self.doc.identifier;
            }
        }
    };

    /** Create a fancytree data structure for the given assocGroup **/
    self.createTree = function(assocGroup, treeSide) {
        // Go through all items
        for (let i = 0; i < self.items.length; ++i) {
            // for treeSide1, make sure previously-saved ftNodeDatas are cleared from all items
            if (treeSide === 1) {
                self.items[i].ftNodeData = null;
            }
        }

        // start with the document
        let t = {
            "title": self.doc.title,
            "key": self.doc.identifier,
            "children": [],
            "active": true,
            "folder": true,
            "expanded": true,
            "checkbox": false,   // tree should not have a checkbox
            "unselectable": true,   // tree should not be selectable
            "ref": self.doc
        };

        /** Function to get the appropriate title for a tree item */
        function treeItemTitle(item) {
            // start with the standard title for the item
            let title = self.getItemTitle(item);

            // if we're in chooser mode...
            if (apx.query.mode === "chooser") {
                // don't include the link indicator

            } else {
                // if the item has an association other than isChildOf *in apx.mainDoc*, show an indicator to that effect
                let associationDisplay = "none";
                let ci2 = apx.mainDoc.itemHash[item.identifier];
                if (!empty(ci2)) {
                    for (let j = 0; j < ci2.assocs.length; ++j) {
                        let a = ci2.assocs[j];
                        if (a.type !== "isChildOf") {
                            associationDisplay = "block";
                            break;
                        }
                    }
                }

                // if we don't show it now, it's there in case we add an association later
                title = '<div class="treeHasAssociation" style="display:' + associationDisplay + '"><img src="/assets/img/association-icon.png" title="This item is the origin for one or more associations."></div>' + title;
            }

            return title;
        }

        /** recursive function to find isChildOf associations of a parent */
        function findChildren(parent) {
            // go through all associations
            for (let i = 0; i < self.assocs.length; ++i) {
                let a = self.assocs[i];
                // note that a.origin.item and a.dest.item will be identifiers (guids)

                // if we find a child of the parent that matches the assocGroup
                if ("isChildOf" === a.type
                    && true !== a.inverse
                    && a.dest.item === parent.key
                    && a.groupId == assocGroup // if the association is in the "default group", a.groupId will be undefined; we want to use == so that it matches null
                ) {
                    let child = {
                        "title": a.origin.item,
                        "key": a.origin.item,
                        "children": [],
                        "seq": a.seq,
                        "childOfAssocId": a.id,     // stash the assocId for use elsewhere
                        // we really shouldn't need to set a default ref like this, but just in case...
                        "ref": {
                            "nodeType": "item",
                            "item": "unknown",
                            "doc": self
                        }
                    };

                    // look for the child in itemHash (we should always find it)
                    let childItem = self.itemHash[a.origin.item];
                    if (!empty(childItem)) {
                        // set the title
                        child.title = treeItemTitle(childItem);

                        // and add the item's reference
                        child.ref = childItem;

                        // then link the ft node to the childItem if we're rendering the left side
                        if (treeSide === 1) {
                            childItem.ftNodeData = child;
                        }
                    }

                    // push child onto children array
                    parent.children.push(child);

                    // recurse to find this child's children
                    findChildren(child);

                    // if the child has children...
                    if (child.children.length > 0) {
                        // make the child node a folder in fancytree
                        child.folder = true;

                        // expand the folder if it's the currentItem, or if it was expanded previously
                        child.expanded = (childItem === self.currentItem || op(self.expandedFolders[treeSide], assocGroup, child.key) === true);
                    }
                }
            }

            // sort children of parent
            parent.children.sort(function(a,b) {
                // try to sort by a.seq
                let seqA = a.seq * 1;
                let seqB = b.seq * 1;
                if (isNaN(seqA)) seqA = 100000;
                if (isNaN(seqB)) seqB = 100000;
                // if seqA != seqB, sort by seq
                if (seqA !== seqB) {
                    return seqA - seqB;
                }

                // else try to sort by the item's listEnumeration field
                let leA = 100000;
                let leB = 100000;
                if (!empty(a.ref) && !empty(a.ref.le)) {
                    leA = a.ref.le*1;
                }
                if (!empty(b.ref) && !empty(b.ref.le)) {
                    leB = b.ref.le*1;
                }

                if (isNaN(leA)) leA = 100000;
                if (isNaN(leB)) leB = 100000;

                if (leA !== leB) {
                    return leA - leB;
                }

                // else try to sort by the item's human coding scheme

                let hcsA = op(a, "ref", "hcs");
                let hcsB = op(b, "ref", "hcs");

                if (empty(hcsA) && empty(hcsB)) return 0;
                if (empty(hcsB)) return -1;
                if (empty(hcsA)) return 1;

                let lang = (document.documentElement.lang !== "") ? document.documentElement.lang : undefined;

                return hcsA.localeCompare(hcsB, lang, { numeric: true, sensitivity: 'base' });
            });
        }

        // find the document's children (and its children's children, and so on)
        findChildren(t);

        // If we're showing the default association group look for any orphaned items
        if (empty(assocGroup)) {
            // flag all items as "orphaned"; we'll clear these flags below
            for (let i = 0; i < self.items.length; ++i) {
                self.items[i].orphaned = true;
            }

            // now go through all associations and clear orphaned flag for non-orphaned items
            for (let i = 0; i < self.assocs.length; ++i) {
                let a = self.assocs[i];
                // if this is an isChildOf...
                if (a.type === "isChildOf" && a.inverse !== true) {
                    // if the origin (child) item exists...
                    let childItem = self.itemHash[a.origin.item];
                    if (!empty(childItem)) {
                        // then if the parent (destination) item exists...
                        let parentItem = self.itemHash[a.dest.item];
                        if (!empty(parentItem)) {
                            // then the child isn't an orphan!
                            delete childItem.orphaned;
                        }
                    }
                }
            }

            // go back through the items and find the orphans
            let orphans = [];
            for (let i = 0; i < self.items.length; ++i) {
                let item = self.items[i];
                if (item.orphaned === true) {
                    orphans.push(item);
                }
            }

            // if we found any, push them onto the tree
            if (orphans.length > 0) {
                let orphanParent = {
                    "title": "*Orphaned Items*",
                    "key": "orphans",
                    "children": [],
                    "folder": true,
                    "seq": 100000,
                    "ref": {
                        "nodeType": "item",
                        "item": "orphanParent",
                        "title": "*Orphaned Items*",
                        "doc": self
                    }
                };
                for (let i = 0; i < orphans.length; ++i) {
                    let orphan = orphans[i];
                    let child = {
                        "title": treeItemTitle(orphan),
                        "key": orphan.identifier,
                        "children": [],
                        "seq": i,
                        "ref": orphan
                    };
                    // then link the ft node to the childItem if we're rendering the left side
                    if (treeSide === 1) {
                        orphan.ftNodeData = child;
                    }
                    orphanParent.children.push(child)
                }
                t.children.push(orphanParent);
            }
        }

        // for fancytree we need an array with the single document item
        return [t];
    };

    /** Record folders currently expanded in this document's tree, for each side and each assocGroup */
    self.expandedFolders = {1: {}, 2: {}};
    self.recordExpandedFolders = function(side) {
        let efo;
        if (side === 1) {
            efo = self.expandedFolders[1][self.currentAssocGroup] = {};
        } else {
            efo = self.expandedFolders[2][self.currentAssocGroup2] = {};
        }

        function findExpandedFolders(n) {
            if (!empty(n.expanded) && n.expanded === true) {
                efo[n.key] = true;
            }
            if (!empty(n.children)) {
                for (let i = 0; i < n.children.length; ++i) {
                    findExpandedFolders(n.children[i]);
                }
            }
        }
        findExpandedFolders(self.getFt(side).getNodeByKey(self.doc.identifier));
    };

    /** Determine if this document was loaded from a url (as opposed to loaded via an id) */
    self.loadedFromUrl = function() {
        return !empty(self.initializer.url);
    };

    /** Determine if this is an "external" doc -- loaded from a different server */
    self.isExternalDoc = function() {
        // it's an external doc if it's in the apx.mainDoc.associatedDocs array and its url doesn't start with "local"
        if (!empty(apx.mainDoc.associatedDocs) && !empty(apx.mainDoc.associatedDocs[self.doc.identifier]) && apx.mainDoc.associatedDocs[self.doc.identifier].url.search(/local/) != 0) {
            return true;
        } else {
            return false;
        }
    };

    // Not sure if we need this; plan is to always use the identifier (guid) as the key...
    self.getDocKey = function() {
        if (empty(self.doc)) return null;
        // use the document guid (identifier) as the key for the document
        return self.doc.identifier;
    };

    /** Retrieve an array of associations for the item, optionally checking only associations of type assocType and/or only group assocGroup */
    self.getAssocsForItem = function(item, assocType, assocGroup, inverse) {
        // if the assoc isn't inversed, a.inverse will === undefined; so if we got a "inverse" parameter of false or null, change it to undefined
        if (inverse === false || inverse === null) {
            inverse = undefined;
        }

        let assocs = [];
        for (let i = 0; i < item.assocs.length; ++i) {
            let a = item.assocs[i];
            if ((empty(assocType) || assocType == a.type) && inverse === a.inverse) {
                if (typeof(assocGroup) === "undefined" || assocGroup == a.groupId) {    // use == so null matches undefined
                    assocs.push(a);
                }
            }
        }
        return assocs;
    };

    /** Retrieve the association groups for the item, optionally checking only associations of type assocType */
    self.getAssocGroupsForItem = function(item, assocType) {
        let assocGroups = [];
        for (let i = 0; i < item.assocs.length; ++i) {
            let a = item.assocs[i];
            let groupId;
            if (empty(a.groupId)) {
                groupId = null;
            } else {
                groupId = a.groupId;
            }
            // add to assocGroups if groupId isn't already there
            if ($.inArray(groupId, assocGroups) === -1) {
                assocGroups.push(groupId);
            }
        }
        return assocGroups;
    };

    /** get the fancyTree object for this document on the given side */
    self.getFt = function(side) {
        return self["ft" + side].fancytree("getTree");
    };

    /** get the fancyTree node for an item on the given side */
    self.getFtNode = function(item, side) {
        return self.getFt(side).getNodeByKey(item.identifier);
    };

    self.getItemUri = function(item) {
        if(empty(item)) {
            return "?";
        }

        if(!empty(item.uri)) {
            return item.uri;
        }

        if(!empty(self.doc.uriBase) && !empty(item.identifier)) {
            return self.doc.uriBase + item.identifier;
        }

        return "?";
    };

    self.getItemTitleBlock = function(item, requireFullStatement) {
        let title = self.getItemStatement(item, requireFullStatement);

        title = render.block(title);

        if (item !== self.doc) {
            // add humanCodingScheme to the start if we have one
            if (!empty(item.hcs)) {
                title = '<span class="item-humanCodingScheme">' + render.escaped(item.hcs) + '</span> ' + title;
            }
        }

        return title;
    };

    self.getItemTitle = function(item, requireFullStatement) {
        let title = self.getItemStatement(item, requireFullStatement);

        title = render.inline(title);

        if (item !== self.doc) {
            // add humanCodingScheme to the start if we have one
            if (!empty(item.hcs)) {
                title = '<span class="item-humanCodingScheme">' + render.escaped(item.hcs) + '</span> ' + title;
            }
        }

        return title;
    };

    self.getItemStatement = function(item, requireFullStatement) {
        let title = '';

        if (item === self.doc && !empty(item.title)) {
            // for the document, use title
            title = item.title;
        } else if (!empty(item.fstmt)) {
            // else it's an item
            // by default we'll use the fullStatement, which is a required field for CF items
            title = item.fstmt;

            // use abbreviatedStatement if we have one and requireFullStatement isn't true
            if (!empty(item.astmt) && requireFullStatement !== true) {
                title = item.astmt;
            }
        } else if (!empty(item.item) && 'orphanParent' === item.item) {
            // else it's an orphan
            title = '*Orphaned Items*';
        }

        return title;
    };

    self.getAssociationTypePretty = function(a) {
        let s = a.type[0].toUpperCase() + a.type.substr(1).replace(/([A-Z])/g, " $1");
        if (a.inverse === true) {
            // look for inverse assoc type
            for (let i = 0; i < apx.assocTypes.length; ++i) {
                if (apx.assocTypes[i] === s) {
                    s = apx.inverseAssocTypes[i];
                    // put an " (R)" after "Is Peer Of" to note that it's a reverse-is-peer-of association
                    if (s === "Is Peer Of") {
                        s += " (R)";
                    }
                    return s;
                }
            }
            s += " (REVERSE)";
        }
        return s;
    };

    self.condenseType = function (type) {
        return type[0].toLowerCase() + type.substr(1).replace(/ /g, "");    // convert type to camel case
    };

    self.getAssociationTypeCondensed = function(a) {
        return self.condenseType(a.type);
    };

    /** render the association group menu for this document */
    self.$assocGroupMenu = null;
    self.$assocGroupMenu2 = null;
    self.renderAssocGroupMenu = function($menu, side) {
        if (empty(side)) {
            side = 1;
        }

        // record the menu for this document/side, so that we can set the menu properly in setCurrentAssocGroup
        if (side === 1) {
            self.$assocGroupMenu = $menu;
        } else {
            self.$assocGroupMenu2 = $menu;
        }

        // clear options out of the menu
        $menu.html('');

        // if we have any groups, build and show the menu
        if (self.assocGroups.length > 0) {
            // default group
            $menu.append('<option value="default">– Default Group –</option>');

            // other groups
            for (let i = 0; i < self.assocGroups.length; ++i) {
                let title = self.assocGroups[i].title;
                if (60 < title.length) {
                    title = title.substr(0, 58) + "\u2026";
                }
                $menu.append('<option value="' + self.assocGroups[i].id + '">' + render.escaped(title) + '</option>');
            }

            // show the menu
            $menu.closest(".assocGroupFilter").show();

            // and select the current group
            if (side === 1) {
                if (self.currentAssocGroup == null) {
                    $menu.val("default");
                } else {
                    $menu.val(self.currentAssocGroup + "");
                }
            } else {
                if (self.currentAssocGroup2 == null) {
                    $menu.val("default");
                } else {
                    $menu.val(self.currentAssocGroup2 + "");
                }
            }

        // otherwise hide the menu
        } else {
            $menu.closest(".assocGroupFilter").hide();
        }
    };

    /** An assocGroup was selected from the document's menu, on side 1 or 2 */
    self.assocGroupSelected = function(menu, side) {
        // get the menu val; convert "default" to null; setCurrentAssocGroup
        let val = $(menu).val();
        if (val === "default") {
            self.setCurrentAssocGroup(null, side);
        } else {
            self.setCurrentAssocGroup(val*1, side);
        }

        // render the fancytree on the appropriate side
        self["ftRender" + side]();

        // if this is the left side...
        if (side === 1) {
            // select the document
            apx.treeDoc1.setCurrentItem({"item": self.doc});

            // activate the item
            apx.treeDoc1.activateCurrentItem();

            // we also have to call showCurrentItem, because if the current item is the document it's already active
            apx.treeDoc1.showCurrentItem();

            // push history state
            apx.pushHistoryState();
        }
    };

    // UTILITIES FOR FANCYTREE ELEMENTS (LEFT OR RIGHT SIDE)
    // to get an item from a node (getItemFromNode), just use node.data.ref;

    self.isDocNode = function(node) {
        return (op(node, "data", "ref", "nodeType") === "document");
    };

    // Get tooltip content
    self.tooltipContent = function(node) {
        let content;
        if (self.isDocNode(node)) {
            content = "Document: " + render.block(node.title);
        } else {
            if (empty(node.data.ref)) {
                content = "Item: " + render.block(node.title);    // this shouldn't happen
            } else {
                content = self.getItemTitleBlock(node.data.ref, true);
            }
        }

        return content;
    };

    self.addAssociation = function(atts) {
        let assoc = {
            "id": atts.id,
            "type": atts.type,
            "inverse": atts.inverse,
            "subtype": atts.subtype,
            "annotation": atts.annotation
        };

        if (empty(atts.assocDoc)) {
            atts.assocDoc = self.doc.identifier;
        }
        assoc.assocDoc = atts.assocDoc;

        if (!empty(atts.identifier)) {
            assoc.identifier = atts.identifier; // this isn't really needed
        }

        if (!empty(atts.seq)) {
            assoc.seq = atts.seq;
        }

        if (!empty(atts.groupId)) {
            // note that if groupId is null (meaning default group), we don't store it; this is on purpose
            assoc.groupId = atts.groupId;
        }

        // if we got origin and/or dest already pre-formatted, just store them
        if (!empty(atts.origin)) {
            assoc.origin = atts.origin;
        }
        if (!empty(atts.dest)) {
            assoc.dest = atts.dest;
        }

        // otherwise we should have got an originItem and/or destItem
        if (!empty(atts.originItem)) {
            assoc.origin = {
                "doc": self.doc.identifier,
                "item": atts.originItem.identifier,
                "uri": atts.originItem.identifier
            };
        }
        if (!empty(atts.destItem)) {
            if (atts.destItem === self.doc) {
                assoc.dest = {
                    "doc": self.doc.identifier,
                    "item": self.doc.identifier,
                    "uri": self.doc.identifier
                };
            } else {
                assoc.dest = {
                    "doc": self.doc.identifier,
                    "item": atts.destItem.identifier,
                    "uri": atts.destItem.identifier
                };
            }
        }

        // push onto assocs array
        self.assocs.push(assoc);

        // add to assocIdHash
        self.assocIdHash[assoc.id] = assoc;

        // add to assocHash if we got an identifier
        if (!empty(assoc.identifier)) {
            self.assocHash[assoc.identifier] = assoc;
        }

        // add to the origin's item record in this document, creating an "assocsOnly" record if necessary
        if (empty(self.itemHash[assoc.origin.item])) {
            self.itemHash[assoc.origin.item] = {
                "assocsOnly": true,
                assocs: []
            }
        }
        self.itemHash[assoc.origin.item].assocs.push(assoc);

        return assoc;
    };

    self.addInverseAssociation = function(a) {
        if (a.type !== "exemplar" && !empty(a.dest.item)) {
            let destItem = apx.allItemsHash[a.dest.item];
            if (!empty(destItem) && !empty(destItem.doc)) {
                destItem.doc.addAssociation({
                    "inverse": true,
                    "id": a.id + "-R",
                    "identifier": a.identifier + "-R",
                    "groupId": a.groupId,
                    "assocDoc": self.doc.identifier,
                    "type": a.type,
                    "origin": a.dest,   // switch origin and dest
                    "dest": a.origin,    // switch origin and dest
                    "subtype": a.subtype || null,
                    "annotation": a.annotation || null
                });
            }
        }
    };

    self.deleteAssociation = function(assocId) {
        let assoc = self.assocIdHash[assocId];
        // if the assoc exists...
        if (!empty(assoc)) {
            // splice it from the self.assocs array and the hashes
            for (let j = 0; j < self.assocs.length; ++j) {
                let a = self.assocs[j];
                if (a === assoc) {
                    // delete it from assocHash and assocIdHash, and splice it from the assocs array
                    if (!empty(a.identifier)) {
                        delete self.assocHash[a.identifier];
                    }
                    delete apx.mainDoc.assocIdHash[a.id];
                    apx.mainDoc.assocs.splice(j, 1);
                    break;
                }
            }

            // and remove it from the originItem's assocs array
            let item = self.itemHash[assoc.origin.item];
            if (!empty(item)) {
                for (let j = 0; j < item.assocs.length; ++j) {
                    if (item.assocs[j] === assoc) {
                        item.assocs.splice(j, 1);
                        break;
                    }
                }
            }

            // also try to remove its inverse association
            assocId += "";
            if (assocId.indexOf("-R") > -1) {
                self.deleteAssociation(assocId.replace(/-R/, ""));
            } else {
                self.deleteAssociation(assocId + "-R");
            }
        }
    };

    self.openAssociationItem = function(el, fromAssocView) {
        let assocId = $(el).attr("data-association-id");
        let assocIdentifier = $(el).attr("data-association-identifier");
        let assocItem = $(el).attr("data-association-item");

        // try to find the assoc, in either mainDoc or treeDoc1
        let assoc;
        // look via the assocIdentifier
        if (!empty(assocIdentifier)) {
            assoc = apx.mainDoc.assocHash[assocIdentifier];
            if (empty(assoc)) {
                assoc = apx.treeDoc1.assocHash[assocIdentifier];
            }
        }

        // if we didn't find it via the assocIdentifier, look via assocId
        if (empty(assoc)) {
            assoc = apx.mainDoc.assocIdHash[assocId];
            if (empty(assoc)) {
                assoc = apx.treeDoc1.assocIdHash[assocId];
            }
        }
        console.log("openAssociationItem", assoc);
        if (!empty(assoc)) {
            // when the specified "item" is a url (this should be the case for exemplars, and possibly other items), open url in new window
            if (assoc[assocItem].item.search(/^http(s)?:/) === 0) {
                window.open(assoc[assocItem].item);
                // return false to signal that we opened in another window
                return false;

            // if the item is in the treeDoc1, redirect to the item
            } else if (assoc[assocItem].doc == apx.treeDoc1.doc.identifier) {
                let destItem = apx.treeDoc1.itemHash[assoc[assocItem].item];
                if (!empty(destItem)) {
                    // switch assocGroup if it's different than the current assocGroup
                    if (assoc.groupId != apx.treeDoc1.currentAssocGroup) {
                        apx.treeDoc1.setCurrentAssocGroup(assoc.groupId);
                        apx.treeDoc1.ftRender1();
                    }
                    apx.treeDoc1.setCurrentItem(destItem);
                    apx.pushHistoryState();
                    apx.treeDoc1.activateCurrentItem();
                }
                // return true to signal that we did something in this window
                return true;

            // else try to open the item in a new window
            } else {
                let doc = apx.allDocs[assoc[assocItem].doc];
                if (typeof(doc) === "object") {
                    let url = doc.getItemUri(doc.itemHash[assoc[assocItem].item]);
                    if (url !== "?") {
                        window.open(url);
                        // return false to signal that we opened in another window
                        return false;
                    }
                }
            }
        }

        // if we make it to here return false to signal that nothing happened
        return false;
    };


    self.addItem = function(item) {
        self.items.push(item);
        self.itemHash[item.identifier] = item;
        if (!empty(item.id)) {
            self.itemIdHash[item.id] = item;
        }

        // note that this is an item
        item.nodeType = "item";

        // add reference to the document
        item.doc = self;

        // add assocs array for the item
        item.assocs = [];

        // add to allItemsHash (if it's not already there -- it shouldn't be)
        if (empty(apx.allItemsHash[item.identifier])) {
            apx.allItemsHash[item.identifier] = item;
        } else {
            console.log("item already existed in allItemsHash", item);
        }

        return item;
    };

    self.addNewItemData = function(data) {
        if ("undefined" === typeof apx.mainDoc.itemIdHash[data.id]) {
            // add item and association, then reload tree (and current item?)
            let item = apx.mainDoc.addItem(data);

            // and create and add the isChildOf association and its inverse
            if ("undefined" !== typeof item.newAssoc) {
                let atts = {
                    "id": item.newAssoc.assocId,
                    "identifier": item.newAssoc.identifier,
                    "originItem": item,
                    "type": "isChildOf",
                    "destItem": apx.mainDoc.currentItem,
                    "groupId": apx.mainDoc.currentAssocGroup
                };
                if ("undefined" !== typeof item.newAssoc.assocDoc) {
                    atts['assocDoc'] = item.newAssoc.assocDoc;
                }
                if ("undefined" !== typeof item.newAssoc.dest) {
                    atts['dest'] = item.newAssoc.dest;
                    delete atts.destItem;
                }
                if ("undefined" !== typeof item.newAssoc.seq) {
                    atts['seq'] = item.newAssoc.seq;
                }
                if ("undefined" !== typeof item.newAssoc.groupId) {
                    atts['groupId'] = item.newAssoc.groupId;
                }

                delete item.newAssoc;

                let a = apx.mainDoc.addAssociation(atts);
                apx.mainDoc.addInverseAssociation(a);
            }

            // re-render the tree and re-activate the item
            if ("tree" === apx.viewMode.currentView) {
                apx.treeDoc1.ftRender1();
                apx.treeDoc1.activateCurrentItem();
            }
        }
    };

    self.treeCheckboxToggleAll = function(val, side) {
        let $cb = self["ft" + side].closest(".treeSide").find(".treeCheckboxControl");

        // if this is the first click for this tree, enable checkboxes on the tree
        if ($cb.data("checkboxesEnabled") !== "true") {
            self.treeCheckboxToggleCheckboxes(true, side);

        // else toggle select all
        } else {
            if (empty(val)) {
                val = $cb.is(":checked");
            }

            // determine if something is entered in the search bar
            let searchEntered = false;
            let $filter = self["ft" + side].closest("section").find(".treeFilter");
            if ($filter.length > 0) {
                searchEntered = ($filter.val() !== "");
            }

            // PW 10/11/2017: Only check the top-level items (issues #116 and #204)
            let topChildren = self.getFt(side).rootNode.children[0].children;
            if (!empty(topChildren)) {
                for (let i = 0; i < topChildren.length; ++i) {
                    let node = topChildren[i];
                    // don't select unselectable nodes; also don't select the "Orphaned Items" node
                    if (node.unselectable !== true && node.key !== "orphans") {
                        // if either (we're not filtering) or (the node matches the filter) or (val is false),
                        if (searchEntered === false || node.match === true || val == false) {
                            // set selected to val
                            node.setSelected(val);
                        }
                    }
                }
            }
        }
    };

    self.treeCheckboxToggleCheckboxes = function(val, side) {
        let $cb = self["ft" + side].closest(".treeSide").find(".treeCheckboxControl");
        if (val === true) {
            self["ft" + side].fancytree("getTree").rootNode.checkbox = true;
            self["ft" + side].fancytree("option", "checkbox", true);
            self["ft" + side].fancytree("option", "selectMode", 2);

            // show the menu
            $cb.closest(".input-group").find(".dropdown-toggle").show();

            // mark the cb as enabled
            $cb.data("checkboxesEnabled", "true");

            // reset cb to off
            $cb.prop("checked", false);

        } else {
            self["ft" + side].fancytree("option", "checkbox", false);
            self["ft" + side].fancytree("option", "selectMode", 1);

            // hide the menu
            $cb.closest(".input-group").find(".dropdown-toggle").hide();

            // mark the cb as not enabled
            $cb.data("checkboxesEnabled", "false");

            // reset cb to off
            $cb.prop("checked", false);
        }
    };

    /** restore checkboxes after tree has been redrawn */
    self.treeCheckboxRestoreCheckboxes = function(side) {
        let $cb = self["ft" + side].closest(".treeSide").find(".treeCheckboxControl");
        if ($cb.data("checkboxesEnabled") === "true") {
            self.treeCheckboxToggleCheckboxes(true, side);
        }
    };

    self.treeCheckboxMenuItemSelected = function($menu, side) {
        // get all selected items
        let items = [];
        self["ft" + side].fancytree("getTree").visit(function(node) {
            if (node.selected === true && node.unselectable !== true) {
                items.push(node.data.ref);
            }
        });

        let cmd = $menu.attr("data-cmd");
        if (cmd !== "hideCheckboxes" && items.length === 0) {
            alert("Select one or more items using the checkboxes before choosing a menu item.");
            return;
        }

        if (cmd === "edit") {
            alert("The ability to edit properties of multiple items at the same time is not yet implemented.");
        } else if (cmd === "delete") {
            apx.edit.deleteItems(items);
        } else if (cmd === "makeFolders") {
            self.toggleFolders(items, true);
        } else {    // hideCheckboxes
            // clear checkbox selections
            let $cb = self["ft" + side].closest(".treeSide").find(".treeCheckboxControl");
            self.treeCheckboxToggleAll(false, side);
            self.treeCheckboxToggleCheckboxes(false, side);
        }
    };

    self.initializeTreeFilter = function(side) {
        let debounce = (function() {
            let timeout = null;
            return function(callback, wait) {
                if (timeout) { clearTimeout(timeout); }
                timeout = setTimeout(callback, wait);
            };
        })();

        let $treeside = self["ft" + side].closest(".treeSide");

        $treeside.find(".treeFilter").off().on('keyup', function() {
            let $that = $(this);
            let $tree = self.getFt(side);
            debounce(function(){
                if ($that.val().trim().length > 0) {
                    $tree.filterNodes($that.val(), {
                        autoExpand: true,
                        leavesOnly: false,
                        highlight: false
                    });
                    $that.parent().find(".filterClear").show();

                } else {
                    $tree.clearFilter();
                    $that.parent().find(".filterClear").hide();
                }
            }, 500);
        });

        // clear buttons for search fields
        $treeside.find(".filterClear").off().on('click', function() {
            $(this).parent().find(".treeFilter").val("").trigger("keyup");
        });
    };

    /** Activate the currentItem in the left-side tree */
    self.activateCurrentItem = function(item) {
        self.getFt(1).activateKey(self.currentItem.identifier);
    };

    /** Show the currentItem on the right side */
    self.showCurrentItem = function() {
        // clear apx.unknownAssocsShowing
        apx.unknownAssocsShowing = {};

        let item = self.currentItem;

        let $jq = $("#itemInfo");

        function showDocument() {
            // show title and appropriate icon
            let title = render.block(item.title);
            if (!empty(item.version)) {
                title = '<span style="float:right" class="lessImportant">Version ' + render.escaped(item.version) + '</span>' + title;
            }
            $jq.find(".itemTitleSpan").html(title);
            $jq.find(".itemTitleIcon").attr("src", "/assets/img/doc.png");

            /////////////////////////////////////
            // Show item details
            let html = "";
            let key, attributes, val;
            for (key in attributes = {
                'officialSourceURL': 'Official URL',
                'identifier': 'Identifier',
                'creator': 'Creator',
                'description': 'Description',
                'subjects': 'Subject',
                'language': 'Language',
                'adoptionStatus': 'Adoption Status',
                'note': 'Notes'
            }) {
                if (!empty(item[key])) {
                    val = item[key];
                    // TODO: check these exceptions
                    if (key === 'creator' && !empty(item.publisher)) {
                        val += '<span class="lessImportant">Publisher: ' + render.escaped(item.publisher) + '</span>';
                    } else if (key === 'adoptionStatus') {
                        if (!empty(item.statusStart)) {
                            val += '<span class="lessImportant">From: ' + render.escaped(item.statusStart) + '</span>';
                        }
                        if (!empty(item.statusEnd)) {
                            val += '<span class="lessImportant">Until: ' + render.escaped(item.statusEnd) + '</span>';
                        }
                    } else if (key === 'subjects') {
                        val = "";
                        for (let subject in val) {
                            if (val !== "") {
                                val += ", ";
                            }
                            val += render.escaped(subject.title);
                        }
                    } else if (key === 'officialSourceURL') {
                        val = render.inlineLinked(val);

                        // add target=_blank
                        let $val = $('<div>' + val + '</div>');
                        $('a', $val).attr('target', '_blank');
                        val = $val.html();
                    } else if (key === 'identifier') {
                        val = $('<div>').append(
                            $('<a>', {
                                href: apx.path.uri.replace('ID', val),
                                text: render.escaped(val)
                            })
                        ).html();
                    } else {
                        val = render.escaped(val);
                    }

                    html += '<li class="list-group-item">'
                        + '<strong>' + attributes[key] + ':</strong> '
                        + val
                        + '</li>'
                    ;
                }
            }

            // render license information in case of framework has one.
            if ("undefined" !== typeof self.licenses && self.licenses.length > 0) {
                let licenseDoc = self.licenses;
                let licenseText = licenseDoc[0].licenseText;

                html += '<li class="list-group-item">'
                + '<strong>License:</strong> '
                + render.escaped(licenseDoc[0].title);

                if ( licenseText.length > 0) {
                    html += ' - <i>'
                    + render.escaped(licenseText)
                    + '</i>';
                }

                html += '</li>';
            }
            $jq.find("ul").html(html);

            // kill any existing associations from the dom
            $(".lsItemAssociations").html("");

            // show documentOptions and hide itemOptions and more info link
            $("#itemOptions").hide();
            $(".lsItemDetailsMoreInfoLink").hide();

            if ("undefined" !== typeof apx.locks) {
                $.each([
                    '#editDocModal',
                    '#manageAssocGroupsModal',
                    '#addNewChildModal',
                    '#addChildrenModal',
                    '#updateFrameworkModal'
                ], function (i, button) {
                    if ("undefined" !== typeof apx.locks['docs'][apx.lsDocId]
                        && false !== apx.locks['docs'][apx.lsDocId]) {
                        $('button[data-bs-target="' + button + '"]')
                            .attr('disabled', 'disabled')
                            .addClass('disabled');
                    } else {
                        $('button[data-bs-target="' + button + '"]')
                            .prop('disabled', false)
                            .removeClass('disabled');
                    }
                });
            }

            $("#documentOptions").show();
        }

        function showItem() {
// show title and appropriate icon
            $jq.find(".itemTitleSpan").html(self.getItemTitle(item));
            if (item.setToParent === true || (!empty(item.ftNodeData) && item.ftNodeData.children.length > 0)) {
                $jq.find(".itemTitleIcon").attr("src", "/assets/img/folder.png");
            } else {
                $jq.find(".itemTitleIcon").attr("src", "/assets/img/item.png");
            }

            // show item details
            let html = "";
            let key, attributes, val;
            for (key in attributes = {
                'fstmt': 'Full Statement',
                'identifier': 'Identifier',
                'ck': 'Concept Keywords',
                'el': 'Education Level',
                'itp': 'Type',
                'notes': 'Notes'
            }) {
                if (!empty(item[key])) {
                    val = item[key];
                    if (key === 'fstmt' || key === 'notes') {
                        html += '<li class="list-group-item markdown-body">'
                            + '<strong>' + attributes[key] + ':</strong> '
                            + render.block(val)
                            + '</li>'
                        ;
                    } else if (key === 'identifier') {
                        val = $('<div>').append(
                            $('<a>', {
                                href: apx.path.uri.replace('ID', val),
                                text: render.escaped(val)
                            })
                        ).html();

                        html += '<li class="list-group-item">'
                            + '<strong>' + attributes[key] + ':</strong> '
                            + '<span class="item-' + key + '">'
                              + val
                              + '</span>'
                            + '</li>'
                        ;
                    } else {
                        // TODO: deal with ck, el, itp
                        html += '<li class="list-group-item">'
                            + '<strong>' + attributes[key] + ':</strong> '
                            + '<span class="item-' + key + '">'
                              + render.escaped(val)
                              + '</span>'
                            + '</li>'
                        ;
                    }
                }
            }

            for (key in attributes = {
                'le': 'List Enumeration in Source',
                'cku': 'Concept Keywords URI',
                'lang': 'Language',
                'licenceUri': 'Licence URI',
                'mod': 'Last Changed'
            }) {
                if (!empty(item[key])) {
                    val = item[key];

                    // TODO: deal with cku, licenceUri
                    // for uri, get it from the ApxDocument

                    val = render.escaped(val);

                    html += $('<div>').append(
                        $('<li>', {
                            'class': 'list-group-item lsItemDetailsExtras'
                        }).append(
                            $('<strong>', {
                                text: attributes[key] + ':'
                            }),
                            ' ',
                            val
                        )
                    ).html();
                }
            }

            if ('customFields' in item) {
                for (key in item.customFields) {
                    html += '<li class="list-group-item lsItemCustomFields lsItemDetailsExtras">'
                        + '<strong>' + key + ':</strong> '
                        + render.escaped(item.customFields[key])
                        + '</li>'
                    ;
                }
            }
            $jq.find("ul").html(html);

            /////////////////////////////////////
            // Show associations

            // first create an array with a combination of the this item's associations from this document,
            // along with any associations with this item as the origin in mainDoc (if mainDoc != self)
            let assocs = [];
            for (let i = 0; i < item.assocs.length; ++i) {
                assocs.push(item.assocs[i]);
            }
            if (self !== apx.mainDoc) {
                let mdi = apx.mainDoc.itemHash[item.identifier];
                if (!empty(mdi)) {
                    for (let i = 0; i < mdi.assocs.length; ++i) {
                        assocs.push(mdi.assocs[i]);
                    }
                }
            }

            // now if we have any assocs go through them...
            html = "";
            if (assocs.length > 0) {
                // first sort the assocs by type; put isChildOf at the end
                assocs.sort(function (a, b) {
                    let aSubtype = '';
                    let bSubtype = '';
                    if (!empty(a.subtype)) {
                        aSubtype = a.subtype;
                    }
                    if (!empty(b.subtype)) {
                        bSubtype = b.subtype;
                    }

                    if (a.type === b.type && aSubtype === bSubtype && a.inverse === b.inverse) { return 0; }
                    if (a.type === "isChildOf") { return 1; }
                    if (b.type === "isChildOf") { return -1; }
                    if (a.inverse === true && b.inverse !== true) { return 1; }
                    if (b.inverse === true && a.inverse !== true) { return -1; }
                    if (a.type < b.type) { return -1; }
                    if (a.type > b.type) { return 1; }
                    if (aSubtype < bSubtype) { return -1; }
                    if (aSubtype > bSubtype) { return 1; }
                    return 0;
                });

                // to simplify the list, we only use one association type header for each type
                let lastType = "";
                let lastInverse = -1;
                for (let i = 0; i < assocs.length; ++i) {
                    let a = assocs[i];

                    if (a.type === 'isChildOf') {
                        continue;
                    }

                    let nextType = a.type;
                    let subtype = '';
                    if (!empty(a.subtype)) {
                        nextType += ': ' + a.subtype;
                        subtype = ': ' + a.subtype;
                    }

                    if (nextType !== lastType || a.inverse !== lastInverse) {
                        // close previous type section if we already opened one
                        if (lastType !== "") {
                            html += '</div></div></div></section>';
                        }

                        // open type section
                        let title = self.getAssociationTypePretty(a) + subtype;
                        let icon = "";
                        if (a.type !== "isChildOf") {
                            icon = '<img class="association-card-icon" src="/assets/img/association-icon.png">';
                        }
                        html += '<section class="card card-default card-component item-component">'
                            + '<div class="card-header">' + icon + render.escaped(title) + '</div>'
                            + '<div class="card-body"><div><div class="list-group">'
                        ;

                        lastType = nextType;
                        lastInverse = a.inverse;
                    }

                    // now the associated item

                    // determine if the origin item is a member of the edited doc or an other doc
                    let originDoc = "edited";
                    let removeBtn = $("#associationRemoveBtn").html();  // remove association button (only for editors)
                    let editBtn = $("#associationEditBtn").html();  // remove association button (only for editors)
                    if (a.assocDoc !== apx.mainDoc.doc.identifier) {
                        originDoc = "other";
                        // if it's another doc, no remove btn
                        removeBtn = '';
                        editBtn = '';
                    }
                    if ('exemplar' === a.type || 'isChildOf' === a.type) {
                        editBtn = '';
                    }

                    // assocGroup if assigned -- either in self or mainDoc
                    if (!empty(a.groupId)) {
                        let groupName = "Group " + a.groupId;
                        if (originDoc === "edited") {
                            if (!empty(apx.mainDoc.assocGroupIdHash[a.groupId])) {
                                groupName = self.assocGroupIdHash[a.groupId].title;
                            }

                        } else {
                            if (!empty(self.assocGroupIdHash[a.groupId])) {
                                groupName = self.assocGroupIdHash[a.groupId].title;
                            }
                        }
                        html += '<span class="label label-default">' + render.escaped(groupName) + '</span>';
                    }

                    let annotation = '';
                    if (!empty(a.annotation)) {
                        annotation = a.annotation;
                    }
                    html += '<a data-association-id="' + a.id + '" data-association-identifier="' + a.identifier + '" data-association-item="dest" class="list-group-item lsassociation lsitem clearfix lsassociation-' + originDoc + '-doc">'
                        + removeBtn
                        + editBtn
                        + '<span class="itemDetailsAssociationTitle '+('' !== annotation ? 'annotated' : '')+'" title="'+annotation+'">'
                        + self.associationDestItemTitle(a)
                        + '</span>'
                        + '</a>'
                    ;
                }
                // close final type section
                html += '</div></div></div></section>';
            }
            // End of code composing associations
            $(".lsItemAssociations").html(html);

            // hide enable hidden fields if necessary and restore last more info link state
            if (!self.moreInfoShowing) {
                $jq.find(".lsItemDetailsExtras").hide();
            }
            apx.toggleMoreInfo("restore");

            // make sure make folder and create new item buttons are set appropriately
            self.toggleItemCreationButtons();

            // enable association links
            $jq.find("[data-association-identifier]").on('click', function (e) {
                apx.treeDoc1.openAssociationItem(this, false);
            });

            // enable edit association button(s)
            $jq.find('.btn-edit-association').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                let $target = $($(e.target).closest('a'));
                $('#editAssociationModal').modal('show', $target);

                return false;
            });

            // enable remove association button(s)
            $jq.find(".btn-remove-association").on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // get the assocId from the association link
                let $target = $(e.target);
                let $item = $target.parents('.lsassociation');
                let assocId = $item.attr('data-association-id');

                // call edit.deleteAssociation; on callback, re-show the current item
                apx.edit.deleteAssociation(assocId, function () {
                    apx.treeDoc1.showCurrentItem();
                });
                return false;
            });

            // hide documentOptions and show itemOptions and the more info link
            $("#documentOptions").hide();

            if ("undefined" !== typeof apx.locks) {
                $.each([
                    '#editItemModal',
                    '#addNewChildModal',
                    '#addExemplarModal'
                ], function (i, button) {
                    if ("undefined" !== typeof apx.locks['items'][item.id]
                        && false !== apx.locks['items'][item.id]) {
                        $('button[data-bs-target="' + button + '"]')
                            .attr('disabled', 'disabled')
                            .addClass('disabled');
                    } else {
                        $('button[data-bs-target="' + button + '"]')
                            .prop('disabled', false)
                            .removeClass('disabled');
                    }
                });
                $.each([
                    '#deleteItemBtn',
                    '#toggleFolderBtn'
                ], function (i, button) {
                    if ("undefined" !== typeof apx.locks['items'][item.id]
                        && false !== apx.locks['items'][item.id]) {
                        $(button)
                            .prop('disabled', true)
                            .addClass('disabled');
                    } else {
                        $(button)
                            .prop('disabled', false)
                            .removeClass('disabled');
                    }
                });
            }
            $("#itemOptions").show();

            $(".lsItemDetailsMoreInfoLink").show();
        }

        if (item.nodeType === "document") {
            showDocument();
        } else {
            showItem();
        }
    };

    /** Compose the title for the destination of an association item in the item details view */
    self.associationDestItemTitle = function(a) {
        // set default title
        let title;
        if (!empty(a.dest.uri)) {
            title = a.dest.uri;

            if (0 === title.lastIndexOf("data:text/x-", 0)) {
                // If the destination is a data URI then try to handle it nicer
                let uri = title.substring(12);
                let data = uri.split(',', 2);

                if (/;base64[;,]/.test(data[0])) {
                    title = decodeURIComponent(atob(data[1]).split('').map(function(c) {
                        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                    }).join(''));
                } else {
                    title = decodeURIComponent(data[1]);
                }
            }
        } else if (!empty(a.dest.item)) {
            title = a.dest.item;
        } else if (!empty(a.dest.title)) {
            title = a.dest.title;
        } else {
            title = "Destination";
        }

        let doc = null;

        // if the assoc is an exemplar, title is always the uri
        if (a.type === "exemplar") {
            title = a.dest.uri;

        // else see if the "item" is actually a document
        } else if (!empty(apx.allDocs[a.dest.item]) && typeof(apx.allDocs[a.dest.item]) !== "string") {
            title = "Document: " + apx.allDocs[a.dest.item].doc.title;

        // else if we know about this item via allItemsHash...
        } else if (!empty(apx.allItemsHash[a.dest.item])) {
            let destItem = apx.allItemsHash[a.dest.item];
            title = self.getItemTitle(destItem, true);
            doc = destItem.doc;

        // else we don't (currently at least) know about this item...
        } else {
            // so add the association to apx.unknownAssocsShowing; if info about the item is loaded later, it'll get filled in
            apx.unknownAssocsShowing[a.id] = a;

            if (a.dest.doc !== "?") {
                // look for document in allDocs
                doc = apx.allDocs[a.dest.doc];

                // if we tried to load this document and failed, note that
                if (doc === "loaderror") {
                    title += " (document could not be loaded)";

                // else if we know we're still in the process of loading that doc, note that
                } else if (doc === "loading") {
                    title += " (loading document...)";

                // else we have the doc -- this shouldn't normally happen, because if we know about the doc,
                // we should have found the item in apx.allItemsHash above
                } else if (typeof(doc) === "object") {
                    title += " (item not found in document)";
                }
            }
        }

        // if item comes from another doc, note that
        if (!empty(doc) && typeof(doc) === "object" && doc !== self) {
            let docTitle = doc.doc.title;
            if (docTitle.length > 60) {
                docTitle = docTitle.substr(0, 65);
                docTitle = docTitle.replace(/\w+$/, "");
                docTitle += "…";
            }
            title += ' <span class="label label-default">' + render.escaped(docTitle) + '</span>';
        }

        return title;
    };

    self.toggleItemCreationButtons = function() {
        // if mainDoc isn't showing in tree1 slot, don't do this
        if (apx.treeDoc1 != apx.mainDoc) {
            return;
        }

        let $jq = $("#itemInfo");
        let item = self.currentItem;
        // if item already has children
        if (!empty(item.ftNodeData) && item.ftNodeData.children.length > 0) {
            // hide "Make this item a folder" button
            $jq.find("[id=toggleFolderBtn]").hide();
            // and show the "Add a new child item" button
            $jq.find("[id=addChildBtn]").show();

        // else item doesn't have children
        } else {
            // show "Make this item a folder" button
            $jq.find("[id=toggleFolderBtn]").show();

            // set the text of the toggleFolderBtn and visibility of the addChildBtn appropriately
            if (item.setToParent === true) {
                $jq.find("[id=toggleFolderBtn]").text("Make This Item a Child");
                $jq.find("[id=addChildBtn]").show();
            } else {
                $jq.find("[id=toggleFolderBtn]").text("Make This Item a Parent");
                $jq.find("[id=addChildBtn]").hide();
            }
        }
    };

    self.toggleFolders = function(items, val) {
        if (!Array.isArray(items)) {
            items = [self.currentItem];
        }
        if (typeof(val) !== "boolean") {
            val = "toggle";
        }
        for (let i = 0; i < items.length; ++i) {
            let item = items[i];
            // can't change anything that has children already
            if (item.ftNodeData.children.length > 0) {
                continue;
            }

            if (val === "toggle") {
                item.setToParent = !(item.setToParent === true);
            } else {
                item.setToParent = val;
            }
            let ftNode = self.getFtNode(item, 1);
            ftNode.folder = item.setToParent;
            ftNode.render();
            if (item.setToParent === true) {
                $(ftNode.li).find(".fancytree-icon").addClass("fancytree-force-folder");
            } else {
                $(ftNode.li).find(".fancytree-icon").removeClass("fancytree-force-folder");
            }

            // if this is the currentItem, update the icon
            if (item == self.currentItem) {
                let src;
                if (item.setToParent) {
                    src = "/assets/img/folder.png";
                } else {
                    src = "/assets/img/item.png";
                }
                $("#itemInfo").find(".itemTitleIcon").attr("src", src);
            }
        }

        self.toggleItemCreationButtons();
    };
}

export default ApxDocument;
