<?php
/*
 * Plugin Name: WF Reply to Comment
 * Plugin URI:  https://wordpress.org/plugins/wf-replytocomment/
 * Description: Let's subscribers to comment by replying to emails
 * Author:      Phil Marx
 * Author URI:  https://www.webfalken.de
 * Version:     0.9
 * Text Domain: reply-to-comment
 * Domain Path: /languages/
 * License:     GPL v2 or later
 */

/**
 * Returns Commenthash for this specific comment
 * @param  id $comment_id Comment ID of this specific comment
 * @return string             generated hash
 */
function wf_rtc_get_commenthash($comment_id)
{
	$commentdata = get_comment($comment_id);
	$commenthash = get_option("wf_commenthash_string");
	
	$hash = md5($commenthash . $commentdata->comment_date . $commentdata->comment_ID);
	
	return $hash;
}

/**
 * calls checkmail.php doing the connection to mailserver, checking for mails, inserting them into comments
 */
function do_wf_rtc_checkmail()
{
	include("checkmail.php");
}

/**
 * Runs when plugin gets activated
 * 
 */
function wf_rtc_activate()
{
	global $wpdb;
	
	// If constant AUTH_KEY is not existent, create own random key as pseudo-salt
	if(empty(AUTH_KEY)) define("AUTH_KEY", md5(get_option("active_plugins").time()));
	update_option("wf_commenthash_string", AUTH_KEY);
	
	/*
	Following lines are creating hashes for all existent comments. Doing a huge INSERT is much faster than calling add_commentmeta() multiple (up to 10.000+) times.
	 */
	$hash_sql = "INSERT INTO " . $wpdb->prefix . "commentmeta VALUES ";
	
	$lastcomments = $wpdb->get_results("SELECT comment_ID, comment_date FROM " . $wpdb->prefix . "comments WHERE comment_type=''", ARRAY_A);
	$count        = count($lastcomments);
	for ($i = 0; $i < $count; $i++) {
		$hash = wf_rtc_get_commenthash($lastcomments[$i]['comment_ID']);
		$hash_sql .= "('', " . $lastcomments[$i]['comment_ID'] . ", 'wf_commenthash', '" . $hash . "'), ";
	} 
	$hash_sql = substr($hash_sql, 0, -2) . ";";
	$wpdb->query($hash_sql);
	
	// Creating WP Cron for mailcheck - scheduled every five minutes
	if (!wp_next_scheduled('wf_rtc_checkmail')) {
		wp_schedule_event(time(), 'fiveminutes', 'wf_rtc_checkmail');
	} 
	
}

/**
 * Funtions runs when plugin is uninstalled
 */
function wf_rtc_uninstall()
{
	global $wpdb;

	// Calling a single query is much faster than calling delete_commentmeta() multiple (10.000+) times
	$wpdb->query("DELETE FROM " . $wpdb->prefix . "commentmeta WHERE meta_key='wf_commenthash'");
	
	// Deleting all created custom options
	delete_option("wf-rtc-mailbox");
	delete_option("wf-rtc-server");
	delete_option("wf-rtc-user");
	delete_option("wf-rtc-password");
	delete_option("wf-rtc-type");
	delete_option("wf-rtc-port");
	delete_option("wf-rtc-ssl");
	
}

/**
 * Runs when plugin is disabled, but not deleted
 */
function wf_rtc_deactivate()
{
	global $wpdb;
	
	// Deletes all hashes, so if option wf_commenthash_string get compromised, new hashes will be created.
	$wpdb->query("DELETE FROM " . $wpdb->prefix . "commentmeta WHERE meta_key='wf_commenthash'");
	
	// Delete WP Cron Event
	wp_clear_scheduled_hook('wf_rtc_checkmail');
	
}

/**
 * Modifes the text in the notification mail to add the comment hash.
 * @param  string $message The original message
 * @param  int $id      the comment ID
 * @return string          the modified message containing the comment hash
 */
function wf_rtc_modify_notification_text($message, $id)
{
	$comment = get_comment($id);

	// Only process mails of type comment, no pingbacks, ...
	if (empty($comment->comment_type)) {
		$message = str_replace(__('You can see all comments on this post here:'), __('You can reply to this comment just by replying to this email.', 'reply-to-comment') . "\r\n\r\n" . __('You can see all comments on this post here:'), $message);
		$message = $message . "\r\n\r\nComment-Hash: " . wf_rtc_get_commenthash($id);
	}

	return $message;
}

/**
 * Modifes notification mail headers, proceses Reply-To header
 * @param  string $headers Original header created by WordPress
 * @return string          Modified headers with Reply-To header
 */
function wf_rtc_modify_notification_headers($headers)
{
	$mail_address = get_option("wf-rtc-mailbox");
	
	if (preg_match('/Reply-To/', $headers) == 1) {
		$headers = preg_replace("/^Reply-To:(.*?)$/im", "Reply-To:" . $mail_address . "\n", $headers);
	}
	else {
		$headers = $headers . "Reply-To:" . $mail_address . "\n";
	}
	
	return $headers;
}

/**
 * Adds commenthash to wp_commentmeta
 * @param  int $comment_ID       Comment ID
 * @param  int $comment_approved Result of WordPress comment approval algorhitm
 * @return void                  
 */
function wf_rtc_add_hash($comment_ID, $comment_approved)
{
	if (1 === $comment_approved) {
		add_comment_meta($comment_ID, 'wf_commenthash', wf_rtc_get_commenthash($comment_ID));
	}
}

/**
 * Adds option page to WordPress Admin Dashboard
 * @return void
 */
