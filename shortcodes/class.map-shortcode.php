<?php
/**
 * Map Shortcode
 *
 * Displays map with [leaflet-map ...atts]
 *
 * JavaScript equivalent : L.map("id");
 *
 * @category Shortcode
 * @author   Benjamin J DeLong <ben@bozdoz.com>
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once LEAFLET_MAP__PLUGIN_DIR . 'shortcodes/class.shortcode.php';

/**
 * Leaflet_Map_Shortcode Class
 */
class Leaflet_Map_Shortcode extends Leaflet_Shortcode
{
    /**
     * Instantiate class
     */
    public function __construct()
    {
        parent::__construct();

        $this->enqueue();
    }

    /**
     * Enqueue Scripts and Styles for Leaflet
     *
     * @return null
     */
    protected function enqueue()
    {
        wp_enqueue_style('leaflet_stylesheet');
        wp_enqueue_script('wp_leaflet_map');

        if (wp_script_is('leaflet_mapquest_plugin', 'registered')) {
            // mapquest doesn't accept direct tile access as of July 11, 2016
            wp_enqueue_script('leaflet_mapquest_plugin');
        }

        // enqueue user-defined scripts
        // ! will fire for each map
        do_action('leaflet_map_enqueue');
    }

    /**
     * Merge shortcode options with default options
     *
     * @param array|string $atts    key value pairs from shortcode
     *
     * @return array new atts, which is actually an array
     */
    protected function getAtts($atts='')
    {
        $atts = (array) $atts;
        extract($atts, EXTR_SKIP);

        $settings = Leaflet_Map_Plugin_Settings::init();

        $atts['zoom'] = array_key_exists('zoom', $atts) ?
            $zoom : $settings->get('default_zoom');
        $atts['height'] = empty($height) ?
            $settings->get('default_height') : $height;
        $atts['width'] = empty($width) ? $settings->get('default_width') : $width;
        $atts['zoomcontrol'] = isset($zoomControl)
            ? $zoomControl
            : (array_key_exists('zoomcontrol', $atts)
                ? $zoomcontrol
                : $settings->get('show_zoom_controls'));
        $atts['min_zoom'] = array_key_exists('min_zoom', $atts) ?
            $min_zoom : $settings->get('default_min_zoom');
        $atts['max_zoom'] = empty($max_zoom) ?
            $settings->get('default_max_zoom') : $max_zoom;
        $atts['scrollwheel'] = isset($scrollWheelZoom)
            ? $scrollWheelZoom
            : (array_key_exists('scrollwheel', $atts)
                ? $scrollwheel
                : $settings->get('scroll_wheel_zoom'));
         $atts['doubleclickzoom'] = isset($doubleClickZoom)
             ? $doubleClickZoom
             : (array_key_exists('doubleclickzoom', $atts)
                 ? $doubleclickzoom
                 : $settings->get('double_click_zoom'));

        // @deprecated backwards-compatible fit_markers
        $atts['fit_markers'] = array_key_exists('fit_markers', $atts) ?
            $fit_markers : $settings->get('fit_markers');

        // fitbounds is what it should be called @since v2.12.0
        $atts['fitbounds'] = array_key_exists('fitbounds', $atts) ?
            $fitbounds : $atts['fit_markers'];

        /* allow percent, but add px for ints */
        $atts['height'] .= is_numeric($atts['height']) ? 'px' : '';
        $atts['width'] .= is_numeric($atts['width']) ? 'px' : '';

        /*
        need to allow 0 or empty for removal of attribution
        */
        if (!array_key_exists('attribution', $atts)) {
            $atts['attribution'] = $settings->get('default_attribution');
        }

        /* allow a bunch of other (boolean) options */
        // http://leafletjs.com/reference.html#map
        $map_options = array(
            'closePopupOnClick' => isset($closePopupOnClick)
                ? $closePopupOnClick
                : (isset($closepopuponclick)
                    ? $closepopuponclick
                    : null),
            'trackResize' => isset($trackResize)
                ? $trackResize
                : (isset($trackresize)
                    ? $trackresize
                    : null),
            'boxZoom' => isset($boxzoom)
                ? $boxzoom
                : (isset($boxZoom)
                    ? $boxZoom
                    : null),
            'touchZoom' => isset($touchZoom) ? $touchZoom : null,
            'dragging' => isset($dragging) ? $dragging : null,
            'keyboard' => isset($keyboard) ? $keyboard : null,
            'zoomAnimation' => isset($zoomAnimation) ?  $zoomAnimation : null,
            'fadeAnimation' => isset($fadeAnimation) ?  $fadeAnimation : null,
            'markerZoomAnimation' => isset($markerZoomAnimation) ?  $markerZoomAnimation : null,
            'inertia' => isset($inertia) ?  $inertia : null,
            'worldCopyJump' => isset($worldCopyJump) ?  $worldCopyJump : null,
            'tap' => isset($tap) ? $tap : null,
            'bounceAtZoomLimits' => isset($bounceAtZoomLimits) ? $bounceAtZoomLimits : null,
            // defined above, but can be validated here
            'zoomControl' => $atts['zoomcontrol'],
            'scrollWheelZoom' => $atts['scrollwheel'],
            'doubleClickZoom' => $atts['doubleclickzoom']
        );

        // filter out nulls
        $map_options = $this->LM->filter_null($map_options);

        // custom field for moving to JavaScript
        $map_options['fitBounds'] = $atts['fitbounds'];

        // change string booleans to booleans
        $map_options = filter_var_array($map_options, FILTER_VALIDATE_BOOLEAN);

        // add min/max zoom validations
        $zoom_options = array(
            'minZoom' => $atts['min_zoom'],
            'maxZoom' => $atts['max_zoom']
        );

        $zoom_options = filter_var_array($zoom_options, FILTER_VALIDATE_FLOAT);

        $map_options = array_merge(
            $map_options,
            $zoom_options
        );

        // update atts too
        $atts['minZoom'] = $zoom_options['minZoom'];
        $atts['maxZoom'] = $zoom_options['maxZoom'];
        $map_options['maxBounds'] = isset($maxbounds) ? $this->LM->convert_bounds_str_to_arr($maxbounds) : null;

        // custom field for moving to javascript
        // filter out any unwanted HTML tags (including img)
        if ($atts['attribution'] !== 0) {
            $map_options['attribution'] = wp_kses_post($atts['attribution']);
        }

        // wrap as JSON
        $atts['map_options'] = json_encode($map_options);

        // get raw variables, allowing for JavaScript variables in values
        $raw_map_options = array();
        foreach($map_options as $key=>$val) {
            $original_value = isset($atts[$key]) ? $atts[$key] : null;

            $liquid = $this->LM->liquid($original_value);

            if ($liquid && isset($liquid['raw']) && $liquid['raw']) {
                // raw leaves original value un-quoted
                $raw_map_options[$key] = $liquid['original'];
            }
        }

        $atts['raw_map_options'] = $this->LM->rawDict($raw_map_options);

        $tile_layer_options = array(
            'tileSize' => empty($tilesize) ? $settings->get('tilesize') : $tilesize,
            'subdomains' => empty($subdomains) ? $settings->get('map_tile_url_subdomains') : $subdomains,
            'id' => empty($mapid) ? $settings->get('mapid') : $mapid,
            'accessToken' => empty($accesstoken) ? $settings->get('accesstoken') : $accesstoken,
            'zoomOffset' => empty($zoomoffset) ? $settings->get('zoomoffset') : $zoomoffset,
            'noWrap' => filter_var(empty($nowrap) ? $settings->get('tile_no_wrap') : $nowrap, FILTER_VALIDATE_BOOLEAN),
            'maxZoom' => $atts['maxZoom']
        );


        $tile_layer_options = $this->LM->filter_empty_string($tile_layer_options);
        $tile_layer_options = $this->LM->filter_null($tile_layer_options);

        // Leaflet supports `subdomains` as a string (e.g. "abc") or an array.
        // Keep backward compatibility, and avoid numeric-only strings being treated as numbers.
        if (isset($tile_layer_options['subdomains']) && is_string($tile_layer_options['subdomains'])) {
            $subdomains_str = $tile_layer_options['subdomains'];

            if (strpos($subdomains_str, ',') !== false) {
                // subdomains can be comma-separated
                $tile_layer_options['subdomains'] = explode(',', $subdomains_str);
            } elseif (ctype_digit($subdomains_str) && strlen($subdomains_str) > 1) {
                // e.g. "1234" => ["1","2","3","4"]
                $tile_layer_options['subdomains'] = str_split($subdomains_str);
            }
        }

        $atts['tile_layer_options'] = json_encode($tile_layer_options, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);

        // TODO: find a better way to do this
        $validations = array(
            'detect_retina' => FILTER_VALIDATE_BOOLEAN,
            'zoom' => FILTER_VALIDATE_FLOAT,
            'fitBounds' => FILTER_VALIDATE_BOOLEAN
        );

        $atts = $this->LM->sanitize_inclusive($atts, $validations);

        return $atts;
    }

