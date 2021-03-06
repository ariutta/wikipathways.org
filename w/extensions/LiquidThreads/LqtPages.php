<?php

/**
* @package MediaWiki
* @subpackage LiquidThreads
* @author David McCabe <davemccabe@gmail.com>
* @licence GPL2
*/

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( -1 );
}

class TalkpageView extends LqtView {
	/* Added to SkinTemplateTabs hook in TalkpageView::show(). */
	function customizeTabs( $skintemplate, $content_actions ) {
		// The arguments are passed in by reference.
		unset($content_actions['edit']);
		unset($content_actions['viewsource']);
		unset($content_actions['addsection']);
		unset($content_actions['history']);
		unset($content_actions['watch']);
		unset($content_actions['move']);

		/*
		TODO:
		We could make these tabs actually follow the tab metaphor if we repointed
		the 'history' and 'edit' tabs to the original subject page. That way 'discussion'
		would just be one of four ways to view the article. But then those other tabs, for
		logged-in users, don't really fit the metaphor. What to do, what to do?
		*/
		return true;
	}

	function permalinksForThreads($ts, $method = null, $operand = null) {
		$ps = array();
		foreach ($ts as $t) {
			$u = $this->permalinkUrl($t, $method, $operand);
			$l = $t->subjectWithoutIncrement();
			$ps[] = "<a href=\"$u\">$l</a>";
		}
		return $ps;
	}

	function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */

		$article = new Article( $this->title );
		$revision = Revision::newFromId($article->getLatest());
		if( $revision ) $article_text = $revision->getRawText();

		$oldid = $this->request->getVal('oldid', null);
		$editlink = $this->title->getFullURL( 'action=edit' );

		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->exists() && $article_text != "" ) {
			$historylink = $this->title->getFullURL( 'action=history' );
			$this->openDiv('lqt_header_content');
			$this->showPostBody($article, $oldid);
			$this->outputList('ul', 'lqt_header_commands', null, array(
				"[<a href=\"$editlink\">".wfMessage( 'edit' )->text()."&uarr;</a>]",
				"[<a href=\"$historylink\">".wfMessage( 'history_short' )->text()."&uarr;</a>]"
				));
				$this->closeDiv();
		} else {
			$this->output->addHTML("<p class=\"lqt_header_notice\">[<a href=\"$editlink\">".wfMessage( 'lqt_add_header' )->text()."</a>]</p>");
		}
	}

	function outputList( $kind, $class, $id, $contents ) {
		$this->output->addHTML(wfOpenElement($kind, array('class'=>$class,'id'=>$id)));
		foreach ($contents as $li) {
			$this->output->addHTML( wfOpenElement('li') );
			$this->output->addHTML( $li );
			$this->output->addHTML( wfCloseElement('li') );
		}
		$this->output->addHTML(wfCloseElement($kind));
	}

	function showTOC($threads) {
		$sk = $this->user->getSkin();
		$toclines = array();
		$i = 1;
		$toclines[] = $sk->tocIndent();
		foreach($threads as $t) {
			$toclines[] = $sk->tocLine($this->anchorName($t), $t->subjectWithoutIncrement(), $i, 1);
			$i++;
		}
		$toclines[] = $sk->tocUnindent(1);

		$this->openDiv('lqt_toc_wrapper');
		$this->output->addHTML('<h2 class="lqt_toc_title">'.wfMessage( 'lqt_contents_title' )->text().'</h2> <ul>');

		foreach($threads as $t) {
			$this->output->addHTML('<li><a href="#'.$this->anchorName($t).'">'.$t->subjectWithoutIncrement().'</a></li>');
		}

		$this->output->addHTML('</ul></div>');
	}

	function showArchiveWidget($threads) {
		$threadlinks = $this->permalinksForThreads($threads);
		$url = $this->talkpageUrl($this->title, 'talkpage_archive');

		if ( count($threadlinks) > 0 ) {
			$this->openDiv('lqt_archive_teaser');
			$this->output->addHTML('<h2 class="lqt_recently_archived">'.wfMessage( 'lqt_recently_archived' )->text().'</h2>');
			//			$this->output->addHTML("<span class=\"lqt_browse_archive\">[<a href=\"$url\">".wfMessage( 'lqt_browse_archive_with_recent' )->text()."</a>]</span></h2>");
			$this->outputList('ul', '', '', $threadlinks);
			$this->closeDiv();
		} else {
		}
	}

	function showTalkpageViewOptions($article) {
		// TODO WTF who wrote this?

		if( $this->methodApplies('talkpage_sort_order') ) {
			$remember_sort_checked = $this->request->getBool('lqt_remember_sort') ? 'checked ' : '';
			$this->user->setOption('lqt_sort_order', $this->sort_order);
			$this->user->saveSettings();
		} else {
			$remember_sort_checked = '';
		}

		if($article->exists()) {
			$nc_sort = $this->sort_order==LQT_NEWEST_CHANGES ? ' selected' : '';
			$nt_sort = $this->sort_order==LQT_NEWEST_THREADS ? ' selected' : '';
			$ot_sort = $this->sort_order==LQT_OLDEST_THREADS ? ' selected' : '';
			$newest_changes = wfMessage( 'lqt_sort_newest_changes' )->text();
			$newest_threads = wfMessage( 'lqt_sort_newest_threads' )->text();
			$oldest_threads = wfMessage( 'lqt_sort_oldest_threads' )->text();
			$lqt_remember_sort = wfMessage( 'lqt_remember_sort' )->text() ;
			$form_action_url = $this->talkpageUrl( $this->title, 'talkpage_sort_order');
			$lqt_sorting_order = wfMessage( 'lqt_sorting_order' )->text();
			$lqt_sort_newest_changes = wfMessage( 'lqt_sort_newest_changes' )->text();
			$lqt_sort_newest_threads = wfMessage( 'lqt_sort_newest_threads' )->text();
			$lqt_sort_oldest_threads = wfMessage( 'lqt_sort_oldest_threads' )->text();
			$go=wfMessage( 'go' )->text();
			if($this->user->isLoggedIn()) {
				$remember_sort =
				<<<HTML
<br />
<label for="lqt_remember_sort_checkbox">
<input id="lqt_remember_sort_checkbox" name="lqt_remember_sort" type="checkbox" value="1" $remember_sort_checked />
$lqt_remember_sort</label>
HTML;
			} else {
				$remember_sort = '';
			}
			if ( in_array('deletedhistory',  $this->user->getRights()) ) {
				$show_deleted_checked = $this->request->getBool('lqt_show_deleted_threads') ? 'checked ' : '';
				$show_deleted = "<br />\n" .
								"<label for=\"lqt_show_deleted_threads_checkbox\">\n" .
								"<input id=\"lqt_show_deleted_threads_checkbox\" name=\"lqt_show_deleted_threads\" type=\"checkbox\" value=\"1\" $show_deleted_checked />\n" .
								wfMessage( 'lqt_delete_show_checkbox' )->text() . "</label>\n";
			} else {
				$show_deleted = "";
			}
			$this->openDiv('lqt_view_options');
			$this->output->addHTML(

			<<<HTML
<form name="lqt_sort" action="$form_action_url" method="post">$lqt_sorting_order
<select name="lqt_order" class="lqt_sort_select">
<option value="nc"$nc_sort>$lqt_sort_newest_changes</option>
<option value="nt"$nt_sort>$lqt_sort_newest_threads</option>
<option value="ot"$ot_sort>$lqt_sort_oldest_threads</option>
</select>
$remember_sort
$show_deleted
<input name="submitsort" type="submit" value="$go" class="lqt_go_sort"/>
</form>
HTML
			);
			$this->closeDiv();
		}

	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		$this->output->setPageTitle( $this->title->getTalkPage()->getPrefixedText() );
		self::addJSandCSS();
		$article = new Article( $this->title ); // Added in r29715 sorting. Why?

		// Removed in r29715 sorting. Again, why?
		$this->showHeader();

		global $wgRequest; // TODO
		if( $this->methodApplies('talkpage_new_thread') ) {
			$this->showNewThreadForm();
		} else {
			$this->showTalkpageViewOptions($article);
			$url = $this->talkpageUrl( $this->title, 'talkpage_new_thread' );
			$this->output->addHTML("<strong><a class=\"lqt_start_discussion\" href=\"$url\">".wfMessage( 'lqt_new_thread' )->text()."</a></strong>");
		}

		$threads = $this->queries->query('fresh');

		$this->openDiv('lqt_toc_archive_wrapper');

		$this->openDiv('lqt_archive_teaser_empty');
		$this->output->addHTML("<div class=\"lqt_browse_archive\"><a href=\"{$this->talkpageUrl($this->title, 'talkpage_archive')}\">".wfMessage( 'lqt_browse_archive_without_recent' )->text()."</a></div>");
		$this->closeDiv();
		$recently_archived_threads = $this->queries->query('recently-archived');
		if(count($threads) > 3 || count($recently_archived_threads) > 0) {
			$this->showTOC($threads);
		}
		$this->showArchiveWidget($recently_archived_threads);
		$this->closeDiv();
		// Clear any floats
		$this->output->addHTML('<br clear="all" />');

		foreach($threads as $t) {
			$this->showThread($t);
		}
		return false;
	}
}

