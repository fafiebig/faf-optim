<?php

/*
Plugin Name: FAF Optim
Plugin URI: https://github.com/fafiebig/faf-optim
Description: Optimize images of your WordPress media storage.
Version: 1.0
Author: F.A. Fiebig
Author URI: http://fafworx.com
License: GNU GENERAL PUBLIC LICENSE
*/

defined('ABSPATH') or die('No direct script access allowed!');

/**
 * trigger only when admin is loggedin
 */
if (is_admin()) {
    add_action('admin_menu', 'addOptionsPage');
    add_action('admin_init', 'registerOptions');
}

function getSizes()
{
    return get_intermediate_image_sizes();
}

/**
 *
 */
function addOptionsPage()
{
    add_options_page('FAF Optim', 'FAF Optim', 'manage_options', 'faf-optim.php', 'optionsPage');
}

/**
 *
 */
function registerOptions()
{ // whitelist options
    register_setting('faf-optim', 'keepexif');
    register_setting('faf-optim', 'autoopt');
    register_setting('faf-optim', 'quality');

    $sizes = getSizes();
    foreach ($sizes AS $size) {
        register_setting('faf-optim', $size);
    }
}

/**
 *
 */
function optionsPage()
{
    ?>
    <div class="wrap">
        <h1>FAF Image Optimierung</h1>

        <p>Optimiere alle generierten Bilder (JPG, PNG)</p>

        <?php
        if (!`which jpegoptim`) {
            echo '<p>Du benötigst jpegoptim auf deinem Server!</p>';
        }
        if (!`which optipng`) {
            echo '<p>Du benötigst optipng auf deinem Server!</p>';
        }
        ?>

        <table width="100%">
            <tr>
                <td width="50%" style="vertical-align:top">

                    <h2>Einstellungen:</h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('faf-optim');
                        do_settings_sections('faf-optim');
                        ?>
                        <dl>
                            <dt>Exif Daten behalten (kann nicht wieder hergestellt werden)</dt>
                            <dd><input type="checkbox" name="keepexif" value="yes" <?php if (get_option('keepexif') === 'yes') { ?>checked<?php } ?>></dd>

                            <dt>Autooptimierung (bei Mediaupload)</dt>
                            <dd><input type="checkbox" name="autoopt" value="yes" <?php if (get_option('autoopt') === 'yes') { ?>checked<?php } ?>></dd>

                            <dt>Qualität (JPGs)</dt>
                            <dd><input type="number" name="quality" value="<?php echo esc_attr(get_option('quality')); ?>" placeholder="Quality"></dd>

                            <?php
                                $sizes = getSizes();
                                foreach($sizes AS $size) {
                            ?>
                                    <dt><?php echo ucfirst($size); ?> optimieren</dt>
                                    <dd><input type="checkbox" name="<?php echo $size; ?>" value="yes" <?php if (get_option($size) === 'yes') { ?>checked<?php } ?>></dd>
                            <?php
                                }
                            ?>
                        </dl>

                        <?php submit_button('Einstellungen speichern', 'primary', 'settings'); ?>
                    </form>

                </td>
                <td width="50%" style="vertical-align:top">

                    <?php
                    $ids    = [];
                    $args   = array(
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
                        'post_status' => 'inherit',
                        'posts_per_page' => -1,
                    );
                    $images = new WP_Query($args);

                    foreach ($images->posts as $image) {
                        $ids[] = $image->ID;
                    }
                    ?>

                    <h2><span id="counter"></span><?php echo count($images->posts); ?> Bilder in der Mediathek.</h2>
                    <button id="optimize" type="button" class="button button-primary">Optimieren jetzt alle Bilder</button>

                    <p>
                        <textarea id="report" style="width:80%; height:200px;"></textarea>
                    </p>

                </td>
            </tr>
        </table>

        <script>
            jQuery(document).ready(function ($) {
                jQuery('#optimize').on('click', function(){
                    var images = [<?php echo implode(',', $ids); ?>];
                    jQuery('#report').html('');

                    jQuery.each(images, function(ix, val){

                        setTimeout( function(){
                            var data = {
                                'action': 'optimize',
                                'image': val
                            };

                            jQuery.post(ajaxurl, data, function (response) {
                                jQuery('#report').append(response);
                                jQuery('#counter').html((ix+1) + ' / ');
                            })
                        }, 500);

                    });
                });
            });
        </script>

    </div>
<?php
}

/**
 * @param $path
 */
function optimizeImage($path)
{
    if (!is_file($path)) {
        return false;
    }

    $q          = get_option('quality') * 1;
    $quality    = ($q >= 0 || $q <= 100) ? $q : 80;

    //$size   = getHumanFilesize(filesize($path));
    $pinfo  = pathinfo($path);
    $ext    = $pinfo['extension'];
    $strip  = (get_option('keepexif') === 'yes') ? '--strip-com --strip-icc --strip-iptc' : '--strip-all';

    switch ($ext) {
        case 'jpeg';
        case 'jpg';
            system('jpegoptim -m' . $quality . ' ' . $strip . ' ' . $path);
            echo PHP_EOL;
            break;
        case 'png';
            system('optipng ' . $path);
            echo PHP_EOL;
            break;
        default:
            break;
    }
}

/**
 * @param $bytes
 * @param int $decimals
 * @return string
 */
function getHumanFilesize($bytes, $decimals = 2)
{
    $size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 *
 */
function optimizeCallback()
{
    $image = get_post($_POST['image']);
    $sizes = getSizes();

    foreach ($sizes AS $size) {
        if (get_option($size) === 'yes') {

            $file = get_attached_file($image->ID, true);
            $info = image_get_intermediate_size($image->ID, $size);
            $path = realpath(str_replace(wp_basename($file), $info['file'], $file));

            optimizeImage($path);
        }
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}
add_action('wp_ajax_optimize', 'optimizeCallback');

/**
 * @param $postID
 * @param $post
 * @param $update
 */
function optimizeAfterUpload( $data )
{
    if (get_option('autoopt') === 'yes') {
        $path   = wp_upload_dir();
        $dir    = $path['path'];
        $sizes  = getSizes();

        foreach ($sizes AS $size) {
            if (get_option($size) === 'yes' && isset($data['sizes'][$size]['file'])) {
                $file = $dir.'/'.$data['sizes'][$size]['file'];

                optimizeImage($file);
            }
        }
    }

    return $data;
}
//add_action( 'wp_generate_attachment_metadata', 'optimizeAfterUpload' );
