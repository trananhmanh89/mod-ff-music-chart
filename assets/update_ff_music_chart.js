function updateFFMusicChart(id) {
    var baseUrl = Joomla.getOptions('system.paths').root;

    jQuery.ajax({
        url: baseUrl + '?option=com_ajax&module=ff_music_chart&method=updateCache&format=json&id=' + id,
    });
}