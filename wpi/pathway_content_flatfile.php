<?php
require_once("wpi.php");
require_once("Pathway.php");
require_once("PathwayData.php");

error_reporting(0);

//Get species restriction
$restrictSpecies = $_REQUEST['species'];

//Get output format
$outputFormat = $_REQUEST['output'];
if(!$outputFormat){
	$outputFormat = 'tab'; //set default
}


// Print header
//NOTE: Model Organism Databases = HUGO, MGI, RGD, ZFIN, FlyBase, WormBase, SGD
if ($outputFormat =='html'){
print "<html><table border=1 cellpadding=3>
<tr bgcolor=\"#CCCCFF\" font><td>Pathway Name</td><td>Organism</td><td>Gene Ontology</td><td>Url to WikiPathways</td><td>Last Changed</td><td>Last Revision</td><td>Author</td><td>Count</td>
	<td>Entrez Gene</td>
	<td>Ensembl</td>
	<td>SwissProt</td>
	<td>UniGene</td>
	<td>RefSeq</td>
	<td>MOD</td>
	<td>PubChem</td>
	<td>CAS</td>
	<td>ChEBI</td></tr>\n";
}
elseif ($outputFormat == 'excel'){
	//TODO (see Pear module for spreadsheet writer)
	print "Not available yet...\n";
}
else {
//set mime type and prompt for download
$filename = "wikipathways_data_" . date('Ymd') . ".tab";
header("Content-type: text/plain");
header("Content-Disposition: attachment; filename=\"$filename\"");
//print header
print "Pathway Name\tOrganism\tGene Ontology\tUrl to WikiPathways\tLast Changed\tLast Revision\tAuthor\tCount\tEntrez Gene\tEnsembl\tSwissProt\tUniGene\tRefSeq\tMOD\tPubChem\tCAS\tChEBI\n";
} 

$all_pathways = Pathway::getAllPathways();
foreach (array_keys($all_pathways) as $pathway) {

//Apply species restriction if necessary
$species = $all_pathways[$pathway]->species();
if($restrictSpecies) {
	if ($species != $restrictSpecies) continue; 	
}

//Exclude Sandbox pathway
$taggedIds = CurationTag::getPagesForTag('Curation:Tutorial');
$page_id = $all_pathways[$pathway]->getPageIdDB();
if (in_array($page_id, $taggedIds)) continue;
try {
$xml = $all_pathways[$pathway]->getPathwayData();  
$gpml = $xml->getGpml();
$modTime = $all_pathways[$pathway]->getGpmlModificationTime();
$url = $all_pathways[$pathway]->getFullUrl();
$author = $gpml["Author"];
$categories = "";
$lastRevision = $all_pathways[$pathway]->getLatestRevision();
$catArray = $xml->getWikiCategories();
foreach ($catArray as $cat){
	$categories .= $cat.",";
}
perlChop($categories);

// Print pathways data
if ($outputFormat =='html'){
$name = $all_pathways[$pathway]->getName();
print "<tr><td>".$name."</td><td>".$species."</td><td>".$categories."&nbsp</td><td>".$url."</td><td>".$modTime."</td><td>".$lastRevision."</td><td>".$author."&nbsp</td><td>";
}
elseif ($outputFormat == 'excel'){
	//TODO
}
else {
	print $name."\t".$species."\t".$categories."\t".$url."\t".$modTime."\t".$lastRevision."\t".$author."\t";
}

$count = 0;
$L_list = "";
$En_list = "";
$S_list = "";
$U_list = "";
$Q_list = "";
$MOD_list = "";
$Pc_list = "";
$CAS_list = "";
$Che_list = "";
$nodes = $xml->getUniqueElements('DataNode', 'TextLabel');
foreach ($nodes as $datanode){
	$xref = $datanode->Xref;
     if ($xref[ID] && $xref[ID] != '' && $xref[ID] != ' '){
	
	//Replace space characters with underscore to generate proper IDs (e.g., NP 067461 -> NP_067461)
	$xrefID = str_replace(' ','_',$xref[ID]);	

	if ($xref[Database] == 'Entrez Gene'){
		$L_list .= $xrefID.",";
	}
	else if ($xref[Database] == 'Ensembl'){
		$En_list .= $xrefID.",";
	}
        else if ($xref[Database] == 'SwissProt'){
                $S_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'UniGene'){
                $U_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'RefSeq'){
                $Q_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'HUGO'
		|| $xref[Database] == 'MGI'
		|| $xref[Database] == 'RGD'
                || $xref[Database] == 'ZFIN'
                || $xref[Database] == 'FlyBase'
                || $xref[Database] == 'WormBase'
                || $xref[Database] == 'SGD'
                ){
                $MOD_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'PubChem'){
                $Pc_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'CAS'){
                $CAS_list .= $xrefID.",";
        }
        else if ($xref[Database] == 'ChEBI'){
                $Che_list .= $xrefID.",";
        }
	$count++;
     }
}
perlChop($L_list);
perlChop($En_list);
perlChop($S_list);
perlChop($U_list);
perlChop($Q_list);
perlChop($MOD_list);
perlChop($Pc_list);
perlChop($CAS_list);
perlChop($Che_list);

//Print gene content data
if ($outputFormat =='html'){
	//append with space character toprovide for empty cells in html table 
	print $count
	."<td>".$L_list."&nbsp"
	."</td><td>".$En_list."&nbsp"
        ."</td><td>".$S_list."&nbsp"
        ."</td><td>".$U_list."&nbsp"
        ."</td><td>".$Q_list."&nbsp"
        ."</td><td>".$MOD_list."&nbsp"
        ."</td><td>".$Pc_list."&nbsp"
        ."</td><td>".$CAS_list."&nbsp"
        ."</td><td>".$Che_list."&nbsp"
        ."</td></tr>";
}
elseif ($outputFormat == 'excel'){
	//TODO
}
else {
	print $count
	."\t".$L_list
	."\t".$En_list
        ."\t".$S_list
        ."\t".$U_list
        ."\t".$Q_list
        ."\t".$MOD_list
        ."\t".$Pc_list
        ."\t".$CAS_list
        ."\t".$Che_list
        ."\n";
}

} catch (Exception $e)
{
 // we can safely ignore exceptions
 // erroneous pathways simply won't get processed
}

} // end foreach pathway

//Print footer
if ($outputFormat =='html'){
print "</table></html>";
}
elseif ($outputFormat == 'excel'){
	//TODO
}
else {
}

function perlChop(&$string){
        $endchar = substr("$string", strlen("$string") - 1, 1);
 	$string = substr("$string", 0, -1); 
	return $endchar;
 }

?>