    /**
     * Get the div tag for the map to instantiate
     *
     * @param string $height
     * @param string $width
     *
     * @return string HTML div element
     */
    protected function getDiv($height, $width) {
        // div does not get wrapped in script tags
        ob_start();
        ?>
<div class="leaflet-map WPLeafletMap" style="height:<?php
    echo htmlspecialchars($height);
?>; width:<?php
    echo htmlspecialchars($width);
?>;"></div><?php
        return ob_get_clean();
    }

    /**
     * Get script for shortcode
     *
     * @param array  $atts    sometimes this is null
     * @param string $content anything within a shortcode
     *
     * @return string HTML
     */
    protected function getHTML($atts='', $content=null)
    {
        extract($this->getAtts($atts));

        if (!empty($address)) {
            /* try geocoding */
            include_once LEAFLET_MAP__PLUGIN_DIR . 'class.geocoder.php';
            $location = new Leaflet_Geocoder($address);
            $lat = $location->lat;
            $lng = $location->lng;
        }

        $settings = Leaflet_Map_Plugin_Settings::init();

        // map uses lat/lng
        $lat = isset($lat) ? $lat : $settings->get('default_lat');
        $lng = isset($lng) ? $lng : $settings->get('default_lng');

        // validate lat/lng
        $lat = $this->LM->filter_float($lat);
        $lng = $this->LM->filter_float($lng);

        /*
        mapquest doesn't need tile urls
        */
        if (wp_script_is('leaflet_mapquest_plugin', 'registered')) {
            $tileurl = '';
        } else {
            $tileurl = empty($tileurl) ? $settings->get('map_tile_url') : $tileurl;
        }

        $detect_retina = empty($detect_retina) ? $settings->get('detect_retina') : $detect_retina;

        $detect_retina = filter_var($detect_retina, FILTER_VALIDATE_BOOLEAN);

        /* should be iterated for multiple maps */
        $tiling_service = $settings->get('default_tiling_service');
        $amap_layer = $settings->get('amap_layer');
        $bing_layer = $settings->get('bing_layer');
        $bing_key = $settings->get('bing_key');

        $labelurl = '';

        // AMap: satellite labels are derived from the base URL via style=8.
        if (!empty($tileurl) && $tiling_service === 'amap' && $amap_layer === 'satellite') {
            if (strpos($tileurl, 'style=') !== false) {
                $labelurl = preg_replace('/style=\d+/', 'style=8', $tileurl);
            } else {
                $labelurl = $tileurl . (strpos($tileurl, '?') === false ? '?' : '&') . 'style=8';
            }
        }

        // Bing: if aerial (no labels), overlay the labels layer (h{q}).
        if ($tiling_service === 'bing') {
            // If the base tileurl uses the aerial tile (a{q}) and user selected aerial, create label overlay using h{q}.
            if (!empty($tileurl) && strpos($tileurl, 'a{q}') !== false) {
                $labelurl = str_replace('a{q}', 'h{q}', $tileurl);
            }
            // If base already contains labels (h{q}), no extra overlay required.
        }

        ob_start();
        ?>/*<script>*/
var baseUrl = atob('<?php echo base64_encode(filter_var($tileurl, FILTER_SANITIZE_URL)); ?>');
var labelUrl = atob('<?php echo base64_encode(filter_var($labelurl, FILTER_SANITIZE_URL)); ?>');
var tileOptions = L.Util.extend({}, {
        detectRetina: <?php echo $detect_retina ? '1' : '0'; ?>
    },
    <?php echo $tile_layer_options; ?>
);

var QuadKeyTileLayer = L.TileLayer.extend({
    getTileUrl: function(coords) {
        var quadKey = '';
        for (var i = coords.z; i > 0; i--) {
            var digit = 0;
            var mask = 1 << (i - 1);
            if ((coords.x & mask) !== 0) digit += 1;
            if ((coords.y & mask) !== 0) digit += 2;
            quadKey += digit;
        }

        return L.Util.template(this._url, L.Util.extend({
            s: this._getSubdomain(coords),
            x: coords.x,
            y: coords.y,
            z: coords.z,
            q: quadKey,
            r: L.Browser.retina ? '@2x' : ''
        }, this.options));
    }
});

function makeTileLayer(url) {
    if (!url) return null;
    if (url.indexOf('{q}') !== -1) {
        return new QuadKeyTileLayer(url, tileOptions);
    }
    return L.tileLayer(url, tileOptions);
}

var base = (!baseUrl && window.MQ) ? window.MQ.mapLayer() : makeTileLayer(baseUrl);
var labelLayer = makeTileLayer(labelUrl);
    var options = L.Util.extend({}, {
        layers: labelLayer ? [base, labelLayer] : [base],
        attributionControl: false
    },
    <?php echo $map_options; ?>,
    <?php echo $raw_map_options; ?>
);
window.WPLeafletMapPlugin.createMap(options).setView(<?php
    echo '[' . $lat . ',' . $lng . '],' . $zoom;
?>);<?php

        $show_scale = isset($show_scale) ? $show_scale : $settings->get('show_scale');

        if ($show_scale) {
            echo do_shortcode('[leaflet-scale noScriptWrap]');
        }

        $script = ob_get_clean();

        return $this->getDiv($height, $width) . $this->wrap_script($script, 'WPLeafletMapShortcode');
    }
}
