# Excel to OJS3 XML conversion tool

Version 1.3.1.0 supports the schema for OJS 3.2.1.

The tool was created for "in-house use" at the Federation of Finnish Learned Societies (https://tsv.fi). *It is not pretty*. It has not been thoroughly tested, but has been used to import the archives of around 20 journals since 2017. Feel free to use and develop further.

## Installation

Download and unzip the tsvConverter.

Make sure you can run php from command line.

Go to the tsvConverter folder and install or update dependencies via Composer (https://getcomposer.org/). The conversion tool uses https://github.com/PHPOffice/PhpSpreadsheet for reading sheets.

    composer install
    cp config.TEMPLATE.php config.php
    # edit config.php to configure all your variables

## Usage 

Before importing the created data to your production server, **you should try to import the data to a test environment to ensure that the created XML files work as expected**.

Usage:

	php convert.php sheetFilename filesFolderName [outfilexml|''] [-v|-d]

Convert to STDOUT:

	php convert.php sheetFilename filesFolderName

Only validate by adding -v:

	php convert.php sheetFilename filesFolderName '' -v


### Step by step instructions
1. Create an Excel file containing the article data. See the details below and the "exampleMinimal.xlsx" and "exampleAdvanced.xlsx" files. The metadata of each article is in one row. The order of the columns does not matter. 
2. Sort the Excel file according to the publication date field (issueDatepublished) and the article sequence field (seq). See https://www.contextures.com/xlSort01.html#sorttwo
3. Move the Excel file to the same folder with the conversion script. Move the full text files to a folder, for example "exampleFiles", below the conversion script.
4. Run *php convert.php exampleMinimal.xlsx exampleFiles*
5. The script will create one XML per year.

## Article
| Field | Description |  Required|
|----------|:--------:|:--------:|
| prefix |  "The", "A" |  |
| title |  Article title | x |
| subTitle |  Article subtitle |   |
| abstract|  Article abstract |   |
| seq |  Article sequence inside an issue, first article '1' | x  |
| pages| For example "23-45"  |  |
| language| Article language "en", "fi", "sv", "de", "fr"  | x |
| keywords| Word 1; Word 2; Word3 |  |
| disciplines| History; Political science; Astronomy |  |
| articleCopyrightYear| 2005 |  |
| articleCopyrightHolder| "John Doe" |  |
| articleLicenseUrl| http://creativecommons.org/licenses/by/4.0 |  |
| doi| "10.1234/art.182" |  |

## Issue
| Field | Description |  Required|
|----------|:--------:|:--------:|
| issueDatepublished |  Issue publication date, yyyy-mm-dd. Note! has to be unique for each individual issue. | x |
| issueVolume |  Issue volume |  |
| issueNumber |  Issue number |  |
| issueYear |  Issue year | x |
| issueTitle |  Issue title |  |
| sectionTitle |  Section title, eg. "Articles" | x  |
| sectionAbbrev |  Section abbreviation, eg. "ART" | x  |

## Multiplied fields
An article can have multiple authors or full text files. Every article has to have at least one author and one file.

If an article has for example three authors, the excel file should include columns for each author with the number behind the column name changing. The first name of the third author will be saved to a field called *authorFirstname3*.

### Authors
| Field | Description |  Required|
|----------|:--------:|:--------:|
| authorFirstname1|  Given name | x |
| authorMiddlename1|  Middle name |  |
| authorLastname1|  Family name |   |
| authorEmail1|  Email |  |
| authorAffiliation1|  Affiliation |   |
| country1|  "FI", "SE", "DK", "CA", "US" |   |
| orcid1|  Orcid ID, should include "https://". Note that adding Orcid ID's this way is not recommended by Orcid. |   |
| authorBio1|  Biography |   |

### Files
| Field | Description |  Required|
|----------|:--------:|:--------:|
| file1|  Name of the file, "article1.pdf" or url for remote galley| x |
| fileLabel1|  Usually "PDF"| x |
| fileGenre1|  Usually "Article Text"| x |
| fileLocale1|  "en", "fi" etc. | x |

## Importing multilingual data

The new version of the converter supports three different ways of handling locales:
- Alternative 1: If all your data is in one language, you can just give the defaultLocale value in the converter settings.
- Alternative 2: If some of your articles are for example in English and some in Finnish, you can add an additional column named "language" and give the article locale in that column. See the example xls-file. All the article medata will be saved using the locale given in the language field. For example *title* can contain both English and Finnish titles as long as the language column matches the language used in the field.
- Alternative 3: If your articles are all in one language, but you also have some metadata in other languages, for example an abstract in another language, you can give an additional abstract field in a column named locale:abstract (for example en:abstract)


fi - Finnish
en - English
sv - Swedish
fr - French
de - German

## Licence
The conversion tool is distributed under the GNU GPL v3.

## Changes in version 1.3.1.0 (Mar 2021)
- Support OJS 3.2

## Changes in version 1.2.0.0 (Mar 2021)
- Use PhpSpreadsheet (https://github.com/PHPOffice/PhpSpreadsheet) and Composer
- Use GPL v3

## Changes in version 1.1.0.12 (Dec 2018)
- Support multilingual keywords

## Changes in version 1.1.0.11 (Nov 2018)
- Support remote galleys

## Changes in version 1.1.0.8 (Sep 2018)
- Support rich text in abstract fields

## Changes in version 1.1.0.7 (Sep 2018)
- Support for keywords and disciplines, authorEmail and authorMiddlename
- better support for articles in alternative locales

