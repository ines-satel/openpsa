<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\schemadb;
use midcom\datamanager\datamanager;
use midcom\datamanager\controller;

/**
 * n.n.blog create page handler
 *
 * @package net.nehmer.blog
 */
class net_nehmer_blog_handler_create extends midcom_baseclasses_components_handler
{
    /**
     * The article which has been created
     *
     * @var midcom_db_article
     */
    private $article;

    /**
     * Displays an article create view.
     *
     * If create privileges apply, we relocate to the created article
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        $this->_topic->require_do('midgard:create');

        $schema_name = $args[0];
        $schemadb = schemadb::from_path($this->_config->get('schemadb'));
        if (   $this->_config->get('simple_name_handling')
            && !midcom::get()->auth->can_user_do('midcom:urlname')) {
            foreach ($schemadb->all() as $schema) {
                $field =& $schema->get_field('name');
                $field['readonly'] = true;
            }
        }

        $this->article = new midcom_db_article();
        $this->article->topic = $this->_topic->id;

        $dm = new datamanager($schemadb);
        $data['controller'] = $dm->set_storage($this->article, $schema_name)
            ->get_controller();

        midcom::get()->head->set_pagetitle(sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get($schemadb->get($schema_name)->get('description'))));

        $workflow = $this->get_workflow('datamanager', [
            'controller' => $data['controller'],
            'save_callback' => [$this, 'save_callback']
        ]);

        return $workflow->run();
    }

    public function save_callback(controller $controller)
    {
        // Reindex the article
        $indexer = midcom::get()->indexer;
        net_nehmer_blog_viewer::index($controller->get_datamanager(), $indexer, $this->_topic);

        if ($this->_config->get('callback_function')) {
            if ($this->_config->get('callback_snippet')) {
                midcom_helper_misc::include_snippet_php($this->_config->get('callback_snippet'));
            }

            $callback = $this->_config->get('callback_function');
            $callback($this->article, $this->_topic);
        }
    }
}