class TalkpageArchiveView extends TalkpageView {
	function __construct(&$output, &$article, &$title, &$user, &$request) {
		parent::__construct($output, $article, $title, $user, $request);
		$this->loadQueryFromRequest();
	}

	function showThread($t) {
		$this->output->addHTML(<<<HTML
<tr>
<td><a href="{$this->permalinkUrl($t)}">{$t->subjectWithoutIncrement()}</a></td>
<td>
HTML
		);		if( $t->hasSummary() ) {
			$this->showPostBody($t->summary());
		} else if ( $t->type() == Threads::TYPE_MOVED ) {
			$rthread = $t->redirectThread();
			if( $rthread  && $rthread->summary() ) {
				$this->showPostBody($rthread->summary());
			}
		}
		$this->output->addHTML(<<<HTML
</td>
</tr>
HTML
		);
	}

	function loadQueryFromRequest() {
		// Begin with with the requirements for being *in* the archive.
		$startdate = Date::now()->nDaysAgo($this->archive_start_days)->midnight();
		$where = array(Threads::articleClause($this->article),
		'thread.thread_parent is null',
		'(thread.thread_summary_page is not null' .
		' OR thread.thread_type = '.Threads::TYPE_MOVED.')',
		'thread.thread_modified < ' . $startdate->text());
		$options = array('ORDER BY thread.thread_modified DESC');

		$annotations = array( wfMessage( 'lqt-searching' )->text());

		$r = $this->request;

		/* START AND END DATES */
		// $this->start and $this->end are clipped into the range of available
		// months, for use in the actual query and the selects. $this->raw* are
		// as actually provided, for use by the 'older' and 'newer' buttons.
		$ignore_dates = ! $r->getVal('lqt_archive_filter_by_date', true);
		if ( !$ignore_dates ) {
			$months = Threads::monthsWhereArticleHasThreads($this->article);
		}
		$s = $r->getVal('lqt_archive_start');
		if ($s && ctype_digit($s) && strlen($s) == 6 && !$ignore_dates) {
			$this->selstart = new Date( "{$s}01000000" );
			$this->starti = array_search($s, $months);
			$where[] = 'thread.thread_modified >= ' . $this->selstart->text();
		}
		$e = $r->getVal('lqt_archive_end');
		if ($e && ctype_digit($e) && strlen($e) == 6 && !$ignore_dates) {
			$this->selend = new Date("{$e}01000000");
			$this->endi = array_search($e, $months);
			$where[] = 'thread.thread_modified < ' . $this->selend->nextMonth()->text();
		}
		if ( isset($this->selstart) && isset($this->selend) ) {

			$this->datespan = $this->starti - $this->endi;

			$annotations[] = "from {$this->selstart->text()} to {$this->selend->text()}";
		} else if (isset($this->selstart)) {
			$annotations[] = "after {$this->selstart->text()}";
		} else if (isset($this->selend)) {
			$annotations[] = "before {$this->selend->text()}";
		}

		$this->where = $where;
		$this->options = $options;
		$this->annotations = implode("<br />\n", $annotations);
	}

