<?php
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
			$hookContainer->register( 'SkinTemplateNavigation', __CLASS__ . '::onSkinTemplateNavigation' );
		}
	}

	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		global $wgTitle, $wgEmailPageGroup;
		if ( is_object( $wgTitle ) && $skin->getUser()->isRegistered()
			&& ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $skin->getUser()->getEffectiveGroups() ) )
		) {
			$url = htmlspecialchars( SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( [ 'ea-title' => $wgTitle->getPrefixedText() ] ) );
			$sidebar['TOOLBOX'][] = [
				"text" => wfMessage( 'emailpage' )->text(),
				"href" => $url,
			];
		}
		return true;
	}

	public static function onSkinTemplateNavigation( SkinTemplate $skinTemplate, array &$links ) {
		global $wgTitle, $wgEmailPageGroup;
		if ( is_object( $wgTitle )
			&& $skinTemplate->getUser()->isRegistered()
			&& ( empty( $wgEmailPageGroup ) || in_array( $wgEmailPageGroup, $skinTemplate->getUser()->getEffectiveGroups() ) )
		) {
			$url = SpecialPage::getTitleFor( 'EmailPage' )->getLocalURL( [ 'ea-title' => $wgTitle->getPrefixedText() ] );
			$actions['views']['email'] = [ 'text' => wfMessage( 'email' )->text(), 'class' => false, 'href' => $url ];
		}
		return true;
	}
}
