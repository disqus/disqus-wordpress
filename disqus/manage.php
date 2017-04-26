<?php
// HACK: For old versions of WordPress
if ( !function_exists('wp_nonce_field') ) {
    function wp_nonce_field() {}
}

global $dsq_api;

require(ABSPATH . 'wp-includes/version.php');

if ( !current_user_can('moderate_comments') ) {
    die('The account you\'re logged in to doesn\'t have permission to access this page.');
}

function has_valid_nonce() {
    $nonce_actions = array('upgrade', 'reset', 'install', 'settings', 'active');
    $nonce_form_prefix = 'dsq-form_nonce_';
    $nonce_action_prefix = 'dsq-wpnonce_';

    // Check all available nonce actions
    foreach ( $nonce_actions as $key => $value ) {
        if ( isset($_POST[$nonce_form_prefix.$value]) ) {
            check_admin_referer($nonce_action_prefix.$value, $nonce_form_prefix.$value);
            return true;
        }
    }

    return false;
}

if ( ! empty($_POST) ) {
    $nonce_result_check = has_valid_nonce();
    if ($nonce_result_check === false) {
        die('Unable to save changes. Make sure you are accessing this page from the Wordpress dashboard.');
    }
}

if( isset($_POST['dsq_username']) ) {
    $_POST['dsq_username'] = stripslashes($_POST['dsq_username']);
}

if( isset($_POST['dsq_password']) ) {
    $_POST['dsq_password'] = stripslashes($_POST['dsq_password']);
}

// Handle export function.
if( isset($_POST['export']) and DISQUS_CAN_EXPORT ) {
    require_once(dirname(__FILE__) . '/export.php');
    dsq_export_wp();
}

// Handle resetting.
if ( isset($_POST['reset']) ) {
    foreach (dsq_options() as $opt) {
        delete_option($opt);
    }
    unset($_POST);
    dsq_reset_database();
?>

<div class="wrap">
    <h2><?php echo dsq_i('Disqus Reset'); ?></h2>
    <form method="POST" action="?page=disqus">
        <?php wp_nonce_field('dsq-wpnonce_reset', 'dsq-form_nonce_reset'); ?>
        <p><?php echo dsq_i('Disqus has been reset successfully.') ?></p>
        <ul style="list-style: circle;padding-left:20px;">
            <li><?php echo dsq_i('Local settings for the plugin were removed.') ?></li>
            <li><?php echo dsq_i('Database changes by Disqus were reverted.') ?></li>
        </ul>
        <p>
            <?php echo dsq_i('If you wish to reinstall, you can do that now.') ?>
            <a href="?page=disqus&amp;step=1">&nbsp;<?php echo dsq_i('Reinstall') ?></a>
        </p>
    </form>
</div>
<?php
die();
}

// Post fields that require verification.
$check_fields = array(
    'dsq_user_api_key' => array(
            'key_name' => 'dsq_user_api_key',
            'min' => 64,
            'max' => 64,
        ),
    'disqus_api_key' => array(
            'key_name' => 'disqus_api_key',
            'min' => 64,
            'max' => 64,
        ),
    'disqus_public_key' => array(
            'key_name' => 'disqus_public_key',
            'min' => 64,
            'max' => 64,
        ),
    'disqus_secret_key' => array(
            'key_name' => 'disqus_secret_key',
            'min' => 64,
            'max' => 64,
        ),
    'disqus_partner_key' => array(
            'key_name' => 'disqus_partner_key',
            'min' => 64,
            'max' => 64,
        ),
    'dsq_forum' => array(
            'key_name' => 'dsq_forum',
            'min' => 1,
            'max' => 64,
        ),
    'disqus_forum_url' => array(
            'key_name' => 'disqus_forum_url',
            'min' => 1,
            'max' => 64,
        ),
    'disqus_replace' => array(
            'key_name' => 'disqus_replace',
            'min' => 3,
            'max' => 6,
        ),
    'dsq_username' => array(
            'key_name' => 'dsq_username',
            'min' => 3,
            'max' => 250,
        ),
    );