	function threads() {
		return Threads::where($this->where, $this->options);
	}

	function formattedMonth($yyyymm) {
		global $wgLang; // TODO global.
		return $wgLang->getMonthName( substr($yyyymm, 4, 2) ).' '.substr($yyyymm, 0, 4);
	}

	function monthSelect($months, $name) {
		$selection =  $this->request->getVal($name);

		// Silently adjust to stay in range.
		$selection = max( min( $selection, $months[0] ), $months[count($months)-1] );

		$options = array();
		foreach($months as $m) {
			$options[$this->formattedMonth($m)] = $m;
		}
		$result = "<select name=\"$name\" id=\"$name\">";
		foreach( $options as $label => $value ) {
			$selected = $selection == $value ? 'selected="true"' : '';
			$result .= "<option value=\"$value\" $selected>$label";
		}
		$result .= "</select>";
		return $result;
	}

	function clip( $vals, $min, $max ) {
		$res = array();
		foreach($vals as $val) $res[] =  max( min( $val, $max ), $min );
		return $res;
	}

	/* @return True if there are no threads to show, false otherwise.
	TODO is is somewhat bizarre. */
	function showSearchForm() {
		$months = Threads::monthsWhereArticleHasThreads($this->article);
		if (count($months) == 0) {
			return true;
		}

		$use_dates = $this->request->getVal('lqt_archive_filter_by_date', null);
		if ( $use_dates === null ) {
			$use_dates = $this->request->getBool('lqt_archive_start', false) ||
			$this->request->getBool('lqt_archive_end', false);
		}
		$any_date_check    = !$use_dates ? 'checked="1"' : '';
		$these_dates_check =  $use_dates ? 'checked="1"' : '';
		$any_date = wfMessage( 'lqt-any-date' )->text();
		$only_date= wfMessage( 'lqt-only-date' )->text();
		$date_from= wfMessage( 'lqt-date-from' )->text();
		$date_to  = wfMessage( 'lqt-date-to' )->text();
		$date_info = wfMessage( 'lqt-date-info' )->text();
		if( isset($this->datespan) ) {
			$oatte = $this->starti + 1;
			$oatts = $this->starti + 1 + $this->datespan;

			$natts = $this->endi - 1;
			$natte = $this->endi - 1 - $this->datespan;

			list($oe, $os, $ns, $ne) =
			$this->clip( array($oatte, $oatts, $natts, $natte),
			0, count($months)-1 );

			$older = '<a class="lqt_newer_older" href="' . $this->queryReplace(array(
			'lqt_archive_filter_by_date'=>'1',
			'lqt_archive_start' => $months[$os],
			'lqt_archive_end' => $months[$oe]))
			. '">«'.wfMessage( 'lqt-older' )->text().'</a>';
			$newer = '<a class="lqt_newer_older" href="' . $this->queryReplace(array(
			'lqt_archive_filter_by_date'=>'1',
			'lqt_archive_start' => $months[$ns],
			'lqt_archive_end' => $months[$ne]))
			. '">'.wfMessage( 'lqt-newer' )->text().'»</a>';
		}
		else {
			$older = '<span class="lqt_newer_older_disabled" title="'.wfMessage( 'lqt-date-info' )->text().'">«'.wfMessage( 'lqt-older' )->text().'</span>';
			$newer = '<span class="lqt_newer_older_disabled" title="'.wfMessage( 'lqt-date-info' )->text().'">'.wfMessage( 'lqt-newer' )->text().'»</span>';
		}

		$this->output->addHTML(<<<HTML
<form id="lqt_archive_search_form" action="{$this->title->getLocalURL()}">
<input type="hidden" name="lqt_method" value="talkpage_archive">
<input type="hidden" name="title" value="{$this->title->getPrefixedURL()}"

<input type="radio" id="lqt_archive_filter_by_date_no"
name="lqt_archive_filter_by_date" value="0" {$any_date_check}>
<label for="lqt_archive_filter_by_date_no">{$any_date}</label>  <br />
<input type="radio" id="lqt_archive_filter_by_date_yes"
name="lqt_archive_filter_by_date" value="1" {$these_dates_check}>
<label for="lqt_archive_filter_by_date_yes">{$only_date}</label> <br />

<table>
<tr><td><label for="lqt_archive_start">{$date_from}</label>
<td>{$this->monthSelect($months, 'lqt_archive_start')} <br />
<tr><td><label for="lqt_archive_end">{$date_to}</label>
<td>{$this->monthSelect($months, 'lqt_archive_end')}
</table>
<input type="submit">
$older $newer
</form>
HTML
		);
		return false;
	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		$this->output->setPageTitle( $this->title->getTalkPage()->getPrefixedText() );
		self::addJSandCSS();

		$empty = $this->showSearchForm();
		if ($empty) {
			$this->output->addHTML('<p><br /><b>'. wfMessage( 'lqt-nothread' )->text() . '</b></p>' );
			return false;
		}
		$lqt_title = wfMessage( 'lqt-title' )->text();
		$lqt_summary = wfMessage( 'lqt-summary' )->text();
		$this->output->addHTML(<<<HTML
<p class="lqt_search_annotations">{$this->annotations}</p>
<table class="lqt_archive_listing">
<col class="lqt_titles" />
<col class="lqt_summaries" />
<tr><th>{$lqt_title}<th>{$lqt_summary}</tr>
HTML
		);
		foreach ($this->threads() as $t) {
			$this->showThread($t);
		}
		$this->output->addHTML('</table>');

		return false;
	}
}

class ThreadPermalinkView extends LqtView {
	protected $thread;

