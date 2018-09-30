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

function wf_rtc_get_commenthash($comment_id)
{
	$commentdata = get_comment($comment_id);
	$commenthash = get_option("wf_commenthash_string");
	
	$hash = md5($commenthash . $commentdata->comment_date . $commentdata->comment_ID);
	
	return $hash;
	
}

function do_wf_rtc_checkmail()
{
	include("checkmail.php");
}

function wf_rtc_activate()
{
	global $wpdb;
	
	if(empty(AUTH_KEY)) define("AUTH_KEY", md5(get_option("active_plugins").time()));
	update_option("wf_commenthash_string", AUTH_KEY);
	
	
	$hash_sql = "INSERT INTO " . $wpdb->prefix . "commentmeta VALUES ";
	
	$lastcomments = $wpdb->get_results("SELECT comment_ID, comment_date FROM " . $wpdb->prefix . "comments WHERE comment_type=''", ARRAY_A);
	$count        = count($lastcomments);
	for ($i = 0; $i < $count; $i++) {
		$hash = wf_rtc_get_commenthash($lastcomments[$i]['comment_ID']);
		$hash_sql .= "('', " . $lastcomments[$i]['comment_ID'] . ", 'wf_commenthash', '" . $hash . "'), ";
	} //$i = 0; $i < $count; $i++
	$hash_sql = substr($hash_sql, 0, -2) . ";";
	$wpdb->query($hash_sql);
	
	if (!wp_next_scheduled('wf_rtc_checkmail')) {
		wp_schedule_event(time(), 'fiveminutes', 'wf_rtc_checkmail');
	} //!wp_next_scheduled('wf_rtc_checkmail')
	
}

function wf_rtc_uninstall()
{
	global $wpdb;
	$wpdb->query("DELETE FROM " . $wpdb->prefix . "commentmeta WHERE meta_key='wf_commenthash'");
	
	delete_option("wf-rtc-mailbox");
	delete_option("wf-rtc-server");
	delete_option("wf-rtc-user");
	delete_option("wf-rtc-password");
	delete_option("wf-rtc-type");
	delete_option("wf-rtc-port");
	delete_option("wf-rtc-ssl");
	
}

function wf_rtc_deactivate()
{
	global $wpdb;
	$wpdb->query("DELETE FROM " . $wpdb->prefix . "commentmeta WHERE meta_key='wf_commenthash'");
	
	wp_clear_scheduled_hook('wf_rtc_checkmail');
	
}

function wf_rtc_modify_notification_text($message, $id)
{
	$comment = get_comment($id);
	if (empty($comment->comment_type)) {
		$message = str_replace(__('You can see all comments on this post here:'), __('You can reply to this comment just by replying to this email.', 'reply-to-comment') . "\r\n\r\n" . __('You can see all comments on this post here:'), $message);
		$message = $message . "\r\n\r\n" . __('Comment-Hash:', 'reply-to-comment') . ' ' . wf_rtc_get_commenthash($id);
		return $message;
	} //empty($comment->comment_type)
}

function wf_rtc_modify_notification_headers($headers)
{
	$mail_address = get_option("wf-rtc-mailbox");
	
	if (preg_match('/Reply-To/', $headers) == 1) {
		$headers = preg_replace("/^Reply-To:(.*?)$/im", "Reply-To:" . $mail_address . "\n", $headers);
	} //preg_match('/Reply-To/', $headers) == 1
	else {
		$headers = $headers . "Reply-To:" . $mail_address . "\n";
	}
	
	return $headers;
}

function wf_rtc_add_hash($comment_ID, $comment_approved)
{
	if (1 === $comment_approved) {
		add_comment_meta($comment_ID, 'wf_commenthash', wf_rtc_get_commenthash($comment_ID));
	} //1 === $comment_approved
}

function wf_rtc_adminmenu()
{
	add_options_page(__('Reply To Comment Settings', 'reply-to-comment'), __('Reply To Comment', 'reply-to-comment'), 'manage_options', 'reply-to-comment', 'wf_rtc_page');
}

function wf_rtc_page()
{
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	} //!current_user_can('manage_options')
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
add_action('wf_rtc_checkmail', 'do_wf_rtc_checkmail');
?>