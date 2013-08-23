<?php

class LocalHooks {
	/* http://developers.pathvisio.org/ticket/1559 */
	static function stopDisplay( $output, $sk ) {
		if( strtolower( 'MediaWiki:Questycaptcha-qna' ) === strtolower( $output->getPageTitle() ) ||
			strtolower( 'MediaWiki:Questycaptcha-q&a' ) === strtolower( $output->getPageTitle() ) ) {
			global $wgUser, $wgTitle;
			if( !$wgTitle->userCan( "edit" ) ) {
				$output->clearHTML();
				$wgUser->mBlock = new Block( '127.0.0.1', 'WikiSysop', 'WikiSysop', 'none', 'indefinite' );
				$wgUser->mBlockedby = 0;
				$output->blockedPage();
				return false;
			}
		}
		return true;
	}

	/* http://www.pathvisio.org/ticket/1539 */
	static public function externalLink ( &$url, &$text, &$link, &$attribs = null ) {
		global $wgExternalLinkTarget;
		wfProfileIn( __METHOD__ );
		wfDebug(__METHOD__.": Looking at the link: $url\n");

		$linkTarget = "_blank";
		if( isset( $wgExternalLinkTarget ) && $wgExternalLinkTarget != "") {
			$linkTarget = $wgExternalLinkTarget;
		}

		/**AP20070417 -- moved from Linker.php by mah 20130327
		 * Added support for opening external links as new page
		 * Usage: [http://www.genmapp.org|_new Link]
		 */
		if ( substr( $url, -5 ) == "|_new" ) {
			$url = substr( $url, 0, strlen( $url ) - 5 );
			$linkTarget = "new";
		} elseif ( substr( $url, -7 ) == "%7c_new" ) {
			$url = substr( $url, 0, strlen( $url ) - 7 );
			$linkTarget = "new";
		}

		# Hook changed to include attribs in 1.15
		if( $attribs !== null ) {
			$attribs["target"] = $linkTarget;
			return true;		/* nothing else should be needed, so we can leave the rest */
		}

		/* ugh ... had to copy this bit from makeExternalLink */
		$l = new Linker;
		$style = $l->getExternalLinkAttributes( $url, $text, 'external ' );
		global $wgNoFollowLinks, $wgNoFollowNsExceptions;
		if( $wgNoFollowLinks && !(isset($ns) && in_array($ns, $wgNoFollowNsExceptions)) ) {
			$style .= ' rel="nofollow"';
		}

		$link = '<a href="'.$url.'" target="'.$linkTarget.'"'.$style.'>'.$text.'</a>';
		wfProfileOut( __METHOD__ );

		return false;
	}


	static public function updateTags( &$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags,
		$revision, &$status = null, $baseRevId = null ) {
		$title = $article->getTitle();
		if( $title->getNamespace() !== NS_PATHWAY ) {
			return true;
		}

		if( !$title->userCan( "autocurate" ) ) {
			wfDebug( __METHOD__ . ": User can't autocurate\n" );
			return true;
		}

		wfDebug( __METHOD__ . ": Autocurating tags for {$title->getText()}\n" );
		$db = wfGetDB( DB_MASTER );
		$tags = MetaTag::getTagsForPage( $title->getArticleID() );
		foreach( $tags as $tag ) {
			$oldRev = $tag->getPageRevision();
			if ( $oldRev ) {
				wfDebug( __METHOD__ . ": Setting {$tag->getName()} to {$revision->getId()}\n" );
				$tag->setPageRevision( $revision->getId() );
				$tag->save();
			} else {
				wfDebug( __METHOD__ . ": No revision information for {$tag->getName()}\n" );
			}
		}
		return true;
	}

