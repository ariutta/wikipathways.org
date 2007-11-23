<?php
require_once("wpi/wpi.php");
require_once("PathwayWishList.php");

class SpecialWishList extends SpecialPage
{		
	private $wishlist;
	
	private $this_url;
	
        function SpecialWishList() {
                SpecialPage::SpecialPage("SpecialWishList");
                self::loadMessages();
        }

        function execute( $par ) {
                global $wgRequest, $wgOut, $wgUser;
                $this->setHeaders();

		$this->this_url = SITE_URL . '/index.php?title=Special:SpecialWishList';

		//Create the wishlist table
		$this->wishlist = new PathwayWishList();
		$checkwishes = array();
		foreach(array_keys($_REQUEST) as $key) {
			if('check_' == substr($key, 0, 6)) {
				$wid = substr($key, 6);
					$checkwishes[$wid] = $_REQUEST[$key];
			}
		}
		switch($_REQUEST['wishaction']) {
			case 'add':
				$done = $this->add($_REQUEST['name'], $_REQUEST['comments']);
				break;
			case 'resolved':
				$done = $this->markResolved($_REQUEST['id'], $_REQUEST['pathway']);
				break;
			case 'watch':
				$done = $this->setWatch($checkwishes);
				break;
			case 'remove':
				$done = $this->remove($_REQUEST['id']);
				break;
			case 'vote':
				$done = $this->vote($_REQUEST['id']);
				break;
			case 'unvote':
				$done = $this->unvote($_REQUEST['id']);
				break;
			default:
				$this->showlist();
		}        }

	function reload() {
		global $wgOut;
		$wgOut->redirect($this->this_url);
	}
	
	function add($name, $comments) {
		$this->wishlist->addWish($name, $comments);
		$this->reload();
	}
	
	function remove($id) {
		
		$wish = new Wish($id);
		$wish->remove();
		
		$this->reload();
	}
	
	function vote($id) {
		global $wgUser;
		$wish = new Wish($id);
		$wish->vote($wgUser->getId());
		$this->reload();
	}
	
	function unvote($id) {
		global $wgUser;
		$wish = new Wish($id);
		$wish->unvote($wgUser->getId());
		$this->reload();
	}
	
	function setWatch($wishes) {
		//Unwatch all unchecked items
		foreach($this->wishlist->getWishlist() as $wish) {
			if($wishes[$wish->getId()]) {
				$wish->watch();
			} else {
				$wish->unwatch();
			}
		}
		$this->reload();
	}
	
	function markResolved($id, $pathwayTitle = '') {
		global $wgOut;
		if($pathwayTitle) {
			$this->doMarkResolved($id, $pathwayTitle);
			$this->reload();
		}
		
		$select = "<select name='pathway'>$pwSelect";
		//First show a form to fill in the pathway names
		$pathways = Pathway::getAllPathways();
		foreach($pathways as $pathway) {
			$name = $pathway->name();
			$species = $pathway->species();
			$title = $pathway->getTitleObject()->getFullText();
			$select .= "<option value='$title'>$name ($species)</option>";
		}
		$select .= '</select>';
		$html = <<<HTML
<H2>Resolve wishlist items</H2>
<P>Please specify for each item the pathway that resolves it:
<P>
<FORM acion="{$this->this_url}" method="post">
$select
<INPUT type="hidden" name="wishaction" value="resolved">
<INPUT type="submit" value="Resolve item">
</FORM>

HTML;
		$wgOut->addHTML($html);
		return true;
	}
	
	function doMarkResolved($id, $pathwayTitle) {
		$wish = new Wish($id);
		$pathway = Pathway::newFromTitle($pathwayTitle);
		$wish->markResolved($pathway);
	}
	
