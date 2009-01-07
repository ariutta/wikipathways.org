<?php
/*

Recent pathway changes
Recent discussions

*/

$wgExtensionFunctions[] = "RecentChangesBox::init";

class RecentChangesBox {
	private $namespace;
	private $limit;
	private $rows;
	private $cssClass;
	private $style;
	
	public static function init() {
		global $wgParser;
		$wgParser->setHook( "recentChanges", "RecentChangesBox::create" );
	}

	public static function create($input, $argv, &$parser) {
		$parser->disableCache();
		
		$ns = $argv['namespace'];
		$limit = $argv['limit'];
		$class = $argv['class'];
		$style = $argv['style'];
		
		$rcb = new RecentChangesBox($ns, $limit);
		$rcb->setCssClass($class);
		$rcb->setStyle($style);
		return $rcb->output();
	}
	
	public function __construct($namespace = NS_MAIN, $limit = 5) {
		$this->namespace = $namespace;
		$this->limit = $limit;
		$this->query();
	}
	
	public function setCssClass($cssClass) {
		$this->cssClass = $cssClass;
	}
	
	public function setStyle($style) {
		$this->style = $style;
	}
	
	public function output() {
		if(count($this->rows) == 0) {
			return "<I>No recent changes</I>";
		}
		
		$style = $this->style ? "style='{$this->style}'" : '';
		$html = "<TABLE class='{$this->cssClass}' $style>";
		
		foreach(array_keys($this->rows) as $date) {
			$html .= "<TR class='recentChangesBoxDate'><TD colspan='2'>$date";
			$html .= $this->rows[$date];
		}
		
		$html .= "</TABLE>";
		return $html;
	}
	
	private function query() {
		global $wgLang;
		
		$dbr =& wfGetDB( DB_SLAVE );
		
		$res = $dbr->query(
			"SELECT DISTINCT (rc_title)
			FROM recentchanges
			WHERE rc_namespace = {$this->namespace}
			ORDER BY rc_timestamp DESC
			LIMIT 0 , {$this->limit}"
		);
		
		$this->rows = array();
		while($row = $dbr->fetchObject( $res )) {
			$title_res = $dbr->query(
				"SELECT rc_title, rc_timestamp, rc_user, rc_comment, rc_new
				FROM recentchanges
				WHERE rc_title = '{$row->rc_title}' AND rc_namespace = {$this->namespace}
				"
			);
			if($title_row = $dbr->fetchObject($title_res)) {
				$date = $wgLang->date($title_row->rc_timestamp, true);
				if($date == $wgLang->date(wfTimestamp(TS_MW))) {
					$date = 'Today';
				}
				$this->rows[$date] .= $this->formatRow($title_row);
				$dbr->freeResult($title_res);
			}
		}
		$dbr->freeResult( $res );
	}
	
	private function formatRow($row) {
		$user = User::newFromId($row->rc_user);
		$userUrl = Title::newFromText('User:' . $user->getTitleKey())->getFullUrl();

		$title = Title::newFromText($row->rc_title, $this->namespace);
		$titleLink = $this->titleLink($title);
		
		if($row->rc_new) {
			$icon = SITE_URL . "/skins/common/images/comment_add.png";
		} else if(substr($row->rc_comment, 0, strlen(Pathway::$DELETE_PREFIX)) == Pathway::$DELETE_PREFIX) {
			$icon = SITE_URL . "/skins/common/images/comment_remove.png";
		} else {
			$icon = SITE_URL . "/skins/common/images/comment_edit.png";
		}
		$img = "<img src='$icon' title='{$row->rc_comment}'></img>";
		
		return "<TR><TD>$img<TD>$titleLink by <a href='$userUrl' title='{$row->rc_comment}'>{$this->getDisplayName($user)}</a>";
	}
	
	private function getDisplayName($user) {
		$name = $user->getRealName();
		
		//Filter out email addresses
		if(preg_match("/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD", $name)) {
			$name = ''; //use username instead
		}
		if(!$name) $name = $user->getName();
		return $name;
	}
	
	private function titleLink($title) {
		$a = array();
		
		switch($title->getNamespace()) {
			case NS_PATHWAY:
				$pathway = Pathway::newFromTitle($title);
				$a['text'] = $pathway->getName() . " (" . $pathway->getSpecies() . ")";
				$a['href'] = $pathway->getTitleObject()->getFullURL();
				break;
			default:
				$a['text'] = $title->getText();
				$a['href'] = $title->getFullURL();
				break;
		}
		return "<a href='{$a['href']}'>{$a['text']}</a>";
	}
}
?>
