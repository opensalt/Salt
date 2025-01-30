apx = window.apx = window.apx||{};
apx.allDocs = {};
apx.allItemsHash = {};

import initTrees from './view-trees';
import initEdit from './view-edit';
import initViewMode from './view-modes';
import initViewX from './viewx';
import initCopy from './copy-framework';
import ob3Credential from '../credential';

initTrees(apx);
initEdit(apx);
initViewMode(apx);
initViewX(apx);
initCopy(apx);

export default apx;
