<?php

include("../../../wp-load.php");

class CheckMail
{
	
	private function validate_contents($message)
	{
		
		$find_hash = preg_match("/" . __('Comment-Hash:', 'WF_CommentToReply') . " (.*)/i", $message, $matches);
		if ($find_hash == 1) {
			$hash = trim($matches[1]);
			
			// print_r($matches);
			
			$comment = get_comments(array(
				'meta_key' => 'wf_commenthash',
				'meta_value' => $hash
			));
			
			// print_r($comment);
			
			if ($comment != NULL) {
				$parent_post_id = $comment[0]->comment_post_ID;
				$commentid      = $comment[0]->comment_ID;
				
				return array(
					'comment_id' => $commentid,
					'post_id' => $parent_post_id
				);
				
			} //$comment != NULL
			
		} //$find_hash == 1
		else {
			return false;
		}
	}
	
	/**
	
	
	
	Copyright (c) 2011 by Josh Grochowski (josh[dot]kastang[at]gmail[dot]com).
	
	Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	
	
	* Strips quotes (older messages) from a message body.
	*
	* This function removes any lines that begin with a quote character (>).
	* Note that quotes in reply bodies will also be removed by this function,
	* so only use this function if you're okay with this behavior.
	*
	* @param $message (string)
	*   The message to be cleaned.
	* @param $plain_text_output (bool)
	*   Set to TRUE to also run the text through strip_tags() (helpful for
	*   cleaning up HTML emails).
	*
	* @return (string)
	*   Same as message passed in, but with all quoted text removed.
	*
	* @see http://stackoverflow.com/a/12611562/100134
	*/
	private function cleanReplyEmail($message, $plain_text_output = FALSE)
	{
		// Strip markup if $plain_text_output is set.
		if ($plain_text_output) {
			$message = strip_tags($message);
		} //$plain_text_output
		// Remove quoted lines (lines that begin with '>').
		$message = preg_replace("/(^\w.+:\n)?(^>.*(\n|$))+/mi", '', $message);
		// Remove lines beginning with 'On' and ending with 'wrote:' (matches
		// Mac OS X Mail, Gmail).
		$message = preg_replace("/^(On).*(wrote:).*$/sm", '', $message);
		// Remove lines like '----- Original Message -----' (some other clients).
		// Also remove lines like '--- On ... wrote:' (some other clients).
		$message = preg_replace("/^---.*$/mi", '', $message);
		// Remove lines like '____________' (some other clients).
		$message = preg_replace("/^____________.*$/mi", '', $message);
		// Remove blocks of text with formats like:
		//   - 'From: Sent: To: Subject:'
		//   - 'From: To: Sent: Subject:'
		//   - 'From: Date: To: Reply-to: Subject:'
		$message = preg_replace("/From:.*^(To:).*^(Subject:).*/sm", '', $message);
		// Remove any remaining whitespace.
		$message = trim($message);
		return $message;
	}
	
	public function MailConnect($host, $user, $password, $type, $port, $is_ssl = '')
	{
		$type             = strtolower($type);
		$connectionstring = '{' . $host . ':' . $port . '/' . $type;
		
		if ($is_ssl == 1) {
			$connectionstring .= '/ssl';
		} //$is_ssl == 1
		
		$connectionstring .= '}INBOX';
		$mbox = imap_open($connectionstring, $user, $password);
		
		return $mbox;
	}
	
	public function ProcessMails($mbox)
	{
		$headers = imap_headers($mbox);
		
		if ($headers) {
			$i = 1;
			foreach ($headers as $val) {
				$structure  = imap_fetchstructure($mbox, $i);
				$imapheader = imap_headerinfo($mbox, $i);
				
				if ($imapheader) {
					$sender_from = $imapheader->from;
					
					$sender_name  = $sender_from[0]->personal;
					$sender_email = $sender_from[0]->mailbox . '@' . $sender_from[0]->host;
					
				} //$imapheader
				
				$mailcontent = imap_qprint(imap_fetchbody($mbox, $i, 1));
				
				$validate = $this->validate_contents($mailcontent);
				if (is_array($validate)) {
					
					$commentdata = array(
						'comment_post_ID' => $validate['post_id'], // to which post the comment will show up
						'comment_author' => $sender_name, //fixed value - can be dynamic 
						'comment_author_email' => $sender_email, //fixed value - can be dynamic 
						'comment_content' => $this->cleanReplyEmail($mailcontent), //fixed value - can be dynamic 
						'comment_type' => '' //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
					);
					
					wp_new_comment($commentdata, true);
					imap_delete($mbox, $i);
					
					
				} //is_array($validate)
				else {
					imap_delete($mbox, $i);
					echo 'Nope!';
				}
				$i++;
			} //$headers as $val
		} //$headers
	}
	
	public function MailClose($mbox)
	{
		imap_close($mbox, CL_EXPUNGE);
	}
	
}

$mail = new CheckMail;

$mail_address = get_option("wf-rtc-mailbox");
$mailserver   = get_option("wf-rtc-server");
$mailuser     = get_option("wf-rtc-user");
$mailpassword = get_option("wf-rtc-password");
$mailtype     = get_option("wf-rtc-type");
$mailport     = get_option("wf-rtc-port");
$mail_is_ssl  = get_option("wf-rtc-ssl");

$mbox = $mail->MailConnect($mailserver, $mailuser, $mailpassword, $mailtype, $mailport, $mail_is_ssl);

$mail->ProcessMails($mbox);

$mail->MailClose($mbox);
?>