	function customizeTabs( $skintemplate, $content_actions ) {
		// Insert fake 'article' and 'discussion' tabs before the thread tab.
		// If you call the key 'talk', the url gets re-set later. TODO:
		// the access key for the talk tab doesn't work.
		if( $this->thread ) {
			$article_t = $this->thread->article()->getTitle();
			$talk_t = $this->thread->article()->getTitle()->getTalkPage();
			efInsertIntoAssoc('article', array(
					'text'=>wfMsg($article_t->getNamespaceKey()),
					'href'=>$article_t->getFullURL(),
					'class' => $article_t->exists() ? '' : 'new'),
				'nstab-thread', $content_actions);
			efInsertIntoAssoc('not_talk', array(
					// talkpage certainly exists since this thread is from it.
					'text'=>wfMessage( 'talk' )->text(),
					'href'=>$talk_t->getFullURL()),
				'nstab-thread', $content_actions);
		}
		unset($content_actions['edit']);
		unset($content_actions['viewsource']);
		unset($content_actions['talk']);
		if( array_key_exists( 'move', $content_actions ) && $this->thread ) {
			$content_actions['move']['href'] =
			SpecialPage::getTitleFor('MoveThread')->getFullURL() . '/' .
			$this->thread->title()->getPrefixedURL();
		}
		if( array_key_exists( 'delete', $content_actions ) && $this->thread ) {
			$content_actions['delete']['href'] =
			SpecialPage::getTitleFor('DeleteThread')->getFullURL() . '/' .
			$this->thread->title()->getPrefixedURL();
		}

		if( array_key_exists('history', $content_actions) ) {
			$content_actions['history']['href'] = $this->permalinkUrl( $this->thread, 'thread_history' );
			if( $this->methodApplies('thread_history') ) {
				$content_actions['history']['class'] = 'selected';
			}
		}

		return true;
	}

	function showThreadHeading( $thread ) {
		if ( $this->headerLevel == 2 ) {
			$this->output->setPageTitle( $thread->wikilink() );
		} else {
			parent::showThreadHeading($thread);
		}
	}

	function noSuchRevision() {
		$this->output->addHTML(wfMessage( 'lqt_nosuchrevision' )->text());
	}

	function showMissingThreadPage() {
		$this->output->addHTML(wfMessage( 'lqt_nosuchthread' )->text());
	}

	function getSubtitle() {
		// TODO the archive month part is obsolete.
		if (Date::now()->nDaysAgo(30)->midnight()->isBefore( new Date($this->thread->modified()) ))
		$query = '';
		else
		$query = 'lqt_archive_month=' . substr($this->thread->modified(),0,6);
		$talkpage = $this->thread->article()->getTitle()->getTalkPage();
		$talkpage_link = $this->user->getSkin()->makeKnownLinkObj($talkpage, '', $query);
		if ( $this->thread->hasSuperthread() ) {
			return wfMsg('lqt_fragment',"<a href=\"{$this->permalinkUrl($this->thread->topmostThread())}\">".wfMessage( 'lqt_discussion_link' )->text()."</a>",$talkpage_link);
		} else {
			return wfMsg('lqt_from_talk', $talkpage_link);
		}
	}

	function __construct(&$output, &$article, &$title, &$user, &$request) {

		parent::__construct($output, $article, $title, $user, $request);

		$t = Threads::withRoot( $this->article );
		$r = $this->request->getVal('lqt_oldid', null);
		if( $r && $t ) {
			$t = $t->atRevision($r);
			if( !$t ) { $this->noSuchRevision(); return; }
		}
		$this->thread = $t;
		if( ! $t ) {
			return; // error reporting is handled in show(). this kinda sucks.
		}

		// $this->article gets saved to thread_article, so we want it to point to the
		// subject page associated with the talkpage, always, not the permalink url.
		$this->article = $t->article(); # for creating reply threads.

	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		if( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}

		self::addJSandCSS();
		$this->output->setSubtitle($this->getSubtitle());

		if( $this->methodApplies('summarize') )
		$this->showSummarizeForm($this->thread);

		$this->showThread($this->thread);
		return false;
	}
}

/*
* Cheap views that just pass through to MW functions.
*/

class TalkpageHeaderView extends LqtView {
	function customizeTabs( $skintemplate, $content_actions ) {
		unset($content_actions['edit']);
		unset($content_actions['addsection']);
		unset($content_actions['history']);
		unset($content_actions['watch']);
		unset($content_actions['move']);

		$content_actions['talk']['class'] = false;
		$content_actions['header'] = array( 'class'=>'selected',
		'text'=>'header',
		'href'=>'');

		return true;
	}

	function show() {
		global $wgHooks, $wgOut, $wgTitle, $wgRequest;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		if( $wgRequest->getVal('action') === 'edit' ) {
			$warn_bold = '<strong>' . wfMessage( 'lqt_header_warning_bold' )->text() . '</strong>';
			$warn_link = '<a href="'.$this->talkpageUrl($wgTitle, 'talkpage_new_thread').'">'.
			wfMessage( 'lqt_header_warning_new_discussion' )->text().'</a>';
			$wgOut->addHTML('<p class="lqt_header_warning">' .
			wfMsg('lqt_header_warning_before_big', $warn_bold, $warn_link) .
			'<big>' . wfMsg('lqt_header_warning_big', $warn_bold, $warn_link) . '</big>' .
			wfMsg('lqt_header_warning_after_big', $warn_bold, $warn_link) .
			'</p>');
		}

		return true;
	}
}

/*
This is invoked in two cases:
(1) the single-article history listing
(2) an old revision of a single article.
*/
class IndividualThreadHistoryView extends ThreadPermalinkView {
	protected $oldid;

	function customizeTabs( $skintemplate, $content_actions ) {
		$content_actions['history']['class'] = 'selected';
		parent::customizeTabs($skintemplate, $content_actions);
		return true;
	}

	/* This customizes the subtitle of a history *listing* from the hook,
	and of an old revision from getSubtitle() below. */
	function customizeSubtitle() {
		$msg = wfMessage( 'lqt_hist_view_whole_thread' )->text();
		$threadhist = "<a href=\"{$this->permalinkUrl($this->thread->topmostThread(), 'thread_history')}\">$msg</a>";
		$this->output->setSubtitle(  parent::getSubtitle() . '<br />' . $this->output->getSubtitle() . "<br />$threadhist" );
		return true;
	}

