<?php
/**
 * Settings View
 * 
 * PHP Version 5.5
 * 
 * @category Admin
 * @author   Benjamin J DeLong <ben@bozdoz.com>
 */

$title = $plugin_data['Name'];
$description = __('A plugin for creating a Leaflet JS map with a shortcode. Boasts two free map tile services and three free geocoders.', 'leaflet-map');
$version = $plugin_data['Version'];
?>
<div class="wrap">

<h1><?php echo $title; ?> <small>version: <?php echo $version; ?></small></h1>

<?php
/** START FORM SUBMISSION */

// validate nonce!
define('NONCE_NAME', 'leaflet-map-nonce');
define('NONCE_ACTION', 'leaflet-map-action');

function verify_nonce () {
    $verified = (
        isset($_POST[NONCE_NAME]) &&
        check_admin_referer(NONCE_ACTION, NONCE_NAME)
    );

    if (!$verified) {
        // side-effects can be fun?
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e('Sorry, your nonce did not verify', 'leaflet-map'); ?></p>
        </div>
        <?php
    }

    return $verified;
}

if (isset($_POST['submit']) && verify_nonce()) {
    /* copy and overwrite $post for checkboxes */
    $form = $_POST;

    foreach ($settings->options as $name => $option) {
        if (!$option->type) continue;

        /* checkboxes don't get sent if not checked */
        if ($option->type === 'checkbox') {
            $form[$name] = isset($_POST[ $name ]) ? 1 : 0;
        }

        $value = trim( stripslashes( $form[$name]) );

        $settings->set($name, $value);
    }

    // If a known tiling service is selected, populate its recommended defaults.
    // This keeps the UX simple: users can switch providers without manually editing URLs.
    if (isset($form['default_tiling_service']) && $form['default_tiling_service'] === 'amap') {
        // decide vector vs satellite based on the amap_layer setting (from POST if present)
        $amap_layer = isset($form['amap_layer']) ? $form['amap_layer'] : $settings->get('amap_layer');

        if ($amap_layer === 'satellite') {
            // Satellite tiles (Autonavi / AMap satellite endpoint)
            $settings->set(
                'map_tile_url',
                'https://webst0{s}.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=6&x={x}&y={y}&z={z}'
            );
        } else {
            // Vector / road tiles
            $settings->set(
                'map_tile_url',
                'https://webrd0{s}.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=7&x={x}&y={y}&z={z}'
            );
        }

        // Comma-separated becomes an array in JS; avoids numeric-string pitfalls.
        $settings->set('map_tile_url_subdomains', '1,2,3,4');
        $settings->set(
            'default_attribution',
            '© <a href="https://www.amap.com/" target="_blank" rel="noopener">AMap</a>'
        );
    }

    /* Tianditu support removed per user request */

    if (isset($form['default_tiling_service']) && $form['default_tiling_service'] === 'bing') {
        $bing_layer = isset($form['bing_layer']) ? $form['bing_layer'] : $settings->get('bing_layer');
        $bing_key = isset($form['bing_key']) ? trim($form['bing_key']) : trim($settings->get('bing_key'));

        // Bing uses quadkey ({q}); handled in shortcode JS.
        if ($bing_layer === 'road') {
            $url = 'https://ecn.t{s}.tiles.virtualearth.net/tiles/r{q}.png?g=1&mkt=zh-CN&n=z';
        } elseif ($bing_layer === 'aerial') {
            $url = 'https://ecn.t{s}.tiles.virtualearth.net/tiles/a{q}.jpeg?g=1&mkt=zh-CN&n=z';
        } else {
            // aerial with labels
            $url = 'https://ecn.t{s}.tiles.virtualearth.net/tiles/h{q}.jpeg?g=1&mkt=zh-CN&n=z';
        }

        if (!empty($bing_key)) {
            $url .= '&key=' . rawurlencode($bing_key);
        }

        $settings->set('map_tile_url', $url);
        $settings->set('map_tile_url_subdomains', '0,1,2,3');
        $settings->set(
            'default_attribution',
            '© <a href="https://www.microsoft.com/maps" target="_blank" rel="noopener">Microsoft Bing Maps</a>'
        );
    }
?>
<div class="notice notice-success is-dismissible">
    <p><?php _e('Options Updated!', 'leaflet-map'); ?></p>
</div>
<?php
} elseif (isset($_POST['reset']) && verify_nonce()) {
    $settings->reset();
?>
<div class="notice notice-success is-dismissible">
    <p><?php _e('Options have been reset to default values!', 'leaflet-map'); ?></p>
</div>
<?php
} elseif (isset($_POST['clear-geocoder-cache']) && verify_nonce()) {
    include_once LEAFLET_MAP__PLUGIN_DIR . 'class.geocoder.php';
    Leaflet_Geocoder::remove_caches();
?>
<div class="notice notice-success is-dismissible">
    <p><?php _e('Location caches have been cleared!', 'leaflet-map'); ?></p>
</div>
<?php
}
/** END FORM SUBMISSION */

