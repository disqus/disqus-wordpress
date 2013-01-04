<?php
global $dsq_api;

require(ABSPATH . 'wp-includes/version.php');

if ( !current_user_can('moderate_comments') ) {
    die();
}

if(isset($_POST['dsq_username'])) {
    $_POST['dsq_username'] = stripslashes($_POST['dsq_username']);
}

if(isset($_POST['dsq_password'])) {
    $_POST['dsq_password'] = stripslashes($_POST['dsq_password']);
}

// HACK: For old versions of WordPress
if ( !function_exists('wp_nonce_field') ) {
    function wp_nonce_field() {}
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
        <p><?php echo dsq_i('Disqus has been reset successfully.') ?></p>
        <ul style="list-style: circle;padding-left:20px;">
            <li><?php echo dsq_i('Local settings for the plugin were removed.') ?></li>
            <li><?php echo dsq_i('Database changes by Disqus were reverted.') ?></li>
        </ul>
        <p><?php echo dsq_i('If you wish to <a href="?page=disqus&amp;step=1">reinstall</a>, you can do that now.') ?></p>
    </form>
</div>
<?php
die();
}

// Clean-up POST parameters.
foreach ( array('dsq_forum', 'dsq_username', 'dsq_user_api_key') as $key ) {
    if ( isset($_POST[$key]) ) { $_POST[$key] = strip_tags($_POST[$key]); }
}


// Handle advanced options.
if ( isset($_POST['disqus_forum_url']) && isset($_POST['disqus_replace']) ) {
    update_option('disqus_partner_key', trim(stripslashes($_POST['disqus_partner_key'])));
    update_option('disqus_replace', $_POST['disqus_replace']);
    update_option('disqus_cc_fix', isset($_POST['disqus_cc_fix']));
    update_option('disqus_manual_sync', isset($_POST['disqus_manual_sync']));
    update_option('disqus_disable_ssr', isset($_POST['disqus_disable_ssr']));
    update_option('disqus_public_key', $_POST['disqus_public_key']);
    update_option('disqus_secret_key', $_POST['disqus_secret_key']);
    // Handle any SSO button and icon uploads
    if ( version_compare($wp_version, '3.5', '>=') ) {
        // Use WP 3.5's new, streamlined, much-improved built-in media uploader

        // Only update if a value is actually POSTed, otherwise any time the form is saved the button and icon will be un-set
        if ($_POST['disqus_sso_button']) { update_option('disqus_sso_button', $_POST['disqus_sso_button']); }
        if ($_POST['disqus_sso_icon']) { update_option('disqus_sso_icon', $_POST['disqus_sso_icon']); }
    } else {
        // WP is older than 3.5, use legacy, less-elegant media uploader
        if(isset($_FILES['disqus_sso_button'])) {
            dsq_image_upload_handler('disqus_sso_button');
        }
        if(isset($_FILES['disqus_sso_icon'])) {
            dsq_image_upload_handler('disqus_sso_icon');
        }
    }
    dsq_manage_dialog(dsq_i('Your settings have been changed.'));
}

// handle disqus_active
if (isset($_GET['active'])) {
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
    update_option('disqus_forum_url', $dsq_forum_url);
    $api_key = $dsq_api->get_forum_api_key($_POST['dsq_user_api_key'], $dsq_forum_id);
    if ( !$api_key || $api_key < 0 ) {
        update_option('disqus_replace', 'replace');
        dsq_manage_dialog(dsq_i('There was an error completing the installation of Disqus. If you are still having issues, refer to the <a href="http://docs.disqus.com/help/87/">WordPress help page</a>.'), true);
    } else {
        update_option('disqus_api_key', $api_key);
        update_option('disqus_user_api_key', $_POST['dsq_user_api_key']);
        update_option('disqus_replace', 'all');
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
            dsq_manage_dialog(dsq_i('There aren\'t any sites associated with this account. Maybe you want to <a href="%s">create a site</a>?', 'http://disqus.com/admin/register/'), true);
        }
    }
}

