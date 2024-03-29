<?php
/**
 * @package     Music Chart Display
 * @subpackage  mod_ff_music_chart
 *
 * @copyright   https://github.com/trananhmanh89/mod-ff-music-chart
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
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
            HTMLHelper::_('behavior.core');
            HTMLHelper::_('jquery.framework');

            $doc = Factory::getDocument();
            $doc->addScript(Uri::root() . '/modules/mod_ff_music_chart/assets/update_ff_music_chart.js');
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
        File::write($updateTimeFile, time());

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
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/hot-100/');
                break;

            case 'billboard_200':
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/billboard-200/');
                break;

            case 'billboard_artist_100';
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/artist-100/');
                break;

            case 'billboard_top_pop_songs';
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/pop-songs/');
                break;

            case 'billboard_top_country_songs';
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/country-songs/');
                break;

            case 'billboard_top_country_albums';
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/country-albums/');
                break;

            case 'billboard_country_airplay':
                $data = self::getBillboardChartByDom('https://www.billboard.com/charts/country-airplay/');
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
            Factory::getApplication()->enQueueMessage("Module get data error.");
            return '';
        }

        File::write($cacheFile, json_encode($data));

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
                $item->rank = (int) trim(DomQuery::create($td[0])->text());
                $item->last = (int) trim(DomQuery::create($td[1])->text());
                $item->peak = (int) trim(DomQuery::create($td[3])->text());
                $item->duration = (int) trim(DomQuery::create($td[4])->text());
                $item->trend = self::parseTrend($item);

                $track = $elm->find('.track');
                $item->title = trim($track->find('.title')->text());
                $item->subtitle = trim($track->find('.artist')->text());

                $cover = $track->find('.cover img');
                $img = self::getImage('https://www.officialcharts.com', $cover->attr('src'));
                $item->image = str_replace('img/small', 'img/medium', $img);
                
                $items[] = $item;
            }

            return $items;
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function getImage($host, $src)
    {
        if (preg_match('/^(http|https):\/\/.*/', $src)) {
            return $src;
        } else {
            return $host . $src;
        }
    }

    protected static function getBillboardChartByDom($url)
    {
        $html = self::crawl($url);

        try {
            $dom = DomQuery::create($html);
            
            $list = $dom->find('.o-chart-results-list-row-container');
            $items = array();

            foreach ($list as $idx => $elm) {
                $item = new stdClass;
                $item->rank = $idx + 1;
                $results = $elm->find('.o-chart-results-list-row > li');

                $cover = $results[1];
                $item->image = $cover->find('.c-lazy-image__img')->attr('data-lazy-src');

                $trend = $results[2];
                $trendLabel = strtolower(trim($trend->find('span.c-label')->text()));
                $trendLabel = preg_replace('/\s+/', '', $trendLabel);
                $trendSymbol = $trend->find('.c-svg > svg > g > path')->attr('d');

                if ($trendLabel === 'new') {
                    $item->trend = 'new';
                } else if ($trendLabel === 're-entry') {
                    $item->trend = 'reenter';
                } else if (preg_match('/^M642/', $trendSymbol)) {
                    $item->trend = 'steady';
                } else if (preg_match('/^M12/', $trendSymbol)) {
                    $item->trend = 'falling';
                } else {
                    $item->trend = 'rising';
                }

                $info = $results[3];
                $rankInfo = $info->children()->first()->children();

                $item->title = trim($rankInfo[0]->find('h3.c-title.a-no-trucate')->text());
                $item->subtitle = trim($rankInfo[0]->find('span.c-label.a-no-trucate')->text());

                $item->last = (int) trim($rankInfo[3]->text());
                $item->peak = (int) trim($rankInfo[4]->text());
                $item->duration = (int) trim($rankInfo[5]->text());

                $items[] = $item;
            }

            return $items;
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function getBillboardChartByData($url)
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
        $app = Factory::getApplication();

        $options = new Registry();
        $options->set('userAgent', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0');

        try
        {
            $res = HttpFactory::getHttp($options)->get($url);
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
        $input = Factory::getApplication()->input;
        $id = $input->getInt('id', 0);

        $module = Table::getInstance('module');
        $module->load($id);

        if (!$module->id || $module->module !== 'mod_ff_music_chart' || !$module->published) {
            die('Error! Unknow module.');
        }

        $params = new Registry($module->params);
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