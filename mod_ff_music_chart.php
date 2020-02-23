<?php
/**
 * @package     Music Chart Display
 * @subpackage  mod_ff_music_chart
 *
 * @copyright   https://github.com/trananhmanh89/mod-ff-music-chart
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/helper.php';

$data = ModFFMusicChartHelper::getData($module, $params);

if (!empty($data)) {
    $doc = Factory::getDocument();
    $doc->addStyleSheet(Uri::root() . 'modules/mod_ff_music_chart/assets/mod_ff_music_chart.css');

    require ModuleHelper::getLayoutPath('mod_ff_music_chart', $params->get('layout', 'default'));
} else {
    Factory::getApplication()->enQueueMessage('Empty content. Module ' . $module->id, 'error');
}
