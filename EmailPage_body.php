<?php
if( !defined( 'MEDIAWIKI' ) ) die();

class SpecialEmailPage extends SpecialPage {

	var $recipients = array();
	var $title;
	var $subject;
	var $message;
	var $group;
	var $list;
	var $textonly;
	var $css;
	var $record;
	var $db;
	var $parser;
	var $args;

	public function __construct() {
		global $wgEmailPageGroup;
		parent::__construct( 'EmailPage', $wgEmailPageGroup );
	}

	/**
	 * Override SpecialPage::execute($param = '')
	 */
	function execute( $param ) {
		global $wgOut, $wgUser, $wgRequest, $wgParser, $wgEmailPageContactsCat, $wgGroupPermissions, $wgSitename,
			$wgRecordAdminCategory, $wgEmailPageCss, $wgEmailPageAllowAllUsers, $wgEmergencyContact;

		$db = wfGetDB( DB_SLAVE );
		$param = str_replace( '_', ' ', $param );
		$this->setHeaders();

		// Get info from request or set to defaults
		$this->title    = $wgRequest->getText( 'ea-title', $param );
		$this->from     = $wgRequest->getText( 'ea-from' );
		$this->subject  = $wgRequest->getText( 'ea-subject', wfMessage( 'ea-pagesend', $this->title, $wgSitename )->text() );
		$this->message  = $wgRequest->getText( 'ea-message' );
		$this->group    = $wgRequest->getText( 'ea-group' );
		$this->to       = $wgRequest->getText( 'ea-to' );
		$this->cc       = $wgRequest->getText( 'ea-cc' );
		$this->textonly = $wgRequest->getText( 'ea-textonly', false );
		$this->css      = $wgRequest->getText( 'ea-css', $wgEmailPageCss );
		$this->record   = $wgRequest->getText( 'ea-record', false );
		$this->addcomments = $wgRequest->getText( 'ea-addcomments', false );
		$this->db       = $db;

		// Bail if no page title to send has been specified
		if( $this->title ) $wgOut->addWikiText( "===" . wfMessage( 'ea-heading', $this->title )->text() . "===" );
		else return $wgOut->addWikiText( wfMessage( 'ea-nopage' )->text() );

		// If the send button was clicked, attempt to send and exit
		if( $wgRequest->getText( 'ea-send', false ) ) return $this->send();

		// Render form
		$special = SpecialPage::getTitleFor( 'EmailPage' );
		$wgOut->addHTML( Xml::element( 'form', array(
			'class'  => 'EmailPage',
			'action' => $special->getLocalURL( 'action=submit' ),
			'method' => 'POST'
		), null ) );
		$wgOut->addHTML( "<table style=\"padding:0;margin:0;border:none;\">" );

		// From (dropdown list of self and wiki addresses)
		$from = "<option>$wgEmergencyContact</option>";
		$ue = $wgUser->getEmail();
		$from = Sanitizer::validateEmail( $ue ) ? "<option>$ue</option>$from" : $from;
		$wgOut->addHTML( "<tr id=\"ea-from\"><th align=\"right\">" . wfMessage( 'ea-from' )->text() . ":</th>" );
		$wgOut->addHTML( "<td><select name=\"ea-from\">$from</select></td></tr>\n" );

		// To
		$wgOut->addHTML( "<tr id=\"ea-to\"><th align=\"right\" valign=\"top\">" . wfMessage( 'ea-to' )->text() . ":</th>" );
		$wgOut->addHTML( "<td><textarea name=\"ea-to\" rows=\"2\" style=\"width:100%\">{$this->to}</textarea>" );
		$wgOut->addHTML( "<br /><small><i>" . wfMessage( 'ea-to-info' )->text() . "</i></small>" );

		// To group
		$groups = "<option />";
		foreach( array_keys( $wgGroupPermissions ) as $group ) if( $group != '*' && $group != 'user' ) {
			$selected = $group == $this->group ? ' selected' : '';
			$groups .= "<option$selected>$group</option>";
		}
		if( $wgEmailPageAllowAllUsers ) {
			$selected = 'user' == $this->group ? ' selected' : '';
			$groups .= "<option$selected value=\"user\">" . wfMessage( 'ea-allusers' )->text() . "</option>";
		}
		$wgOut->addHTML( "<div id=\"ea-group\"><select name=\"ea-group\">$groups</select>" );
		$wgOut->addHTML( " <i><small>" . wfMessage( 'ea-group-info' )->text() . "</small></i></div>" );

		$wgOut->addHTML( "</td></tr>" );

		// Cc
		$wgOut->addHTML( "<tr id=\"ea-cc\"><th align=\"right\">" . wfMessage( 'ea-cc' )->text() . ":</th>" );
		$wgOut->addHTML( "<td>" . 
			Xml::element( 'input', array(
				'type'  => 'text',
				'name'  => 'ea-cc',
				'value' => $this->cc ? $this->cc : $ue,
				'style' => "width:100%"
			) )
		. "</td></tr>" );

		// Subject
		$wgOut->addHTML( "<tr id=\"ea-subject\"><th align=\"right\">" . wfMessage( 'ea-subject' )->text() . ":</th>" );
		$wgOut->addHTML( "<td>" . 
			Xml::element( 'input', array(
				'type'  => 'text',
				'name'  => 'ea-subject',
				'value' => $this->subject,
				'style' => "width:100%"
			) )
		. "</td></tr>" );

		// Message
		$wgOut->addHTML( "<tr id=\"ea-message\"><th align=\"right\" valign=\"top\">" . wfMessage( 'ea-message' )->text() . ":</th>" );
		$wgOut->addHTML( "<td><textarea name=\"ea-message\" rows=\"3\" style=\"width:100%\">{$this->message}</textarea>" );
		$wgOut->addHTML( "<br /><i><small>" . wfMessage( 'ea-message-info' )->text() . "</small></i></td></tr>" );

		// Data
		if( defined( 'NS_FORM' ) ) {
			$options = "";
			$tbl = $db->tableName( 'page' );
			$res = $db->select( $tbl, 'page_id', "page_namespace = " . NS_FORM );
			while( $row = $db->fetchRow( $res ) ) {
				$t = Title::newFromID( $row[0] )->getText();
				$selected = $t == $this->record ? ' selected' : '';
				$options .= "<option$selected>$t</option>";
			}
			$db->freeResult( $res );
			if( $options ) {
				$wgOut->addHTML( "<tr id=\"ea-data\"><th align=\"right\">" . wfMessage( 'ea-data' )->text() . ":</th><td>" );
				$wgOut->addHTML( "<select name=\"ea-record\"><option />$options</select>" );
				$wgOut->addHTML( " <small><i>" . wfMessage( 'ea-selectrecord' )->text() . "</i></small></td></tr>" );
			}
		}

		// Include comments checkbox
		if( defined( 'AJAXCOMMENTS_VERSION' ) && AjaxComments::checkTitle( $this->title ) ) {
			$wgOut->addHTML( "<tr id=\"ea-addcomments\"><th>&nbsp;</th><td>" );
			$wgOut->addHTML( "<input type=\"checkbox\" name=\"ea-addcomments\" />&nbsp;" . wfMessage( 'ea-addcomments' )->text() . "</td></tr>" );
		}

		// Submit buttons & hidden values
		$wgOut->addHTML( "<tr><td colspan=\"2\" align=\"right\">" );
		$wgOut->addHTML( Xml::element( 'input', array( 'type' => 'hidden', 'name' => 'ea-title', 'value' => $this->title ) ) );
		$wgOut->addHTML( Xml::element( 'input', array( 'id' => 'ea-show', 'type' => 'submit', 'name' => 'ea-show', 'value' => wfMessage( 'ea-show' )->text() ) ) );
		$wgOut->addHTML( "&nbsp;&nbsp;" );
		$wgOut->addHTML( Xml::element( 'input', array( 'type' => 'submit', 'name' => 'ea-send', 'value' => wfMessage( 'ea-send' )->text() ) ) . '&#160;' );
		$wgOut->addHTML( "</td></tr>" );

		$wgOut->addHTML( "</table></form>" );

		// If the show button was clicked, render the list
		if( isset( $_REQUEST['ea-show'] ) ) return $this->send( false );
	}

