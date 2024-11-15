<?php

namespace App\Security;

class Permission
{
    final public const string COMMENT_ADD = 'comment';
    final public const string COMMENT_VIEW = 'comment_view';
    final public const string COMMENT_UPDATE = 'comment_update';
    final public const string COMMENT_DELETE = 'comment_delete';

    final public const string FRAMEWORK_CREATE = 'framework_create';
    final public const string FRAMEWORK_LIST = 'list';
    final public const string FRAMEWORK_VIEW = 'view';
    final public const string FRAMEWORK_EDIT = 'edit';
    final public const string FRAMEWORK_EDIT_ALL = 'framework_edit_all';
    final public const string FRAMEWORK_DELETE = 'delete';

    final public const string FRAMEWORK_DOWNLOAD_EXCEL = 'framework_download_excel';

    final public const string ITEM_EDIT = 'edit';
    final public const string ITEM_ADD_TO = 'add-standard-to';

    final public const string ASSOCIATION_ADD_TO = 'add-association-to';
    final public const string ASSOCIATION_EDIT = 'edit';

    final public const string ADDITIONAL_FIELDS_MANAGE = 'manage_additional_fields';

    final public const string MANAGE_MIRRORS = 'manage_mirrors';

    final public const string MANAGE_ORGANIZATIONS = 'manage_organizations';

    final public const string MANAGE_USERS = 'manage_users';
    final public const string MANAGE_THIS_USER = 'manage_user';
    final public const string MANAGE_ALL_USERS = 'manage_all_users';

    final public const string MANAGE_EDITORS = 'manage_editors';

    final public const string MANAGE_SYSTEM_LOGS = 'manage_system_logs';

    final public const string FEATURE_DEV_ENV_CHECK = 'feature_dev_env';

    final public const string CREDENTIAL_DEF_VIEW = 'credential_def_view';
    final public const string CREDENTIAL_DEF_LIST = 'credential_def_list';
    final public const string CREDENTIAL_DEF_CREATE = 'credential_def_create';
    final public const string CREDENTIAL_DEF_EDIT = 'credential_def_edit';
    final public const string CREDENTIAL_DEF_EDIT_ALL = 'credential_def_edit_all';
    final public const string CREDENTIAL_DEF_DELETE = 'credential_def_delete';

    final public const string FRONT_MATTER_VIEW = 'front_matter_view';
    final public const string FRONT_MATTER_LIST = 'front_matter_list';
    final public const string FRONT_MATTER_CREATE = 'front_matter_create';
    final public const string FRONT_MATTER_EDIT = 'front_matter_edit';
    final public const string FRONT_MATTER_EDIT_ALL = 'front_matter_edit_all';
    final public const string FRONT_MATTER_DELETE = 'front_matter_delete';

    final public const string ISSUER_REGISTRY_LIST = 'issuer_registry_list';
    final public const string ISSUER_REGISTRY_VIEW = 'issuer_registry_view';
    final public const string ISSUER_REGISTRY_ADD = 'issuer_registry_add';
    final public const string ISSUER_REGISTRY_EDIT = 'issuer_registry_edit';
    final public const string ISSUER_REGISTRY_DELETE = 'issuer_registry_delete';
}
