<?php

/**
Class that represents a Pathway on WikiPathways
**/
class Pathway {
	public static $idPrefix = 'WP';
	public static $deletePrefix = "Deleted pathway: ";

	private static $fileTypes = array(
		FILETYPE_IMG => FILETYPE_IMG,
		FILETYPE_GPML => FILETYPE_GPML,
		FILETYPE_PNG => FILETYPE_IMG,
	);

	//The title object for the pathway page
	private $pwPageTitle;
	//The pathway identifier
	private $pathwayId;

	//The PathwayData for this pathway
	private $pwData;
	//The first revision of the pathway article
	private $firstRevision;
	//The active revision for this instance
	private $revision;
	//The MetaDataCache object that handles the cached title/species
	private $metaDataCache;
	//Manages permissions for private pathways
	private $permissionMgr;

	/**
	 * Constructor for this class.
	 * @param $id The pathway identifier (NOTE: during the transition
	 * phase to stable identifiers, this will be the page title
	 * without namespace prefix).
	 */
	function __construct($id = null, $updateCache = false) {
		global $wgTitle;
		if( !$id ) {
			throw new Exception("Invalid title passed in!");
		}
		if( $id instanceOf Title ) {
			if( $id->getNamespace() !== NS_PATHWAY ) {
				throw new Exception( "Not a pathway: ". $id );
			}
			$this->pwPageTitle = $id;
		} else {
			$this->pwPageTitle = Title::newFromText($id, NS_PATHWAY);
		}
		$this->pathwayId = $this->pwPageTitle->getDbKey();
		$this->revision = $this->getLatestRevision();
		if($updateCache) $this->updateCache();
	}

	public function getIdentifier() {
		return $this->pathwayId;
	}

	public function getPageIdDB() {
		wfProfileIn( __METHOD__ );
		$dbr = wfGetDB( DB_SLAVE );
		$query = "SELECT page_id FROM `page` WHERE page_title = '$this->pathwayId' ".
			"AND page_namespace = 102";
		$res = $dbr->query($query);
		while ($row = $dbr->fetchRow($res)){
			$pageId = $row["page_id"];
		}
		wfProfileOut( __METHOD__ );
		return $pageId;
	}

	/**
	 * Constructor for this class.
	 * @param name The name of the pathway (without namespace and
	 * species prefix!)
	 * @param species The species (full name, e.g. Human)
	 * @param updateCache Whether the cache should be updated if needed
	 * @deprecated This constructor will be removed after the transision
	 * to stable identifiers.
	 */
	public static function newFromName($name, $species, $updateCache = false) {
		wfDebug("Creating pathway: $name, $species\n");
		if ( !$name )
			throw new Exception("name argument missing in constructor for".
				"Pathway");
		if ( !$species )
			throw new Exception("species argument missing in constructor".
				"for Pathway");

		# general illegal chars

		//~ $rxIllegal = '/[^' . Title::legalChars() . ']/';
		$rxIllegal = '/[^a-zA-Z0-9_ -]/';
		if (preg_match ($rxIllegal, $name, $matches)) {
			throw new Exception("Illegal character '" . $matches[0] .
				"' in pathway name");
		}

		return self::newFromTitle("$species:$name", $updateCache);
	}


	public static function titleIsPathway( $title ) {
		if ( $title->getNamespace() === NS_PATHWAY ) {
			return true;
		}
		return false;
	}

	public static function pathwayExists( $title ) {
		$exists = preg_match( "/" . self::$idPrefix . "\d+/", $title, $match );
		if ( !$exists ) {
			return false;
		}
		return $match[0];
	}


	/**
	 * Parse the pathway identifier from the given string or title object.
	 * @returns the identifier, of false if no identifier could be found.
	 */
	public static function parseIdentifier($title) {
		if ( is_a( $title, 'Title' ) ) {
			if ( !self::titleIsPathway( $title ) ) {
				throw new MWException( "'$title' is not a pathway!" );
			}
			$title = $title->getText();
		}

		$ret = self::pathwayExists( $title );
		if ( $ret === false ) {
			throw new MWException( "'$title' does not exist!" );
		}
		return $ret;
	}

	/**
	 * Get the active revision in the modification
	 * history for this instance. The active revision
	 * is the latest revision by default.
	 * \see Pathway::setActiveRevision(revision)
	 */
	public function getActiveRevision() {
		return $this->revision;
	}

	/**
	 * Get the revision number of the latest version
	 * of this pathway
	 **/
	public function getLatestRevision() {
		return Title::newFromText($this->getIdentifier(), NS_PATHWAY)
			->getLatestRevID();
	}

	/**
	 * Set the active revision for this instance. The active
	 * revision is '0' by default, pointing to the most recent
	 * revision. Set another revision number to retrieve older
	 * versions of this pathway.
	 */
	public function setActiveRevision($revision, $updateCache = false) {
		if($this->revision != $revision) {
			$this->revision = $revision;
			$this->pwData = null; //Invalidate loaded pathway data
			//Make sure the cache for this revision is up to date
			if($updateCache) $this->updateCache();
		}
	}

	/**
	 * Get the PathwayData object that contains the
	 * data stored in the GPML
	 */
	public function getPathwayData() {
		//Return null when deleted and not querying an older revision
		if($this->isDeleted(false, $this->getActiveRevision())) {
			return null;
		}
		//Only create when asked for (performance)
		if(!$this->pwData) {
			$this->pwData = new PathwayData($this);
		}
		return $this->pwData;
	}