$show_advanced = (isset($_GET['t']) && $_GET['t'] == 'adv');

?>
<div class="wrap" id="dsq-wrap">
    <ul id="dsq-tabs">
        <li<?php if (!$show_advanced) echo ' class="selected"'; ?> id="dsq-tab-main" rel="dsq-main"><?php echo (dsq_is_installed() ? dsq_i('Moderate') : dsq_i('Install')); ?></li>
        <li<?php if ($show_advanced) echo ' class="selected"'; ?> id="dsq-tab-advanced" rel="dsq-advanced"><?php echo dsq_i('Plugin Settings'); ?></li>
    </ul>

    <div id="dsq-main" class="dsq-content">
    <?php
switch ( $step ) {
case 3:
?>
        <div id="dsq-step-3" class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

            <p><?php echo dsq_i('Disqus has been installed on your blog.'); ?></p>
            <p><?php echo dsq_i('If you have existing comments, you may wish to <a href="?page=disqus&amp;t=adv#export">export them</a> now. Otherwise, you\'re all set, and the Disqus network is now powering comments on your blog.'); ?></p>
            <p><a href="edit-comments.php?page=disqus"><?php echo dsq_i('Continue to the moderation dashboard'); ?></a></p>
        </div>
<?php
    break;
case 2:
?>
        <div id="dsq-step-2" class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

            <form method="POST" action="?page=disqus&amp;step=3">
            <?php wp_nonce_field('dsq-install-2'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo dsq_i('Select a website'); ?></th>
                    <td>
<?php
foreach ( $dsq_sites as $counter => $dsq_site ):
?>
                        <input name="dsq_forum" type="radio" id="dsq-site-<?php echo $counter; ?>" value="<?php echo $dsq_site->id; ?>:<?php echo $dsq_site->shortname; ?>" />
                        <label for="dsq-site-<?php echo $counter; ?>"><strong><?php echo htmlspecialchars($dsq_site->name); ?></strong> (<u><?php echo $dsq_site->shortname; ?>.disqus.com</u>)</label>
                        <br />
<?php
endforeach;
?>
                        <hr />
                        <a href="<?php echo DISQUS_URL; ?>comments/register/"><?php echo dsq_i('Or register a new one on the Disqus website.'); ?></a>
                    </td>
                </tr>
            </table>

            <p class="submit" style="text-align: left">
                <input type="hidden" name="dsq_user_api_key" value="<?php echo htmlspecialchars($dsq_user_api_key); ?>"/>
                <input name="submit" type="submit" value="Next &raquo;" />
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
            <?php wp_nonce_field('dsq-install-1'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo dsq_i('Username'); ?></th>
                    <td>
                        <input id="dsq-username" name="dsq_username" tabindex="1" type="text" />
                        <a href="http://disqus.com/profile/signup/"><?php echo dsq_i('(don\'t have a Disqus Profile yet?)'); ?></a>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo dsq_i('Password'); ?></th>
                    <td>
                        <input type="password" id="dsq-password" name="dsq_password" tabindex="2">
                        <a href="http://disqus.com/forgot/"><?php echo dsq_i('(forgot your password?)'); ?></a>
                    </td>
                </tr>
            </table>

            <p class="submit" style="text-align: left">
                <input name="submit" type="submit" value="Next &raquo;" tabindex="3">
            </p>

            <script type="text/javascript"> document.getElementById('dsq-username').focus(); </script>
            </form>
        </div>
<?php
    break;
case 0:
    $url = get_option('disqus_forum_url');
    if ($url) { $mod_url = 'http://'.$url.'.'.DISQUS_DOMAIN.'/admin/moderate/'; }
    else { $mod_url = DISQUS_URL.'admin/moderate/'; }
?>
        <div class="dsq-main"<?php if ($show_advanced) echo ' style="display:none;"'; ?>>
            <h2><a href="<?php echo $mod_url ?>"><img src="<?php echo DSQ_PLUGIN_URL; ?>/media/images/logo.png"></a></h2>
            <iframe src="<?php echo $mod_url ?>?template=wordpress" style="width: 100%; height: 80%; min-height: 600px;"></iframe>
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
    $dsq_manual_sync = get_option('disqus_manual_sync');
    $dsq_disable_ssr = get_option('disqus_disable_ssr');
    $dsq_public_key = get_option('disqus_public_key');
    $dsq_secret_key = get_option('disqus_secret_key');
    $dsq_sso_button = get_option('disqus_sso_button');
    $dsq_sso_icon = get_option('disqus_sso_icon');
?>
    <!-- Settings -->
    <div id="dsq-advanced" class="dsq-content dsq-advanced"<?php if (!$show_advanced) echo ' style="display:none;"'; ?>>
        <h2><?php echo dsq_i('Settings'); ?></h2>
        <p><?php echo dsq_i('Version: %s', esc_html(DISQUS_VERSION)); ?></p>
        <?php
        if (get_option('disqus_active') === '0') {
            // disqus is not active
            echo dsq_i('<p class="status">Disqus comments are currently disabled. (<a href="?page=disqus&amp;active=1">Enable</a>)</p>');
        } else {
            echo dsq_i('<p class="status">Disqus comments are currently enabled. (<a href="?page=disqus&amp;active=0">Disable</a>)</p>');
        }
        ?>
        <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('dsq-advanced'); ?>
        <table class="form-table">
            
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('<h3>General</h3>'); ?></th>
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
                <th scope="row" valign="top"><?php echo dsq_i('<h3>Appearance</h3>'); ?></th>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Use Disqus Comments on'); ?></th>
                <td>
                    <select name="disqus_replace" tabindex="1" class="disqus-replace">
                        <option value="all" <?php if('all'==$dsq_replace){echo 'selected';}?>><?php echo dsq_i('On all existing and future blog posts.'); ?></option>
                        <option value="closed" <?php if('closed'==$dsq_replace){echo 'selected';}?>><?php echo dsq_i('Only on blog posts with closed comments.'); ?></option>
                    </select>
                    <br />
                    <?php echo dsq_i('Your WordPress comments will never be lost.'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('<h3>Sync</h3>'); ?></th>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Comment Sync'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_manual_sync" name="disqus_manual_sync" <?php if($dsq_manual_sync){echo 'checked="checked"';}?> >
                    <label for="disqus_manual_sync"><?php echo dsq_i('Disable automated comment importing'); ?></label>
                    <br /><?php echo dsq_i('If you have problems with WP-Cron taking too long, or have a large number of comments, you may wish to disable automated sync. Keep in mind this means comments will not automatically sync to your local WordPress database.'); ?>
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
                <th scope="row" valign="top"><?php echo dsq_i('<h3>Patches</h3>'); ?></th>
            </tr>

            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Template Conflicts'); ?></th>
                <td>
                    <input type="checkbox" id="disqus_comment_count" name="disqus_cc_fix" <?php if($dsq_cc_fix){echo 'checked="checked"';}?> >
                    <label for="disqus_comment_count"><?php echo dsq_i('Output JavaScript in footer'); ?></label>
                    <br /><?php echo dsq_i('Enable this if you have problems with comment counts or other irregularities. For example: missing counts, counts always at 0, Disqus code showing on the page, broken image carousels, or longer-than-usual home page load times (<a href="%s" onclick="window.open(this.href); return false">more info</a>).', 'http://docs.disqus.com/help/87/'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row" valign="top" colspan="2"><?php echo dsq_i('<h3>Advanced</h3><h4>Single Sign-On</h4><p>Allows users to log in to Disqus via WordPress. (<a href="%s" onclick="window.open(this.href); return false">More info on SSO</a>)</p>', 'http://help.disqus.com/customer/portal/articles/684744'); ?></th>
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
                    <?php echo dsq_i('Found at <a href="%s">Disqus API Applications</a>.','http://disqus.com/api/applications/'); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('API Application Secret Key'); ?></th>
                <td>
                    <input type="text" name="disqus_secret_key" value="<?php echo esc_attr($dsq_secret_key); ?>" tabindex="2">
                    <br />
                    <?php echo dsq_i('Found at <a href="%s">Disqus API Applications</a>.','http://disqus.com/api/applications/'); ?>
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
                        wp_enqueue_media(); ?>

                        <script type="text/javascript">
                        // Uploading files
                        var file_frame;
                         
                          jQuery('.upload_image_button').live('click', function( event ){
                         
                            event.preventDefault();
                         
                            // If the media frame already exists, reopen it.
                            if ( file_frame ) {
                              file_frame.open();
                              return;
                            }
                         
                            // Create the media frame.
                            file_frame = wp.media.frames.file_frame = wp.media({
                              title: jQuery( this ).data( 'uploader_title' ),
                              button: {
                                text: jQuery( this ).data( 'uploader_button_text' ),
                              },
                              multiple: false  // Set to true to allow multiple files to be selected
                            });
                         
                            // When an image is selected, run a callback.
                            file_frame.on( 'select', function() {
                              // We set multiple to false so only get one image from the uploader
                              attachment = file_frame.state().get('selection').first().toJSON();
                         
                              // Do something with attachment.id and/or attachment.url here
                              jQuery('#disqus_sso_button').val(attachment.url);
                            });
                         
                            // Finally, open the modal
                            file_frame.open();
                          });
                        </script>

                        <input type="button" value="<?php echo ($dsq_sso_button ? dsq_i('Change') : dsq_i('Choose')).' '.dsq_i('button'); ?>" class="button upload_image_button" tabindex="2">
                        <input type="hidden" name="disqus_sso_button" id="disqus_sso_button" value=""/>
                    <?php } else { // use pre-WP 3.5 media upload functionality ?>
                        <input type="file" name="disqus_sso_button" value="<?php echo esc_attr($dsq_sso_button); ?>" tabindex="2">
                    <?php } ?>
                    <br />
                    <?php echo dsq_i('Adds a button to the Disqus log-in interface. (<a href="%s">Example screenshot</a>.)','http://content.disqus.com/docs/sso-button.png'); ?>
                    <?php echo dsq_i('<br />See <a href="%s">our SSO button documentation</a> for a template to create your own button.','http://help.disqus.com/customer/portal/articles/236206#sso-login-button'); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Custom Log-in Icon<br>'); ?></th>
                <td>
                    <?php if (!empty($dsq_sso_icon)) { ?>
                    <img src="<?php echo esc_attr($dsq_sso_icon); ?>" alt="<?php echo esc_attr($dsq_sso_icon); ?>" />
                    <br />
                    <?php } ?>
                    <?php if ( version_compare($wp_version, '3.5', '>=') ) {
                        // HACK: Use WP's new (as of WP 3.5), streamlined, much-improved built-in media uploader
                        
                        // Use WP 3.5's new consolidated call to get all necessary media uploader scripts and styles
                        wp_enqueue_media(); ?>

                        <script type="text/javascript">
                        // Uploading files
                        var file_frame2;
                         
                          jQuery('.upload_image_button2').live('click', function( event ){
                         
                            event.preventDefault();
                         
                            // If the media frame already exists, reopen it.
                            if ( file_frame2 ) {
                              file_frame2.open();
                              return;
                            }
                         
                            // Create the media frame.
                            file_frame2 = wp.media.frames.file_frame = wp.media({
                              title: jQuery( this ).data( 'uploader_title' ),
                              button: {
                                text: jQuery( this ).data( 'uploader_button_text' ),
                              },
                              multiple: false  // Set to true to allow multiple files to be selected
                            });
                         
                            // When an image is selected, run a callback.
                            file_frame2.on( 'select', function() {
                              // We set multiple to false so only get one image from the uploader
                              attachment = file_frame2.state().get('selection').first().toJSON();
                         
                              // Do something with attachment.id and/or attachment.url here
                              jQuery('#disqus_sso_icon').val(attachment.url);
                            });
                         
                            // Finally, open the modal
                            file_frame2.open();
                          });
                        </script>

                        <input type="button" value="<?php echo ($dsq_sso_icon ? dsq_i('Change') : dsq_i('Choose')).' '.dsq_i('icon'); ?>" class="button upload_image_button2" tabindex="2">
                        <input type="hidden" name="disqus_sso_icon" id="disqus_sso_icon" value=""/>
                    <?php } else { // use pre-WP 3.5 media upload functionality ?>
                        <input type="file" name="disqus_sso_icon" value="<?php echo esc_attr($dsq_sso_icon); ?>" tabindex="2">
                    <?php } ?>
                    <br />
                    <?php echo dsq_i('Adds an icon to the Disqus Classic log-in modal. This does not apply for sites using Disqus 2012. (<a href="%s">Example screenshot</a>.)','http://content.disqus.com/docs/sso-icon.png'); ?>
                    <?php echo dsq_i('<br />Dimensions: 16x16.'); ?>
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
                        <p class="status"><a href="#" class="button"><?php echo dsq_i('Export Comments'); ?></a>  <?php echo dsq_i('This will export your existing WordPress comments to Disqus'); ?></p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th scope="row" valign="top"><?php echo dsq_i('Sync Disqus with WordPress'); ?></th>
                <td>
                    <div id="dsq_import">
                        <div class="status">
                            <p><a href="#" class="button"><?php echo dsq_i('Sync Comments'); ?></a>  <?php echo dsq_i('This will download your Disqus comments and store them locally in WordPress'); ?></p>
                            <label><input type="checkbox" id="dsq_import_wipe" name="dsq_import_wipe" value="1"/> <?php echo dsq_i('Remove all imported Disqus comments before syncing.'); ?></label><br/>
                        </div>
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
                        <?php wp_nonce_field('dsq-reset'); ?>
                        <p><input type="submit" value="Reset" name="reset" onclick="return confirm('<?php echo dsq_i('Are you sure you want to reset the Disqus plugin?'); ?>')" class="button" /> <?php echo dsq_i('This removes all Disqus-specific settings. Comments will remain unaffected.') ?></p>
                        <?php echo dsq_i('If you have problems with resetting taking too long you may wish to first manually drop the <code>disqus_dupecheck</code> index from your <code>commentmeta</code> table.') ?>
                    </form>
                </td>
            </tr>
        </table>
        <br/>

        <h3><?php echo dsq_i('Debug Information'); ?></h3>
        <p><?php echo dsq_i('Having problems with the plugin? Check out our <a href="%s" onclick="window.open(this.href); return false">WordPress Troubleshooting</a> documentation. You can also <a href="%s">drop us a line</a> including the following details and we\'ll do what we can.', 'http://docs.disqus.com/help/87/', 'mailto:help+wp@disqus.com'); ?></p>
        <textarea style="width:90%; height:200px;">URL: <?php echo get_option('siteurl'); ?>
PHP Version: <?php echo phpversion(); ?>
Version: <?php echo $wp_version; ?>
Active Theme: <?php $theme = get_theme(get_current_theme()); echo $theme['Name'].' '.$theme['Version']; ?>
URLOpen Method: <?php echo dsq_url_method(); ?>

Plugin Version: <?php echo DISQUS_VERSION; ?>

Settings:

dsq_is_installed: <?php echo dsq_is_installed(); ?>

<?php foreach (dsq_options() as $opt) {
    echo $opt.': '.get_option($opt)."\n";
} ?>

Plugins:

<?php
foreach (get_plugins() as $key => $plugin) {
    $isactive = "";
    if (is_plugin_active($key)) {
        $isactive = "(active)";
    }
    echo $plugin['Name'].' '.$plugin['Version'].' '.$isactive."\n";
}
?></textarea><br/>
    </div>
</div>