	/* */
	function getSubtitle() {
		$this->article->setOldSubtitle($this->oldid);
		$this->customizeSubtitle();
		return $this->output->getSubtitle();
	}

	function show() {
		global $wgHooks;
		/*
		$this->oldid = $this->request->getVal('oldid', null);
		if( $this->oldid !== null ) {

			parent::show();
			return false;
		}
		*/
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		$wgHooks['PageHistoryBeforeList'][] = array($this, 'customizeSubtitle');

		return true;
	}
}

class ThreadDiffView {
	function customizeTabs( $skintemplate, $content_actions ) {
		unset($content_actions['edit']);
		unset($content_actions['viewsource']);
		unset($content_actions['talk']);

		$content_actions['talk']['class'] = false;
		$content_actions['history']['class'] = 'selected';

		return true;
	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');
		return true;
	}
}

class ThreadWatchView extends ThreadPermalinkView {
	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');
		return true;
	}
}

class ThreadProtectionFormView {
	function customizeTabs( $skintemplate, $content_actions ) {
		unset($content_actions['edit']);
		unset($content_actions['addsection']);
		unset($content_actions['viewsource']);
		unset($content_actions['talk']);

		$content_actions['talk']['class'] = false;
		if ( array_key_exists('protect', $content_actions) )
		$content_actions['protect']['class'] = 'selected';
		else if ( array_key_exists('unprotect', $content_actions) )
		$content_actions['unprotect']['class'] = 'selected';

		return true;
	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');
		return true;
	}
}

class ThreadHistoryListingView extends ThreadPermalinkView {

	private function rowForThread($t) {
		global $wgLang, $wgOut; // TODO global.

		/* TODO: best not to refer to LqtView class directly. */
		/* We don't use oldid because that has side-effects. */
		$result = array();
		$change_names = array(	Threads::CHANGE_EDITED_ROOT => wfMessage( 'lqt_hist_comment_edited' )->text(),
		Threads::CHANGE_EDITED_SUMMARY => wfMessage( 'lqt_hist_summary_changed' )->text(),
		Threads::CHANGE_REPLY_CREATED => wfMessage( 'lqt_hist_reply_created' )->text(),
		Threads::CHANGE_NEW_THREAD => wfMessage( 'lqt_hist_thread_created' )->text(),
		Threads::CHANGE_DELETED => wfMessage( 'lqt_hist_deleted' )->text(),
		Threads::CHANGE_UNDELETED => wfMessage( 'lqt_hist_undeleted' )->text(),
		Threads::CHANGE_MOVED_TALKPAGE => wfMessage( 'lqt_hist_moved_talkpage' )->text());
		$change_label = array_key_exists($t->changeType(), $change_names) ? $change_names[$t->changeType()] : "";

		$url = LqtView::permalinkUrlWithQuery( $this->thread, 'lqt_oldid=' . $t->revisionNumber() );

		$user_id = $t->changeUser()->getID(); # ever heard of a User object?
		$user_text = $t->changeUser()->getName();
		$sig = $this->user->getSkin()->userLink( $user_id, $user_text ) .
		$this->user->getSkin()->userToolLinks( $user_id, $user_text );

		$change_comment=$t->changeComment();
		if(!empty($change_comment))
		$change_comment="<em>($change_comment)</em>";

		$result[] = "<tr>";
		$result[] = "<td><a href=\"$url\">" . $wgLang->timeanddate($t->modified()) . "</a></td>";
		$result[] = "<td>" . $sig . "</td>";
		$result[] = "<td>$change_label</td>";
		$result[] = "<td>$change_comment</td>";
		$result[] = "</tr>";
		return implode('', $result);
	}

	function showHistoryListing($t) {
		$revisions = new ThreadHistoryIterator($t, $this->perPage, $this->perPage * ($this->page - 1));

		$this->output->addHTML('<table>');
		foreach($revisions as $ht) {
			$this->output->addHTML($this->rowForThread($ht));
		}
		$this->output->addHTML('</table>');

		if ( count($revisions) == 0 && $this->page == 1 ) {
			$this->output->addHTML('<p>'.wfMessage( 'lqt_hist_no_revisions_error' )->text());
		}
		else if ( count($revisions) == 0 ) {
			// we could redirect to the previous page... yow.
			$this->output->addHTML('<p>'.wfMessage( 'lqt_hist_past_last_page_error' )->text());
		}

		if( $this->page > 1 ) {
			$this->output->addHTML( '<a class="lqt_newer_older" href="' . $this->queryReplace(array('lqt_hist_page'=>$this->page - 1)) .'">'.wfMessage( 'lqt_newer' )->text().'</a>' );
		} else {
			$this->output->addHTML( '<span class="lqt_newer_older_disabled" title="'.wfMessage( 'lqt_hist_tooltip_newer_disabled' )->text().'">'.wfMessage( 'lqt_newer' )->text().'</span>' );
		}

		$is_last_page = false;
		foreach($revisions as $r)
		if( $r->changeType() == Threads::CHANGE_NEW_THREAD )
		$is_last_page = true;
		if( $is_last_page ) {
			$this->output->addHTML( '<span class="lqt_newer_older_disabled" title="'.wfMessage( 'lqt_hist_tooltip_older_disabled' )->text().'">'.wfMessage( 'lqt_older' )->text().'</span>' );
		} else {
			$this->output->addHTML( '<a class="lqt_newer_older" href="' . $this->queryReplace(array('lqt_hist_page'=>$this->page + 1)) . '">'.wfMessage( 'lqt_older' )->text().'</a>' );
		}
	}

	function __construct(&$output, &$article, &$title, &$user, &$request) {
		parent::__construct($output, $article, $title, $user, $request);
		$this->loadParametersFromRequest();
	}

	function loadParametersFromRequest() {
		$this->perPage = $this->request->getInt('lqt_hist_per_page', 10);
		$this->page = $this->request->getInt('lqt_hist_page', 1);
	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array($this, 'customizeTabs');

		if( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}
		self::addJSandCSS();

		$this->output->setSubtitle($this->getSubtitle() . '<br />' . wfMessage( 'lqt_hist_listing_subtitle' )->text());

		$this->showThreadHeading($this->thread);
		$this->showHistoryListing($this->thread);

		$this->showThread($this->thread);

		return false;
	}
}