	/**
	 * Send the message to the recipients (or just list them if arg = false)
	 */
	function send( $send = true ) {
		global $wgOut, $wgUser, $wgParser, $wgServer, $wgScript, $wgArticlePath, $wgScriptPath, $wgEmergencyContact,
			$wgEmailPageCss, $wgEmailPageGroup, $wgEmailPageAllowRemoteAddr, $wgEmailPageAllowAllUsers, $wgEmailPageSepPattern,
			$wgEmailPageNoLinks;

		// Set error and bail if user not in postmaster group, and request not from trusted address
		if( $wgEmailPageGroup && !in_array( $wgEmailPageGroup, $wgUser->getGroups() )
		&& !in_array( $_SERVER['REMOTE_ADDR'], $wgEmailPageAllowRemoteAddr ) ) {
			$denied = wfMessage( 'ea-denied' )->text();
			$wgOut->addWikiText( wfMessage( 'ea-error', $this->title, $denied )->text() );
			return false;
		}

		// Get email addresses from users in selected group
		$db = $this->db;
		if( $this->group && ( $wgEmailPageAllowAllUsers || $this->group != 'user' ) ) {
			$group = $db->addQuotes( $this->group );
			$res = $this->group == 'user'
				? $db->select( 'user', 'user_email', 'user_email != \'\'', __METHOD__ )
				: $db->select( array( 'user', 'user_groups' ), 'user_email', "ug_user = user_id AND ug_group = $group", __METHOD__ );
			foreach( $res as $row ) $this->addRecipient( $row->user_email );
		}

		// Recipients from the "to" and "cc" fields
		foreach( preg_split( $wgEmailPageSepPattern, $this->to ) as $item ) $this->addRecipient( $item );
		foreach( preg_split( $wgEmailPageSepPattern, $this->cc ) as $item ) $this->addRecipient( $item );

		// Compose the wikitext content of the page to send
		$title = Title::newFromText( $this->title );
		$opt   = new ParserOptions;
		$page  = new Article( $title );
		$message = $page->getPage()->getContent()->getNativeData();
		if( $this->message ) $message = "{$this->message}\n\n$message";

		// Convert the message text to html unless textonly
		if( $this->textonly == '' ) {

			// Parse the wikitext using absolute URL's for local page links
			$tmp           = array( $wgArticlePath, $wgScriptPath, $wgScript );
			$wgArticlePath = $wgServer . $wgArticlePath;
			$wgScriptPath  = $wgServer . $wgScriptPath;
			$wgScript      = $wgServer . $wgScript;
			$message       = $wgParser->parse( $message, $title, $opt, true, true )->getText();
			list( $wgArticlePath, $wgScriptPath, $wgScript ) = $tmp;

			// If add comments is set append them to the message now
			if( $this->addcomments ) {
				global $wgAjaxComments;
				$article = new Article( Title::newFromText( $this->title ) );
				$message .= $wgAjaxComments->onUnknownAction( 'ajaxcommentsinternal', $article );
			}

			// If no links allowed in message, change them all to spans
			if( $wgEmailPageNoLinks ) $message = preg_replace( "|(</?)a([^>]*)|i", "$1u", $message );

			// Get CSS content if any
			if( $this->css ) {
				$page = new Article( Title::newFromText( $this->css ) );
				$css  = "<style type='text/css'>" . $page->getPage()->getContent()->getNativeData() . "</style>";
			} else $css = '';

			// Create a html wrapper for the message
			$doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
			$head    = "<head>$css</head>";
			$message = "$doctype\n<html>$head<body style=\"margin:10px\"><div id=\"bodyContent\">$message</div></body></html>";
		}

		// Send message or list recipients
		$count = count( $this->recipients );
		if( $count > 0 ) {

			// Set up new mailer instance if sending
			if( $send ) {
				$mail           = new PHPMailer();
				$mail->From     = $this->from;
				$mail->FromName = User::whoIsReal( $wgUser->getId() );
				$mail->Subject  = $this->subject;
				$mail->Body     = $message;
				$mail->IsHTML( !$this->textonly );
			}
			else $msg = "===" . wfMessage( 'ea-listrecipients', $count )->text() . "===";

			// Loop through recipients sending or adding to list
			foreach( $this->recipients as $recipient ) {
				$error = '';
				if( $send ) {
					if( $this->record ) $mail->Body = $this->replaceFields( $message, $recipient );
					$mail->AddAddress( $recipient );
					if( $state = $mail->Send() ) $msg = wfMessage( 'ea-sent', $this->title, $count, $wgUser->getName() )->text();
					else $error .= "Couldn't send to $recipient: {$mail->ErrorInfo}<br />\n";
					$mail->ClearAddresses();
				} else $msg .= "\n*[mailto:$recipient $recipient]";
				if( $error ) $msg = wfMessage( 'ea-error', $this->title, $error )->text();
			}
		}
		else $msg = wfMessage( 'ea-error', $this->title, wfMessage( 'ea-norecipients' ) )->text();

		$wgOut->addWikiText( $msg );
		return $send ? $state : $count;
	}

