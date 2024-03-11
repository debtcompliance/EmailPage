<?php

use MediaWiki\MediaWikiServices;

class EmailPage {

	public static function onRegistration() {
		global $wgEmailPageGroup, $wgGroupPermissions, $wgEmailPageAllowRemoteAddr,
			$wgEmailPageToolboxLink, $wgEmailPageActionLink;

		if ( $wgEmailPageGroup ) {
			$wgGroupPermissions['sysop'][$wgEmailPageGroup] = true;
		}

		if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$wgEmailPageAllowRemoteAddr[] = $_SERVER['SERVER_ADDR'];
		}

		// If form has been posted, include the phpmailer class
		if ( isset( $_REQUEST['ea-send'] ) ) {
			$dir = __DIR__;
			$files = glob( "$dir/vendor/autoload.php" );
			if ( $files ) {
				require_once $files[0];
			} else {
				die( "PHPMailer class not found!" );
			}
		}

		// Add toolbox and action links
		$hookContainer = \MediaWiki\MediaWikiServices::getInstance()->getHookContainer();
		if ( $wgEmailPageToolboxLink ) {
			$hookContainer->register( 'SidebarBeforeOutput', __CLASS__ . '::onSidebarBeforeOutput' );
		}
		if ( $wgEmailPageActionLink )  {
			$hookContainer->register( 'SkinTemplateNavigation::Universal', __CLASS__ . '::onSkinTemplateNavigationUniversal' );
		}
	}

	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		global $wgEmailPageGroup;
		$user = $skin->getUser();
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $user );
		if ( $skin->getTitle() && $user->isRegistered()
			&& ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $groups ) )
		) {
			$url = htmlspecialchars( SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( [ 'ea-title' => $skin->getTitle()->getPrefixedText() ] ) );
			$sidebar['TOOLBOX'][] = [
				"text" => wfMessage( 'emailpage' )->text(),
				"href" => $url,
			];
		}
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		global $wgEmailPageGroup;
		$user = $skinTemplate->getUser();
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $user );
		if ( $skinTemplate->getTitle()
			&& $user
			&& ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $groups ) )
		) {
			$url = SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( [ 'ea-title' => $skinTemplate->getTitle()->getPrefixedText() ] );
			$links['views']['email'] = [ 'text' => wfMessage( 'email' )->text(), 'class' => false, 'href' => $url ];
		}
	}
}
