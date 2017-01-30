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
    add_action('admin_menu', 'addSettingsPage');
}

/**
 *
 */
function addSettingsPage()
{
    add_menu_page('FAF Optim', 'FAF Optim', 'administrator', __FILE__, 'settingsPage', 'dashicons-admin-generic');
}

/**
 *
 */
function settingsPage()
{
    ?>
    <div class="wrap">
        <h1>FAF Image Optimierung</h1>

        <p>Optimiere deine generierten Bilder (JPG, PNG)</p>

        <?php
        if (!`which jpegoptim`) {
            echo '<p>Du benötigst jpegoptim auf deinem Server!</p>';
        }
        if (!`which optipng`) {
            echo '<p>Du benötigst optipng auf deinem Server!</p>';
        }
        ?>

        <form method="post" action="">
            <dl>
                <dt>Quality</dt>
                <dd><input type="number" name="quality" value="80" placeholder="Quality"></dd>

                <dt>Thumbnail</dt>
                <dd><input type="checkbox" name="thumbnail" value="yes" checked placeholder="Thumbnail"></dd>

                <dt>Medium</dt>
                <dd><input type="checkbox" name="medium" value="yes" checked placeholder="Medium"></dd>

                <dt>Large</dt>
                <dd><input type="checkbox" name="large" value="yes" checked placeholder="Large"></dd>
            </dl>

            <?php submit_button('jetzt optimieren', 'primary', 'optimize'); ?>
        </form>

        <?php
        if (isset($_POST['optimize'])) {
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            );
            $images = new WP_Query($args);
            $sizes = array('thumbnail', 'medium', 'large');

            foreach ($images->posts as $image) {
                foreach ($sizes AS $size) {
                    if (isset($_POST[$size]) && $_POST[$size] === 'yes') {
                        optimizeImage($image, $size);
                    }
                }

            }
        }
        ?>
    </div>
<?php
}

/**
 * @param $image
 * @param string $size
 */
function optimizeImage($image, $size)
{
    $q = (int)$_POST['quality'];
    $quality = ($q >= 0 || $q <= 100) ? $q : 80;
    $file = get_attached_file($image->ID, true);
    $info = image_get_intermediate_size($image->ID, $size);
    $path = realpath(str_replace(wp_basename($file), $info['file'], $file));
    $size = getHumanFilesize(filesize($path));
    $pinfo = pathinfo($path);
    $ext = $pinfo['extension'];

    echo $path;
    echo ' > ';
    echo $size;
    echo '<br>';

    switch ($ext) {
        case 'jpeg';
        case 'jpg';
            system('jpegoptim -m' . $quality . ' --strip-all ' . $path);
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