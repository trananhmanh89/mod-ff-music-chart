<?php
/**
 * @package     Funfis
 * @subpackage  mod_ff_music_chart
 *
 * @copyright   https://funfis.com
 * @license     GNU General Public License version 2 or later;
 */

use Rct567\DomQuery\DomQuery;

defined('_JEXEC') or die('Restricted access');

class ModFFMusicChartHelper
{
    public static function getData($module, $params)
    {
        $source = $params->get('source');
        if ($source === 'billboard') {
            $type = $params->get('billboard_chart');
        } else {
            $type = $params->get('official_chart');
        }

        $strId = "$source-$type-" . $module->id;

        $mediaPath = JPATH_ROOT . '/media/mod_ff_music_chart';

        $updateTime = +$params->get('update_time', 0);
        $updateTimeFile = $mediaPath . '/lastUpdate-' . $strId . '.txt';
        $lastUpdate = file_exists($updateTimeFile) ? +file_get_contents($updateTimeFile) : 0;
        $now = time();

        $cacheFile = $mediaPath . '/cache-' . $strId . '.txt';

        if ($lastUpdate + $updateTime < $now && file_exists($cacheFile)) {
            JHtml::_('behavior.core');
            JHtml::_('jquery.framework');

            $doc = JFactory::getDocument();
            $doc->addScript(JUri::root() . '/modules/mod_ff_music_chart/assets/update_ff_music_chart.js');
            $doc->addScriptDeclaration(';updateFFMusicChart(' . $module->id . ');');
        }


        if (file_exists($cacheFile)) {
            return @json_decode(file_get_contents($cacheFile));
        }

        $cache = self::setCache($cacheFile, $updateTimeFile, $params);

        return $cache;
    }

    protected static function setCache($cacheFile, $updateTimeFile, $params)
    {
        JFile::write($updateTimeFile, time());

        require_once JPATH_ROOT . '/modules/mod_ff_music_chart/vendor/DomQuery/CssToXpath.php';
        require_once JPATH_ROOT . '/modules/mod_ff_music_chart/vendor/DomQuery/DomQueryNodes.php';
        require_once JPATH_ROOT . '/modules/mod_ff_music_chart/vendor/DomQuery/DomQuery.php';

        $source = $params->get('source');
        if ($source === 'billboard') {
            $type = $params->get('billboard_chart');
        } else {
            $type = $params->get('official_chart');
        }

        switch ($type) {
            case 'billboard_hot_100':
                $data = self::getBillboard('https://www.billboard.com/charts/hot-100');
                break;

            case 'billboard_200':
                $data = self::getBillboard('https://www.billboard.com/charts/billboard-200');
                break;

            case 'billboard_artist_100';
                $data = self::getBillboarArtist100();
                break;

            case 'uk_single_top_100':
                $data = self::getUkChart('https://www.officialcharts.com/charts/singles-chart/');
                break;

            case 'uk_album_top_100':
                $data = self::getUkChart('https://www.officialcharts.com/charts/albums-chart/');
                break;
            
            default:
                $data = array();
                break;
        }
        
        if (!$data) {
            JFactory::getApplication()->enQueueMessage("Module get data error.");
            return '';
        }

        JFile::write($cacheFile, json_encode($data));

        return $data;
    }

