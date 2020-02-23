<?php

use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die('Restricted access');

class JFormFieldSponsor extends FormField
{
    protected $type = 'sponsor';

    protected function getInput()
    {
        return "<script type='text/javascript' src='https://ko-fi.com/widgets/widget_2.js'></script><script type='text/javascript'>kofiwidget2.init('Buy me a coffee', '#29abe0', 'I3I71FSC5');kofiwidget2.draw();</script>";
    }
}