<?php

class PathwayPage {
	private $pathway;
	private $data;

	function __construct($pathway) {
		$this->pathway = $pathway;
		$this->data = $pathway->getPathwayData();
	}

	function getContent() {
		$text = <<<TEXT
{$this->titleEditor()}
{$this->privateWarning()}
{{Template:PathwayPage:Top}}
{$this->curationTags()}
{$this->descriptionText()}
{$this->ontologyTags()}
{$this->bibliographyText()}
{{Template:PathwayPage:Bottom}}
TEXT;
		return $text;
	}

	function titleEditor() {
		$title = $this->pathway->getName();
		return "<pageEditor id='pageTitle' type='title'>$title</pageEditor>\n";
	}

	function privateWarning() {
		global $wgScriptPath, $wgLang;

		$warn = '';
		if(!$this->pathway->isPublic()) {
			$url = SITE_URL;
			$msg = wfMessage( 'private_warning' )->text();

			$pp = $this->pathway->getPermissionManager()->getPermissions();
			$expdate = $pp->getExpires();
			$expdate = $wgLang->date($expdate, true);
			$msg = str_replace('$DATE', $expdate, $msg);
			$warn = "<div class='private_warn'>$msg</div>";
		}
		return $warn;
	}

	function curationTags() {
		$tags = "== Curation Tags ==\n" .
			"<CurationTags></CurationTags>";
		return $tags;
	}

	function descriptionText() {
		wfProfileIn( __METHOD__ );

		//Get WikiPathways description
		$content = $this->data->getWikiDescription();

		$description = $content;
		if(!$description) {
			$description = "<I>No description</I>";
		}
		$description = "== Description ==\n<div id='descr'>"
			 . $description . "</div>";

		$description .= "<pageEditor id='descr' type='description'>$content</pageEditor>\n";

		//Get additional comments
		$comments = '';
		foreach($this->data->getGpml()->Comment as $comment) {
			if(	$comment['Source'] == COMMENT_WP_DESCRIPTION ||
				$comment['Source'] == COMMENT_WP_CATEGORY)
			{
				continue; //Skip description and category comments
			}
			$text = (string)$comment;
			$text = html_entity_decode($text);
			$text = nl2br($text);
			$text = PathwayPage::formatPubMed($text);
			if(!$text) continue;
			$comments .= "; " . $comment['Source'] . " : " . $text . "\n";
		}
		if($comments) {
			$description .= "\n=== Comments ===\n<div id='comments'>\n$comments<div>";
		}
		wfProfileOut( __METHOD__ );
		return $description;
	}


	function ontologyTags() {
		global $wpiEnableOtag;
		if($wpiEnableOtag) {
			$otags = "== Ontology Tags ==\n" .
				"<OntologyTags></OntologyTags>";
			return $otags;
		}
	}


	function bibliographyText() {
		global $wgUser;

		$out = "<pathwayBibliography></pathwayBibliography>";
		//No edit button for now, show help on how to add bibliography instead
		//$button = $this->editButton('javascript:;', 'Edit bibliography', 'bibEdit');
		#&$parser, $idClick = 'direct', $idReplace = 'pwThumb', $new = '', $pwTitle = '', $type = 'editor'
		$help = '';
		if($wgUser->isLoggedIn()) {
			$help = "{{Template:Help:LiteratureReferences}}";
		}
		return "== Bibliography ==\n$out\n$help";
			//"<div id='bibliography'><div style='float:right'>$button</div>\n" .
			//"$out</div>\n{{#editApplet:bibEdit|bibliography|0||bibliography|0|250px}}";
	}

	function editButton($href, $title, $id = '') {
		global $wgUser, $wgTitle;
		# Check permissions
		if( $wgUser->isLoggedIn() && $wgTitle && $wgTitle->userCan('edit')) {
			$label = 'edit';
		} else {
			/*
			$pathwayURL = $this->pathway->getTitleObject()->getFullText();
			$href = SITE_URL . "/index.php?title=Special:Userlogin&returnto=$pathwayURL";
			$label = 'log in';
			$title = 'Log in to edit';
			*/
			return "";
		}
		return "<fancyButton title='$title' href='$href' id='$id'>$label</fancyButton>";
	}

	static function getDownloadURL($pathway, $type) {
		if($pathway->getActiveRevision()) {
			$oldid = "&oldid={$pathway->getActiveRevision()}";
		}
		return WPI_SCRIPT_URL . "?action=downloadFile&type=$type&pwTitle={$pathway->getTitleObject()->getFullText()}{$oldid}";
	}

	static function getImageURL($pathway, $type) {
		if($pathway->getActiveRevision()) {
			$oldid = "&oldid={$pathway->getActiveRevision()}";
		}
		return WPI_SCRIPT_URL . "?action=display&type=$type&pwTitle={$pathway->getTitleObject()->getFullText()}{$oldid}";
	}

	static function editDropDown($pathway) {
		global $wgOut;
		wfProfileIn( __METHOD__ );

		//AP20081218: Operating System Detection
		require_once 'DetectBrowserOS.php';
		//echo (browser_detection( 'os' ));
		$download = array(
			'PathVisio (.gpml)' => self::getDownloadURL($pathway, 'gpml'),
			'Scalable Vector Graphics (.svg)' => self::getDownloadURL($pathway, 'svg'),
			'Gene list (.txt)' => self::getDownloadURL($pathway, 'txt'),
			'Biopax level 3 (.owl)' => self::getDownloadURL($pathway, 'owl'),
			'Eu.Gene (.pwf)' => self::getDownloadURL($pathway, 'pwf'),
			'Png image (.png)' => self::getDownloadURL($pathway, 'png'),
			'Acrobat (.pdf)' => self::getDownloadURL($pathway, 'pdf'),
		);
		$downloadlist = '';
		foreach(array_keys($download) as $key) {
			$downloadlist .= "<li><a href='{$download[$key]}'>$key</a></li>";
		}

		$dropdown = <<<DROPDOWN
<ul id="nav" name="nav">
<li><a href="#nogo2" class="button buttondown"><span>Download</span></a>
		<ul>
			$downloadlist
		</ul>
</li>
</ul>

DROPDOWN;

		$script = <<<SCRIPT
<script type="text/javascript">

sfHover = function() {
	var sfEls = document.getElementById("nav").getElementsByTagName("LI");
	for (var i=0; i<sfEls.length; i++) {
		sfEls[i].onmouseover=function() {
			this.className+=" sfhover";
		}
		sfEls[i].onmouseout=function() {
			this.className=this.className.replace(" sfhover", "");
		}
	}
}
if (window.attachEvent) window.attachEvent("onload", sfHover);

</script>
SCRIPT;
		$wgOut->addScript($script);
		wfProfileIn( __METHOD__ );
		return $dropdown;
	}
	
	static function formatPubMed($text) {
		wfProfileIn( __METHOD__ );
		$link = "http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?db=pubmed&cmd=Retrieve&dopt=AbstractPlus&list_uids=";
		if(preg_match_all("/PMID: ([0-9]+)/", $text, $ids)) {
			foreach($ids[1] as $id) {
				$text = str_replace($id, "[$link$id $id]", $text);
			}
		}
		wfProfileOut( __METHOD__ );
		return $text;
	}
}
