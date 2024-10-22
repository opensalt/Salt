import * as boilerplate from 'html5-boilerplate/dist/js/plugins';

import $ from 'jquery';
globalThis.$ = globalThis.jQuery = $;

import jQueryMigrate from 'jquery-migrate';
import jQueryUi from 'jquery-ui';
import bootstrapSass from 'bootstrap-sass/assets/javascripts/bootstrap';
import fancytree from 'jquery.fancytree/dist/jquery.fancytree-all';

import check from './session';
check();
