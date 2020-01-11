<?php
/**
 * @package     Music Chart Module
 * @subpackage  mod_ff_music_chart
 *
 * @copyright   https://funfis.com
 * @license     GNU General Public License version 2 or later;
 */

defined('_JEXEC') or die('Restricted access');

$num_item = +$params->get('num_item');
$items = array();

foreach ($data as $key => $value) {
    if ($key >= $num_item) {
        break;
    }

    $value->isLong = $key + 1 > 99 ? true : false;

    switch ($value->trend) {
        case 'rising':
            $value->trend_icon = '<img src="'.JUri::root(true).'/modules/mod_ff_music_chart/assets/images/up.svg" />';
            break;

        case 'falling':
            $value->trend_icon = '<img src="'.JUri::root(true).'/modules/mod_ff_music_chart/assets/images/down.svg" />';
            break;

        case 'steady':
            $value->trend_icon = '<img src="'.JUri::root(true).'/modules/mod_ff_music_chart/assets/images/right.svg" />';
            break;

        case 'reenter':
            $value->trend_icon = JText::_('MOD_FF_MUSIC_CHART_RE_ENTER');
            break;

        default:
            $value->trend_icon = JText::_('MOD_FF_MUSIC_CHART_NEW');
            break;
    }

    $promo = $value->last - $value->rank;

    if ($value->trend === 'new' || $value->trend === 'reenter' || $promo === 0) {
        $value->promo = '-';
    } else if ($promo > 0) {
        $value->promo = "+$promo";
    } else {
        $value->promo = $promo;
    }

    if (!$value->image) {
        $value->image = $params->get('default_cover', 'modules/mod_ff_music_chart/assets/images/song-icon.jpg');
    }

    $items[] = $value;
}
?>

<div class="mod-<?php echo $module->id ?> ff-music-items">
    <?php foreach ($items as $key => $item): ?>
    <div class="ff-music-item">
        <div class="ff-music-item__rank <?php echo $item->isLong ? 'ff-music-item__rank--long' : '' ?>">
            <div class="rank__number"><?php echo $key + 1 ?></div>
            <div class="trend__icon color--<?php echo $item->trend ?>"><?php echo $item->trend_icon ?></div>
        </div>
        <div class="ff-music-item__detail">
            <div class="ff-music-item__title">
                <?php echo $item->title ?>
            </div>
            <div class="ff-music-item__subtitle">
                <?php echo $item->subtitle ?>
            </div>
            <div class="ff-music-item__promo color--<?php echo $item->trend ?>">
                <span><?php echo $item->promo ?></span>
            </div>
            <div class="ff-music-item__meta">
                <span title="<?php echo JText::_('MOD_FF_MUSIC_CHART_LAST_WEEK') ?>">
                    <?php echo $item->last ?> <span class="ff-music-item__meta-label"><?php echo JText::_('MOD_FF_MUSIC_CHART_LAST') ?></span>
                </span> |
                <span title="<?php echo JText::_('MOD_FF_MUSIC_CHART_PEAK') ?>">
                    <?php echo $item->peak ?> <span class="ff-music-item__meta-label"><?php echo JText::_('MOD_FF_MUSIC_CHART_PEAK') ?></span>
                </span> |
                <span title="<?php echo JText::_('MOD_FF_MUSIC_CHART_DURATION') ?>">
                    <?php echo $item->duration ?> 
                    <span class="ff-music-item__meta-label">
                        <?php echo $item->duration === 1 ? JText::_('MOD_FF_MUSIC_CHART_WEEK') : JText::_('MOD_FF_MUSIC_CHART_WEEKs') ?>
                    </span>
                </span>
            </div>
        </div>
        <div class="ff-music-item__image">
            <img src="<?php echo $item->image ?>" alt="<?php echo $item->title ?>">
        </div>
    </div>
    <?php endforeach ?>
</div>