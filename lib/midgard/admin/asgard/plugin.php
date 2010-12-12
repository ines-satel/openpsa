<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Plugin interface
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_plugin extends midcom_baseclasses_components_plugin
{
    public function _on_initialize()
    {
        $_MIDCOM->load_library('midgard.admin.asgard');
        $_MIDCOM->auth->require_user_do('midgard.admin.asgard:access', null, 'midgard_admin_asgard_plugin');
        // Disable content caching
        $_MIDCOM->cache->content->no_cache();

        // Preferred language
        if (($language = midgard_admin_asgard_plugin::get_preference('interface_language')))
        {
            $_MIDCOM->i18n->set_language($language);
        }
    }

    /**
     * Static method other plugins may use
     *
     * @param string $title     Page title
     * @param array &$data      Local request data
     */
    public static function prepare_plugin($title, &$data)
    {
        $_MIDCOM->auth->require_user_do('midgard.admin.asgard:access', null, 'midgard_admin_asgard_plugin');
        // Disable content caching
        $_MIDCOM->cache->content->no_cache();
        $data['view_title'] = $title;
        $data['asgard_toolbar'] = new midcom_helper_toolbar();

        // Preferred language
        if (($language = midgard_admin_asgard_plugin::get_preference('interface_language')))
        {
            $_MIDCOM->i18n->set_language($language);
        }

        $_MIDCOM->skip_page_style = true;
        $_MIDCOM->style->prepend_component_styledir('midgard.admin.asgard');
        $_MIDCOM->style->prepend_component_styledir(str_replace('asgard_','',$data['plugin_name']));
    }

    function get_type_label($type)
    {
        $ref = midcom_helper_reflector_tree::get($type);
        return $ref->get_class_label();
    }

    /**
     * Static method for binding view to an object
     */
    function bind_to_object($object, $handler_id, &$data)
    {
        // Tell our object to MidCOM
        $_MIDCOM->set_26_request_metadata($object->metadata->revised, $object->guid);
        $data['object_reflector'] = midcom_helper_reflector::get($object);
        $data['tree_reflector'] = midcom_helper_reflector_tree::get($object);

        $data['object'] =& $object;

        // Populate toolbars
        if ($_MIDCOM->dbclassloader->is_midcom_db_object($object))
        {
            // These toolbars only work with DBA objects as they do ACL checks
            $_MIDCOM->bind_view_to_object($object);
            $data['asgard_toolbar'] = midgard_admin_asgard_plugin::get_object_toolbar($object, $handler_id, $data);
        }

        midgard_admin_asgard_plugin::get_common_toolbar($data);

        // Figure out correct title and language handling
        switch ($handler_id)
        {
            case '____mfa-asgard-object_edit':
                $title_string = $_MIDCOM->i18n->get_string('edit %s %s', 'midgard.admin.asgard');
                break;
            case '____mfa-asgard-object_metadata':
                $title_string = $_MIDCOM->i18n->get_string('metadata of %s %s', 'midgard.admin.asgard');
                break;
            case '____mfa-asgard-object_attachments':
            case '____mfa-asgard-object_attachments_edit':
            case '____mfa-asgard-object_attachments_delete':
                $title_string = $_MIDCOM->i18n->get_string('attachments of %s %s', 'midgard.admin.asgard');
                break;
            case '____mfa-asgard-object_parameters':
                $title_string = $_MIDCOM->i18n->get_string('parameters of %s %s', 'midgard.admin.asgard');
                break;
            case '____mfa-asgard-object_permissions':
                // Figure out label for the object's class
                switch (get_class($this->_object))
                {
                    case 'midcom_db_topic':
                        $type = $_MIDCOM->i18n->get_string('folder', 'midgard.admin.asgard');
                        break;
                    default:
                        $type = $data['object_reflector']->get_class_label();
                }
                $title_string = sprintf($_MIDCOM->i18n->get_string('permissions for %s %s', 'midgard.admin.asgard'), $type, midgard_admin_asgard_handler_object_permissions::resolve_object_title($this->_object));
                break;
            case '____mfa-asgard-object_create':
                $title_string = sprintf($_MIDCOM->i18n->get_string('create %s under %s', 'midgard.admin.asgard'), midgard_admin_asgard_plugin::get_type_label($data['new_type_arg']), '%s %s');
                break;
            case '____mfa-asgard-object_delete':
                $title_string = $_MIDCOM->i18n->get_string('delete %s %s', 'midgard.admin.asgard');
                break;
            case '____mfa-asgard-object_rcs_history':
            case '____mfa-asgard-object_rcs_diff':
            case '____mfa-asgard-object_rcs_preview':
                $title_string = $_MIDCOM->i18n->get_string('revision history of %s %s', 'midgard.admin.asgard');
                break;
            default:
                $title_string = $_MIDCOM->i18n->get_string('%s %s', 'midgard.admin.asgard');
                break;
        }
        $label = $data['object_reflector']->get_object_label($object);
        $type_label = midgard_admin_asgard_plugin::get_type_label(get_class($object));
        $data['view_title'] = sprintf($title_string, $type_label, $label);
        $_MIDCOM->set_pagetitle($data['view_title']);
    }

    /**
     * Helper function that sets the default object mode
     */
    public static function get_default_mode(&$data)
    {
        //only set mode once per request
        if (!empty($data['default_mode']))
        {
            return $data['default_mode'];
        }

        if (midcom_baseclasses_components_configuration::get('midgard.admin.asgard', 'config')->get('edit_mode') == 1)
        {
            $data['default_mode'] = 'edit';
        }
        else
        {
            $data['default_mode'] = 'view';
        }

        if (midgard_admin_asgard_plugin::get_preference('edit_mode') == 1)
        {
            $data['default_mode'] = 'edit';
        }
        else
        {
            $data['default_mode'] = 'view';
        }

        return $data['default_mode'];
    }

    /**
     * Helper to construct urls for the toolbar and breadcrumbs
     *
     * @param string $action The action
     * @param string $guid The GUID
     */
    private static function _generate_url($action, $guid)
    {
        $url = '__mfa/asgard/object/' . $action . '/' . $guid . '/';

        return $url;
    }


    /**
     * Populate the object toolbar
     *
     * @param mixed $object        MgdSchema object for which the toolbar will be created
     * @param String $handler_id   Initialized handler id
     * @param array $data          Local request data
     */
    function get_object_toolbar($object, $handler_id, &$data)
    {
        $toolbar = new midcom_helper_toolbar();

        midgard_admin_asgard_plugin::get_default_mode($data);

        // Show view toolbar button, if the user hasn't configured to use straight the edit mode
        if ($data['default_mode'] === 'view')
        {
            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('view', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('view', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/view.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'v',
                )
            );
        }

        if (   !is_a($object, 'midcom_db_style')
            && !is_a($object, 'midcom_db_element')
            && !is_a($object, 'midcom_db_snippetdir')
            && !is_a($object, 'midcom_db_snippet')
            && !is_a($object, 'midcom_db_page')
            && !is_a($object, 'midcom_db_pageelement')
            && !is_a($object, 'midcom_db_parameter')
            && substr($object->__mgdschema_class_name__, 0, 23) != 'org_routamc_positioning'
            && substr($object->__mgdschema_class_name__, 0, 14) != 'net_nemein_tag')
        {
            $link = $_MIDCOM->permalinks->resolve_permalink($object->guid);
            if ($link)
            {
                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $link,
                        MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('view on site', 'midgard.admin.asgard'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_internet.png',
                    )
                );
            }
        }

        if ($object->can_do('midgard:update'))
        {
            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('edit', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('edit', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }

        if ($object->can_do('midgard:create'))
        {
            if (midcom_helper_reflector_tree::get_child_objects($object))
            {
                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => self::_generate_url('copy/tree', $object->guid),
                        MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('copy', 'midcom'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editcopy.png',
                    )
                );
            }
            else
            {
                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => self::_generate_url('copy', $object->guid),
                        MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('copy', 'midcom'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editcopy.png',
                    )
                );
            }
        }

        if ($object->can_do('midgard:update'))
        {
            if (   is_a($object, 'midcom_db_topic')
                && $object->component
                && $object->can_do('midcom:component_config'))
            {
                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => "__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/",
                        MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('component configuration', 'midcom'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_folder-properties.png',
                    )
                );
            }

            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('metadata', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('metadata', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/metadata.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'm',
                )
            );
            /** COPIED from midcom_services_toolbars */
            if ($GLOBALS['midcom_config']['metadata_approval'])
            {
                $metadata = midcom_helper_metadata::retrieve($object);
                if (   $metadata
                    && $metadata->is_approved())
                {
                    $icon = 'stock-icons/16x16/page-approved.png';
                    if (   !$GLOBALS['midcom_config']['show_hidden_objects']
                        && !$metadata->is_visible())
                    {
                        // Take scheduling into account
                        $icon = 'stock-icons/16x16/page-approved-notpublished.png';
                    }
                    $toolbar->add_item
                    (
                        array
                        (
                            MIDCOM_TOOLBAR_URL => "__ais/folder/unapprove/",
                            MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('unapprove', 'midcom'),
                            MIDCOM_TOOLBAR_HELPTEXT => $_MIDCOM->i18n->get_string('approved', 'midcom'),
                            MIDCOM_TOOLBAR_ICON => $icon,
                            MIDCOM_TOOLBAR_POST => true,
                            MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                            (
                                'guid' => $object->guid,
                                'return_to' => $_SERVER['REQUEST_URI'],
                            ),
                            MIDCOM_TOOLBAR_ACCESSKEY => 'u',
                            MIDCOM_TOOLBAR_ENABLED => $object->can_do('midcom:approve'),
                        )
                    );
                }
                else
                {
                    $icon = 'stock-icons/16x16/page-notapproved.png';
                    if (   !$GLOBALS['midcom_config']['show_hidden_objects']
                        && !$metadata->is_visible())
                    {
                        // Take scheduling into account
                        $icon = 'stock-icons/16x16/page-notapproved-notpublished.png';
                    }
                    $toolbar->add_item
                    (
                        array
                        (
                            MIDCOM_TOOLBAR_URL => "__ais/folder/approve/",
                            MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('approve', 'midcom'),
                            MIDCOM_TOOLBAR_HELPTEXT => $_MIDCOM->i18n->get_string('unapproved', 'midcom'),
                            MIDCOM_TOOLBAR_ICON => $icon,
                            MIDCOM_TOOLBAR_POST => true,
                            MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                            (
                                'guid' => $object->guid,
                                'return_to' => $_SERVER['REQUEST_URI'],
                            ),
                            MIDCOM_TOOLBAR_ACCESSKEY => 'a',
                            MIDCOM_TOOLBAR_ENABLED => $object->can_do('midcom:approve'),
                        )
                    );
                }
            }
            /** /COPIED from midcom_services_toolbars */

            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('attachments', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('attachments', 'midgard.admin.asgard'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/attach.png',
                )
            );

            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('parameters', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('parameters', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                    MIDCOM_TOOLBAR_ENABLED => $object->can_do('midgard:parameters'),
                )
            );

            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('permissions', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('privileges', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'midgard.admin.asgard/permissions-16.png',
                    MIDCOM_TOOLBAR_ENABLED => $object->can_do('midgard:privileges'),
                )
            );


            if (   $_MIDCOM->componentloader->is_installed('midcom.helper.replicator')
                && $_MIDCOM->auth->admin)
            {
                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => "__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/",
                        MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('replication information', 'midcom.helper.replicator'),
                        MIDCOM_TOOLBAR_ICON => 'midcom.helper.replicator/replicate-server-16.png',
                        MIDCOM_TOOLBAR_ACCESSKEY => 'r',
                    )
                );
            }
        }

        if ($object->can_do('midgard:create'))
        {
            // Find out what types of children the object can have and show create buttons for them
            $child_types = $data['tree_reflector']->get_child_classes();
            if (!is_array($child_types))
            {
                debug_add("\$data['tree_reflector']->get_child_classes() failed critically, recasting \$child_types as array", MIDCOM_LOG_WARN);
                $child_types = array();
            }
            foreach ($child_types as $type)
            {
                $display_button = true;
                if (is_a($object, 'midcom_db_topic'))
                {
                    // With topics we should check for component before populating create buttons as so many types can be children of topics
                    switch ($type)
                    {
                        case 'midgard_topic':
                        case 'midgard_article':
                            // Articles and topics can always be created
                            break;
                        default:
                            $midcom_dba_classname = $_MIDCOM->dbclassloader->get_midcom_class_name_for_mgdschema_object($type);
                            if (!$midcom_dba_classname)
                            {
                                $display_button = false;
                                break;
                            }
                            $component = $_MIDCOM->dbclassloader->get_component_for_class($type);
                            if ($component != $object->component)
                            {
                                $display_button = false;
                            }
                            break;
                    }
                }
                elseif (   is_a($object, 'midcom_db_article')
                        && $object->topic)
                {
                    $topic = new midcom_db_topic($object->topic);
                    // With articles we should check for topic component before populating create buttons as so many types can be children of topics
                    switch ($type)
                    {
                        case 'midgard_article':
                            // Articles can always be created
                            break;
                        default:
                            $component = $_MIDCOM->dbclassloader->get_component_for_class($type);
                            if ($component != $topic->component)
                            {
                                $display_button = false;
                            }
                            break;
                    }
                }

                if (!$display_button)
                {
                    // Skip this type
                    continue;
                }

                $toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => self::_generate_url('create/' . $type, $object->guid),
                        MIDCOM_TOOLBAR_LABEL => sprintf($_MIDCOM->i18n->get_string('create %s', 'midcom'), midgard_admin_asgard_plugin::get_type_label($type)),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/' . $data['tree_reflector']->get_create_icon($type),
                    )
                );
            }
        }

        if ($object->can_do('midgard:delete'))
        {
            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('delete', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('delete', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'd',
                )
            );
        }

        if (   $GLOBALS['midcom_config']['midcom_services_rcs_enable']
            && $object->can_do('midgard:update')
            && $object->_use_rcs)
        {
            $toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => self::_generate_url('rcs', $object->guid),
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show history'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/history.png',
                    MIDCOM_TOOLBAR_ENABLED => (substr($handler_id, 0, 25) === '____mfa-asgard-object_rcs') ? false : true,
                    MIDCOM_TOOLBAR_ACCESSKEY => 'h',
                )
            );
        }
        $tmp = array();

        $breadcrumb = array();
        $label = $data['object_reflector']->get_object_label($object);
        $breadcrumb[] = array
        (
            MIDCOM_NAV_URL => self::_generate_url('view', $object->guid),
            MIDCOM_NAV_NAME => $label,
        );

        $parent = $object->get_parent();

        if (   is_a($object, 'midcom_db_parameter')
            && is_object($parent)
            && $parent->guid)
        {
            // Add "parameters" list to breadcrumb if we're in a param
            $breadcrumb[] = array
            (
                MIDCOM_NAV_URL => self::_generate_url('parameters', $parent->guid),
                MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('parameters', 'midcom'),
            );
        }

        $i = 0;
        while (   is_object($parent)
               && $parent->guid
               && $i < 10)
        {
            $i++;
            $parent_reflector = midcom_helper_reflector::get($parent);
            $parent_label = $parent_reflector->get_object_label($parent);
            $breadcrumb[] = array
            (
                MIDCOM_NAV_URL => self::_generate_url('view', $parent->guid),
                MIDCOM_NAV_NAME => $parent_label,
            );
            $parent = $parent->get_parent();
        }
        $breadcrumb = array_reverse($breadcrumb);

        switch ($handler_id)
        {
            case '____mfa-asgard-object_view':
                $toolbar->disable_item(self::_generate_url('view', $object->guid));
                break;
            case '____mfa-asgard-object_edit':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('edit', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('edit', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('edit', $object->guid));
                break;
            case '____mfa-asgard-object_copy':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('copy', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('copy', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('copy', $object->guid));
                break;
            case '____mfa-asgard-object_copy_tree':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('copy/tree', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('copy', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('copy/tree', $object->guid));
                break;
            case '____mfa-asgard-components_configuration_edit_folder':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => "__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/",
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('component configuration', 'midcom'),
                );
                $toolbar->disable_item("__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/");
                break;
            case '____mfa-asgard-object_metadata':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('metadata', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('metadata', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('metadata', $object->guid));
                break;
            case '____mfa-asgard-object_attachments':
            case '____mfa-asgard-object_attachments_edit':
            case '____mfa-asgard-object_attachments_delete':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('attachments', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('attachments', 'midgard.admin.asgard'),
                );

                if ($handler_id == '____mfa-asgard-object_attachments_edit')
                {
                    $breadcrumb[] = array
                    (
                        MIDCOM_NAV_URL => "__mfa/asgard/object/attachments/{$object->guid}/edit/",
                        MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('edit', 'midcom'),
                    );
                }
                if ($handler_id == '____mfa-asgard-object_attachments_delete')
                {
                    $breadcrumb[] = array
                    (
                        MIDCOM_NAV_URL => "__mfa/asgard/object/attachments/{$object->guid}/delete/",
                        MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('delete', 'midcom'),
                    );
                }

                $toolbar->disable_item(self::_generate_url('attachments', $object->guid));
                break;
            case '____mfa-asgard-object_parameters':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('parameters', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('parameters', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('parameters', $object->guid));
                break;
            case '____mfa-asgard-object_permissions':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('permissions', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('privileges', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('permissions', $object->guid));
                break;
            case '____mfa-asgard-object_create':
                if ($data['new_type_arg'] == 'midgard_parameter')
                {
                    // Add "parameters" list to breadcrumb if we're creating a param
                    $breadcrumb[] = array
                    (
                        MIDCOM_NAV_URL => self::_generate_url('parameters', $object->guid),
                        MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('parameters', 'midcom'),
                    );
                }
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('create' . $data['new_type_arg'], $object->guid),
                    MIDCOM_NAV_NAME => sprintf($_MIDCOM->i18n->get_string('create %s', 'midcom'), midgard_admin_asgard_plugin::get_type_label($data['new_type_arg'])),
                );
                $toolbar->disable_item(self::_generate_url('create' . $data['new_type_arg'], $object->guid));
                break;
            case '____mfa-asgard-object_delete':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => self::_generate_url('delete', $object->guid),
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('delete', 'midcom'),
                );
                $toolbar->disable_item(self::_generate_url('delete', $object->guid));
                break;
            case '____mfa-asgard_midcom.helper.replicator-object':
                $breadcrumb[] = array
                (
                    MIDCOM_NAV_URL => "__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/",
                    MIDCOM_NAV_NAME => $_MIDCOM->i18n->get_string('replication information', 'midcom.helper.replicator'),
                );
                $toolbar->disable_item("__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/");
                break;
            case '____mfa-asgard-object_rcs_diff':
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "__mfa/asgard/object/rcs/preview/{$this->_object->guid}/{$data['args'][1]}/{$data['args'][2]}",
                    MIDCOM_NAV_NAME => sprintf($this->_l10n->get('differences between %s and %s'), $data['args'][1], $data['args'][2]),
                );

            case '____mfa-asgard-object_rcs_preview':
                if (isset($data['args'][2]))
                {
                    $current = $data['args'][2];
                }
                else
                {
                    $current = $data['args'][1];
                }

                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "__mfa/asgard/object/rcs/preview/{$this->_object->guid}/{$current}/",
                    MIDCOM_NAV_NAME => sprintf($this->_l10n->get('version %s'), $current),
                );

            case '____mfa-asgard-object_rcs_history':
                $tmp[] = array
                (
                    MIDCOM_NAV_URL => "__mfa/asgard/object/rcs/{$this->_object->guid}/",
                    MIDCOM_NAV_NAME => $this->_l10n->get('show history'),
                );

                $tmp = array_reverse($tmp);

                $breadcrumb = array_merge($breadcrumb, $tmp);

                break;
        }

        $_MIDCOM->set_custom_context_data('midcom.helper.nav.breadcrumb', $breadcrumb);

        return $toolbar;
    }

    /**
     * Add Asgard styling for plugins
     */
    public static function asgard_header()
    {
        midcom_show_style('midgard_admin_asgard_header');
        midcom_show_style('midgard_admin_asgard_middle');
    }

    /**
     * Add Asgard styling for plugins
     */
    public static function asgard_footer()
    {
        midcom_show_style('midgard_admin_asgard_footer');
    }

    function get_common_toolbar(&$data)
    {
    }

    /**
     * Get a preference for the current user
     *
     * @param string $preference    Name of the preference
     */
    public static function get_preference($preference)
    {
        static $preferences = array();

        if (!$_MIDCOM->auth->user)
        {
            return;
        }

        if (!isset($preferences[$preference]))
        {
            // Store the person statically
            if (!isset($preferences[$_MIDCOM->auth->user->guid]))
            {
                $preferences[$_MIDCOM->auth->user->guid] = new midcom_db_person($_MIDCOM->auth->user->guid);
            }

            $preferences[$preference] = $preferences[$_MIDCOM->auth->user->guid]->get_parameter('midgard.admin.asgard:preferences', $preference);
        }

        return $preferences[$preference];
    }

    /**
     * Get the MgdSchema root classes
     *
     * @return array containing class name and translated name
     */
    public static function get_root_classes()
    {
        static $root_classes = array();

        // Return cached results
        if (!empty($root_classes))
        {
            return $root_classes;
        }

        // Initialize the returnable array
        $root_classes = array();

        // Get the classes
        $classes = midcom_helper_reflector_tree::get_root_classes();

        // Get the translated name
        foreach ($classes as $class)
        {
            $ref = new midcom_helper_reflector($class);
            $root_classes[$class] = $ref->get_class_label();
        }

        asort($root_classes);

        return $root_classes;
    }
}
?>