    protected static function getUkChart($url)
    {
        $html = self::crawl($url);
        
        try {
            $dom = DomQuery::create($html);
            $dom->find('.headings')->remove();
            $dom->find('.mobile-actions')->remove();
            $dom->find('.actions-view')->remove();
            $dom->find('tr > td > .adspace')->parent()->parent()->remove();

            $list = $dom->find('.chart-positions > tr');
            $items = array();

            foreach ($list as $elm) {
                $item = new stdClass;
                $td = $elm->find('td');
                $item->rank = (int) trim(DomQuery::create($td->get(0))->text());
                $item->last = (int) trim(DomQuery::create($td->get(1))->text());
                $item->peak = (int) trim(DomQuery::create($td->get(3))->text());
                $item->duration = (int) trim(DomQuery::create($td->get(4))->text());
                $item->trend = self::parseTrend($item);

                $track = $elm->find('.track');
                $item->title = trim($track->find('.title')->text());
                $item->subtitle = trim($track->find('.artist')->text());

                $cover = $track->find('.cover img');
                $item->image = str_replace('img/small', 'img/medium', $cover->attr('src'));
                
                $items[] = $item;
            }

            return $items;
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function getBillboarArtist100()
    {
        $html = self::crawl('https://www.billboard.com/charts/artist-100');

        try {
            $dom = DomQuery::create($html);
            
            $list = $dom->find('.chart-list .chart-list-item');
            $items = array();

            foreach ($list as $elm) {
                $item = new stdClass;
                $item->title = trim($elm->data('title'));
                $item->subtitle = '';
                $item->rank = (int) trim($elm->data('rank'));

                $miniStats = $elm->find('.chart-list-item__ministats  > .chart-list-item__ministats-cell');
                $item->last = (int) trim($miniStats->first()->text());
                $item->duration = (int) trim($miniStats->last()->text());
                $item->peak = (int) trim(DomQuery::create($miniStats->get(1))->text());

                $item->trend = self::parseTrend($item);

                $img = $elm->find('.chart-list-item__image-wrapper > img');
                $src = $img->attr('src');
                if (preg_match('/bb-placeholder-new\.jpg/', $src)) {
                    $item->image = '';
                } else {
                    $srcset = $img->data('srcset');
                    $set = explode(',', $srcset);
                    $last = array_pop($set);
                    $trimmed = trim($last);
                    $exploded = explode(' ', $trimmed);
                    $item->image = @$exploded[0];
                }

                $items[] = $item;
            }

            return $items;
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function getBillboard($url)
    {
        $html = self::crawl($url);

        try {
            $dom = DomQuery::create($html);
            $elm = $dom->find('#charts');
            $data = $elm->attr('data-charts');
            $data = @json_decode($data);

            if (!$data || !is_array($data)) {
                return array();
            }

            $chartData = array_map(function($item) {
                $result = new stdClass;
                $result->title = $item->title;
                $result->subtitle = $item->artist_name;
                $result->rank = (int) $item->rank;
                $result->duration = (int) $item->history->weeks_on_chart;
                $result->peak = (int) $item->history->peak_rank;
                $result->last = (int) $item->history->last_week;
                $result->trend = self::parseTrend($result);

                if (isset($item->title_images->sizes->{'ye-landing-sm'})) {
                    $result->image = 'https://charts-static.billboard.com' . $item->title_images->sizes->{'ye-landing-sm'}->Name;
                } else {
                    $result->image = '';
                }

                return $result;
            }, $data);

            return $chartData;
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function parseTrend($item)
    {
        $trend = $item->rank - $item->last;

        if ($item->duration == 1) {
            return 'new';
        } else if ($item->duration > 1 && !$item->last) {
            return 'reenter';
        } else if ($trend === 0) {
            return 'steady';
        } else if ($trend < 0 ) {
            return 'rising';
        } else {
            return 'falling';
        }
    }

    protected static function crawl($url)
    {
        $app = JFactory::getApplication();

        $options = new JRegistry;
        $options->set('userAgent', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0');

        try
        {
            $res = JHttpFactory::getHttp($options)->get($url);
        } catch (RuntimeException $e)
        {
            $app->enQueueMessage("Could not open this url: " . $url);
            return '';
        }

        if ($res->code != 200)
        {
            $app->enQueueMessage("Could not open this url: " . $url);
            return '';
        }

        return $res->body;
    }

    public static function updateCacheAjax()
    {
        $input = JFactory::getApplication()->input;
        $id = $input->getInt('id', 0);

        $module = JTable::getInstance('module');
        $module->load($id);

        if (!$module->id || $module->module !== 'mod_ff_music_chart' || !$module->published) {
            die('Error! Unknow module.');
        }

        $params = new JRegistry($module->params);
        $source = $params->get('source');
        if ($source === 'billboard') {
            $type = $params->get('billboard_chart');
        } else {
            $type = $params->get('official_chart');
        }

        $strId = "$source-$type-" . $module->id;

        $mediaPath = JPATH_ROOT . '/media/mod_ff_music_chart';

        $updateTime = +$params->get('update_time', 0);
        $updateTimeFile = $mediaPath . '/lastUpdate-' . $strId . '.txt';
        $lastUpdate = file_exists($updateTimeFile) ? +file_get_contents($updateTimeFile) : 0;
        $now = time();
        
        $cacheFile = $mediaPath . '/cache-' . $strId . '.txt';

        if ($lastUpdate + $updateTime < $now) {
            self::setCache($cacheFile, $updateTimeFile, $params);
        }
    }
}