// Check keys keys and remove bad input.
foreach ( $check_fields as $key ) {

    if ( isset($_POST[$key['key_name']]) ) {

        // Strip tags before checking
        $_POST[$key['key_name']] = trim(strip_tags($_POST[$key['key_name']]));

        // Check usernames independently because they can have special characters
        // or be email addresses
        if ( 'dsq_username' ===  $key['key_name'] ) {
            if ( !is_valid_dsq_username($_POST[$key['key_name']], $key['min'], $key['max']) ) {
                unset($_POST[$key['key_name']]);
            }
        }
        else {
            if ( !is_valid_dsq_key($_POST[$key['key_name']], $key['min'], $key['max']) ) {
                unset($_POST[$key['key_name']]);
            }
        }
    }
}

function is_valid_dsq_username($value, $min=3, $max=250) {
    if ( is_email($value) ) {
        return true;
    }
    else {
        return is_valid_dsq_key($value, $min, $max);
    }
}

function is_valid_dsq_key($value, $min=1, $max=64) {
    return preg_match('/^[\0-9\\:A-Za-z_-]{'.$min.','.$max.'}+$/', $value);
}

// Handle advanced options.
if ( isset($_POST['disqus_forum_url']) && isset($_POST['disqus_replace']) ) {
    update_option('disqus_partner_key', isset($_POST['disqus_partner_key']) ? esc_attr( trim(stripslashes($_POST['disqus_partner_key'])) ) : '');
    update_option('disqus_replace', isset($_POST['disqus_replace']) ? esc_attr( $_POST['disqus_replace'] ) : 'all');
    update_option('disqus_cc_fix', isset($_POST['disqus_cc_fix']));
    update_option('dsq_external_js', isset($_POST['dsq_external_js']) ? '1' : '0');
    update_option('disqus_manual_sync', isset($_POST['disqus_manual_sync']));
    update_option('disqus_disable_ssr', isset($_POST['disqus_disable_ssr']));
    update_option('disqus_public_key', isset($_POST['disqus_public_key']) ? esc_attr( $_POST['disqus_public_key'] ) : '');
    update_option('disqus_secret_key', isset($_POST['disqus_secret_key']) ? esc_attr( $_POST['disqus_secret_key'] ) : '');
    // Handle SSO button uploads
    if ( version_compare($wp_version, '3.5', '>=') ) {
        // Use WP 3.5's new, streamlined, much-improved built-in media uploader

        // Only update if a value is actually POSTed, otherwise any time the form is saved the button will be un-set
        if ( $_POST['disqus_sso_button'] ) {
            update_option('disqus_sso_button', isset($_POST['disqus_sso_button']) ? esc_url( $_POST['disqus_sso_button'] ) : '');
        }
    } else {
        // WP is older than 3.5, use legacy, less-elegant media uploader
        if(isset($_FILES['disqus_sso_button'])) {
            dsq_image_upload_handler('disqus_sso_button');
        }
    }
    dsq_manage_dialog(dsq_i('Your settings have been changed.'));
}

// handle disqus_active
if ( isset($_POST['active']) && isset($_GET['active']) ) {
    update_option('disqus_active', ($_GET['active'] == '1' ? '1' : '0'));
}

$dsq_user_api_key = isset($_POST['dsq_user_api_key']) ? $_POST['dsq_user_api_key'] : null;

// Get installation step process (or 0 if we're already installed).
$step = @intval($_GET['step']);
if ($step > 1 && $step != 3 && $dsq_user_api_key) $step = 1;
elseif ($step == 2 && !isset($_POST['dsq_username'])) $step = 1;
$step = (dsq_is_installed()) ? 0 : ($step ? $step : 1);

