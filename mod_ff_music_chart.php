<?php
/**
 * @package     Funfis
 * @subpackage  mod_ff_billboard_chart
 *
 * @copyright   https://funfis.com
 * @license     GNU General Public License version 2 or later;
 */


defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/helper.php';

$data = ModFFMusicChartHelper::getData($module, $params);

if (!empty($data)) {
    $doc = JFactory::getDocument();
    $doc->addStyleSheet(JUri::root() . 'modules/mod_ff_music_chart/assets/mod_ff_music_chart.css');

    require JModuleHelper::getLayoutPath('mod_ff_music_chart', $params->get('layout', 'default'));
} else {
    JFactory::getApplication()->enQueueMessage('Empty content. Module ' . $module->id, 'error');
}
