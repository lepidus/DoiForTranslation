# DOI for submission with translation

Other languages:
- [Português do Brasil](README.pt_BR.md)
- [Español](README.es.md)

## Description

This plugin allows you to create a copy of a submission, where this new submission represents a translation of the original in a certain language. In addition, it will send Crossref the hasTranslation/isTranslationOf relationship linking the DOI of the original language with the DOI of the submission that is a translation.

Finally, it inserts links on the page of each submission so that users can easily find the versions in all available languages.

## Compatibility

This plugin is compatible with the following PKP applications:

- OJS 3.3.0-x

Translations start in the submission stage with a new submission date. The original DOI (including its custom suffix), URL path, publication date and issue assignment are not copied. The primary contact points to the copied author. Editors must review the translated metadata and assign a separate DOI before publishing.

## Installation

Install the *DOI For Translation* plugin via the *Plugin Gallery* in `Dashboard > Website > Plugins`.

## How to use

With the plugin installed and enabled, you can access an active submission, click on the “Create translation” option and select the translation language. A copy of the submission will be created, which you can view under “Translations”. In the submission translation, you can add submission files in the language in question.  

## Credits

This plugin was developed by [Lepidus Tecnologia](https://lepidus.com.br/) for the [REGEPE](https://regepe.org.br/) journal of the National Association for Studies in Entrepreneurship and Small Business Management ([ANEGEPE](https://anegepe.org.br/)).

## License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2023-2025 Lepidus Tecnologia__