class ThreadHistoricalRevisionView extends ThreadPermalinkView {

	/* TOOD: customize tabs so that History is highlighted. */

	function postDivClass($thread) {
		$is_changed_thread = $thread->changeObject() &&
		$thread->changeObject()->id() == $thread->id();
		if ( $is_changed_thread )
		return 'lqt_post_changed_by_history';
		else
		return 'lqt_post';
	}

	function showHistoryInfo() {
		global $wgLang; // TODO global.
		$this->openDiv('lqt_history_info');
		$this->output->addHTML(wfMsg('lqt_revision_as_of', $wgLang->timeanddate($this->thread->modified())) .'<br />' );

		$ct = $this->thread->changeType();
		if( $ct == Threads::CHANGE_NEW_THREAD ) $msg = wfMessage( 'lqt_change_new_thread' )->text();
		else if( $ct == Threads::CHANGE_REPLY_CREATED ) $msg = wfMessage( 'lqt_change_reply_created' )->text();
		else if( $ct == Threads::CHANGE_EDITED_ROOT ) {
			$diff_url = $this->permalinkUrlWithDiff($this->thread);
			$msg = wfMessage( 'lqt_change_edited_root' )->text() . " [<a href=\"$diff_url\">" . wfMessage( 'diff' )->text() . '</a>]';
		}
		$this->output->addHTML($msg);
		$this->closeDiv();
	}

	function show() {
		if( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}
		$this->showHistoryInfo();
		parent::show();
		return false;
	}
}


class SummaryPageView extends LqtView {
	function show() {
		$thread = Threads::withSummary($this->article);
		if( $thread ) {
			$url = $thread->root()->getTitle()->getFullURL();
			$name = $thread->root()->getTitle()->getPrefixedText();
			$this->output->setSubtitle(
			wfMsg('lqt_summary_subtitle',
			'<a href="'.$url.'">'.$name.'</a>'));
		}
		return true;
	}
}


class SpecialMoveThreadToAnotherPage extends SpecialPage {
	private $user, $output, $request, $title, $thread;

	function __construct() {
		SpecialPage::SpecialPage( 'Movethread' );
		$this->includable( false );
	}

	/**
	* @see SpecialPage::getDescription
	*/
	function getDescription() {
		return wfMessage( 'lqt_movethread' )->text();
	}

	function handleGet() {
		$form_action = $this->title->getLocalURL() . '/' . $this->thread->title()->getPrefixedURL();
		$thread_name = $this->thread->title()->getPrefixedText();
		$article_name = $this->thread->article()->getTitle()->getTalkPage()->getPrefixedText();
		$edit_url = LqtView::permalinkUrl($this->thread, 'edit', $this->thread);
		$wfMsg = 'wfMsg'; // functions can only be called within string expansion by variable name.
		$this->output->addHTML(<<<HTML
<p>{$wfMsg('lqt_move_movingthread', "<b>$thread_name</b>", "<b>$article_name</b>")}</p>
<p>{$wfMsg('lqt_move_torename', "<a href=\"$edit_url\">{$wfMessage( 'lqt_move_torename_edit' )->text()}</a>")}</p>
<form id="lqt_move_thread_form" action="$form_action" method="POST">
<table>
<tr>
<td><label for="lqt_move_thread_target_title">{$wfMessage( 'lqt_move_destinationtitle' )->text()}</label></td>
<td><input id="lqt_move_thread_target_title" name="lqt_move_thread_target_title" tabindex="100" size="40" /></td>
</tr><tr>
<td><label for="lqt_move_thread_reason">{$wfMessage( 'movereason' )->text()}</label></td>
<td><input id="lqt_move_thread_reason" name="lqt_move_thread_reason" tabindex="200" size="40" /></td>
</tr><tr>
<td>&nbsp;</td>
<td><input type="submit" value="{$wfMessage( 'lqt_move_move' )->text()}" style="float:right;" tabindex="300" /></td>
</tr>
</table>
</form>
HTML
		);

	}

	function checkUserRights() {
		if ( !$this->user->isAllowed( 'move' ) ) {
			$this->output->showErrorPage( 'movenologin', 'movenologintext' );
			return false;
		}
		if ( $this->user->isBlocked() ) {
			$this->output->blockedPage();
			return false;
		}
		if ( wfReadOnly() ) {
			$this->output->readOnlyPage();
			return false;
		}
		if ( $this->user->pingLimiter( 'move' ) ) {
			$this->output->rateLimited();
			return false;
		}
		/* Am I forgetting anything? */
		return true;
	}

	function redisplayForm($problem_fields, $message) {
		$this->output->addHTML($message);
		$this->handleGet();
	}

	function handlePost() {
		if( !$this->checkUserRights() )
		return;

		$tmp = $this->request->getVal('lqt_move_thread_target_title');
		if( $tmp === "" ) {
			$this->redisplayForm(array('lqt_move_thread_target_title'), wfMessage( 'lqt_move_nodestination' )->text());
			return;
		}
		$newtitle = Title::newFromText( $tmp )->getSubjectPage();

		$reason = $this->request->getVal('lqt_move_thread_reason', wfMessage( 'lqt_noreason' )->text());

		// TODO no status code from this method.
		$this->thread->moveToSubjectPage( $newtitle, $reason, true );

		$this->showSuccessMessage( $newtitle->getTalkPage() );
	}

	function showSuccessMessage( $target_title ) {
		$this->output->addHTML(wfMsg('lqt_move_success',
		'<a href="'.$target_title->getFullURL().'">'.$target_title->getPrefixedText().'</a>'));
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgTitle, $wgUser;
		$this->user = $wgUser;
		$this->output = $wgOut;
		$this->request = $wgRequest;
		$this->title = $wgTitle;

		$this->setHeaders();

		if( $par === null || $par === "") {
			$this->output->addHTML(wfMessage( 'lqt_threadrequired' )->text());
			return;
		}
		// TODO should implement Threads::withTitle(...).
		$thread = Threads::withRoot( new Article(Title::newFromURL($par)) );
		if (!$thread) {
			$this->output->addHTML(wfMessage( 'lqt_nosuchthread' )->text());
			return;
		}

		$this->thread = $thread;

		if ( $this->request->wasPosted() ) {
			$this->handlePost();
		} else {
			$this->handleGet();
		}

	}
}

