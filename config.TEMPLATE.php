<?php

/**
 enter here the configuration variables that you would like to replace the original ones:
 $defaultLocale, $uploader, $defaultAuthor['givenname'], $filesFolder, $locales, date_default_timezone_set()
 */

# $defaultLocale = 'en_US';

# $uploader = "admin";
# $defaultAuthor['givenname'] = "Editorial Board";

# $filesFolder = dirname(__FILE__) . "/". $files ."/";
#   for absolute path use:
# $filesFolder = $files ."/";
# 

/** 
 * $split_output_by_issue_date_published: 
 * if 1 and $outfile !='php://stdout' split output xml in several file based on IssueDatepublished
 */

# $split_output_by_issue_date_published=0;

# $locales = array( 
#   'en' => 'en_US', 'fi' => 'fi_FI', 'sv' => 'sv_SE', 'de' => 'de_DE',
#   'ge' => 'de_DE', 'ru' => 'ru_RU', 'fr' => 'fr_FR', 'no' => 'nb_NO',
#   'da' => 'da_DK', 'es' => 'es_ES',
# );

/**
 * $copy_if_language_does_not_exist:
 * If a multilingual metadata is not present in the .xlsx file in one of these languages, 
 * it must be added identical to the default language.
 */
# $copy_if_language_does_not_exist= array('it','en');

# date_default_timezone_set('Europe/Helsinki');