	static public function blockPage( ) {
		/* 'Listusers'                 => array( 'SpecialPage', 'Listusers', 'block' ), */
		/* 'Statistics'                => array( 'SpecialPage', 'Statistics', 'block' ), */
		/* 'Randompage'                => array( 'SpecialPage', 'Randompage', 'block'), */
		/* 'Lonelypages'               => array( 'SpecialPage', 'Lonelypages', 'block' ), */
		/* 'Uncategorizedpages'        => array( 'SpecialPage', 'Uncategorizedpages', 'block' ), */
		/* 'Uncategorizedcategories'   => array( 'SpecialPage', 'Uncategorizedcategories', 'block' ), */
		/* 'Uncategorizedimages'       => array( 'SpecialPage', 'Uncategorizedimages', 'block' ), */
		/* 'Uncategorizedtemplates'    => array( 'SpecialPage', 'Uncategorizedtemplates', 'block' ), */
		/* 'Unusedcategories'          => array( 'SpecialPage', 'Unusedcategories', 'block' ), */
		/* 'Unusedimages'              => array( 'SpecialPage', 'Unusedimages', 'block' ), */
		/* 'Wantedpages'               => array( 'IncludableSpecialPage', 'Wantedpages', 'block' ), */
		/* 'Wantedcategories'          => array( 'SpecialPage', 'Wantedcategories', 'block' ), */
		/* 'Mostlinked'                => array( 'SpecialPage', 'Mostlinked', 'block' ), */
		/* 'Mostlinkedcategories'      => array( 'SpecialPage', 'Mostlinkedcategories', 'block' ), */
		/* 'Mostlinkedtemplates'       => array( 'SpecialPage', 'Mostlinkedtemplates', 'block' ), */
		/* 'Mostcategories'            => array( 'SpecialPage', 'Mostcategories', 'block' ), */
		/* 'Mostimages'                => array( 'SpecialPage', 'Mostimages', 'block' ), */
		/* 'Mostrevisions'             => array( 'SpecialPage', 'Mostrevisions', 'block' ), */
		/* 'Fewestrevisions'           => array( 'SpecialPage', 'Fewestrevisions', 'block' ), */
		/* 'Shortpages'                => array( 'SpecialPage', 'Shortpages', 'block' ), */
		/* 'Longpages'                 => array( 'SpecialPage', 'Longpages', 'block' ), */
		/* 'Newpages'                  => array( 'IncludableSpecialPage', 'Newpages', 'block' ), */
		/* 'Ancientpages'              => array( 'SpecialPage', 'Ancientpages', 'block' ), */
		/* 'Deadendpages'              => array( 'SpecialPage', 'Deadendpages', 'block' ), */
		/* 'Protectedpages'            => array( 'SpecialPage', 'Protectedpages', 'block' ), */
		/* 'Protectedtitles'           => array( 'SpecialPage', 'Protectedtitles', 'block' ), */
		/* 'Allpages'                  => array( 'IncludableSpecialPage', 'Allpages', 'block' ), */
		/* 'Prefixindex'               => array( 'IncludableSpecialPage', 'Prefixindex', 'block' ) , */
		/* 'Ipblocklist'               => array( 'SpecialPage', 'Ipblocklist', 'block' ), */
		/* 'Categories'                => array( 'SpecialPage', 'Categories', 'block' ), */
		/* 'Export'                    => array( 'SpecialPage', 'Export', 'block' ), */
		/* 'Allmessages'               => array( 'SpecialPage', 'Allmessages', 'block' ), */
		/* 'Log'                       => array( 'SpecialPage', 'Log', 'block' ), */
		/* 'MIMEsearch'                => array( 'SpecialPage', 'MIMEsearch', 'block' ), */
		/* 'Listredirects'             => array( 'SpecialPage', 'Listredirects', 'block' ), */
		/* 'Unusedtemplates'           => array( 'SpecialPage', 'Unusedtemplates', 'block' ), */
		/* 'Withoutinterwiki'          => array( 'SpecialPage', 'Withoutinterwiki', 'block' ), */
		/* 'Filepath'                  => array( 'SpecialPage', 'Filepath', 'block' ), */
	}

	public static function loginMessage( &$user, &$html ) {
		global $wgScriptPath;

		# Run any hooks; ignore results
		$addr = $user->getEmail();
		$name = $user->getName();
		$realname = $user->getRealName();
		$prefs = $wgScriptPath . '/index.php/Special:Preferences';
		$watch = $wgScriptPath . '/index.php/Special:Watchlist/edit';
		$injected_html = "<p>You are now logged in as:
<ul><li><i>Username:</i> <b>$name</b>
<li><i>Real name:</i> <b>$realname</b> (<a href=$prefs>change</a>)
<li><i>Email:</i> <b>$addr</b> (<a href=$prefs>change</a>)</ul></p>
<p>Your <i>real name</i> will show up in the author list of any
pathway you create or edit.  Your <i>email</i> will not be shown
to other users, but it will be used to contact you if a pathway
you have created or added to your <a href=$watch>watchlist</a> is
altered or commented on by other users. Your <i>email</i> is the
only means by which WikiPathways can contact you if any of your
content requires special attention. <b>Please keep your
<i>email</i> up-to-date.</b></p>";
	}

	public static function addSnoopLink( &$item, $row ) {
		//AP20081006 - replaced group info with links to User_snoop
		$snoop = Title::makeTitle( NS_SPECIAL, 'User_snoop');
		$snooplink = $this->getSkin()->makeKnownLinkObj( $snoop, 'info', wfArrayToCGI( array('username' => $row->user_name)), '','','');

		$item = wfSpecialList( $name, $snooplink);
	}


}

$wgHooks['SpecialListusersFormatRow'][] = 'LocalHooks::addSnoopLink';
$wgHooks['UserLoginComplete'][] = 'LocalHooks::loginMessage';
$wgHooks['SpecialPage_initList'][] = 'LocalHooks::blockPage';
$wgHooks['LinkerMakeExternalLink'][] = 'LocalHooks::externalLink';
$wgHooks['BeforePageDisplay'][] = 'LocalHooks::stopDisplay';
$wgHooks['ArticleSaveComplete'][] = 'LocalHooks::updateTags';