	function showlist() {
	 	global $wgRequest, $wgOut, $wgUser, $wgLang, $wgScriptPath;
	 	$this->addJavaScript();
		
		//Create a small toolbar with 'new' and 'help' actions
		//TODO: pretty style	 	
	 	$wgOut->addHTML("<table><tbody><tr><td>");
	 	$elm = $this->getNewFormElements();
	 	$newdiv = $elm['div'];
	 	$newbutton = $elm['button'];
	 	$wgOut->addHTML("$newbutton<td>");
	 	$elm = $this->getHelpElements();
	 	$helpbutton = $elm['button'];
	 	$helpdiv = $elm['div'];
		$wgOut->addHTML("$helpbutton</tbody></table>");
		$wgOut->addHTML($newdiv . $helpdiv);
		
		//Create the actual wishlist
	 	$wishes = $this->wishlist->getWishlist();
	 	$html = <<<HTML
<form action='{$this->this_url}' method='post'>
<table class="prettytable"><tbody>
<th>Pathway name</th><th>Requested by</th><th>Date</th><th>Comments</th><th>Votes
HTML;
		if($wgUser->isLoggedIn()) {
			$html .= "<th>Watch</th><th>Resolve";
		}
	 	$wgOut->addHTML($html);
		foreach($wishes as $wish) {
			if(!$wish->isResolved()) {
				$this->createUnresolvedRow($wish);
			}
		}
		$html = "";
		if($wgUser->isLoggedIn()) {
			$html = <<<HTML
<tr><td colspan="5"><td align="center">
	<input type="hidden" name="wishaction" value="watch">
	<input type="submit" value="Apply">
HTML;
		}
		$html .= "</tbody></table></form>";
		$wgOut->addHTML($html);
				
		$wgOut->addWikiText("== Resolved items ==");
		$wgOut->addHTML("<table class='prettytable'><tbody>
				<th>Item name<th>Date resolved<th>Pathway<th>Created on<th>Created by");
		foreach($wishes as $wish) {
			if($wish->isResolved()) {
				$this->createResolvedRow($wish);
			}
		}
		$wgOut->addHTML("</tbody></table>");
	}
	
	function addJavaScript() {
		global $wgOut;
		$js = <<<JS
<script type="text/javascript">
	function showhide(id, toggle, hidelabel, showlabel) {
		elm = document.getElementById(id);
		if(toggle.innerHTML == hidelabel) {
			elm.style.display = "none";
			toggle.innerHTML = showlabel;
		} else {
			elm.style.display = "";
			toggle.innerHTML = hidelabel;
		}
	}
</script>
JS;
		$wgOut->addScript($js);
	}
	
	function getNewFormElements() {
		global $wgUser;
		
		$div = <<<DIV
<div id="new" style="display:none">
Fill in the form below to add a new item to the wishlist.
<FORM action="{$this->this_url}" method="post">
    <P>
    <LABEL for="name">Name of pathway: </LABEL>
              <INPUT type="text" id="name" name="name"><BR>
    <LABEL for="comments">Description, example urls or comments: </LABEL>
              <TEXTAREA id="comments" name="comments" rows="10" cols="30"></TEXTAREA><BR>
    <INPUT type="hidden" name="wishaction" value="add">
    <INPUT type="submit" value="Add">
    </P>
</FORM>
</div>	
DIV;
		
		if(wfReadOnly() || !$wgUser->isAllowed('edit')) {
			$href = SITE_URL . "/index.php?title=Special:Userlogin&returnto=Special:SpecialWishList";
			$button = "<a href=$href>Log in</a> to add pathways to the wishlist";
		} else {
			$button = "<a href=\"javascript:showhide('new', this, 'Add new wishlist item', '');\">Add new wishlist item</a>";
		}
		return array('button' => $button, 'div' => $div);
	}
	
	function getHelpElements() {
		global $wgScriptPath;
		
		$button = "<a href=\"javascript:showhide('help', this, 'Help', 'Hide help');\">Help</a>";
		$div = <<<HELP
<div id="help" style="display:none">
<p>This page shows a list of pathways that users would like to see added to WikiPahtways. You can add a pathway request to the list,
by clicking 'Add new wishlist item'.
<p>
<table frame="box"><head><i>Legend for the pathway wishlist table:</i><tbody>
<td colspan="2"><b>Watch:</b>
<tr>
<td colspan="2">
You can watch items in the wishlist by checking the checkbox in the 'Watch' column and clicking the 'Apply' button.
When you watch an item from the wishlist, you will recieve an email notification when somebody modifies or resolves the item.
<tr>
<td colspan="2"><b>Resolve:</b>
<tr>
<td><img align='right' style='border:1' src='$wgScriptPath/skins/common/images/apply.gif'/>
<td>Use this button to resolve an item when the requested pathway is created. The item will
be transfered to the 'resolved items' list.
<tr>
<td><img align='right' style='border:1' src='$wgScriptPath/skins/common/images/cancel.gif'/>
<td>Removes the item from the list, it will not show up in the 'resolved items' list. You can only remove
items that you created yourself.
<tr>
<td colspan="2"><b>Votes:</b>
<tr>
<td><img align='right' style='border:1' src='$wgScriptPath/skins/common/images/plus.png'/>
<td>Vote for the pathway</td>
<tr>
<td><img align='right' style='border:1' src='$wgScriptPath/skins/common/images/minus.png'/>
<td>Remove your vote</td>
</tbody></table></div>
HELP;
		return array('button' => $button, 'div' => $div);
	}
		
	function createResolvedRow($wish) {
		global $wgOut, $wgLang, $wgUser, $wgScriptPath;
		$title = $wish->getTitle()->getText();
		$pathway = $wish->getResolvedPathway();
		$rev = $pathway->getFirstRevision();
		$resDate = $wgLang->timeanddate($wish->getResolvedDate());
		$pwDate = $wgLang->timeanddate($rev->getTimestamp());
		$user = $wgUser->getSkin()->userLink( $rev->getUser(), $rev->getUserText() );
		if($wish->isResolved()) {
			$wgOut->addHTML("<tr><td>$title<td>$resDate<td>");
			$wgOut->addWikiText("[[{$pathway->getTitleObject()->getFullText()} | {$pathway->name()} ({$pathway->species()})]]");
			$wgOut->addHTML("<td>$pwDate<td>$user");
			if($wish->userCan('delete') && $wgUser->getId() == $wish->getRequestUser()->getId()) {
				$wgOut->addHTML("<td>" . $this->createButton("cancel.gif", "remove", "Remove this item", $wish->getId()));
			}
		}	
	}
	
	function createUnresolvedRow($wish) {
		global $wgOut, $wgLang, $wgScriptPath, $wgUser;
		
		$login = $wgUser->isLoggedIn();
		
		$id = $wish->getId();
		$url = $wish->getTitle()->getFullURL();
		$title = $wish->getTitle()->getText();
		$user = $wish->getRequestUser();
		$user = $wgUser->getSkin()->userLink( $user, $user->getName());
		$date = $wgLang->timeanddate( $wish->getRequestDate(), true );
		$watching = $wish->userIsWatching() ? "CHECKED" : "";
		$votes = (int)$wish->countVotes();
		$fullComment = str_replace('"', "'", $wish->getComments());
		$comment = $this->truncateComment($wish, 75); //Cutoff comment at 20 chars
		if($wish->userCan('vote')) {
			$voteButton = '<td style="border:0px">' . 
				$this->createButton('plus.png', 'vote', 'Vote for this pathway', $id);
		} else if ($wish->userCan('unvote')) {
			$voteButton = '<td style="border:0px">' . 
				$this->createButton('minus.png', 'unvote', 'Remove your vote', $id);
		}
		$wgOut->addHTML("<tr><td><b><a href='$url'>$title</a></b><td>$user
				<td>$date<td title=\"$fullComment\">");
		$wgOut->addWikiText($comment);
		$wgOut->addHTML("<td><table class='prettytable' style='border:0px'><tr><td style='border:0px'>$votes" . $voteButton . '</table>');
		if($login) { //Following columns only when user is logged in
			$wgOut->addHTML("<td align='center'><input type=checkbox value='1' name='check_$id' $watching>");
			$wgOut->addHTML("<td><table class='prettytable' style='border:0px'><tr>");
			if($wish->userCan('resolve')) {
				$wgOut->addHTML('<td style="border:0px">' . 
					$this->createButton("apply.gif", "resolved", "Resolve this item", $id));
			}
			if($wish->userCan('delete') && $wgUser->getId() == $wish->getRequestUser()->getId()) {
				$wgOut->addHTML('<td style="border:0px">' . 
					$this->createButton("cancel.gif", "remove", "Remove this item", $id));
			}
			$wgOut->addHTML("</table>");
		}
	}
	
	function truncateComment($wish, $cutoff = 0) {
		$pagename = $wish->getTitle()->getFullText();
		$comment = $wish->getComments();
		if($cutoff && strlen($comment) > $cutoff) {
			//Truncate comment to cutoff length
			$comment = substr($comment, 0, $cutoff);
			//Append with 'more' link
			$comment .= "...'''[[$pagename |more]]'''";
		}
		return $comment;
	}
	
	function createButton($image, $action, $title, $id) {
		global $wgScriptPath;
		return "<a href='{$this->this_url}&wishaction=$action&id=$id'> 
						<img align='right' style='border:1' src='$wgScriptPath/skins/common/images/$image'
						title='$title'/></a>";
	}
	
	function createLink($label, $action, $title, $id) {
		global $wgScriptPath;
		return "<a href='{$this->this_url}&wishaction=$action&id=$id' title='$title'>$label</a>";
	}
	
		
    function loadMessages() {
        static $messagesLoaded = false;
        global $wgMessageCache;
        if ( $messagesLoaded ) return;
        $messagesLoaded = true;

        require( dirname( __FILE__ ) . '/SpecialWishList.i18n.php' );
        foreach ( $allMessages as $lang => $langMessages ) {
                $wgMessageCache->addMessages( $langMessages, $lang );
            }
    }
}
?>
