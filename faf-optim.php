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
    register_setting('faf-optim', 'thumbnail');
    register_setting('faf-optim', 'medium');
    register_setting('faf-optim', 'large');
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

                <dt>Thumbnail optimieren</dt>
                <dd><input type="checkbox" name="thumbnail" value="yes" <?php if (get_option('thumbnail') === 'yes') { ?>checked<?php } ?>></dd>

                <dt>Medium optimieren</dt>
                <dd><input type="checkbox" name="medium" value="yes" <?php if (get_option('medium') === 'yes') { ?>checked<?php } ?>></dd>

                <dt>Large optimieren</dt>
                <dd><input type="checkbox" name="large" value="yes" <?php if (get_option('large') === 'yes') { ?>checked<?php } ?>></dd>
            </dl>

            <?php submit_button('Einstellungen speichern', 'primary', 'settings'); ?>
        </form>

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

        <h2><?php echo count($images->posts); ?> Bilder in der Mediathek.</h2>
        <h4 id="counter"></h4>
        <button id="optimize" type="button" class="button button-primary">Jetzt alle Bilder optimieren</button>

        <p id="report"></p>
        <script>
            jQuery(document).ready(function ($) {
                jQuery('#optimize').on('click', function(){
                    var images = [<?php echo implode(',', $ids); ?>];

                    jQuery.each(images, function(ix, val){
                        var data = {
                            'action': 'optimize',
                            'image': val
                        };

                        jQuery.post(ajaxurl, data, function (response) {
                            jQuery('#report').append(response);
                            jQuery('#counter').html((ix+1) + '/' + images.length + 'optimiert');
                        });
                    });
                });
            });
        </script>

    </div>
<?php
}

/**
 * @param $image
 * @param string $size
 */
function optimizeImage($image)
{
    $sizes = array('thumbnail', 'medium', 'large');

    foreach ($sizes AS $size) {
        if (get_option($size) === 'yes') {
            $q = get_option('quality') * 1;
            $quality = ($q >= 0 || $q <= 100) ? $q : 80;
            $file = get_attached_file($image->ID, true);
            $info = image_get_intermediate_size($image->ID, $size);
            $path = realpath(str_replace(wp_basename($file), $info['file'], $file));
            $size = getHumanFilesize(filesize($path));
            $pinfo = pathinfo($path);
            $ext = $pinfo['extension'];
            $strip = (get_option('keepexif') === 'yes') ? '--strip-com --strip-icc --strip-iptc' : '--strip-all';

            echo $path;
            echo ' > ';
            echo $size;
            echo '<br>';

            switch ($ext) {
                case 'jpeg';
                case 'jpg';
                    system('jpegoptim -m' . $quality . ' ' . $strip . ' ' . $path);
                    echo '<br>';
                    break;
                case 'png';
                    system('optipng ' . $path);
                    echo '<br>';
                    break;
                default:
                    break;
            }

            echo '<br>';
        }
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
    optimizeImage($image);

    wp_die(); // this is required to terminate immediately and return a proper response
}
add_action('wp_ajax_optimize', 'optimizeCallback');