class SpecialDeleteThread extends SpecialPage {
	private $user, $output, $request, $title, $thread;

	function __construct() {
		SpecialPage::SpecialPage( 'Deletethread' );
		$this->includable( false );
	}

	/**
	* @see SpecialPage::getDescription
	*/
	function getDescription() {
		return wfMessage( 'lqt_deletethread' )->text();
	}

	function handleGet() {
		if( !$this->checkUserRights() )
		return;

		$form_action = $this->title->getLocalURL() . '/' . $this->thread->title()->getPrefixedURL();
		$thread_name = $this->thread->title()->getPrefixedText();
		$article_name = $this->thread->article()->getTitle()->getTalkPage()->getPrefixedText();

		$deleting = $this->thread->type() != Threads::TYPE_DELETED;

		$operation_message = $deleting ?
		wfMsg('lqt_delete_deleting', "<b>$thread_name</b>", '<b>'.wfMessage( 'lqt_delete_deleting_allreplies' )->text().'</b>')
		//				"Deleting <b>$thread_name</b> and <b>all replies</b> to it."
		: wfMsg('lqt_delete_undeleting', "<b>$thread_name</b>");
		$button_label = $deleting ?
		wfMessage( 'lqt_delete_deletethread' )->text()
		: wfMessage( 'lqt_delete_undeletethread' )->text();
		$part_of = wfMsg('lqt_delete_partof', '<b>'.$article_name.'</b>');
		$reason = wfMessage( 'movereason' )->text(); // XXX arguably wrong to use movereason.

		$this->output->addHTML(<<<HTML
<p>$operation_message
$part_of</p>
<form id="lqt_delete_thread_form" action="{$form_action}" method="POST">
<table>
<tr>
<td><label for="lqt_delete_thread_reason">$reason</label></td>
<td><input id="lqt_delete_thread_reason" name="lqt_delete_thread_reason" tabindex="200" size="40" /></td>
</tr><tr>
<td>&nbsp;</td>
<td><input type="submit" value="$button_label" style="float:right;" tabindex="300" /></td>
</tr>
</table>
</form>
HTML
		);

	}

	function checkUserRights() {
		if( in_array('delete', $this->user->getRights()) ) {
			return true;
		} else {
			$this->output->addHTML(wfMessage( 'lqt_delete_unallowed' )->text());
			return false;
		}
	}

	function redisplayForm($problem_fields, $message) {
		$this->output->addHTML($message);
		$this->handleGet();
	}

	function handlePost() {
		// in theory the model should check rights...
		if( !$this->checkUserRights() )
		return;

		$reason = $this->request->getVal('lqt_delete_thread_reason', wfMessage( 'lqt_noreason' )->text());

		// TODO: in theory, two fast-acting sysops could undo each others' work.
		$is_deleted_already = $this->thread->type() == Threads::TYPE_DELETED;
				$dbw = wfGetDB( DB_MASTER );
				$ns = $this->thread->title()->getNamespace();
				$t = $this->thread->title()->getDBkey();

		if ( $is_deleted_already ) {
			$this->thread->undelete($reason);
			$dbw->update( 'recentchanges', array( 'rc_deleted' => '0' ),
								array( 'rc_namespace' => $ns, 'rc_title' => $t ), 'RecentChange::set_deleted');
		} else {
			$this->thread->delete($reason);
			$dbw->update( 'recentchanges', array( 'rc_deleted' => '1' ),
				array( 'rc_namespace' => $ns, 'rc_title' => $t ), 'RecentChange::set_deleted');
		}
		$this->showSuccessMessage( $is_deleted_already );
	}

	function showSuccessMessage( $is_deleted_already ) {
		// TODO talkpageUrl should accept threads, and look up their talk pages.
		$talkpage_url = LqtView::talkpageUrl($this->thread->article()->getTitle()->getTalkPage());
		$message = $is_deleted_already ? wfMessage( 'lqt_delete_undeleted' )->text() : wfMessage( 'lqt_delete_deleted' )->text();
		$message .= ' ';
		$message .= wfMsg('lqt_delete_return', '<a href="'.$talkpage_url.'">'.wfMessage( 'lqt_delete_return_link' )->text().'</a>');
		$this->output->addHTML($message);
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgTitle, $wgUser;
		$this->user = $wgUser;
		$this->output = $wgOut;
		$this->request = $wgRequest;
		$this->title = $wgTitle;

		$this->setHeaders();

		if( $par === null || $par === "") {
			$this->output->addHTML(wfMessage( 'lqt_threadrequired' )->text());
			return;
		}
		// TODO should implement Threads::withTitle(...).
		$thread = Threads::withRoot( new Article(Title::newFromURL($par)) );
		if (!$thread) {
			$this->output->addHTML(wfMessage( 'lqt_nosuchthread' )->text());
			return;
		}

		$this->thread = $thread;

		if ( $this->request->wasPosted() ) {
			$this->handlePost();
		} else {
			$this->handleGet();
		}

	}
}

class NewUserMessagesView extends LqtView {

	protected $threads;
	protected $tops;
	protected $targets;

	protected function htmlForReadButton($label, $title, $class, $ids) {
		$ids_s = implode(',', $ids);
		return <<<HTML
		<form method="POST" class="{$class}">
		<input type="hidden" name="lqt_method" value="mark_as_read" />
		<input type="hidden" name="lqt_operand" value="{$ids_s}" />
		<input type="submit" value="{$label}" name="lqt_read_button" title="{$title}" />
		</form>
HTML;
	}

	function showReadAllButton($threads) {
		$ids =  array_map(create_function('$t', 'return $t->id();'), $threads);
		$this->output->addHTML(
			$this->htmlForReadButton(
				wfMessage( 'lqt-read-all' )->text(),
				wfMessage( 'lqt-read-all-tooltip' )->text(),
				"lqt_newmessages_read_all_button",
				$ids )
		);
	}