// Handle installation process.
if ( 3 == $step && isset($_POST['dsq_forum']) && isset($_POST['dsq_user_api_key']) ) {
    list($dsq_forum_id, $dsq_forum_url) = explode(':', $_POST['dsq_forum']);
    update_option('disqus_forum_url', esc_attr( $dsq_forum_url ) );

    // Output javascript in external files by default
    update_option('dsq_external_js', '1');

    // Output Javascript in footer by default (no effect when dsq_external_js is enabld)
    update_option('disqus_cc_fix', '1');

    $api_key = $dsq_api->get_forum_api_key($_POST['dsq_user_api_key'], $dsq_forum_id);
    if ( !$api_key || $api_key < 0 ) {
        update_option('disqus_replace', 'replace');
        dsq_manage_dialog(
            dsq_i('There was an error completing the installation of Disqus.')
            . ' '
            . dsq_i('If you are still having issues, refer to the help documentation.')
            . ' '
            . '<a href="https://help.disqus.com/customer/portal/articles/472005" target="_blank">'
            . dsq_i('WordPress Help Page')
            . '</a>',
            true);
    } else {
        update_option('disqus_api_key', esc_attr( $api_key ));
        update_option('disqus_user_api_key', esc_attr( $_POST['dsq_user_api_key']) );
        update_option('disqus_replace', 'all');
        update_option('disqus_active', '1');
    }

    if (!empty($_POST['disqus_partner_key'])) {
        $partner_key = trim(stripslashes($_POST['disqus_partner_key']));
        if (!empty($partner_key)) {
            update_option('disqus_partner_key', $partner_key);
        }
    }
}

if ( 2 == $step && isset($_POST['dsq_username']) && isset($_POST['dsq_password']) ) {
    $dsq_user_api_key = $dsq_api->get_user_api_key($_POST['dsq_username'], $_POST['dsq_password']);
    if ( $dsq_user_api_key < 0 || !$dsq_user_api_key ) {
        $step = 1;
        dsq_manage_dialog($dsq_api->get_last_error(), true);
    }

    if ( $step == 2 ) {
        $dsq_sites = $dsq_api->get_forum_list($dsq_user_api_key);
        if ( $dsq_sites < 0 ) {
            $step = 1;
            dsq_manage_dialog($dsq_api->get_last_error(), true);
        } else if ( !$dsq_sites ) {
            $step = 1;
            dsq_manage_dialog(
                dsq_i('There aren\'t any sites associated with this account. Maybe you want to create a site?')
                . ' '
                . '<a href="https://disqus.com/admin/register/" target="_blank">'
                . dsq_i('Create a site')
                . '</a>',
                true);
        }
    }
}

$show_advanced = (isset($_GET['t']) && $_GET['t'] == 'adv');

?>
<div class="wrap" id="dsq-wrap">
    <ul id="dsq-tabs">
        <li<?php if (!$show_advanced) echo ' class="selected"'; ?> id="dsq-tab-main" rel="dsq-main">
            <?php echo (dsq_is_installed() ? dsq_i('Moderate') : dsq_i('Install')); ?>
        </li>
        <li<?php if ($show_advanced) echo ' class="selected"'; ?> id="dsq-tab-advanced" rel="dsq-advanced">
            <?php echo dsq_i('Plugin Settings'); ?>
        </li>
    </ul>

    <div id="dsq-main" class="dsq-content">
    <?php