	public function getPermissionManager() {
		if(!$this->permissionMgr) {
			$this->permissionMgr = new PermissionManager(
				$this->getTitleObject()->getArticleId());
		}
		return $this->permissionMgr;
	}

	/**
	 * Make this pathway private for the given user. This
	 * will reset all existing permissions.
	 */
	public function makePrivate($user) {
		wfProfileIn( __METHOD__ );
		$title = $this->getTitleObject();
		if($title->userCan(PermissionManager::$ACTION_MANAGE)) {
			$mgr = $this->getPermissionManager();
			$pp = new PagePermissions($title->getArticleId());
			$pp->addReadWrite($user->getId());
			$pp->addManage($user->getId());
			$pp = PermissionManager::resetExpires($pp);
			$mgr->setPermissions($pp);
		} else {
			wfProfileOut( __METHOD__ );
			throw new Exception("Current user is not allowed to manage ".
				"permissions for " . $this->getIdentifier());
		}
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Find out if this pathway is public (no additional permissions set).
	 */
	public function isPublic() {
		$mgr = $this->getPermissionManager();
		return $mgr->getPermissions() ? false : true;
	}

	/**
	 * Find out if the current user has permissions to view this pathway
	 */
	public function isReadable() {
		throw new MWException
			("use ->getTitleObject()->userCan('read'); instead");
	}

	/**
	 * Get the MetaDataCache object for this pathway
	 */
	private function getMetaDataCache() {
		if(!$this->metaDataCache &&  $this->exists()) {
			$this->metaDataCache = new MetaDataCache($this);
		}
		return $this->metaDataCache;
	}

	/**
	 * Forces a reload of the cached metadata on the next
	 * time a cached value is queried.
	 */
	private function invalidateMetaDataCache() {
		$this->metaDataCache = null;
	}

	/**
	   Convert a species code to a species name (e.g. Hs to Human)
	*/
	public static function speciesFromCode($code) {
		$org = Organism::getByCode($code);
		if($org) {
			return $org->getLatinName();
		}
	}

	public static function getAllPathways($species = false) {
		wfProfileIn( __METHOD__ );
		//Check if species is supported
		if($species) {
			if(!in_array($species, self::getAvailableSpecies())) {
				wfProfileOut( __METHOD__ );
				throw new Exception("Species '$species' is not supported.");
			}
		}
		$allPathways = array();
		$dbr =& wfGetDB(DB_SLAVE);
		$ns = NS_PATHWAY;
		$query = "SELECT page_title FROM page
				WHERE page_namespace = $ns AND page_is_redirect = 0";
		$res = $dbr->query($query, __METHOD__);
		while( $row = $dbr->fetchRow( $res )) {
			try {
				$pathway = Pathway::newFromTitle($row[0]);
				//Skip deleted pathways
				if($pathway->isDeleted()) continue;
				//Filter by organism
				if($species && $pathway->getSpecies() != $species) continue;
				//Skip hidden pathways
				if(!$pathway->getTitleObject()->userCan('read')) continue;
				$allPathways[$pathway->getIdentifier()] = $pathway;
			} catch(Exception $e) {
				wfDebug(__METHOD__ . ": Unable to add pathway to list: $e");
			}
		}
		$dbr->freeResult($res);
		ksort($allPathways);
		wfProfileOut( __METHOD__ );
		return $allPathways;
	}

	/**
	   Convert a species name to species code (e.g. Human to Hs)
	*/
	public static function codeFromSpecies($species) {
		$org = Organism::getByLatinName($species);
		if($org) {
			return $org->getCode();
		}
	}

	/**
	 * Create a new Pathway from the given title
	 *
	 * @param Title The full title of the pathway page
	 * (e.g. Pathway:Human:Apoptosis), or the MediaWiki Title object
	 *
	*/
	public static function newFromTitle($title, $checkCache = false) {
		//Remove url and namespace from title
		if ( !( $title instanceof Title ) ) {
			$titleId = self::parseIdentifier( $title );
			if ( $titleId === false ) {
				return false;
			}
			return new Pathway( $titleId, $checkCache );
		} else {
			return new Pathway( $title, $checkCache );
		}
	}

	/**
	 * Create a new Pathway based on a filename
	 *
	 * @param Title The full title of the pathway file
	 * (e.g. Hs_Apoptosis.gpml), or the MediaWiki Title object
	*/
	public static function newFromFileTitle( $title, $checkCache = false ) {
		if ($title instanceof Title) {
			$title = $title->getText();
		}
		//"Hs_testpathway.ext"
		if ( ereg("^([A-Z][a-z])_(.+)\.[A-Za-z]{3,4}$", $title, $regs) ) {
			$species = Pathway::speciesFromCode($regs[1]);
			$name = $regs[2];
		}
		if ( !$name || !$species ) {
			throw new Exception("Couldn't parse file title: $title");
		}
		return self::newFromTitle("$species:$name", $checkCache);
	}

	/**
	 * Get all pathways with the given name and species (optional).

	 * @param $name The name to match
	 * @param $species The species to match, leave blank to include
	 * all species
	 * @returns A list of pathway objects for the pathways that match
	 * the name/species
	 */
	public static function getPathwaysByName($name, $species = '') {
		wfProfileIn( __METHOD__ );
		$pages =
			MetaDataCache::getPagesByCache(MetaDataCache::$FIELD_NAME, $name);
		$pathways = array();
		foreach ($pages as $pageId) {
			$pathway = Pathway::newFromTitle(Title::newFromId( $pageId ));
			if (!$species || $pathway->getSpecies() == $species) {
				if (!$pathway->isDeleted()) { //Don't add deleted pathways
					$pathways[] = $pathway;
				}
			}
		}
		wfProfileOut( __METHOD__ );
		return $pathways;
	}

	/**
	 * Get the full url to the pathway page
	 */
	public function getFullURL() {
		return $this->getTitleObject()->getFullURL();
	}

	/**
	 * Get the MediaWiki Title object for the pathway page
	 */
	public function getTitleObject() {
		return $this->pwPageTitle;
	}

	/**
	 * Returns a list of species
	 */
	public static function getAvailableSpecies() {
		return array_keys(Organism::listOrganisms());
	}

	/**
	 * @deprecated this won't work with stable IDs
	 */
	private static function nameFromTitle($title) {
		$parts = explode(':', $title);

		if (count($parts) < 2) {
			throw new Exception("Invalid pathway article title: $title");
		}
		return array_pop($parts);
	}

	/**
	 * @deprecated this won't work with stable IDs
	 */
	private static function speciesFromTitle($title) {
		$parts = explode(':', $title);

		if(count($parts) < 2) {
			throw new Exception("Invalid pathway article title: $title");
		}
		$species = array_slice($parts, -2, 1);
		$species = array_pop($species);
		$species = str_replace('_', ' ', $species);
		return $species;
	}

	/**
	 * Get or set the pathway name (without namespace or species prefix)
	 * @param name changes the name to this value if not null
	 * @return the name of the pathway
	 * @deprecated use #getName instead! Name can only be set by
	 * editing the GPML.
	 */
	public function name($name = NULL) {
		if($name) {
			throw new Exception("Species can only be set by editing GPML");
		}
		return $this->getName();
	}

	/**
	 * Temporary function used during the transition
	 * to stable identifiers. This method does not return
	 * the cached name, but the name as it is in the pathway page title
	 */
	public function getNameFromTitle() {
		return self::nameFromTitle($this->getTitleObject());
	}

	/**
	 * Temporary function used during the transition
	 * to stable identifiers. This method does not return
	 * the cached species, but the species as it is in the pathway page title
	 */
	public function getSpeciesFromTitle() {
		return self::speciesFromTitle($this->getTitleObject());
	}

	/**
	 * Get the pathway name (without namespace or species prefix).
	 * This method will not load the GPML, but use the
	 * metadata cache for performance.
	 */
	public function getName($textForm = true) {
		if($this->exists()) { //Only use cache if this pathway exists
			return $this->getMetaDataCache()
				->getValue(MetaDataCache::$FIELD_NAME);
		} else {
			return "";
		}
	}

	/**
	 * Get the species for this pathway.
	 * This method will not load the GPML, but use the
	 * metadata cache for performance.
	 */
	public function getSpecies() {
		if($this->exists()) { //Only use cache if this pathway exists
			return $this->getMetaDataCache()
				->getValue(MetaDataCache::$FIELD_ORGANISM)->getText();
		} else {
			return "";
		}
	}

	/**
	 * Get the species for this pathway.
	 * This method will not load the GPML, but use the
	 * metadata cache for performance.
	 */
	public function getSpeciesAbbr() {
		if($this->exists()) { //Only use cache if this pathway exists
			$species = $this->getMetaDataCache()
				->getValue(MetaDataCache::$FIELD_ORGANISM);
			$m = array();
			preg_match( "/(\S)\S*\s*(\S)/", $species, $m );
			if( count($m) === 3 ) {
				return $m[1] . $m[2];
			} else {
				return "";
			}
		} else {
			return "";
		}
	}

	/**
	 * Get the unique xrefs in this pathway.
	 * This method will not load the GPML, but use the
	 * metadata cache for performance.
	 */
	public function getUniqueXrefs() {
		wfProfileIn( __METHOD__ );
		if($this->exists()) { //Only use cache if this pathway exists
			$xrefStr = $this->getMetaDataCache()
				->getValue(MetaDataCache::$FIELD_XREFS);
			$xrefStr = explode(MetaDataCache::$XREF_SEP, $xrefStr);
			$xrefs = array();
			foreach($xrefStr as $s) $xrefs[$s] = Xref::fromText($s);
			wfProfileOut( __METHOD__ );
			return $xrefs;
		} else {
			wfProfileOut( __METHOD__ );
			return array();
		}
	}

	/**
	 * Get or set the pathway species
	 * @param species changes the species to this value if not null
	 * @return the species of the pathway
	 * @deprecated use #getSpecies instead! Species can only be set by
	 * editing the GPML.
	 */
	public function species($species = NULL) {
		if($species) {
			throw new Exception("Species can only be set by editing GPML");
		}
		return $this->getSpecies();
	}

	/**
	 * Get the species code (abbrevated species name, e.g. Hs for Human)
	 */
	public function getSpeciesCode() {
		$org = Organism::getByLatinName($this->getSpecies());
		if($org) {
			return $org->getCode();
		}
	}

	/**
	 * Check if this pathway exists in the database
	 * @return true if the pathway exists, false if not
	 */
	public function exists() {
		$title = $this->getTitleObject();
		return !is_null($title) && $title->exists();
	}

	/**
	 * Find out if there exists a pathway that has a
	 * case insensitive match with the name of this
	 * pathway object. This method is necessary to perform
	 * a case insensitive search on pathways, since MediaWiki
	 * titles are case sensitive.
	 * @return A title object representing the page title of
	 * the found pathway in the proper case, or null if no
	 * matching pathway was found
	 */
	public function findCaseInsensitive() {
		wfProfileIn( __METHOD__ );
		$title = strtolower($this->getTitleObject()->getDbKey());
		$dbr =& wfGetDB(DB_SLAVE);
		$ns = NS_PATHWAY;
		$query = "SELECT page_id FROM page
					WHERE page_namespace = $ns
					AND page_is_redirect = 0
					AND LOWER(page_title) = '$title'";
		$res = $dbr->query($query, __METHOD__);
		$title = null;
		if($res->numRows() > 0) {
			$row = $dbr->fetchRow($res);
			$title = Title::newFromID($row[0]);
		}
		$dbr->freeResult($res);
		wfProfileOut( __METHOD__ );
		return $title;
	}

	/**
	 * Get the GPML code for this pathway (the active revision will be
	 * used, see Pathway::getActiveRevision)
	 */
	public function getGpml() {
		if ( $this->getTitleObject()->userCan('read') ) {
			$gpmlTitle = $this->getTitleObject();
			$gpmlRef = Revision::newFromTitle($gpmlTitle, $this->revision);
			return $gpmlRef == null ? null : $gpmlRef->getContent();
		} else {
			# permission denied -- use internal MW function
			throw new MWException("Fill in this permission denied bit");
		}
	}

	/**
	 * Check if the given file type is valid (a pathway can
	 * be converted to this file type)
	 */
	public static function isValidFileType($fileType) {
		return in_array($fileType, array_keys(self::$fileTypes));
	}


	private function getPath( $fileType ) {
		throw new MWException( "don't use getPath" );
	}

	/**
	 * Get the filename of a cached file following the naming conventions
	 * @param the file type to get the name for (one of the FILETYPE_* constants)
	 */
	public function getFileObj( $fileType ) {
		$rev_suffix = "";
		if($this->revision) {
			$rev_suffix = "_" . $this->revision;
		}
		$filename = "{$this->getIdentifier()}{$rev_suffix}";
		return PathwayCache::newFromKey( $filename, $fileType );
	}

	/**
	 * Gets the url that points to the the cached file
	 * @param the file type to get the name for
	 * (one of the FILETYPE_* constants)
	 * @param whether to update the cache (if needed) or not
	 */
	public function getFileURL($fileType, $updateCache = true) {
		if($updateCache) {
			$this->updateCache($fileType);
		}
		global $IP, $wgServer, $wgUploadPath;
		$stem = str_replace( "$IP/images", '', $this->getFileLocation( $fileType ) );
		return $wgServer . $wgUploadPath . $stem;
	}

	/**
	 * Register a file type that can be exported to
	 * (needs to be supported by the GPML exporter
	 */
	public static function registerFileType($fileType) {
		self::$fileTypes[$fileType] = $fileType;
	}

	/**
	 * Creates a MediaWiki title object that represents the article in the
	 * NS_IMAGE namespace for cached file of given file type.
	 * There is no guarantee that an article exists for each filetype.
	 * Currently articles exist for FILETYPE_IMG
	 * (.svg articles in the NS_IMAGE namespace)
	 */
	public function getFileTitle($fileType) {
		//Append revision number if it's not the most recent
		$rev_stuffix = '';
		if($this->revision) {
			$rev_stuffix = "_" . $this->revision;
		}
		$fileName = "{$this->getIdentifier()}{$rev_stuffix}." . $fileType;
		$title = Title::newFromText( $fileName, NS_IMAGE );
		if(!$title || !$fileType ) {
			throw new Exception("Invalid file title for pathway " . $fileName);
		}
		return $title;
	}

	/**
	 * Get the title object for the image page.
	 * Equivalent to <code>getFileTitle(FILETYPE_IMG)</code>
	 */
	public function getImageTitle() {
		return $this->getFileTitle(FILETYPE_IMG);
	}

	/**
	 * Get the prefix part of the filename, with all illegal characters
	 * filtered out (e.g. Hs_Apoptosis for Human:Apoptosis)
	 */
	public function getFilePrefix() {
		$prefix = $this->getSpeciesCode() . "_" . $this->getName();
		/*
		 * Filter out illegal characters, and try to make a legible name
		 * out of it. We'll strip some silently that Title would die on.
		 */
		$filtered = preg_replace ( "/[^".Title::legalChars()."]|:/",
			'-', $prefix );
		/*
		 * Filter out additional illegal character that shouldn't be
		 * in a file name
		 */
		$filtered = preg_replace( "/[\/\?\<\>\\\:\*\|\[\]]/", '-', $prefix);

		$title = Title::newFromText( $filtered, NS_IMAGE );
		if(!$title) {
			throw new Exception("Invalid file title for pathway " + $fileName);
		}
		return $title->getDBKey();
	}

	/**
	 * Get first revision for current title
	 * @FIXME this should use the Revision::fetchRevision() method,
	 * not fetchAllRevisions() which never worked anyway (and was removed.)
	 */
	public function getFirstRevision() {
		if($this->exists() && !$this->firstRevision) {
			$revs = LocalHooks::fetchAllRevisions($this->getTitleObject());
			$revs->seek($revs->numRows() - 1);
			$row = $revs->fetchRow();
			$this->firstRevision = Revision::newFromId($row['rev_id']);
		}
		return $this->firstRevision;
	}

	/**
	 * Get the revision id for the first revision after the given one.
	 */
	public function getFirstRevisionAfterRev( $rev ) {
		$r = Revision::newFromId( $rev );
		return $r->getNext();
	}

	/**
	 * Get revision id for the last revision prior to specified datae.
	 * This is useful for generating statistics over the history of
	 * the archive.
	 */
	public function getLastRevisionPriorToDate($timestamp) {
		throw new Exception( "getLastRevisionPriorToDate() doesn't do what ".
			"you think. It uses the old ".
			"fetchAllRevisions which was broken and has been removed.  ".
			"See https://bugzilla.wikimedia.org/18821" );
		/* This code should be more efficient than what was here, but
		 * it is untested.  Leaving it here because I couldn't find
		 * any use of this function. */
		$rev = Revision::loadFromTimestamp( wfGetDB( DB_SLAVE ),
			$this->getTitleObject(), $timestamp );
		return $rev->getPrevious();

		$revs =  LocalHooks::fetchAllRevisions( $this->getTitleObject() );
		foreach($revs as $eachRev){
			$revTime = $eachRev->rev_timestamp;
			print "$revTime\n";
			if ($revTime < $timestamp){
				return $eachRev;
			}
		}
		return NULL;
	}

	/**
	 * Creates a new pathway on the wiki. A unique identifier will be generated
	 * for the pathway.
	 * @param $gpmlData The GPML code for the pathway
	 * @return The Pathway object for the created pathway
	 */
	public static function createNewPathway($gpmlData,
		$description = "New pathway") {
		$id = self::generateUniqueId();
		$pathway = new Pathway($id, false);
		if($pathway->exists()) {
			throw new Exception("Unable to generate unique id, ".
				"$id already exists");
		}
		$pathway->updatePathway($gpmlData, $description);
		$pathway = new Pathway($id);
		return $pathway;
	}

	private static function checkGpmlSpecies($gpml) {
		$gpml = utf8_encode($gpml);
		//preg_match can fail on very long strings, so first try to find the
		//<Pathway ...> part with strpos
		$startTag = strpos($gpml, "<Pathway");
		if(!$startTag)
			throw new Exception("Unable to find start of '<Pathway ...>' tag.");
		$endTag = strpos($gpml, ">", $startTag);
		if(!$endTag)
			throw new Exception("Unable to find end of '<Pathway ...>' tag.");

		if( preg_match("/<Pathway.*Organism=\"(.*?)\"/us",
				substr($gpml, $startTag, $endTag - $startTag), $match)) {
			$species = $match[1];
			if(!in_array($species, self::getAvailableSpecies())) {
				throw new Exception("The organism '$species' for the pathway ".
					"is not supported.");
			}
		} else {
			throw new Exception("The pathway doesn't have an ".
				"organism attribute.");
		}
	}

	private static function generateUniqueId() {
		wfProfileIn( __METHOD__ );
		//Get the highest identifier
		$dbr = wfGetDB( DB_SLAVE );
		$ns = NS_PATHWAY;
		$prefix = self::$idPrefix;
		$query = "SELECT page_title FROM page " .
			"WHERE page_namespace =$ns " .
			"AND page_is_redirect =0 " .
			"AND page_title LIKE '{$prefix}_%' " .
			"ORDER BY length(page_title) DESC, page_title DESC " .
			"LIMIT 0 , 1 ";
		$res = $dbr->query($query);
		$row = $dbr->fetchObject( $res );
		if($row) {
			$lastid = $row->page_title;
		} else {
			$lastid = Pathway::$idPrefix . "0";
		}
		$dbr->freeResult( $res );

		$lastidNum = substr($lastid, 2);
		$newidNum = $lastidNum + 1;
		$newid = Pathway::$idPrefix . $newidNum;
		wfProfileOut( __METHOD__ );
		return $newid;
	}

	/**
	 * Update the pathway with the given GPML code
	 * \param gpmlData The GPML code that contains the updated pathway data
	 * \param description A description of the changes
	 */
	public function updatePathway($gpmlData, $description) {
		global $wgUser;

		//First validate the gpml
		if($error = self::validateGpml($gpmlData)) {
			throw new Exception($error);
		}

		$gpmlTitle = $this->getTitleObject();

		//Check permissions
		if(is_null($wgUser) || !$wgUser->isLoggedIn()) {
			throw new Exception("User is not logged in");
		}
		if($wgUser->isBlocked()) {
			throw new Exception("User is blocked");
		}
		if(!$gpmlTitle->userCan( 'edit' )) {
			throw new Exception("User has wrong permissions to edit ".
				"the pathway");
		}
		if(wfReadOnly()) {
			throw new Exception("Database is read-only");
		}

		//Force update from the newest version
		$gpmlArticle = new Article($gpmlTitle, 0);
		if(!$gpmlTitle->exists()) {
			//This is a new pathway, add the author to the watch list
			$gpmlArticle->doWatch();
		}

		$succ = true;
		$succ =  $gpmlArticle->doEdit($gpmlData, $description);
		if($succ) {
			//Force reload of data
			$this->setActiveRevision($this->getLatestRevision());
			//Update metadata cache
			$this->invalidateMetaDataCache();
		} else {
			throw new Exception("Unable to save GPML, are you logged in?");
		}
		return $succ;
	}

	/**
	 * Parse a mediawiki page that contains a pathway list.
	 * Assumes one pathway per line, invalid lines will be ignored.
	 */
	public static function parsePathwayListPage($listPage) {
		wfProfileIn( __METHOD__ );
		$listRev = Revision::newFromTitle(Title::newFromText($listPage), 0);
		if($listRev != null) {
			$lines = explode("\n", $listRev->getText());
		} else {
			$lines = array();
		}
		$pathwayList = array();

		//Try to parse a pathway from each line
		foreach($lines as $title) {
			//Regex to fetch title from "* [[title|...]]"
			// \*\ *\[\[(.*)\]\]
			$title = preg_replace('/\*\ *\[\[(.*)\]\]/', '$1', $title);
			$title = Title::newFromText($title);
			if($title != null) {
				try {
					$article = new Article($title);
					//Follow redirects
					if($article->isRedirect()) {
						$redirect = $article->fetchContent();
						$title = Title::newFromRedirect($redirect);
					}
					//If pathway creation works and the pathway
					//exists, add to array
					$pathway = Pathway::newFromTitle($title);
					if(!is_null($pathway) && $pathway->exists()) {
						$pathwayList[] = $pathway;
					}
				} catch(Exception $e) {
					//Ignore the pathway
				}
			}
		}
		wfProfileOut( __METHOD__ );
		return $pathwayList;
	}

	static $gpmlSchemas = array(
		"http://genmapp.org/GPML/2007" => "GPML2007.xsd",
		"http://genmapp.org/GPML/2008a" => "GPML2008a.xsd",
		"http://genmapp.org/GPML/2010a" => "GPML2010a.xsd",
		"http://pathvisio.org/GPML/2013a" => "GPML2013a.xsd"
	);

	/**
	 * Validates the GPML code and returns the error if it's invalid
	 * @return <code>null</code> if the GPML is valid, the error if
	 * it's invalid
	 **/
	static function validateGpml($gpml) {
		wfProfileIn( __METHOD__ );
		$return = null;
		//First, check if species is supported
		try {
			self::checkGpmlSpecies($gpml);
		} catch(Exception $e) {
			$return = $e->getMessage();
		}
		//Second, validate GPML to schema
		$xml = new DOMDocument();
		$parsed = $xml->loadXML($gpml);
		if(!$parsed) {
			wfProfileOut( __METHOD__ );
			return "Error: no valid XML provided\n$gpml";
		}

		if( !method_exists( $xml->firstChild, "getAttribute" ) ) {
			wfProfileOut( __METHOD__ );
			return "Not valid GPML!";
		}

		$ns = $xml->firstChild->getAttribute('xmlns');
		$schema = self::$gpmlSchemas[$ns];
		if(!$schema) {
			wfProfileOut( __METHOD__ );
			return "Error: no xsd found for $ns\n$gpml";
		}

		if(!$xml->schemaValidate(WPI_SCRIPT_PATH . "/bin/$schema")) {
			$error = libxml_get_last_error();
			$return  = $gpml[$error->line - 1] . "\n";
			$return .= str_repeat('-', $error->column) . "^\n";

			switch ($error->level) {
				case LIBXML_ERR_WARNING:
					$return .= "Warning $error->code: ";
					break;
				case LIBXML_ERR_ERROR:
					$return .= "Error $error->code: ";
					break;
				case LIBXML_ERR_FATAL:
					$return .= "Fatal Error $error->code: ";
					break;
			}

			$return .= trim($error->message) .
				"\n  Line: $error->line" .
				"\n  Column: $error->column";

			if ($error->file) {
				$return .= "\n  File: $error->file";
			}
		}
		wfProfileOut( __METHOD__ );
		return $return;
	}

	/**
	 * Revert this pathway to an old revision
	 * \param oldId The id of the old revision to revert the pathway to
	 */
	public function revert($oldId) {
		global $wgUser, $wgLang;
		$rev = Revision::newFromId($oldId);
		$gpml = $rev->getText();
		if(self::isDeletedMark($gpml)) {
			throw new Exception("You are trying to revert to a deleted version".
				" of the pathway. Please choose another version to revert to.");
		}
		if($gpml) {
			$usr = RequestContext::getMain()->getSkin()
				->userLink($wgUser->getId(), $wgUser->getName());
			$date = $wgLang->timeanddate( $rev->getTimestamp(), true );
			$this->updatePathway($gpml, "Reverted to version '$date' by $usr");
		} else {
			throw new Exception("Unable to get gpml content");
		}
	}

	/**
	 * Check whether this pathway is marked as deleted.
	 * @param $useCache Set to false to use actual page text to
	 * check if the pathway is deleted. If true or not specified,
	 * the cache will be used.
	 * @param $revision Set to true if you want to check if the
	 * given revision is a deletion mark (not the newest revision).
	 **/
	public function isDeleted($useCache = true, $revision = '') {
		wfProfileIn( __METHOD__ );
		if(!$this->exists()) {
			wfProfileOut( __METHOD__ );
			return false;
		}
		if($useCache && !$revision) {
			$deprev = $this->getMetaDataCache()
				->getValue(MetaDataCache::$FIELD_DELETED);
			if($deprev) {
				$rev = $this->getActiveRevision();
				if($rev == 0 || $rev == $deprev->getPageRevision())
					return true;
			}

			wfProfileOut( __METHOD__ );
			return false;
		} else {
			if(!$revision) $revision = $this->getLatestRevision();
			$text = Revision::newFromId($revision)->getText();
			wfProfileOut( __METHOD__ );
			return self::isDeletedMark($text);
		}
	}

	/**
	 * Check if the given text marks the pathway as deleted.
	 */
	public static function isDeletedMark($text) {
		return substr($text, 0, 9) == "{{deleted";
	}

	/**
	 * Delete this pathway. The pathway will not really deleted,
	 * instead, the pathway page will be marked as deleted by replacing the GPML
	 * with a deletion mark.
	 */
	public function delete($reason = "") {
		global $wgUser;
		if($this->isDeleted(false)) return; //Already deleted, nothing to do

		//Check permissions
		if(is_null($wgUser) || !$wgUser->isLoggedIn()) {
			throw new Exception("User is not logged in");
		}
		if($wgUser->isBlocked()) {
			throw new Exception("User is blocked");
		}
		if(!$this->getTitleObject()->userCan('delete')) {
			throw new Exception("User doesn't have permissions to mark ".
				"this pathway as deleted");
		}
		if(wfReadOnly()) {
			throw new Exception("Database is read-only");
		}

		$article = new Article($this->getTitleObject(), 0);
		global $wpiDisableValidation; //Temporarily disable GPML validation hook
		$wpiDisableValidation = true;

		$succ =  $article->doEdit("{{deleted|$reason}}", self::$deletePrefix .
			$reason);
		if($succ) {
			//Update metadata cache
			$this->invalidateMetaDataCache();

			//Clean up file cache
			$this->clearCache(null);
		} else {
			throw new Exception("Unable to mark pathway deleted, are you ".
				"logged in?");
		}
	}

	private function deleteImagePage($reason) {
		$title = $this->getFileTitle(FILETYPE_IMG);
		Pathway::deleteArticle($title, $reason);
		$img = new Image($title);
		$img->delete($reason);
	}

	/**
	 * Delete a MediaWiki article
	 */
	public static function deleteArticle($title, $reason='not specified') {
		global $wgUser;

		$article = new Article($title);

		if (wfRunHooks('ArticleDelete', array(&$this, &$wgUser, &$reason))) {
			$article->doDeleteArticle($reason);
			wfRunHooks('ArticleDeleteComplete',
				array(&$this, &$wgUser, $reason));
		}
	}

	/**
	 * Checks whether the cached files are up-to-data and updates them
	 * if neccesary
	 * @param fileType The file type to check the cache for (one of
	 * FILETYPE_* constants) or null to check all files
	 */
	public function updateCache($fileType = null) {
		wfProfileIn( __METHOD__ );
		wfDebug( "updateCache called for filetype $fileType\n" );
		//Make sure to update GPML cache first
		if( !$fileType == FILETYPE_GPML ) {
			$this->updateCache( FILETYPE_GPML );
		}

		if(!$fileType) { //Update all
			foreach( self::$fileTypes as $type ) {
				$this->updateCache( $type );
			}
			wfProfileOut( __METHOD__ );
			return;
		}
		$obj = $this->getFileObj( $fileType );
		if( !$obj->isCacheGood() ) {
			wfDebug( "\t->Updating cached file for $fileType\n" );
			switch( $fileType ) {
				case FILETYPE_PNG:
					$this->savePngCache( $obj );
					break;
				case FILETYPE_GPML:
					$this->saveGpmlCache( $obj );
					break;
				default:
					$this->saveConvertedCache( $fileType );
					break;
			}
		}
		wfProfileOut( __METHOD__ );
	}

	public function getImage() {
		wfProfileIn( __METHOD__ );
		throw new MWException("Replace repo!");
		$repo = RepoGroup::singleton()->getLocalRepo();
		$img = new LocalFile($this->getFileTitle(FILETYPE_IMG), $repo);
		$img->loadFromFile();

		if ( !$img->exists() ) { /* Avoid calling this unless we need to */
			$this->updateCache(FILETYPE_IMG);
			$img->loadFromFile();
		}

		wfProfileOut( __METHOD__ );
		return $img;
	}

	/**
	 * Clear all cached files
	 * @param fileType The file type to remove the cache for (one of
	 * FILETYPE_* constants) or null to remove all files
	 */
	public function clearCache($fileType = null) {
		if(!$fileType) { //Update all
			$this->clearCache(FILETYPE_PNG);
			$this->clearCache(FILETYPE_GPML);
			$this->clearCache(FILETYPE_IMG);
		} else {
			$file = $this->getFileLocation($fileType, false);
		}
	}

	//Check if the cached version of the GPML data derived file is out of date
	private function isOutOfDate($fileType) {
		wfProfileIn( __METHOD__ );
		wfDebug("isOutOfDate for $fileType\n");

		$gpmlTitle = $this->getTitleObject();
		$gpmlRev = Revision::newFromTitle($gpmlTitle);
		if($gpmlRev) {
			$gpmlDate = $gpmlRev->getTimestamp();
		} else {
			$gpmlDate = -1;
		}

		$path = $this->getPath( $fileType );
		$repo = RepoGroup::singleton()->getLocalRepo();

		if( $repo->fileExists( $path ) ) {
			$fmt = $repo->getFileTimestamp( $path );
			wfDebug("\tFile exists, cache: $fmt, gpml: $gpmlDate\n");
			wfProfileOut( __METHOD__ );
			return  $fmt < $gpmlDate;
		} else { //No cached version yet, so definitely out of date
			wfDebug("\tFile doesn't exist\n");
			wfProfileOut( __METHOD__ );
			return true;
		}
	}

	public function getGpmlModificationTime() {
		$gpmlTitle = $this->getTitleObject();
		$gpmlRev = Revision::newFromTitle($gpmlTitle);
		if($gpmlRev) {
			$gpmlDate = $gpmlRev->getTimestamp();
		} else {
			throw new Exception("No GPML page");
		}
		return $gpmlDate;
	}

	/**
	 * Save a cached version of a filetype to be converted
	 * from GPML
	 */
	private function saveConvertedCache($fileType) {
		wfProfileIn( __METHOD__ );
		/* Convert gpml to fileType */
		$conFile = $this->getFileLocation( $fileType, false );
		if( $this->getFileObj( $fileType )->isCacheGood() ) {
			wfProfileOut( __METHOD__ );
			wfDebug("not Saving");
			return $conFile;
		}
		$gpmlFile = $this->getFileLocation( FILETYPE_GPML );
		$outFile = basename( $gpmlFile, FILETYPE_GPML );

		if ( $conFile === null ) {
			$conFile = wfTempDir() . "/$outFile$fileType";
		}
		wfDebug( "Saving $gpmlFile to $fileType in $conFile\n" );
		$this->convert($conFile);
		wfProfileOut( __METHOD__ );
		return $conFile;
	}

	/**
	 * Convert the given GPML file to another
	 * file format. The file format will be determined by the
	 * output file extension.
	 */
	public function convert( $outFile ) {
		global $wgMaxShellMemory;
		wfProfileIn( __METHOD__ );
		$gpmlObj = $this->getFileObj( FILETYPE_GPML );
		$this->saveGpmlCache( $gpmlObj );
		$gpmlFile = $gpmlObj->getPath();
		$baseName = basename( $outFile );
		$final = wfTempDir() . "/$baseName";

		$basePath = WPI_SCRIPT_PATH;
		//Max script memory on java program in megabytes
		$maxMemoryM = intval($wgMaxShellMemory / 1024);
		$cmd = "java -Xmx{$maxMemoryM}M -jar $basePath/bin/pathvisio_core.jar ".
			"\"$gpmlFile\" \"{$final}\" 2>&1";
		wfDebug("CONVERTER: $cmd\n");
		$msg = wfJavaExec($cmd, $status);

		if($status != 0 ) {
			// Not needed anymore, since we now use a unique file name
			// for each revision, so it's guaranteed to update.
			wfDebug("Unable to convert to $outFile:\n<BR>Status:$status\n".
				"<BR>Message:$msg\n<BR>Command:$cmd<BR>");
			wfProfileOut( __METHOD__ );
			throw new Exception("Unable to convert to $outFile:\n<BR>Status:".
				"$status\n<BR>Message:$msg\n<BR>Command:$cmd<BR>");
		}
		wfDebug("moving into place $outFile\n");
		$realpath = realpath( $outFile );
		$dir = dirname( $outFile );
		if ( !$realpath && !file_exists( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		if( !is_writable( $dir ) ) {
			throw new MWException( "Directory ($dir) not writable!" );
		}
		if( !rename( $final, $outFile ) ) {
			wfProfileOut( __METHOD__ );
			throw new MWException( "Error while uploading from $final: " .
				$status->getHTML() );
		}
		wfDebug("moved into place $baseName\n");
		wfProfileOut( __METHOD__ );
		return true;
	}

	private function saveGpmlCache( $obj ) {
		wfProfileIn( __METHOD__ );
		$gpml = $this->getGpml();
		$time = $this->getGpmlModificationTime();

		//Only write cache if there is GPML
		if($gpml !== null && !$obj->isCacheGood( $time ) ) {
			$obj->saveText( $gpml );
		}
		wfProfileOut( __METHOD__ );
	}

	private function savePngCache( $obj ) {
		global $wgSVGConverters, $wgSVGConverter, $wgSVGConverterPath;
		wfProfileIn( __METHOD__ );

		$input = $this->getFileLocation(FILETYPE_IMG);
		$output = $this->getFileLocation(FILETYPE_PNG, false);

		$dir = dirname( $output );
		if( !file_exists( $dir ) ) {
			if( false === mkdir( $dir, 0777, true ) ) {
				throw new Exception( "Couldn't create $dir!" );
			}
		}

		$width = 1000;
		$retval = 0;
		if(isset($wgSVGConverters[$wgSVGConverter])) {
			$cmd = str_replace( //TODO: calculate proper height for rsvg
				array( '$path/', '$width', '$input', '$output' ),
				array( $wgSVGConverterPath ?
					wfEscapeShellArg( "$wgSVGConverterPath/" ) : "",
					intval( $width ),
					wfEscapeShellArg( $input ),
					wfEscapeShellArg( $output ) ),
				$wgSVGConverters[$wgSVGConverter] ) . " 2>&1";
			$err = wfShellExec( $cmd, $retval );
			if($retval != 0 || !file_exists($output)) {
				wfProfileOut( __METHOD__ );
				throw new Exception("Unable to convert to png: $err\n".
					"Command: $cmd");
			}
		} else {
			wfProfileOut( __METHOD__ );
			throw new Exception("Unable to convert to png, no SVG rasterizer".
				" found");
		}
		$ex = file_exists($output);
		wfDebug("PNG CACHE SAVED: $output, $ex;\n");
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Gets the path that points to the cached file
	 * @param the file type to get the name for (one of the FILETYPE_*
	 * constants)
	 * @param whether to update the cache (if needed) or
	 * not
	 */
	public function getFileLocation( $fileType, $updateCache = true ) {
		if( $updateCache ) {
			$this->updateCache( $fileType );
		}
		return $this->getFileObj( $fileType )->getPath();
	}

	static public function toggleAll( $tableId, $count, $show ) {
		if($count > $show) {
			$expand = "<b>View all...</b>";
			$collapse = "<b>View last " . $show . "...</b>";
			return "<table><tr><td width='51%'> <div onClick='".
				'doToggle("'.$tableId.'", this, "'.$collapse.'", "'.$expand.'")'.
				"' style='cursor:pointer;color:#0000FF'>".
				"$expand<td width='45%'></tr></table>";
		}
	}

}