/** CHECK LEAFLET VERSION */
$db_js_url = $settings->get('js_url');
$unpkg_url = "https://unpkg.com/leaflet";
$is_unpkg_url = substr_compare($db_js_url, $unpkg_url, 0, strlen($unpkg_url)) === 0;

if ($is_unpkg_url && $db_js_url !== $settings->options[ 'js_url' ]->default) {
?>
    <div class="notice notice-info is-dismissible">
        <p><?php 
        _e('Info: your leaflet version may be out-of-sync with the latest default version: ', 'leaflet-map'); 
        echo Leaflet_Map::$leaflet_version;
        ?></p>
    </div>
<?php
}
/** END LEAFLET VERSION */
?>

<p><?php echo $description; ?></p>
<h3><?php _e('Found an issue?', 'leaflet-map') ?></h3>
<p><?php _e('Post it to ', 'leaflet-map') ?><b><?php _e('WordPress Support', 'leaflet-map') ?></b>: <a href="https://wordpress.org/support/plugin/leaflet-map/" target="_blank">Leaflet Map (WordPress)</a></p>
<p><?php _e('Add an issue on ', 'leaflet-map') ?><b>GitHub</b>: <a href="https://github.com/bozdoz/wp-plugin-leaflet-map/issues" target="_blank">Leaflet Map (GitHub)</a></p>

<div class="wrap">
    <div class="wrap">
    <form method="post">
        <?php wp_nonce_field(NONCE_ACTION, NONCE_NAME); ?>
        <div class="container">
            <h2><?php _e('Settings', 'leaflet-map'); ?></h2>
            <hr>
        </div>
    <?php
    foreach ($settings->options as $name => $option) {
        if (!$option->type) continue;
    ?>
    <div class="container">
        <label>
            <span class="label"><?php echo $option->display_name; ?></span>
            <span class="input-group">
            <?php
            $option->widget($name, $settings->get($name));
            ?>
            </span>
        </label>

        <?php
        if ($option->helptext) {
        ?>
        <div class="helptext">
            <p class="description"><?php echo $option->helptext; ?></p>
        </div>
        <?php
        }
        ?>
    </div>
    <?php
    }
    ?>
    <div class="submit">
        <input type="submit" 
            name="submit" 
            id="submit" 
            class="button button-primary" 
            value="<?php _e('Save Changes', 'leaflet-map'); ?>">
        <input type="submit" 
            name="reset" 
            id="reset" 
            class="button button-secondary" 
            value="<?php _e('Reset to Defaults', 'leaflet-map'); ?>">
        <input type="submit" 
            name="clear-geocoder-cache" 
            id="clear-geocoder-cache" 
            class="button button-secondary" 
            value="<?php _e('Clear Geocoder Cache', 'leaflet-map'); ?>">
    </div>

    </form>

    <div>
        <p><?php _e('Leaf icon provided by ', 'leaflet-map') ?><a href="https://fontawesome.com/" target="_blank">Font Awesome</a><?php _e( ', under their free license.', 'leaflet-map' ) ?></p>
    </div>

    </div>
</div>