	function preShowThread($t) {
		//		$t_ids = implode(',', array_map(create_function('$t', 'return $t->id();'), $this->targets[$t->id()]));
		$read_button = $this->htmlForReadButton(
			wfMessage( 'lqt-read-message' )->text(),
			wfMessage( 'lqt-read-message-tooltip' )->text(),
			'lqt_newmessages_read_button',
			$this->targets[$t->id()]);
		$this->output->addHTML(<<<HTML
<table ><tr>
<td style="padding-right: 1em; vertical-align: top; padding-top: 1em;" >
$read_button
</td>
<td>
HTML
		);
	}

	function postShowThread($t) {
		$this->output->addHTML(<<<HTML
</td>
</tr></table>
HTML
		);
	}

	function showUndo($ids) {
		$noUndo = false;
		if( count($ids) == 1 ) {
			$t = Threads::withId($ids[0]);
			if( $t ) {
				$msg = wfMsg( 'lqt-marked-read',$t->subject()  );
			} else {
				$msg = wfMessage( 'lqt-nothread' )->text();
				$noUndo = true;
			}
		} else {
			$count = count($ids);
			$msg =  wfMsg( 'lqt-count-marked-read',$count );
		}
		$operand = implode(',', $ids);
		$lqt_email_undo = wfMessage( 'lqt-email-undo' )->text();
		$lqt_info_undo = wfMessage( 'lqt-email-info-undo' )->text();
		if( !$noUndo ) {
			$this->output->addHTML(<<<HTML
<form method="POST" class="lqt_undo_mark_as_read">
$msg
<input type="hidden" name="lqt_method" value="mark_as_unread" />
<input type="hidden" name="lqt_operand" value="{$operand}" />
<input type="submit" value="{$lqt_email_undo}" name="lqt_read_button" title="{$lqt_info_undo}" />
</form>
HTML
			);
		}
	}

	function postDivClass($thread) {
		$topid = $thread->topmostThread()->id();
		if( in_array($thread->id(), $this->targets[$topid]) )
			return 'lqt_post_new_message';
		else
			return 'lqt_post';
	}

	function showOnce() {
		self::addJSandCSS();

		if( $this->request->wasPosted() ) {
			// If they just viewed this page, maybe they still want that notice.
			// But if they took the time to dismiss even one message, they
			// probably don't anymore.
			$this->user->setNewtalk(false);
		}

		if( $this->request->wasPosted() && $this->methodApplies('mark_as_unread') ) {
			$ids = explode(',', $this->request->getVal('lqt_operand', ''));
			if( $ids !== false ) {
				foreach($ids as $id) {
					NewMessages::markThreadAsUnreadByUser(Threads::withId($id), $this->user);
				}
				$this->output->redirect( $this->title->getFullURL() );
			}
		}

		else if( $this->request->wasPosted() && $this->methodApplies('mark_as_read') ) {
			$ids = explode(',', $this->request->getVal('lqt_operand', ''));
			if( $ids !== false && isset( $ids[0] ) ) {
				foreach($ids as $id) {
					NewMessages::markThreadAsReadByUser(Threads::withId($id), $this->user);
				}
				$query = 'lqt_method=undo_mark_as_read&lqt_operand=' . implode(',', $ids);
				$this->output->redirect( $this->title->getFullURL($query) );
			}
		}

		else if( $this->methodApplies('undo_mark_as_read') ) {
			$ids = explode(',', $this->request->getVal('lqt_operand', ''));
			$this->showUndo($ids);
		}
	}

	function show() {
		if ( ! is_array( $this->threads ) ) {
			throw new MWException('You must use NewUserMessagesView::setThreads() before calling NewUserMessagesView::show().');
		}

		// Do everything by id, because we can't depend on reference identity; a simple Thread::withId
		// can change the cached value and screw up your references.

		$this->targets = array();
		$this->tops = array();
		foreach( $this->threads as $t ) {
			$top = $t->topmostThread();
			if( !in_array($top->id(), $this->tops) )
				$this->tops[] = $top->id();
			if( !array_key_exists($top->id(), $this->targets) )
				$this->targets[$top->id()] = array();
			$this->targets[$top->id()][] = $t->id();
		}

		foreach($this->tops as $t_id) {
			$t = Threads::withId($t_id);
			// It turns out that with lqtviews composed of threads from various talkpages,
			// each thread is going to have a different article... this is pretty ugly.
			$this->article = $t->article();

			$this->preShowThread($t);
			$this->showThread($t);
			$this->postShowThread($t);
		}
		return false;
	}

	function setThreads( $threads ) {
		$this->threads = $threads;
	}
}

class SpecialNewMessages extends SpecialPage {
	private $user, $output, $request, $title;

	function __construct() {
		SpecialPage::SpecialPage( 'Newmessages' );
		$this->includable( true );
	}

	/**
	* @see SpecialPage::getDescription
	*/
	function getDescription() {
		return wfMessage( 'lqt_newmessages' )->text();
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgTitle, $wgUser;
		$this->user = $wgUser;
		$this->output = $wgOut;
		$this->request = $wgRequest;
		$this->title = $wgTitle;

		$this->setHeaders();

		$view = new NewUserMessagesView( $this->output, new Article($this->title),
		$this->title, $this->user, $this->request );

		$first_set = NewMessages::newUserMessages($this->user);
		$second_set = NewMessages::watchedThreadsForUser($this->user);
		$both_sets = array_merge($first_set, $second_set);
		$view->showReadAllButton($both_sets); // ugly hack.

		$view->setHeaderLevel(3);
		$view->showOnce(); // handles POST etc.

		$this->output->addHTML('<h2 class="lqt_newmessages_section">'.wfMessage( 'lqt-messages-sent' )->text().'</h2>');
		$view->setThreads( $first_set );
		$view->show();

		$this->output->addHTML('<h2 class="lqt_newmessages_section">'.wfMessage( 'lqt-other-messages' )->text().'</h2>');
		$view->setThreads( $second_set );
		$view->show();
	}
}