	/**
	 * Add a recipient the list if not already present
	 */
	function addRecipient( $recipient ) {
		if( $valid = Sanitizer::validateEmail( $recipient ) && !in_array( $recipient, $this->recipients ) ) {
			$this->recipients[] = $recipient;
		}
		return $valid;
	}

	/**
	 * Replace fields in message (enclosed in single braces)
	 * - fields can have a default value, eg {name|default}
	 */
	function replaceFields( $text, $email ) {

		// Scan all records of this type for the first containing matching email address
		$dbr  = $this->db;
		$tbl  = $dbr->tableName( 'templatelinks' );
		$type = $dbr->addQuotes( $this->record );
		$res  = $dbr->select( $tbl, 'tl_from', "tl_namespace = 10 AND tl_title = $type", __METHOD__ );
		while( $row = $dbr->fetchRow( $res ) ) {
			$a = new Article( Title::newFromID( $row[0] ) );
			$c = $a->getPage()->getContent()->getNativeData();

			// Check if this records email address matches
			if( preg_match( "|\s*\|\s*\w+\s*=\s*$email\s*(?=[\|\}])|s", $c ) ) {

				// Extract all the fields from the content (should use examineBraces here)
				$this->args = array();
				preg_match_all( "|\|\s*(.+?)\s*=\s*(.*?)\s*(?=[\|\}])|s", $c, $m );
				foreach( $m[1] as $i => $k ) $this->args[strtolower( $k )] = $m[2][$i];

				// Replace any fields in the message text with our extracted args (should use wiki parser for this)
				$text = preg_replace_callback( "|\{(\w+)(\|(.+?))?\}|s", array( $this, 'replaceField' ), $text );

				break;
			}
		}
		$dbr->freeResult( $res );
		return $text;
	}

	/**
	 * Replace a single field
	 */
	function replaceField( $match ) {
		$key = strtolower( $match[1] );
		$default = isset( $match[3] ) ? $match[3] : false;
		if( array_key_exists( $key, $this->args ) ) $replace = $this->args[$key];
		else $replace = $default ? $default : $match[0];
		return $replace;
	}
}

