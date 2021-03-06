<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\storage\container;

use midcom_core_dbaobject;
use midcom\datamanager\schema;
use midcom\datamanager\storage\transientnode;
use midcom\datamanager\storage\node;
use midcom\datamanager\storage\blobs;

/**
 * Experimental storage baseclass
 */
class dbacontainer extends container
{
    /**
     *
     * @var midcom_core_dbaobject
     */
    private $object;

    public function __construct(schema $schema, midcom_core_dbaobject $object, array $defaults)
    {
        $this->object = $object;
        $this->schema = $schema;

        foreach ($this->schema->get('fields') as $name => $config) {
            if (array_key_exists($name, $defaults)) {
                $config['default'] = $defaults[$name];
            }
            $config['name'] = $name;
            $field = $this->prepare_field($config);
            if (   isset($config['default'])
                && (!$this->object->id || $field instanceof transientnode)) {
                $field->set_value($config['default']);
            }

            $this->fields[$name] = $field;
        }
    }

    public function lock()
    {
        if (!$this->object->id) {
            return true;
        }
        return $this->object->metadata->lock();
    }

    public function unlock()
    {
        if (!$this->object->id) {
            return true;
        }
        if ($this->object->metadata->can_unlock()) {
            return $this->object->metadata->unlock();
        }
        return false;
    }

    public function is_locked()
    {
        return $this->object->metadata->is_locked();
    }

    /**
     * {@inheritdoc}
     */
    public function get_value()
    {
        return $this->object;
    }

    /**
     * {@inheritdoc}
     */
    public function set_value($object)
    {
        $this->object = $object;
    }

    /**
     *
     * @param array $config
     * @return node
     */
    private function prepare_field(array $config)
    {
        if (   empty($config['storage']['location'])
               // This line is needed because a parameter default is set by the schema parser and then ignored
               // by the type. The things we do for backwards compatibility...
            || $config['storage']['location'] === 'parameter') {
            if (class_exists('midcom\datamanager\storage\\' . $config['type'])) {
                $classname = 'midcom\datamanager\storage\\' . $config['type'];
            } elseif (strtolower($config['storage']['location']) === 'parameter') {
                $classname = 'midcom\datamanager\storage\parameter';
            } else {
                return new transientnode($config);
            }
        } elseif (strtolower($config['storage']['location']) === 'metadata') {
            $classname = 'midcom\datamanager\storage\metadata';
        } elseif (strtolower($config['storage']['location']) === 'privilege') {
            $classname = 'midcom\datamanager\storage\privilege';
        } else {
            $classname = 'midcom\datamanager\storage\property';
        }
        return new $classname($this->object, $config);
    }

    public function save()
    {
        if ($this->object->id) {
            $stat = $this->object->update();
        } elseif ($stat = $this->object->create()) {
            $this->object->set_parameter('midcom.helper.datamanager2', 'schema_name', $this->schema->get_name());
        }
        if (!$stat) {
            if (\midcom_connection::get_error() === MGD_ERR_ACCESS_DENIED) {
                throw new \midcom_error_forbidden('Failed to save: ' . \midcom_connection::get_error_string());
            }
            throw new \midcom_error('Failed to save: ' . \midcom_connection::get_error_string());
        }

        foreach ($this->fields as $node) {
            $node->save();
        }
    }

    public function move_uploaded_files()
    {
        $total_moved = 0;
        foreach ($this->fields as $node) {
            if ($node instanceof blobs) {
                $total_moved += $node->move_uploaded_files();
            }
        }
        return $total_moved;
    }
}