switch ( $step ) {
case 3:
?>
        <div id="dsq-step-3" class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

            <p><?php echo dsq_i('Disqus has been installed on your blog.'); ?></p>
            <p>
                <?php echo dsq_i('If you have existing comments, you may wish to export them now.') ?>&nbsp;
                <?php echo dsq_i('Otherwise, you\'re all set, and the Disqus network is now powering comments on your site.'); ?>
            </p>
            <p><a href="edit-comments.php?page=disqus"><?php echo dsq_i('Continue to the moderation dashboard'); ?></a></p>
        </div>
<?php
    break;
case 2:
?>
        <div id="dsq-step-2" class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

            <form method="POST" action="?page=disqus&amp;step=3">
            <?php wp_nonce_field('dsq-wpnonce_install', 'dsq-form_nonce_install'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo dsq_i('Select a website'); ?></th>
                    <td>
<?php
foreach ( $dsq_sites as $counter => $dsq_site ):
?>
                        <input name="dsq_forum" type="radio" id="dsq-site-<?php echo esc_attr($counter); ?>" value="<?php echo esc_attr($dsq_site->id); ?>:<?php echo esc_attr($dsq_site->shortname); ?>" />
                        <label for="dsq-site-<?php echo esc_attr($counter); ?>"><strong><?php echo esc_attr($dsq_site->name); ?></strong> (<u><?php echo esc_attr($dsq_site->shortname); ?>.disqus.com</u>)</label>
                        <br />
<?php
endforeach;
?>
                        <hr />
                        <a href="https://disqus.com/admin/signup/"><?php echo dsq_i('Or register a new one on the Disqus website.'); ?></a>
                    </td>
                </tr>
            </table>

            <p class="submit" style="text-align: left">
                <input type="hidden" name="dsq_user_api_key" value="<?php echo esc_attr($dsq_user_api_key); ?>"/>
                <input name="submit" type="submit" class="button-primary button" value="Next &raquo;" />
            </p>
            </form>
        </div>
<?php
    break;
case 1:
?>
        <div id="dsq-step-1" class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

            <form method="POST" action="?page=disqus&amp;step=2">
            <?php wp_nonce_field('dsq-wpnonce_install', 'dsq-form_nonce_install'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row" valign="top"><?php echo dsq_i('Username or email'); ?></th>
                        <td>
                            <input id="dsq-username" name="dsq_username" tabindex="1" type="text" />
                            (<a href="https://disqus.com/profile/signup/"><?php echo dsq_i('don\'t have a Disqus Profile yet?'); ?></a>)
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" valign="top"><?php echo dsq_i('Password'); ?></th>
                        <td>
                            <input type="password" id="dsq-password" name="dsq_password" tabindex="2">
                            (<a href="https://disqus.com/forgot/"><?php echo dsq_i('forgot your password?'); ?></a>)
                        </td>
                    </tr>
                </table>

                <p class="submit" style="text-align: left">
                    <input name="submit" type="submit" class="button-primary button" value="Next &raquo;" tabindex="3">
                </p>
            </form>
        </div>
<?php
    break;
case 0:
    $url = get_option('disqus_forum_url');
    if ($url) { $mod_url = 'https://'.$url.'.disqus.com/admin/moderate/'; }
    else { $mod_url = 'https://disqus.com/admin/moderate/'; }

?>
        <div class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><a href="<?php echo esc_attr( $mod_url ) ?>"><img src="<?php echo esc_attr( plugins_url( '/media/images/logo.png', __FILE__ ) ); ?>"></a></h2>
            <a class="button-primary button" href="<?php echo esc_attr( $mod_url ) ?>" target="_blank"><?php echo dsq_i('Go to Disqus Moderation'); ?></a>
        </div>
<?php } ?>
    </div>

<?php
    $dsq_replace = get_option('disqus_replace');
    $dsq_forum_url = strtolower(get_option('disqus_forum_url'));
    $dsq_api_key = get_option('disqus_api_key');
    $dsq_user_api_key = get_option('disqus_user_api_key');
    $dsq_partner_key = get_option('disqus_partner_key');
    $dsq_cc_fix = get_option('disqus_cc_fix');
    $dsq_external_js = get_option('dsq_external_js');
    $dsq_manual_sync = get_option('disqus_manual_sync');
    $dsq_disable_ssr = get_option('disqus_disable_ssr');
    $dsq_public_key = get_option('disqus_public_key');
    $dsq_secret_key = get_option('disqus_secret_key');
    $dsq_sso_button = get_option('disqus_sso_button');
    $disqus_enabled = get_option('disqus_active') == '1';
    $disqus_enabled_state = $disqus_enabled ? 'enabled' : 'disabled';
?>
    <!-- Settings -->
    <div id="dsq-advanced" class="dsq-content dsq-advanced"<?php if (!$show_advanced) echo ' style="display:none;"'; ?>>
        <h2><?php echo dsq_i('Settings'); ?></h2>
        <p><?php echo dsq_i('Version: %s', esc_html(DISQUS_VERSION)); ?></p>

        <!-- Enable/disable Disqus toggle -->
        <form method="POST" action="?page=disqus&amp;active=<?php echo (string)((int)($disqus_enabled != true)); ?>">
        <?php wp_nonce_field('dsq-wpnonce_active', 'dsq-form_nonce_active'); ?>
            <p class="status">
                <?php echo dsq_i('Disqus comments are currently '); ?>
                <span class="dsq-<?php echo esc_attr( $disqus_enabled_state ); ?>-text"><?php echo $disqus_enabled_state; ?></span>
            </p>
            <input type="submit" name="active" class="button" value="<?php echo $disqus_enabled ? dsq_i('Disable') : dsq_i('Enable'); ?>" />
        </form>

        <!-- Configuration form -->
        <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('dsq-wpnonce_settings', 'dsq-form_nonce_settings'); ?>
        <table class="form-table">

            <tr>
                <th scope="row" valign="top"><?php echo '<h3>' . dsq_i('General') . '</h3>'; ?></th>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Forum Shortname'); ?></th>
                <td>
                    <input type="hidden" name="disqus_forum_url" value="<?php echo esc_attr($dsq_forum_url); ?>"/>
                    <code><?php echo esc_attr($dsq_forum_url); ?></code>
                    <br />
                    <?php echo dsq_i('This is the unique identifier for your website in Disqus, automatically set during installation.'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo '<h3>' . dsq_i('Appearance') . '</h3>'; ?></th>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Use Disqus Comments on'); ?></th>
                <td>
                    <select name="disqus_replace" tabindex="1" class="disqus-replace">
                        <option value="all" <?php if($dsq_replace == 'all'){echo 'selected';}?>><?php echo dsq_i('All blog posts.'); ?></option>
                        <option value="closed" <?php if('closed'==$dsq_replace){echo 'selected';}?>><?php echo dsq_i('Blog posts with closed comments only.'); ?></option>
                    </select>
                    <br />
                    <?php
                        if ($dsq_replace == 'closed') echo '<p class="dsq-alert">'.dsq_i('You have selected to only enable Disqus on posts with closed comments. If you aren\'t seeing Disqus on new posts, change this option to "All blog posts".').'</p>';
                        else echo dsq_i('Shows comments on either all blog posts, or ones with closed comments. Select the "Blog posts with closed comments only" option if you plan on disabling Disqus, but want to keep it on posts which already have comments.');
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo '<h3>' . dsq_i('Sync') . '</h3>'; ?></th>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Comment Importing'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_manual_sync" name="disqus_manual_sync" <?php if($dsq_manual_sync){echo 'checked="checked"';}?> >
                    <label for="disqus_manual_sync"><?php echo dsq_i('Disable automated comment importing'); ?></label>
                    <br /><?php echo dsq_i('If you have problems with WP-Cron taking too long, or have a large number of comments, you may wish to disable automated sync. Comments will only be imported to your local Wordpress database if you do so manually.'); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Server-Side Rendering'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_disable_ssr" name="disqus_disable_ssr" <?php if($dsq_disable_ssr){echo 'checked="checked"';}?> >
                    <label for="disqus_disable_ssr"><?php echo dsq_i('Disable server-side rendering of comments'); ?></label>
                    <br /><?php echo dsq_i('Hides comments from nearly all search engines.'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo '<h3>' . dsq_i('Patches') . '</h3>'; ?></th>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('External Javascript Files'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_external_javascript" name="dsq_external_js" <?php if($dsq_external_js == '1'){ echo 'checked="checked"'; } ?> >
                    <label for="disqus_external_javascript"><?php echo dsq_i('Render Javascript in external files'); ?></label>
                    <br /><?php echo dsq_i('This will render the Disqus scripts as external files in the footer as recommended by Wordpress. Disable this if are not seeing Disqus appear on pages that normally have comments. This will fix the issue if your theme does not support the \'wp_enqueue_script\' function, are caching your site on a CDN.'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Template Conflicts'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_comment_count" name="disqus_cc_fix" <?php if($dsq_external_js == '1'){ echo 'disabled '; } if($dsq_cc_fix == '1' || $dsq_external_js == '1'){ echo 'checked="checked"'; } ?> >
                    <label for="disqus_comment_count"><?php echo dsq_i('Output JavaScript in footer'); ?></label>
                    <br /><?php echo dsq_i('Enable this if you have problems with comment counts or other irregularities. For example: missing counts, counts always at 0, Disqus code showing on the page, broken image carousels, or longer-than-usual home page load times'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top" colspan="2">
                    <h3><?php echo dsq_i('Advanced'); ?></h3>
                    <h4><?php echo dsq_i('Single Sign-On'); ?></h4>
                    <p>
                        <?php echo dsq_i('Allows users to log in to Disqus via WordPress.'); ?>
                        <a href="https://help.disqus.com/customer/portal/articles/684744" target="_blank">
                            <?php echo dsq_i('More info on SSO'); ?>
                        </a>
                    </p>
                </th>
            </tr>
            <?php if (!empty($dsq_partner_key)) {// this option only shows if it was already present ?>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Disqus Partner Key'); ?></th>
                <td>
                    <input type="text" name="disqus_partner_key" value="<?php echo esc_attr($dsq_partner_key); ?>" tabindex="2">
                </td>
            </tr>
            <?php } ?>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('API Application Public Key'); ?></th>
                <td>
                    <input type="text" name="disqus_public_key" value="<?php echo esc_attr($dsq_public_key); ?>" tabindex="2">
                    <br />
                    <a href="https://disqus.com/api/applications/" target="_blank"><?php echo dsq_i('Disqus API Applications'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('API Application Secret Key'); ?></th>
                <td>
                    <input type="text" name="disqus_secret_key" value="<?php echo esc_attr($dsq_secret_key); ?>" tabindex="2">
                    <br />
                    <a href="https://disqus.com/api/applications/" target="_blank"><?php echo dsq_i('Disqus API Applications'); ?></a>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Custom Log-in Button'); ?></th>
                <td>
                    <?php if (!empty($dsq_sso_button)) { ?>
                    <img src="<?php echo esc_attr($dsq_sso_button); ?>" alt="<?php echo esc_attr($dsq_sso_button); ?>" />
                    <br />
                    <?php } ?>

                    <?php if ( version_compare($wp_version, '3.5', '>=') ) {
                        // HACK: Use WP's new (as of WP 3.5), streamlined, much-improved built-in media uploader

                        // Use WP 3.5's new consolidated call to get all necessary media uploader scripts and styles
                        wp_enqueue_media();
                    ?>

                        <input type="button" value="<?php echo ($dsq_sso_button ? dsq_i('Change') : dsq_i('Choose')).' '.dsq_i('button'); ?>" class="button upload_image_button" tabindex="2">
                        <input type="hidden" name="disqus_sso_button" id="disqus_sso_button" value=""/>
                    <?php } else { // use pre-WP 3.5 media upload functionality ?>
                        <input type="file" name="disqus_sso_button" value="<?php echo esc_attr($dsq_sso_button); ?>" tabindex="2">
                    <?php } ?>
                    <br />
                    <?php echo dsq_i('Adds a button to the Disqus log-in interface.'); ?>
                    (<a href="https://d8v2sqslxfuhj.cloudfront.net/docs/sso-button.png" target="_blank"><?php echo dsq_i('Example screenshot'); ?></a>)
                    <br />
                    <?php echo dsq_i('See our documentation for a template to create your own button.'); ?>&nbsp;
                    <a href="https://help.disqus.com/customer/portal/articles/236206#sso-login-button"><?php echo dsq_i('SSO button documentation'); ?></a>
                </td>
            </tr>

        </table>

        <p class="submit" style="text-align: left">
            <input type="hidden" name="disqus_api_key" value="<?php echo esc_attr($dsq_api_key); ?>"/>
            <input type="hidden" name="disqus_user_api_key" value="<?php echo esc_attr($dsq_user_api_key); ?>"/>
            <input name="submit" type="submit" value="Save" class="button-primary button" tabindex="4">
        </p>
        </form>

        <h3>Import and Export</h3>

        <table class="form-table">
            <?php if (DISQUS_CAN_EXPORT): ?>
            <tr id="export">
                <th scope="row" valign="top"><?php echo dsq_i('Export comments to Disqus'); ?></th>
                <td>
                    <div id="dsq_export">
                        <form method="POST" action="">
                        <?php wp_nonce_field('dsq-wpnonce_export', 'dsq-form_nonce_export'); ?>
                            <p class="status">
                                <a href="#" class="button"><?php echo dsq_i('Export Comments'); ?></a>
                                <?php echo dsq_i('This will export your existing WordPress comments to Disqus'); ?>
                            </p>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Sync Disqus with WordPress'); ?></th>
                <td>
                    <div id="dsq_import">
                        <form method="POST" action="">
                        <?php wp_nonce_field('dsq-wpnonce_import', 'dsq-form_nonce_import'); ?>
                            <div class="status">
                                <p>
                                    <a href="#" class="button"><?php echo dsq_i('Sync Comments'); ?></a>
                                    <?php echo dsq_i('This will download your Disqus comments and store them locally in WordPress'); ?>
                                </p>
                                <label>
                                    <input type="checkbox" id="dsq_import_wipe" name="dsq_import_wipe" value="1"/>
                                    <?php echo dsq_i('Remove all imported Disqus comments before syncing.'); ?>
                                </label>
                                <br/>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
        </table>

        <h3>Reset</h3>

        <table class="form-table">
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Reset Disqus'); ?></th>
                <td>
                    <form action="?page=disqus" method="POST">
                        <?php wp_nonce_field('dsq-wpnonce_reset', 'dsq-form_nonce_reset'); ?>
                        <p>
                            <input type="submit" value="Reset" name="reset" onclick="return confirm('<?php echo dsq_i('Are you sure you want to reset the Disqus plugin?'); ?>')" class="button" />
                            <?php echo dsq_i('This removes all Disqus-specific settings. Comments will remain unaffected.') ?>
                        </p>
                        <?php echo dsq_i('If you have problems with resetting taking too long you may wish to first manually drop the \'disqus_dupecheck\' index from your \'commentmeta\' table.') ?>
                    </form>
                </td>
            </tr>
        </table>
        <br/>

        <h3><?php echo dsq_i('Debug Information'); ?></h3>
        <p>
            <?php echo dsq_i('Having problems with the plugin? Check out our troubleshooting documentation.'); ?>&nbsp;
            <a href="https://help.disqus.com/customer/portal/articles/472005" target="_blank"><?php echo dsq_i('WordPress troubleshooting ocumentation'); ?></a>
            <?php echo dsq_i('You can also email us and include the debug info below.'); ?>&nbsp;
            <a href="mailto:help+wp@disqus.com"><?php echo dsq_i('Contact support'); ?></a>
        </p>
        <textarea style="width:90%; height:200px;">
URL: <?php echo esc_url( get_option('siteurl') ); ?>

PHP Version: <?php echo esc_html( phpversion() ); ?>

Version: <?php echo esc_html( $wp_version ); ?>

Active Theme:
<?php
if ( !function_exists('wp_get_theme') ) {
    $theme = get_theme(get_current_theme());
    echo esc_html( $theme['Name'] . ' ' . $theme['Version'] );
} else {
    $theme = wp_get_theme();
    echo esc_html( $theme->Name . ' ' . $theme->Version );
}
?>

URLOpen Method: <?php echo esc_html( dsq_url_method() ); ?>

Plugin Version: <?php echo esc_html( DISQUS_VERSION ); ?>

Settings:
dsq_is_installed: <?php echo esc_html( dsq_is_installed().'\n' ); ?>
<?php foreach (dsq_options() as $opt) {
    echo esc_html( $opt.': '.get_option($opt).'\n' );
}
?>

Plugins:
<?php
foreach (get_plugins() as $key => $plugin) {
    $isactive = '';
    if (is_plugin_active($key)) {
        $isactive = '(active)';
    }
    echo esc_html( $plugin['Name'].' '.$plugin['Version'].' '.$isactive.'\n' );
}
?>
        </textarea><br/>
    </div>
</div>
