<?php
/**
 * @package org.openpsa.directmarketing
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\datamanager;

/**
 * Direct marketing page handler
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_campaign_create extends midcom_baseclasses_components_handler
{
    /**
     * The campaign which has been created
     *
     * @var org_openpsa_directmarketing_campaign_dba
     */
    private $_campaign;

    /**
     * Displays an campaign create view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        midcom::get()->auth->require_user_do('midgard:create', null, org_openpsa_directmarketing_campaign_dba::class);

        $this->_campaign = new org_openpsa_directmarketing_campaign_dba();
        $this->_campaign->node = $this->_topic->id;

        $dm = datamanager::from_schemadb($this->_config->get('schemadb_campaign'));
        $dm->set_storage($this->_campaign, $args[0]);

        midcom::get()->head->set_pagetitle(sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get($dm->get_schema()->get('description'))));

        $workflow = $this->get_workflow('datamanager', [
            'controller' => $dm->get_controller(),
            'save_callback' => [$this, 'save_callback']
        ]);
        return $workflow->run();
    }

    public function save_callback()
    {
        return "campaign/{$this->_campaign->guid}/";
    }
}
