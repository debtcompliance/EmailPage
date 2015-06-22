<?php
class EmailPage {

	public static function onRegistration() {
		global $wgEmailPageGroup, $wgGroupPermissions, $wgEmailPageAllowRemoteAddr, $wgEmailPageToolboxLink, $wgEmailPageActionLink;

		if( $wgEmailPageGroup ) $wgGroupPermissions['sysop'][$wgEmailPageGroup] = true;

		if( isset( $_SERVER['SERVER_ADDR'] ) ) $wgEmailPageAllowRemoteAddr[] = $_SERVER['SERVER_ADDR'];

		// If form has been posted, include the phpmailer class
		if( isset( $_REQUEST['ea-send'] ) ) {
			if( $files = glob( "$dir/*/class.phpmailer.php" ) ) require_once( $files[0] );
			else die( "PHPMailer class not found!" );
		}

		// Add toolbox and action links
		if( $wgEmailPageToolboxLink ) Hooks::register( 'SkinTemplateToolboxEnd', __CLASS__ . '::onSkinTemplateToolboxEnd' );
		if( $wgEmailPageActionLink )  {
			Hooks::register( 'SkinTemplateTabs', __CLASS__ . '::onSkinTemplateTabs' );
			Hooks::register( 'SkinTemplateNavigation', __CLASS__ . '::onSkinTemplateNavigation' );
		}
	}

	public static function onSkinTemplateToolboxEnd() {
		global $wgTitle, $wgUser, $wgEmailPageGroup;
		if ( is_object( $wgTitle ) && $wgUser->isLoggedIn() && ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $wgUser->getEffectiveGroups() ) ) ) {
			$url = htmlspecialchars( SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( array( 'ea-title' => $wgTitle->getPrefixedText() ) ) );
			echo( "<li><a href=\"$url\">" . wfMessage( 'emailpage' )->text() . "</a></li>" );
		}
		return true;
	}

	public static function onSkinTemplateTabs( $skin, &$actions ) {
		global $wgTitle, $wgUser, $wgEmailPageGroup;
		if( is_object( $wgTitle ) && $wgUser->isLoggedIn() && ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $wgUser->getEffectiveGroups() ) ) ) {
			$url = SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( array( 'ea-title' => $wgTitle->getPrefixedText() ) );
			$actions['email'] = array( 'text' => wfMessage( 'email' )->text(), 'class' => false, 'href' => $url );
		}
		return true;
	}

	public static function onSkinTemplateNavigation( $skin, &$actions ) {
		global $wgTitle, $wgUser, $wgEmailPageGroup;
		if( is_object( $wgTitle ) && $wgUser->isLoggedIn() && ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $wgUser->getEffectiveGroups() ) ) ) {
			$url = SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( array( 'ea-title' => $wgTitle->getPrefixedText() ) );
			$actions['views']['email'] = array( 'text' => wfMessage( 'email' )->text(), 'class' => false, 'href' => $url );
		}
		return true;
	}
}