function wf_rtc_adminmenu()
{
	add_options_page(__('Reply To Comment Settings', 'reply-to-comment'), __('Reply To Comment', 'reply-to-comment'), 'manage_options', 'reply-to-comment', 'wf_rtc_page');
}

/**
 * Creates Admin Page
 * @return void
 */
function wf_rtc_page()
{
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}
?>
  <div class="wrap">
    <h1><?php echo __('Reply To Comment', 'reply-to-comment'); ?></h1>
  </div>

  <form method="post" action="options.php">
    <?php
	settings_fields('wf-rtc-settings');
	do_settings_sections('wf-rtc-settings');
?>
      <table class="form-table">

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Mailbox', 'reply-to-comment');
?>
          </th>
          <td><input type="text" name="wf-rtc-mailbox" value="<?php
	echo esc_attr(get_option('wf-rtc-mailbox'));
?>" /><br /><label for="wf-rtc-mailbox"><?php
	echo __('Email address WordPress should use for Reply To Comment', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Server', 'reply-to-comment');
?>
          </th>
          <td><input type="text" name="wf-rtc-server" value="<?php
	echo esc_attr(get_option('wf-rtc-server'));
?>" /><br /><label for="wf-rtc-server"><?php
	echo __('Server address to this mailbox', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Username', 'reply-to-comment');
?>
          </th>
          <td><input type="text" name="wf-rtc-user" value="<?php
	echo esc_attr(get_option('wf-rtc-user'));
?>" /><br /><label for="wf-rtc-user"><?php
	echo __('Username for this mailbox', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Password', 'reply-to-comment');
?>
          </th>
          <td><input type="password" name="wf-rtc-password" value="<?php
	echo esc_attr(get_option('wf-rtc-password'));
?>" /><br /><label for="wf-rtc-password"><?php
	echo __('Password for this mailbox', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Type', 'reply-to-comment');
?>
          </th>
          <td><select name="wf-rtc-type">
          <option value="imap"<?php
	if (esc_attr(get_option('wf-rtc-type')) == "imap")
		echo ' selected="selected"';
?>>IMAP</option>
          <option value="pop3"<?php
	if (esc_attr(get_option('wf-rtc-type')) == "pop3")
		echo ' selected="selected"';
?>>POP3</option>
          </select><br /><label for="wf-rtc-type"><?php
	echo __('Which protocol do you want to use to access to mailbox?', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('Port', 'reply-to-comment');
?>
          </th>
          <td><input type="number" name="wf-rtc-port" value="<?php
	echo esc_attr(get_option('wf-rtc-port'));
?>" /><br /><label for="wf-rtc-port"><?php
	echo __('Port to mailbox server (IMAP Standard: 143 / POP3 Standard: 110)', 'reply-to-comment');
?></label></td>
        </tr>

        <tr valign="top">
          <th scope="row">
            <?php
	echo __('SSL', 'reply-to-comment');
?>
          </th>
          <td><input type="checkbox" name="wf-rtc-ssl" value="1" <?php
	if (esc_attr(get_option('wf-rtc-ssl')) == "1")
		echo ' checked="checked"';
?> /><br /><label for="wf-rtc-ssl"><?php
	echo __('Is the connection to the server encrypted? Please check, if port has to be changed (IMAP Standard: 993 / POP3 Standard: 995)', 'reply-to-comment');
?></label></td>
        </tr>
      </table>


      <?php
	submit_button();
?>
  </form>
  <?php
}

/**
 * Registers custom options
 * @return void
 */
function wf_rtc_register()
{
	register_setting('wf-rtc-settings', 'wf-rtc-mailbox', array(
		'sanitize_callback' => 'sanitize_email'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-server', array(
		'sanitize_callback' => 'sanitize_text_field'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-user', array(
		'sanitize_callback' => 'sanitize_text_field'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-password', array(
		'sanitize_callback' => 'sanitize_text_field'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-type', array(
		'sanitize_callback' => 'sanitize_text_field'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-port', array(
		'sanitize_callback' => 'intval'
	));
	register_setting('wf-rtc-settings', 'wf-rtc-ssl', array(
		'sanitize_callback' => 'sanitize_text_field'
	));
}

/**
 * Create custom WP cron interval (5 minutes)
 * @param  array $schedules current WP Cron Schedules
 * @return array            customized WP cron schadules including 5 minute interval
 */
function wf_rtc_schedules($schedules)
{
	$schedules['fiveminutes'] = array(
		'interval' => 300,
		'display' => __('Every five Minutes')
	);
	return $schedules;
}

register_deactivation_hook(__FILE__, 'wf_rtc_deactivate');
register_activation_hook(__FILE__, 'wf_rtc_activate');
register_uninstall_hook(__FILE__, 'wf_rtc_uninstall');
add_filter('comment_notification_text', 'wf_rtc_modify_notification_text', 10, 2);
add_filter('comment_notification_headers', 'wf_rtc_modify_notification_headers');
add_filter('cron_schedules', 'wf_rtc_schedules');
add_action('comment_post', 'wf_rtc_add_hash', 10, 2);
add_action('admin_menu', 'wf_rtc_adminmenu');
add_action('admin_init', 'wf_rtc_register');
<<<<<<< HEAD:index.php
add_action('wf_rtc_checkmail', 'do_wf_rtc_checkmail');
=======
add_action('wf_rtc_checkmail', 'do_wf_rtc_checkmail');
?>
>>>>>>> a1a923c9b58c4116e6cf5edd948487fbebb55c6b:reply-to-comment.php
