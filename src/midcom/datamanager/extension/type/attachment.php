<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use midcom\datamanager\extension\listener\attachment as listener;

/**
 * Experimental attachment type
 */
class attachment extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$builder->add('title', 'text');
    	$builder->add('file', 'file');
    	$builder->add('identifier', 'hidden');
    }

    public function getName()
    {
        return 'attachment';
    }
}