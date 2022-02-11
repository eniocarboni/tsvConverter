<?php

# Usage:
# php convert.php sheetFilename filesFolderName

/* 
 * Settings 
 * ---------
 */

$fileName = '';
$files = "files";
$onlyValidate = 0;
$debug = 0;

// Get arguments from command line
# $argv[1] fileName
if (isset($argv[1])) {
	$fileName = $argv[1];
}
# $argv[2] files folder
if (isset($argv[2])) {
	$files = $argv[2];
}
# $argv[3] output xml
if (isset($argv[3]) and ! empty($argv[3])) {
	$outfile = $argv[3];
}
else {
	$outfile = 'php://stdout';
}
# $argv[4] validate parameter
if (isset($argv[4]) && $argv[4] == '-v') {
	$onlyValidate = 1;
}
if (isset($argv[4]) && $argv[4] == '-d') {
	$debug = 1;
}

/** 
 * $split_output_by_issue_date_published: 
 * if 1 and $outfile !='php://stdout' split output xml in several file based on IssueDatepublished
 */

$split_output_by_issue_date_published=0;

// The default locale. For alternative locales use language field. For additional locales use locale:fieldName.
$defaultLocale = 'en_US';

// The uploader account name
$uploader = "admin";

// Default author name. If no author is given for an article, this name is used instead.
$defaultAuthor['givenname'] = "Editorial Board";

// Location of full text files
$filesFolder = dirname(__FILE__) . "/". $files ."/";

// Possible locales
$locales = array(
				'en' => 'en_US',
				'fi' => 'fi_FI',
				'sv' => 'sv_SE',
				'de' => 'de_DE',
				'ge' => 'de_DE',
				'ru' => 'ru_RU',
				'fr' => 'fr_FR',
				'no' => 'nb_NO',
				'da' => 'da_DK',
				'es' => 'es_ES',
				'it' => 'it_IT',
			);
/**
 * $copy_if_language_does_not_exist:
 * If a multilingual metadata is not present in the .xlsx file in one of these languages, 
 * it must be added identical to the default language.
 */
//$copy_if_language_does_not_exist= array('it','en');
$copy_if_language_does_not_exist= array();

// PHPExcel settings
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
date_default_timezone_set('Europe/Helsinki');
define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require 'config.php';

/* 
 * Check that a file and a folder exists
 * ------------------------------------
 */
if (!file_exists($fileName)) {
	echo '<error>' . date('H:i:s') . " ERROR: given file '$fileName' does not exist" . EOL .'</error>';
	die();
}

if (!file_exists($filesFolder)) {
	echo '<error>' . date('H:i:s') . " ERROR: given folder '$filesFolder' does not exist" . EOL .'</error>';
	die();
}

/* 
 * Load Excel data to an array
 * ------------------------------------
 */
disp("Creating a new PHPExcel object");

$objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fileName);
$objReader->setReadDataOnly(false);
$objPhpSpreadsheet = $objReader->load($fileName);
$sheet = $objPhpSpreadsheet->setActiveSheetIndex(0);

disp("Creating an array");

$articles = createArray($sheet);
$maxAuthors = countMaxAuthors($sheet);
$maxFiles = countMaxFiles($sheet);

/* 
 * Data validation   
 * -----------
 */

disp("Validating data");

$errors = validateArticles($articles);
if ($errors != ""){
	echo '<error>' .$errors, EOL . '</error>';
	die();	
}

# If only validation is selected, exit
if ($onlyValidate == 1){
	disp("Validation complete");
	die();
}


/* 
 * Prepare data for output
 * ----------------------------------------
 */

disp("Preparing data for output");

# Save section data
foreach ($articles as $article){
	$sections[$article['issueDatepublished']][$article['sectionAbbrev']] = $article['sectionTitle'];
}


/* 
 * Create XML  
 * --------------------
 */

disp("Starting XML output");
$currentIssueDatepublished = null;	
$currentYear = null;
$fileId = 1;
$authorId = 1;
$submissionId = 1;

