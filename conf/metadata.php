<?php
/**
 * Options for the gallery plugin
 *
 * @author i-net software [Gerry Weißbach] <dokuwiki@inetsoftware.de>
 */

$meta['return_type']  = array('multichoice','_choices' => array('jpg','png'));
$meta['bgc']  = array('string', '_pattern' => '/^#?[A-Fa-f0-9]{3}(([A-Fa-f0-9]{3})?|([A-Fa-f0-9]{5})?)$/');
$meta['reflect_height'] = array('numeric', '_pattern' => '/^0\.[0-9]{1,2}$/');
$meta['fade_start'] = array('numeric');
$meta['fade_end'] = array('numeric');