if (! ($split_output_by_issue_date_published == 1 && $outfile != 'php://stdout') ) {
	$xmlfile = fopen ($outfile,'w');
	fwrite ($xmlfile,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n");
	fwrite ($xmlfile,"<issues xmlns=\"http://pkp.sfu.ca\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n");
}
foreach ($articles as $key => $article){
	
	# Issue :: if issueDatepublished has changed, start a new issue
	if ($currentIssueDatepublished != $article['issueDatepublished']){
		
		$newYear = date('Y', strtotime($article['issueDatepublished']));

		# close old issue if one exists
		if ($currentIssueDatepublished != null){
			fwrite ($xmlfile,"\t\t</articles>\r\n");
			fwrite ($xmlfile,"\t</issue>\r\n\r\n");
		}
		
		
		# Start a new XML file if year changes and $split_output_by_issue_date_published == 1 and not output on STDOUT
		if ($newYear != $currentYear && $split_output_by_issue_date_published == 1 && $outfile != 'php://stdout') {

			if ($currentYear != null){
				disp("Closing XML file");
				fwrite ($xmlfile,"</issues>\r\n\r\n");
			}
			
			disp("Creating a new XML file ". $newYear . "-" . $outfile);
			
			$xmlfile = fopen ($newYear."-".$outfile,'w');
			fwrite ($xmlfile,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n");
			fwrite ($xmlfile,"<issues xmlns=\"http://pkp.sfu.ca\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n");
		}
		
		fwrite ($xmlfile,"\t<issue xmlns=\"http://pkp.sfu.ca\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" published=\"1\" current=\"0\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n\r\n");
		
		disp("Adding issue with publishing date ".$article['issueDatepublished']);

		# Issue description
		if (!empty($article['issueDescription']))
			fwrite ($xmlfile,"\t\t<description locale=\"".$defaultLocale."\"><![CDATA[".$article['issueDescription']."]]></description>\r\n");

		# Issue identification
		fwrite ($xmlfile,"\t\t<issue_identification>\r\n");
		
		if (!empty($article['issueVolume']))
			fwrite ($xmlfile,"\t\t\t<volume><![CDATA[".$article['issueVolume']."]]></volume>\r\n");	
		if (!empty($article['issueNumber']))
			fwrite ($xmlfile,"\t\t\t<number><![CDATA[".$article['issueNumber']."]]></number>\r\n");			
		fwrite ($xmlfile,"\t\t\t<year><![CDATA[".$article['issueYear']."]]></year>\r\n");
		
		if (!empty($article['issueTitle'])){
			fwrite ($xmlfile,"\t\t\t<title locale=\"".$defaultLocale."\"><![CDATA[".$article['issueTitle']."]]></title>\r\n");
		}
		# Add alternative localisations for the issue title
		fwrite ($xmlfile, searchLocalisations('issueTitle', $article, 3, 'title'));
		
		fwrite ($xmlfile,"\t\t</issue_identification>\r\n\r\n");
		
		fwrite ($xmlfile,"\t\t<date_published><![CDATA[".$article['issueDatepublished']."]]></date_published>\r\n\r\n");
		fwrite ($xmlfile,"\t\t<last_modified><![CDATA[".$article['issueDatepublished']."]]></last_modified>\r\n\r\n");
		
		# Sections
		fwrite ($xmlfile,"\t\t<sections>\r\n");
		    
			foreach ($sections[$article['issueDatepublished']] as $sectionAbbrev => $sectionTitle){
				fwrite ($xmlfile,"\t\t\t<section ref=\"".htmlentities($sectionAbbrev, ENT_XML1)."\">\r\n");
				fwrite ($xmlfile,"\t\t\t\t<abbrev locale=\"".$defaultLocale."\">".htmlentities($sectionAbbrev, ENT_XML1)."</abbrev>\r\n");
				foreach ($copy_if_language_does_not_exist as $value) {
					if ($locales[$value] != $defaultLocale) {
						fwrite ($xmlfile,"\t\t\t\t<abbrev locale=\"".$locales[$value]."\">".htmlentities($sectionAbbrev, ENT_XML1)."</abbrev>\r\n");
					}
				}
				fwrite ($xmlfile,"\t\t\t\t<title locale=\"".$defaultLocale."\"><![CDATA[".$sectionTitle."]]></title>\r\n");
				foreach ($copy_if_language_does_not_exist as $value) {
					if ($locales[$value] != $defaultLocale) {
						fwrite ($xmlfile,"\t\t\t\t<title locale=\"".$locales[$value]."\"><![CDATA[".$sectionTitle."]]></title>\r\n");
					}
				}
				fwrite ($xmlfile,"\t\t\t</section>\r\n");
			}

		fwrite ($xmlfile,"\t\t</sections>\r\n\r\n");

		# Issue galleys needed even if empty
		fwrite ($xmlfile,"\t\t<issue_galleys xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\"/>\r\n\r\n");

		# Start articles output
		fwrite ($xmlfile,"\t\t<articles xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n\r\n");

		$currentIssueDatepublished = $article['issueDatepublished'];
		$currentYear = $newYear;

	}


	# Article
	disp("Adding article: ".$article['title']);

	# Check if language has an alternative default locale
	# If it does, use the locale in all fields
	$articleLocale = $defaultLocale;
	if (!empty($article['language'])){
		$articleLocale = $locales[trim($article['language'])];
	}

	fwrite ($xmlfile,"\t\t<article xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" date_submitted=\"".$article['issueDatepublished']."\" status=\"3\" submission_progress=\"0\" current_publication_id=\"".$submissionId."\" stage=\"production\">\r\n\r\n");
	fwrite ($xmlfile,"\t\t\t<id type=\"internal\" advice=\"ignore\">".$submissionId."</id>\r\n\r\n");

		# Submission files
		unset($galleys);
		$fileSeq = 0;

		for ($i = 1; $i <= $maxFiles; $i++) {

			if (empty($article['fileLocale'.$i])) {
				$fileLocale = $articleLocale;
			} else {
				$fileLocale = $locales[trim($article['fileLocale'.$i])];
			}
			
			if (!preg_match("@^https?://@", $article['file'.$i]) && $article['file'.$i] != "") {
					
				$file = $filesFolder.$article['file'.$i];
				$fileSize = filesize($file);				
				if(function_exists('mime_content_type')){
					$fileType = mime_content_type($file);
				}
				elseif(function_exists('finfo_open')){
					$fileinfo = new finfo();
					$fileType = $fileinfo->file($file, FILEINFO_MIME_TYPE);
				}
				else {
					disp("ERROR: You need to enable fileinfo or mime_magic extension.");
				}
				
				$fileContents = file_get_contents ($file);
				
				fwrite ($xmlfile,"\t\t\t<submission_file xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" stage=\"proof\" genre=\"". $article['fileGenre'.$i] ."\" id=\"".$fileId."\" file_id=\"".$fileId."\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n");
				
				if (empty($article['fileGenre'.$i]))
					$article['fileGenre'.$i] = "Article Text";
				
				
				fwrite ($xmlfile,"\t\t\t\t<name locale=\"".$articleLocale."\">". trim(htmlentities($article['file'.$i], ENT_XML1)) ."</name>\r\n");				
				fwrite ($xmlfile,"\t\t\t\t<file id=\"".$fileId."\" filesize=\"$fileSize\" extension=\"pdf\">");
				fwrite ($xmlfile,"\t\t\t\t<embed encoding=\"base64\">");
				fwrite ($xmlfile, base64_encode($fileContents));
				fwrite ($xmlfile,"\t\t\t\t</embed>\r\n");
				fwrite ($xmlfile,"\t\t\t\t</file>");
				
				fwrite ($xmlfile,"\t\t\t</submission_file>\r\n\r\n");

				# save galley data
				$galleys[$fileId] = "\t\t\t\t<article_galley xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" locale=\"".$fileLocale."\" approved=\"false\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n";
				$galleys[$fileId] .= "\t\t\t\t\t<name locale=\"".$fileLocale."\">".$article['fileLabel'.$i]."</name>\r\n";

				//$galleys[$fileId] .= searchLocalisations('fileLabel'.$i, $article, 5, 'name');
				$galleys[$fileId] .= "\t\t\t\t\t<seq>".$fileSeq."</seq>\r\n";
				$galleys[$fileId] .= "\t\t\t\t\t<submission_file_ref id=\"".$fileId."\"/>\r\n";
				$galleys[$fileId] .= "\t\t\t\t</article_galley>\r\n\r\n";

				$fileId++;
			}
			if (preg_match("@^https?://@", $article['file'.$i]) && $article['file'.$i] != "") {
				# save remote galley data
				$galleys[$fileId] = "\t\t\t\t<article_galley xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" locale=\"".$fileLocale."\" approved=\"false\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n";
				$galleys[$fileId] .= "\t\t\t\t\t<name locale=\"".$fileLocale."\">".$article['fileLabel'.$i]."</name>\r\n";
				$galleys[$fileId] .= searchLocalisations('fileLabel'.$i, $article, 5, 'name');
				$galleys[$fileId] .= "\t\t\t\t\t<seq>".$fileSeq."</seq>\r\n";
				$galleys[$fileId] .= "\t\t\t\t\t<remote src=\"" . trim(htmlentities($article['file'.$i], ENT_XML1)) . "\" />\r\n";
				$galleys[$fileId] .= "\t\t\t\t</article_galley>\r\n\r\n";
			}
			$fileSeq++;
		}

		# Publication
		fwrite ($xmlfile,"\t\t\t<publication xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" locale=\"".$articleLocale."\" version=\"1\" status=\"3\" primary_contact_id=\"".$authorId."\" url_path=\"\" seq=\"0\" date_published=\"".$article['issueDatepublished']."\" section_ref=\"".htmlentities($article['sectionAbbrev'], ENT_XML1)."\" access_status=\"0\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n\r\n");
		fwrite ($xmlfile,"\t\t\t\t<id type=\"internal\" advice=\"ignore\">".$submissionId."</id>\r\n\r\n");

		# DOI
		if (!empty($article['doi'])){	
			fwrite ($xmlfile,"\t\t\t\t<id type=\"doi\" advice=\"update\"><![CDATA[".$article['doi']."]]></id>\r\n");
		}

		# title, prefix, subtitle, abstract
		fwrite ($xmlfile,"\t\t\t\t<title locale=\"".$articleLocale."\"><![CDATA[".$article['title']."]]></title>\r\n");
		fwrite ($xmlfile, searchLocalisations('title', $article, 4));

		if (!empty($article['prefix'])){
			fwrite ($xmlfile,"\t\t\t\t<prefix locale=\"".$articleLocale."\"><![CDATA[".$article['prefix']."]]></prefix>\r\n");
		}
		fwrite ($xmlfile, searchLocalisations('prefix', $article, 4));

		if (!empty($article['subtitle'])){	
			fwrite ($xmlfile,"\t\t\t\t<subtitle locale=\"".$articleLocale."\"><![CDATA[".$article['subtitle']."]]></subtitle>\r\n");
		}
		fwrite ($xmlfile, searchLocalisations('subtitle', $article, 4));

		if (!empty($article['abstract'])){
			fwrite ($xmlfile,"\t\t\t\t<abstract locale=\"".$articleLocale."\"><![CDATA[".nl2br($article['abstract'])."]]></abstract>\r\n\r\n");
		}
		fwrite ($xmlfile, searchLocalisations('abstract', $article, 4));

		if (!empty($article['articleLicenseUrl'])) {	
			fwrite ($xmlfile,"\t\t\t\t<licenseUrl><![CDATA[".$article['articleLicenseUrl']."]]></licenseUrl>\r\n");	
		}	
		if (!empty($article['articleCopyrightHolder'])) {	
			fwrite ($xmlfile,"\t\t\t\t<copyrightHolder locale=\"".$articleLocale."\"><![CDATA[".$article['articleCopyrightHolder']."]]></copyrightHolder>\r\n");	
		}	
		if (!empty($article['articleCopyrightYear'])) {	
			fwrite ($xmlfile,"\t\t\t\t<copyrightYear><![CDATA[".$article['articleCopyrightYear']."]]></copyrightYear>\r\n");	
		}

		# Keywords
		if (!empty($article['keywords'])){
			if (trim($article['keywords']) != ""){
				fwrite ($xmlfile,"\t\t\t\t<keywords locale=\"".$articleLocale."\">\r\n");
				$keywords = explode(";", $article['keywords']);
				foreach ($keywords as $keyword){
					fwrite ($xmlfile,"\t\t\t\t\t<keyword><![CDATA[".trim($keyword)."]]></keyword>\r\n");	
				}
				fwrite ($xmlfile,"\t\t\t\t</keywords>\r\n");
			}
			fwrite ($xmlfile, searchTaxonomyLocalisations('keywords', 'keyword', $article, 4));
		}


		# Disciplines
		if (!empty($article['disciplines'])){
			if (trim($article['disciplines']) != "") {
				fwrite ($xmlfile,"\t\t\t\t<disciplines locale=\"".$articleLocale."\">\r\n");
				$disciplines = explode(";", $article['disciplines']);
				foreach ($disciplines as $discipline){
					fwrite ($xmlfile,"\t\t\t\t\t<discipline><![CDATA[".trim($discipline)."]]></discipline>\r\n");	
				}
				fwrite ($xmlfile,"\t\t\t\t</disciplines>\r\n");
			}
			fwrite ($xmlfile, searchTaxonomyLocalisations('disciplines', 'disciplin', $article, 4));
		}
		
		# TODO: add support for subjects, supporting agencies
		/*
		<agencies locale="fi_FI">
			<agency></agency>
		</agencies>
		<subjects locale="fi_FI">
			<subject></subject>
		</subjects>
		*/

		# Authors
		fwrite ($xmlfile,"\t\t\t\t<authors xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://pkp.sfu.ca native.xsd\">\r\n");
		
		for ($i = 1; $i <= $maxAuthors; $i++) {
			
			if ($article['authorFirstname'.$i]) {
				
				fwrite ($xmlfile,"\t\t\t\t\t<author include_in_browse=\"true\" user_group_ref=\"Author\" seq=\"0\" id=\"".$authorId."\">\r\n");
				
				fwrite ($xmlfile,"\t\t\t\t\t\t<givenname locale=\"".$articleLocale."\"><![CDATA[".$article['authorFirstname'.$i].(!empty($article['authorMiddlename'.$i]) ? ' '.$article['authorMiddlename'.$i] : '')."]]></givenname>\r\n");
				if (!empty($article['authorLastname'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<familyname locale=\"".$articleLocale."\"><![CDATA[".$article['authorLastname'.$i]."]]></familyname>\r\n");
				}

				if (!empty($article['authorAffiliation'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<affiliation locale=\"".$articleLocale."\"><![CDATA[".$article['authorAffiliation'.$i]."]]></affiliation>\r\n");
				}
				fwrite ($xmlfile, searchLocalisations('authorAffiliation'.$i, $article, 6, 'affiliation'));

				if (!empty($article['country'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<country><![CDATA[".$article['country'.$i]."]]></country>\r\n");
				}

				if (!empty($article['authorEmail'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<email>".$article['authorEmail'.$i]."</email>\r\n");
				}
				else{
					fwrite ($xmlfile,"\t\t\t\t\t\t<email><![CDATA[]]></email>\r\n");
				}

				if (!empty($article['orcid'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<orcid><![CDATA[".$article['orcid'.$i]."]]></orcid>\r\n");
				}
				if (!empty($article['authorBio'.$i])){
					fwrite ($xmlfile,"\t\t\t\t\t\t<biography locale=\"".$articleLocale."\"><![CDATA[".$article['authorBio'.$i]."]]></biography>\r\n");
				}
				
				fwrite ($xmlfile,"\t\t\t\t\t</author>\r\n");

				
			}
			$authorId++;
		}

		# If no authors are given, use default author name
		if (!$article['authorFirstname1']){
				fwrite ($xmlfile,"\t\t\t\t\t<author primary_contact=\"true\" user_group_ref=\"Author\"  seq=\"0\" id=\"".$authorId."\">\r\n");
				fwrite ($xmlfile,"\t\t\t\t\t\t<givenname><![CDATA[".$defaultAuthor['givenname']."]]></givenname>\r\n");
				fwrite ($xmlfile,"\t\t\t\t\t\t<email><![CDATA[]]></email>\r\n");
				fwrite ($xmlfile,"\t\t\t\t\t</author>\r\n");
				$authorId++;
		}

		fwrite ($xmlfile,"\t\t\t\t</authors>\r\n\r\n");

		# Article galleys
		if (isset($galleys)){
			foreach ($galleys as $galley){
				fwrite ($xmlfile, $galley);
			}
		}

		# pages
		if (!empty($article['pages'])){	
			fwrite ($xmlfile,"\t\t\t\t<pages>".$article['pages']."</pages>\r\n\r\n");
		}

		$submissionId++;
		fwrite ($xmlfile,"\t\t\t</publication>\r\n\r\n");
		fwrite ($xmlfile,"\t\t</article>\r\n\r\n");
	}

	# After exiting the loop close the last XML file
	disp("Closing XML file");
	fwrite ($xmlfile,"\t\t</articles>\r\n");
	fwrite ($xmlfile,"\t</issue>\r\n\r\n");	
	fwrite ($xmlfile,"</issues>\r\n\r\n");


	disp("Conversion finished");


	

/* 
 * Helpers 
 * -----------
 */


# Function for searching alternative locales for a given field
function searchLocalisations($key, $input, $intend, $tag = null, $flags = null) {
	global $locales;
	global $defaultLocale;
	global $copy_if_language_does_not_exist;
	$articleLocale = $defaultLocale;
	if (!empty($input['language'])){
		$articleLocale = $locales[trim($input['language'])];
	}

	
	//if ($tag == "") $tag = $key;
	if (empty($tag)) $tag = $key;
	
	$nodes = "";
	$pattern = "/:".$key."/";
	$values = array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
		
	foreach ($values as $keyval => $value){
		if ($value != ""){
			$shortLocale = explode(":", $keyval);
			if (strpos($value, "\n") !== false || strpos($value, "&") !== false || strpos($value, "<") !== false || strpos($value, ">") !== false ) $value = "<![CDATA[".nl2br($value)."]]>";
			for ($i = 0; $i < $intend; $i++) $nodes .= "\t";
			$nodes .= "<".$tag." locale=\"".$locales[$shortLocale[0]]."\">".$value."</".$tag.">\r\n";
		}
	}
	$v='';
	foreach ($copy_if_language_does_not_exist as $keyval => $value){
		$nowDefLocale=preg_match('/^(issue)|(section)/',$key) ? $defaultLocale : $articleLocale;
		if (empty($input[$value.":".$tag]) && $locales[$value] != $nowDefLocale && !empty($input[$key])) {
			$v=$input[$key];
			if (strpos($v, "\n") !== false || strpos($v, "&") !== false || strpos($v, "<") !== false || strpos($v, ">") !== false ) $v = "<![CDATA[".nl2br($v)."]]>";
			for ($i = 0; $i < $intend; $i++) $nodes .= "\t";
			$nodes .= "<".$tag." locale=\"".$locales[$value]."\">".$v."</".$tag.">\r\n";
		}
	}
	return $nodes;
	
}

# Function for searching alternative locales for a given taxonomy field
function searchTaxonomyLocalisations($key, $key_singular, $input, $intend, $flags = null) {
    global $locales;
		
	$nodes = "";
	$intend_string = "";
	for ($i = 0; $i < $intend; $i++) $intend_string .= "\t";
	$pattern = "/:".$key."/";
	$values = array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
		
	foreach ($values as $keyval => $value){
		if ($value != ""){

			$shortLocale = explode(":", $keyval);

			$nodes .= $intend_string."<".$key." locale=\"".$locales[$shortLocale[0]]."\">\r\n";

			$subvalues = explode(";", $value);
			foreach ($subvalues as $subvalue){
				$nodes .= $intend_string."\t<".$key_singular."><![CDATA[".trim($subvalue)."]]></".$key_singular.">\r\n";	
			}

			$nodes .= $intend_string . "</".$key.">\r\n";

		}
	}
	
	return $nodes;
	
}


# Function for creating an array using the first row as keys
function createArray($sheet) {
	$highestrow = $sheet->getHighestRow();
	$highestcolumn = $sheet->getHighestColumn();
	$columncount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestcolumn);
	$headerRow = $sheet->rangeToArray('A1:' . $highestcolumn . "1");
	$header = $headerRow[0];
	array_unshift($header,"");
	unset($header[0]);
	$array = array();
	for ($row = 2; $row <= $highestrow; $row++) {
		$a = array();
		for ($column = 1; $column <= $columncount; $column++) {
			if (strpos($header[$column], "bstract") !== false) {
					if ($sheet->getCellByColumnAndRow($column,$row)->getValue() instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
						$value = $sheet->getCellByColumnAndRow($column,$row)->getValue();
            			$elements = $value->getRichTextElements();
            			$cellData = "";
						foreach ($elements as $element) {
						    if ($element instanceof \PhpOffice\PhpSpreadsheet\RichText\Run) {
						        if ($element->getFont()->getBold()) {
						            $cellData .= '<b>';
						        } elseif ($element->getFont()->getSubScript()) {
						            $cellData .= '<sub>';  
						        } elseif ($element->getFont()->getSuperScript()) {
						            $cellData .= '<sup>';
						        } elseif ($element->getFont()->getItalic()) {
						            $cellData .= '<em>';
						        }
						    }
						    // Convert UTF8 data to PCDATA
						    $cellText = $element->getText();
						    $cellData .= htmlspecialchars($cellText);
						    if ($element instanceof \PhpOffice\PhpSpreadsheet\RichText\Run) {
						        if ($element->getFont()->getBold()) {
						            $cellData .= '</b>';
						        } elseif ($element->getFont()->getSubScript()) {
						            $cellData .= '</sub>';
						        }  elseif ($element->getFont()->getSuperScript()) {
						            $cellData .= '</sup>';
						        } elseif ($element->getFont()->getItalic()) {
						            $cellData .= '</em>';
						        }
						    }
						}
						$a[$header[$column]] = $cellData;
                	}
                	else{
                		$a[$header[$column]] = $sheet->getCellByColumnAndRow($column,$row)->getFormattedValue();
                	}
			}
			else {
				$key = $header[$column];
				$a[$key] = $sheet->getCellByColumnAndRow($column,$row)->getFormattedValue();
			}
		}
		if ( ! (empty($a['title']) && empty($a['seq']) && empty($a['issueYear']) )) {
			$array[$row] = $a;
		}
		else {
			disp("Discarding row $row: title, seq and issueYear are empy ". $a['title']);
		}
	}
	
	return $array;
}

# Check the highest author number
function countMaxAuthors($sheet) {
	$highestcolumn = $sheet->getHighestColumn();
	$headerRow = $sheet->rangeToArray('A1:' . $highestcolumn . "1");
	$header = $headerRow[0];
	$authorFirstnameValues = array();
	foreach ($header as $headerValue) {
		if (strpos($headerValue, "authorFirstname") !== false) {
			$authorFirstnameValues[] = (int) trim(str_replace("authorFirstname", "", $headerValue));
		}
	}
	return max($authorFirstnameValues);
}

# Check the highest file number
function countMaxFiles($sheet) {
	$highestcolumn = $sheet->getHighestColumn();
	$headerRow = $sheet->rangeToArray('A1:' . $highestcolumn . "1");
	$header = $headerRow[0];
	$fileValues = array();
	$fileValues[] = 1;
	foreach ($header as $headerValue) {
		if (strpos($headerValue, "fileLabel") !== false) {
			$fileValues[] = (int) trim(str_replace("fileLabel", "", $headerValue));
		}
	}
	return max($fileValues);
}

# Function for data validation
function validateArticles($articles) {
	global $filesFolder;
	$errors = "";
	$articleRow = 0;

	foreach ($articles as $article) {

			$articleRow++;

			if (empty($article['issueYear'])) {
				$errors .= date('H:i:s') . " ERROR: Issue year missing for article " . $articleRow . EOL;
			}

			if (empty($article['issueDatepublished'])) {
				$errors .= date('H:i:s') . " ERROR: Issue publication date missing for article " . $articleRow . EOL;
			}

			if (empty($article['title'])) {
				$errors .= date('H:i:s') . " ERROR: article title missing for the given default locale for article " . $articleRow . EOL;
			}

			if (empty($article['sectionTitle'])) {
				$errors .= date('H:i:s') . " ERROR: section title missing for the given default locale for article " . $articleRow . EOL;
			}

			if (empty($article['sectionAbbrev'])) {
				$errors .= date('H:i:s') . " ERROR: section abbreviation missing for the given default locale for article " . $articleRow . EOL;
			}

			for ($i = 1; $i <= 200; $i++) {

				if (isset($article['file'.$i]) && $article['file'.$i] && !preg_match("@^https?://@", $article['file'.$i]) ) {

					$fileCheck = $filesFolder.$article['file'.$i]; 

					if (!file_exists($fileCheck)) 
						$errors .= date('H:i:s') . " ERROR: file ".$i." missing " . $fileCheck . EOL;

					$fileLabelColumn = 'fileLabel'.$i;
					if (empty($fileLabelColumn)) {
						$errors .= date('H:i:s') . " ERROR: fileLabel ".$i." missing for article " . $articleRow . EOL;
					}
					$fileLocaleColumns = 'fileLocale'.$i;
					if (empty($fileLocaleColumns)) {
						$errors .= date('H:i:s') . " ERROR: fileLocale ".$i."  missingfor article " . $articleRow . EOL;
					}
				} else {
					break;
				}
			}	
	}
	
	return $errors;

}

function disp($str) {
	global $debug;
	if ($debug == 1) {
		$msg=date('H:i:s') . " ".$str. EOL;
		fwrite(STDERR, $msg);
	}
}

