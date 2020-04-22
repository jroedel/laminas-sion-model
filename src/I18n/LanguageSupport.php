<?php

namespace SionModel\I18n;

class LanguageSupport
{
    protected $supportedLanguages = ['en', 'es', 'de', 'pt', 'it', 'fr'];
    protected $languages = [
        'aa' => ['en' => "Afar", 'es' => "Afar", 'de' => "Afar", 'pt' => "Afar", 'it' => "Afar", 'fr' => "Afar"],
        'ab' => ['en' => "Abkhazian", 'es' => "Abjaziano", 'de' => "Abchasisch", 'pt' => "Abcázio", 'it' => "Abkhazian", 'fr' => "Abkhaze"],
        'af' => ['en' => "Afrikaans", 'es' => "Afrikaans", 'de' => "Afrikaans", 'pt' => "Africâner", 'it' => "Afrikaans", 'fr' => "Afrikaans"],
        'ak' => ['en' => "Akan", 'es' => "Akan", 'de' => "Akan", 'pt' => "Akan", 'it' => "Akan", 'fr' => "Akan"],
        'am' => ['en' => "Amharic", 'es' => "Ámárico", 'de' => "Amharisch", 'pt' => "Amárico", 'it' => "Amarico", 'fr' => "Amharique"],
        'ar' => ['en' => "Arabic", 'es' => "Árábe", 'de' => "Arabisch", 'pt' => "Árabe", 'it' => "Arabo", 'fr' => "Arabe"],
        'an' => ['en' => "Aragonese", 'es' => "Aragonés", 'de' => "Aragonesisch", 'pt' => "Aragonês", 'it' => "Aragonese", 'fr' => "Aragonais"],
        'as' => ['en' => "Assamese", 'es' => "Assamais", 'de' => "Assamesisch", 'pt' => "Assamês", 'it' => "Assamese", 'fr' => "Assamais"],
        'av' => ['en' => "Avaric", 'es' => "Avaric", 'de' => "Awarisch", 'pt' => "Avárico", 'it' => "Avarico", 'fr' => "Avar"],
        'ae' => ['en' => "Avestan", 'es' => "Avestan", 'de' => "Avestisch", 'pt' => "Avéstico", 'it' => "Avestan", 'fr' => "Avestique"],
        'ay' => ['en' => "Aymara", 'es' => "Aymará", 'de' => "Aymara", 'pt' => "Aimará", 'it' => "Aymara", 'fr' => "Aymara"],
        'az' => ['en' => "Azerbaijani", 'es' => "Azerbayano", 'de' => "Aserbaidschanisch", 'pt' => "Azerbaidjano", 'it' => "Azero", 'fr' => "Azéri"],
        'ba' => ['en' => "Bashkir", 'es' => "Bashkir", 'de' => "Baschkirisch", 'pt' => "Basquir", 'it' => "Baschiro", 'fr' => "Bachkir"],
        'bm' => ['en' => "Bambara", 'es' => "Bambara", 'de' => "Bambara", 'pt' => "Bambara", 'it' => "Bambara", 'fr' => "Bambara"],
        'be' => ['en' => "Belarusian", 'es' => "Bieloruso", 'de' => "Weißrussisch", 'pt' => "Bielorusso", 'it' => "Bielorusso", 'fr' => "Biélorusse"],
        'bn' => ['en' => "Bengali", 'es' => "Bengalí", 'de' => "Bengalisch", 'pt' => "Bengali", 'it' => "Bengalese", 'fr' => "Bengali"],
        'bh' => ['en' => "Bihari languages", 'es' => "Tupi, lenguas", 'de' => "Bihari-Sprachen", 'pt' => "Línguas biaris", 'it' => "Lingue bihari", 'fr' => "Langues Biharis"],
        'bi' => ['en' => "Bislama", 'es' => "Bislama", 'de' => "Bislama", 'pt' => "Bislamá", 'it' => "Bislama", 'fr' => "Bichlamar"],
        'bo' => ['en' => "Tibetan", 'es' => "Tibetano", 'de' => "Tibetisch", 'pt' => "Tibetano", 'it' => "Tibetano", 'fr' => "Tibétain"],
        'bs' => ['en' => "Bosnian", 'es' => "Bosnio", 'de' => "Bosnisch", 'pt' => "Bósnio", 'it' => "Bosniaco", 'fr' => "Bosniaque"],
        'br' => ['en' => "Breton", 'es' => "Bretón", 'de' => "Bretonisch", 'pt' => "Bretão", 'it' => "Bretone", 'fr' => "Breton"],
        'bg' => ['en' => "Bulgarian", 'es' => "Búlgaro", 'de' => "Bulgarisch", 'pt' => "Búlgaro", 'it' => "Bulgaro", 'fr' => "Bulgare"],
        'ca' => ['en' => "Catalan; Valencian", 'es' => "Catalán, Valenciano", 'de' => "Katalanisch; Valencia", 'pt' => "Catalão; Valenciano", 'it' => "Catalano, Valenciano", 'fr' => "Catalan"],
        'cs' => ['en' => "Czech", 'es' => "Checo", 'de' => "Tschechisch", 'pt' => "Tcheco", 'it' => "Ceco", 'fr' => "Tchèque"],
        'ch' => ['en' => "Chamorro", 'es' => "Chamorro", 'de' => "Chamorro", 'pt' => "Chamorro", 'it' => "Chamorro", 'fr' => "Chamorro"],
        'ce' => ['en' => "Chechen", 'es' => "Checheno", 'de' => "Tschetschenisch", 'pt' => "Checheno", 'it' => "Chechen", 'fr' => "Tchétchène"],
        'cu' => ['en' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic", 'es' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic", 'de' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic", 'pt' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic", 'it' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic", 'fr' => "Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic"],
        'cv' => ['en' => "Chuvash", 'es' => "Chuvash", 'de' => "Tschuwaschisch", 'pt' => "Tchuvache", 'it' => "Chuvash", 'fr' => "Tchouvache"],
        'kw' => ['en' => "Cornish", 'es' => "Córnico", 'de' => "Kornisch", 'pt' => "Córnico", 'it' => "Cornish", 'fr' => "Cornique"],
        'co' => ['en' => "Corsican", 'es' => "Corso", 'de' => "Korsisch", 'pt' => "Corso", 'it' => "Corso", 'fr' => "Corse"],
        'cr' => ['en' => "Cree", 'es' => "Cree", 'de' => "Cree", 'pt' => "Cree", 'it' => "Cree", 'fr' => "Cree"],
        'cy' => ['en' => "Welsh", 'es' => "Galés", 'de' => "Walisisch", 'pt' => "Galês", 'it' => "Gallese", 'fr' => "Gallois"],
        'da' => ['en' => "Danish", 'es' => "Danés", 'de' => "Dänisch", 'pt' => "Dinamarquês", 'it' => "Danese", 'fr' => "Danois"],
        'de' => ['en' => "German", 'es' => "Alemán", 'de' => "Deutsch", 'pt' => "Alemão", 'it' => "Tedesco", 'fr' => "Allemand"],
        'dv' => ['en' => "Divehi; Dhivehi; Maldivian", 'es' => "Divehi; Dhivehi; Maldivian", 'de' => "Dhivehi", 'pt' => "Divehi; Maldívio", 'it' => "Divehi; Dhivehi; Maldiviano", 'fr' => "Maldivien"],
        'dz' => ['en' => "Dzongkha", 'es' => "Butaní", 'de' => "Dzongkha", 'pt' => "Butanês", 'it' => "Dzongkha", 'fr' => "Dzongkha"],
        'el' => ['en' => "Greek, Modern (1453-)", 'es' => "Griego Moderno (>1453)", 'de' => "Neugriechisch (ab 1453)", 'pt' => "Grego, Moderno (1453-)", 'it' => "Greco moderno (1453-)", 'fr' => "Grec Moderne (Après 1453)"],
        'en' => ['en' => "English", 'es' => "Inglés", 'de' => "Englisch", 'pt' => "Inglês", 'it' => "Inglese", 'fr' => "Anglais"],
        'eo' => ['en' => "Esperanto", 'es' => "Esperanto", 'de' => "Esperanto", 'pt' => "Esperanto", 'it' => "Esperanto", 'fr' => "Espéranto"],
        'et' => ['en' => "Estonian", 'es' => "Estonio", 'de' => "Estnisch", 'pt' => "Estoniano", 'it' => "Estone", 'fr' => "Estonien"],
        'eu' => ['en' => "Basque", 'es' => "Vasco", 'de' => "Baskisch", 'pt' => "Basco", 'it' => "Basco", 'fr' => "Basque"],
        'ee' => ['en' => "Ewe", 'es' => "Ewe", 'de' => "Ewe-Sprache", 'pt' => "Jeje", 'it' => "Ewe", 'fr' => "Éwé"],
        'fo' => ['en' => "Faroese", 'es' => "Feroés", 'de' => "Färöisch", 'pt' => "Faroês", 'it' => "Faeroese", 'fr' => "Féroïen"],
        'fa' => ['en' => "Persian", 'es' => "Persa", 'de' => "Persisch", 'pt' => "Persa", 'it' => "Persiano", 'fr' => "Persan"],
        'fj' => ['en' => "Fijian", 'es' => "Fidji", 'de' => "Fidschianisch", 'pt' => "Fidjiano", 'it' => "Figiano", 'fr' => "Fidjien"],
        'fi' => ['en' => "Finnish", 'es' => "Finés", 'de' => "Finnisch", 'pt' => "Finlandês", 'it' => "Finlandese", 'fr' => "Finnois"],
        'fr' => ['en' => "French", 'es' => "Francés", 'de' => "Französisch", 'pt' => "Francês", 'it' => "Francese", 'fr' => "Français"],
        'fy' => ['en' => "Western Frisian", 'es' => "Frisón occidental", 'de' => "Westfriesisch", 'pt' => "Frísio ocidental", 'it' => "Frisone occidentale", 'fr' => "Frison Occidental"],
        'ff' => ['en' => "Fulah", 'es' => "Fulah", 'de' => "Ful", 'pt' => "Fula", 'it' => "Fulah", 'fr' => "Peul"],
        'gd' => ['en' => "Gaelic; Scottish Gaelic", 'es' => "Gaélico, escocés gaélico", 'de' => "Gälisch; Schottisches Gälisch", 'pt' => "Escocês", 'it' => "Gaelico; Gaelico scozzese", 'fr' => "Gaélique ; Gaélique Écossais"],
        'ga' => ['en' => "Irish", 'es' => "Irlandés", 'de' => "Irisch", 'pt' => "Irlandês", 'it' => "Irlandese", 'fr' => "Irlandais"],
        'gl' => ['en' => "Galician", 'es' => "Gallego", 'de' => "Galizisch", 'pt' => "Galego", 'it' => "Galiziano", 'fr' => "Galicien"],
        'gv' => ['en' => "Manx", 'es' => "Manx [Gaélico de Manx]", 'de' => "Manx", 'pt' => "Manx", 'it' => "Manx", 'fr' => "Mannois ; Manx"],
        'gn' => ['en' => "Guarani", 'es' => "Guaraní", 'de' => "Guaraní", 'pt' => "Guarani", 'it' => "Guarani", 'fr' => "Guarani"],
        'gu' => ['en' => "Gujarati", 'es' => "guyaratí", 'de' => "Gujarati", 'pt' => "gujerati", 'it' => "Gujarati", 'fr' => "Goudjarâtî (Gujrâtî)"],
        'ht' => ['en' => "Haitian; Haitian Creole", 'es' => "Hawayano (Creole haitiano)", 'de' => "Haitianisch; Haitianisches Kreolisch", 'pt' => "Haitiano; Crioulo haitiano", 'it' => "Haitiano; Haitiano creolo", 'fr' => "Haïtien ; Créole Haïtien"],
        'ha' => ['en' => "Hausa", 'es' => "Haussa", 'de' => "Haussa", 'pt' => "Hauçá", 'it' => "Hausa", 'fr' => "Haoussa"],
        'he' => ['en' => "Hebrew", 'es' => "Hebreo", 'de' => "Hebräisch", 'pt' => "Hebraico", 'it' => "Ebraico", 'fr' => "Hébreu"],
        'hz' => ['en' => "Herero", 'es' => "Herero", 'de' => "Herero", 'pt' => "Hereró", 'it' => "Herero", 'fr' => "Herero"],
        'hi' => ['en' => "Hindi", 'es' => "Hindi", 'de' => "Hindi", 'pt' => "Híndi", 'it' => "Hindi", 'fr' => "Hindi"],
        'ho' => ['en' => "Hiri Motu", 'es' => "Hiri Motu", 'de' => "Hiri-Motu", 'pt' => "Hiri Motu", 'it' => "Hiri Motu", 'fr' => "Hiri Motu"],
        'hr' => ['en' => "Croatian", 'es' => "Croata", 'de' => "Kroatisch", 'pt' => "Croata", 'it' => "Croato", 'fr' => "Croate"],
        'hu' => ['en' => "Hungarian", 'es' => "Húngaro", 'de' => "Ungarisch", 'pt' => "Húngaro", 'it' => "Ungherese", 'fr' => "Hongrois"],
        'hy' => ['en' => "Armenian", 'es' => "Armenio", 'de' => "Armenisch", 'pt' => "Armênio", 'it' => "Armeno", 'fr' => "Arménien"],
        'ig' => ['en' => "Igbo", 'es' => "Igbo", 'de' => "Ibo", 'pt' => "Ibo", 'it' => "Igbo", 'fr' => "Igbo"],
        'io' => ['en' => "Ido", 'es' => "Ido", 'de' => "Ido", 'pt' => "Ido", 'it' => "Ido", 'fr' => "Ido"],
        'ii' => ['en' => "Sichuan Yi; Nuosu", 'es' => "Yi Sinchuán", 'de' => "Sichuan Yi; Nuosu", 'pt' => "Yi de Sichuan; Nuosu", 'it' => "Sichuan Yi; Nuosu", 'fr' => "Yi De Sichuan"],
        'iu' => ['en' => "Inuktitut", 'es' => "Inuktitut", 'de' => "Inuktitut", 'pt' => "Inuktitut", 'it' => "Inuktitut", 'fr' => "Inuktitut"],
        'ie' => ['en' => "Interlingue; Occidental", 'es' => "Interlingue", 'de' => "Interlingua", 'pt' => "Ocidental", 'it' => "Interlingue; Occidentale", 'fr' => "Interlingue"],
        'ia' => ['en' => "Interlingua (International Auxiliary Language Association)", 'es' => "Interlingua (Asociación de la Lengua Auxiliar Internacional)", 'de' => "Interlingua (Internationale Hilfssprachen-Vereinigung)", 'pt' => "Interlíngua", 'it' => "Interlingua (International Auxiliary Language Association)", 'fr' => "Interlingua (Langue Auxiliaire Internationale)"],
        'id' => ['en' => "Indonesian", 'es' => "Indonesio", 'de' => "Indonesisch", 'pt' => "Indonésio", 'it' => "Indonesiano", 'fr' => "Indonésien"],
        'ik' => ['en' => "Inupiaq", 'es' => "Inupiak", 'de' => "Inupiaq", 'pt' => "Inupiaque", 'it' => "Inupiaq", 'fr' => "Inupiaq"],
        'is' => ['en' => "Icelandic", 'es' => "Islandés", 'de' => "Isländisch", 'pt' => "Islandês", 'it' => "Islandese", 'fr' => "Islandais"],
        'it' => ['en' => "Italian", 'es' => "Italiano", 'de' => "Italienisch", 'pt' => "Italiano", 'it' => "Italiano", 'fr' => "Italien"],
        'jv' => ['en' => "Javanese", 'es' => "Javanés", 'de' => "Javanisch", 'pt' => "Javanês", 'it' => "Javanese", 'fr' => "Javanais"],
        'ja' => ['en' => "Japanese", 'es' => "Japonés", 'de' => "Japanisch", 'pt' => "Japonês", 'it' => "Giapponese", 'fr' => "Japonais"],
        'kl' => ['en' => "Kalaallisut; Greenlandic", 'es' => "Kalaallisut; Greenlandic", 'de' => "Kalaallisut; Grönländisch", 'pt' => "Groenlandês", 'it' => "Kalaallisut; Groenlandese", 'fr' => "Groenlandais"],
        'kn' => ['en' => "Kannada", 'es' => "Kannada", 'de' => "Kannada", 'pt' => "Canarês", 'it' => "Kannada", 'fr' => "Kannara (Canara)"],
        'ks' => ['en' => "Kashmiri", 'es' => "Kashmir", 'de' => "Kaschmirisch", 'pt' => "Caxemira", 'it' => "Kashmiri", 'fr' => "Kashmiri"],
        'ka' => ['en' => "Georgian", 'es' => "Georgiano", 'de' => "Georgisch", 'pt' => "Georgiano", 'it' => "Georgiano", 'fr' => "Géorgien"],
        'kr' => ['en' => "Kanuri", 'es' => "Kanuri", 'de' => "Kanuri", 'pt' => "Canúri", 'it' => "Kanuri", 'fr' => "Kanouri"],
        'kk' => ['en' => "Kazakh", 'es' => "Kazako", 'de' => "Kasachisch", 'pt' => "Cazaque", 'it' => "Kazako", 'fr' => "Kazakh"],
        'km' => ['en' => "Central Khmer", 'es' => "Camboyano (jémer) central", 'de' => "Zentral-Khmer", 'pt' => "Khmer Central", 'it' => "Khmer centrale", 'fr' => "Khmer Central"],
        'ki' => ['en' => "Kikuyu; Gikuyu", 'es' => "Kikuyu, Gikuyu", 'de' => "Kikuyu", 'pt' => "Quicuio", 'it' => "Kikuyu; Gikuyu", 'fr' => "Kikuyu"],
        'rw' => ['en' => "Kinyarwanda", 'es' => "Kinyarwanda", 'de' => "Kinyarwanda", 'pt' => "Ruanda", 'it' => "Kinyarwanda", 'fr' => "Rwanda"],
        'ky' => ['en' => "Kirghiz; Kyrgyz", 'es' => "Kirghizo", 'de' => "Kirgisisch", 'pt' => "Quiguiz", 'it' => "Kirghizo; Chirghiso", 'fr' => "Kirghiz"],
        'kv' => ['en' => "Komi", 'es' => "Komi", 'de' => "Komi", 'pt' => "Komi", 'it' => "Komi", 'fr' => "Komi"],
        'kg' => ['en' => "Kongo", 'es' => "Kongo", 'de' => "Kongo", 'pt' => "Congo", 'it' => "Kongo", 'fr' => "Kongo"],
        'ko' => ['en' => "Korean", 'es' => "Coreano", 'de' => "Koreanisch", 'pt' => "Coreano", 'it' => "Coreano", 'fr' => "Coréen"],
        'kj' => ['en' => "Kuanyama; Kwanyama", 'es' => "Kuanyama", 'de' => "Kwanyama", 'pt' => "Cuanhama", 'it' => "Kuanyama; Kwanyama", 'fr' => "Kuanyama"],
        'ku' => ['en' => "Kurdish", 'es' => "Kurdo", 'de' => "Kurdisch", 'pt' => "Curdo", 'it' => "Curdo", 'fr' => "Kurde"],
        'lo' => ['en' => "Lao", 'es' => "laosiano", 'de' => "Laotisch", 'pt' => "Laosiano", 'it' => "Lao", 'fr' => "Laotien"],
        'la' => ['en' => "Latin", 'es' => "Latín", 'de' => "Lateinisch", 'pt' => "Latim", 'it' => "Latino", 'fr' => "Latin"],
        'lv' => ['en' => "Latvian", 'es' => "Letón", 'de' => "Lettisch", 'pt' => "Letão", 'it' => "Lettone", 'fr' => "Letton"],
        'li' => ['en' => "Limburgan; Limburger; Limburgish", 'es' => "Limburgan; Limburger; Limburgish", 'de' => "Limburgisch", 'pt' => "Limburgês", 'it' => "Limburgan", 'fr' => "Limbourgeois"],
        'ln' => ['en' => "Lingala", 'es' => "Lingala", 'de' => "Lingala", 'pt' => "Lingala", 'it' => "Lingala", 'fr' => "Lingala"],
        'lt' => ['en' => "Lithuanian", 'es' => "Lituano", 'de' => "Litauisch", 'pt' => "Lituano", 'it' => "Lituano", 'fr' => "Lituanien"],
        'lb' => ['en' => "Luxembourgish; Letzeburgesch", 'es' => "Luxemburgués", 'de' => "Luxemburgisch; Lëtzebuergesch", 'pt' => "Luxemburguês", 'it' => "Lussemburghese", 'fr' => "Luxembourgeois"],
        'lu' => ['en' => "Luba-Katanga", 'es' => "Luba-Katanga", 'de' => "Luba-Katanga", 'pt' => "Baluba", 'it' => "Luba-katanga", 'fr' => "Luba-Katanga"],
        'lg' => ['en' => "Ganda", 'es' => "Ganda", 'de' => "Ganda", 'pt' => "Nganda", 'it' => "Ganda", 'fr' => "Ganda"],
        'mh' => ['en' => "Marshallese", 'es' => "Marshall", 'de' => "Marschallesisch", 'pt' => "Marshalês", 'it' => "Marshallese", 'fr' => "Marshall"],
        'ml' => ['en' => "Malayalam", 'es' => "malabar", 'de' => "Malayalam", 'pt' => "Malaiala", 'it' => "Malayalam", 'fr' => "Malayalam"],
        'mr' => ['en' => "Marathi", 'es' => "Marath", 'de' => "Marathi", 'pt' => "Marati", 'it' => "Marathi", 'fr' => "Marathe"],
        'mk' => ['en' => "Macedonian", 'es' => "Macedonio", 'de' => "Makedonisch", 'pt' => "Macedônio", 'it' => "Macedone", 'fr' => "Macédonien"],
        'mg' => ['en' => "Malagasy", 'es' => "Malgache", 'de' => "Malagasi", 'pt' => "Malgaxe", 'it' => "Malagasy", 'fr' => "Malgache"],
        'mt' => ['en' => "Maltese", 'es' => "Maltés", 'de' => "Maltesisch", 'pt' => "Maltês", 'it' => "Maltese", 'fr' => "Maltais"],
        'mn' => ['en' => "Mongolian", 'es' => "Mongol", 'de' => "Mongolisch", 'pt' => "Mongol", 'it' => "Mongolo", 'fr' => "Mongol"],
        'mi' => ['en' => "Maori", 'es' => "Maorí", 'de' => "Maori", 'pt' => "Maori", 'it' => "Maori", 'fr' => "Maori"],
        'ms' => ['en' => "Malay", 'es' => "Malayo", 'de' => "Malaiisch", 'pt' => "Malaio", 'it' => "Malay", 'fr' => "Malais"],
        'my' => ['en' => "Burmese", 'es' => "Birmano", 'de' => "Burmesisch", 'pt' => "Birmanês", 'it' => "Burmese", 'fr' => "Birman"],
        'na' => ['en' => "Nauru", 'es' => "Nauru", 'de' => "Nauruisch", 'pt' => "Nauru", 'it' => "Nauru", 'fr' => "Nauru"],
        'nv' => ['en' => "Navajo; Navaho", 'es' => "Navajo", 'de' => "Navajo", 'pt' => "Navarro", 'it' => "Navajo; Navaho", 'fr' => "Navaho"],
        'nr' => ['en' => "Ndebele, South; South Ndebele", 'es' => "Ndebele del Sur; Ndebele meridional", 'de' => "Süd-Ndebele", 'pt' => "Ndebele do Sul", 'it' => "Ndebele del Sud", 'fr' => "Ndébélé Du Sud"],
        'nd' => ['en' => "Ndebele, North; North Ndebele", 'es' => "Ndebele del Norte; Nbdele septentrional", 'de' => "Nord-Ndebele", 'pt' => "Ndebele do Norte", 'it' => "Ndebele del Nord", 'fr' => "Ndébélé Du Nord"],
        'ng' => ['en' => "Ndonga", 'es' => "Ndonga", 'de' => "Ndonga", 'pt' => "Ovampo", 'it' => "Ndonga", 'fr' => "Ndonga"],
        'ne' => ['en' => "Nepali", 'es' => "Nepalés", 'de' => "Nepali", 'pt' => "Nepali", 'it' => "Nepalese", 'fr' => "Népalais"],
        'nl' => ['en' => "Dutch; Flemish", 'es' => "Holandés, Flamenco", 'de' => "Niederländisch; Flämisch", 'pt' => "Holandês; Flamengo", 'it' => "Olandese; Fiammingo", 'fr' => "Néerlandais"],
        'nn' => ['en' => "Norwegian Nynorsk; Nynorsk, Norwegian", 'es' => "Noruego Nynorsk; Nynorsk, Noruego", 'de' => "Neu-Norwegisch", 'pt' => "Norueguês Nynorsk", 'it' => "Nynorsk norvegese", 'fr' => "Norvégien Nynorsk"],
        'nb' => ['en' => "Bokmål, Norwegian; Norwegian Bokmål", 'es' => "Bokmål, Norwegian; Norwegian Bokmål", 'de' => "Norwegisch (Bokmål)", 'pt' => "Norwegian Bokmål", 'it' => "Bokmål, norvegiese; Bokmål norvegese", 'fr' => "Norvégien Bokmål"],
        'no' => ['en' => "Norwegian", 'es' => "Noruego", 'de' => "Norwegisch", 'pt' => "Norueguês", 'it' => "Norvegese", 'fr' => "Norvégien"],
        'ny' => ['en' => "Chichewa; Chewa; Nyanja", 'es' => "Chewa; Chichewa; Nyanja", 'de' => "Chichewa; Chewa; Nyanja", 'pt' => "Cinianja", 'it' => "Chichewa; Chewa; Nyanja", 'fr' => "Nyanja"],
        'oc' => ['en' => "Occitan (post 1500); Provençal", 'es' => "Occitano (después de 1500); Provencal", 'de' => "Okzitanisch (nach 1500); Provenzalisch", 'pt' => "Occitâno (pós-1500); Provençal", 'it' => "Occitano (posteriore 1500); Provenzale", 'fr' => "Occitan (Après 1500) ; Provençal"],
        'oj' => ['en' => "Ojibwa", 'es' => "Ojibwa", 'de' => "Ojibwa", 'pt' => "Obíjua", 'it' => "Ojibwa", 'fr' => "Ojibwa"],
        'or' => ['en' => "Oriya", 'es' => "Oriya", 'de' => "Orija", 'pt' => "Oriá", 'it' => "Oriya", 'fr' => "Oriya"],
        'om' => ['en' => "Oromo", 'es' => "Oromo (Afan)", 'de' => "Oromo", 'pt' => "Oromo", 'it' => "Oromo", 'fr' => "Galla"],
        'os' => ['en' => "Ossetian; Ossetic", 'es' => "Ossetico", 'de' => "Ossetisch", 'pt' => "Osseta", 'it' => "Osseto", 'fr' => "Ossète"],
        'pa' => ['en' => "Panjabi; Punjabi", 'es' => "Panyabí; Penyabí", 'de' => "Panjabi", 'pt' => "Panjabi", 'it' => "Panjabi; Punjabi", 'fr' => "Pendjabi"],
        'pi' => ['en' => "Pali", 'es' => "Pali", 'de' => "Pali", 'pt' => "Páli", 'it' => "Pali", 'fr' => "Pali"],
        'pl' => ['en' => "Polish", 'es' => "Polaco", 'de' => "Polnisch", 'pt' => "Polonês", 'it' => "Polacco", 'fr' => "Polonais"],
        'pt' => ['en' => "Portuguese", 'es' => "Portugués", 'de' => "Portugiesisch", 'pt' => "Português", 'it' => "Portoghese", 'fr' => "Portugais"],
        'ps' => ['en' => "Pushto; Pashto", 'es' => "Pashtún", 'de' => "Paschtunisch", 'pt' => "Pachto", 'it' => "Pasthu; Afgano", 'fr' => "Pachto"],
        'qu' => ['en' => "Quechua", 'es' => "Quechua", 'de' => "Quechua", 'pt' => "Quíchua", 'it' => "Quechua", 'fr' => "Quechua"],
        'rm' => ['en' => "Romansh", 'es' => "Romaní", 'de' => "Bündnerromanisch", 'pt' => "Romanche", 'it' => "Romansh", 'fr' => "Romanche"],
        'ro' => ['en' => "Romanian; Moldavian; Moldovan", 'es' => "Moldavo", 'de' => "Rumänisch; Moldawisch", 'pt' => "Romeno; Moldávio; Moldavo", 'it' => "Romeno; Moldavo", 'fr' => "Roumain ; Moldave"],
        'rn' => ['en' => "Rundi", 'es' => "Kiroundi", 'de' => "Kirundi", 'pt' => "Kirundi", 'it' => "Rundi", 'fr' => "Rundi"],
        'ru' => ['en' => "Russian", 'es' => "Ruso", 'de' => "Russisch", 'pt' => "Russo", 'it' => "Russo", 'fr' => "Russe"],
        'sg' => ['en' => "Sango", 'es' => "Sango", 'de' => "Sango", 'pt' => "Sango", 'it' => "Sango", 'fr' => "Sango"],
        'sa' => ['en' => "Sanskrit", 'es' => "Sánscrito", 'de' => "Sanskrit", 'pt' => "Sânscrito", 'it' => "Sanscrito", 'fr' => "Sanskrit"],
        'si' => ['en' => "Sinhala; Sinhalese", 'es' => "Singala; Cingalés", 'de' => "Singhalesisch", 'pt' => "Cingalês", 'it' => "Sinhala; Sinhalese", 'fr' => "Singhalais"],
        'sk' => ['en' => "Slovak", 'es' => "Eslovaco", 'de' => "Slowakisch", 'pt' => "Eslovaco", 'it' => "Slovacco", 'fr' => "Slovaque"],
        'sl' => ['en' => "Slovenian", 'es' => "Esloveno", 'de' => "Slowenisch", 'pt' => "Esloveno", 'it' => "Sloveno", 'fr' => "Slovène"],
        'se' => ['en' => "Northern Sami", 'es' => "Sami del Norte", 'de' => "Nord-Samisch", 'pt' => "Sami do norte", 'it' => "Sami del Nord", 'fr' => "Sami Du Nord"],
        'sm' => ['en' => "Samoan", 'es' => "Samoano", 'de' => "Samoanisch", 'pt' => "Samoano", 'it' => "Samoano", 'fr' => "Samoan"],
        'sn' => ['en' => "Shona", 'es' => "Shona", 'de' => "Schona", 'pt' => "Xona", 'it' => "Shona", 'fr' => "Shona"],
        'sd' => ['en' => "Sindhi", 'es' => "Sindhi", 'de' => "Sindhi", 'pt' => "Síndi", 'it' => "Sindhi", 'fr' => "Sindhi"],
        'so' => ['en' => "Somali", 'es' => "Somalí", 'de' => "Somali", 'pt' => "Somali", 'it' => "Somalo", 'fr' => "Somali"],
        'st' => ['en' => "Sotho, Southern", 'es' => "Sotho del Sur", 'de' => "Sotho (Süd)", 'pt' => "Sesoto do Sul", 'it' => "Sotho del Sud", 'fr' => "Sotho Du Sud"],
        'es' => ['en' => "Spanish", 'es' => "Español", 'de' => "Spanisch", 'pt' => "Espanhol", 'it' => "Spagnolo", 'fr' => "Castillan"],
        'sq' => ['en' => "Albanian", 'es' => "Albanés", 'de' => "Albanisch", 'pt' => "Albanês", 'it' => "Albanese", 'fr' => "Albanais"],
        'sc' => ['en' => "Sardinian", 'es' => "Sardo", 'de' => "Sardisch", 'pt' => "Sardo", 'it' => "Sardo", 'fr' => "Sarde"],
        'sr' => ['en' => "Serbian", 'es' => "Serbio", 'de' => "Serbisch", 'pt' => "Sérvio", 'it' => "Serbo", 'fr' => "Serbe"],
        'ss' => ['en' => "Swati", 'es' => "Siswati", 'de' => "Swazi", 'pt' => "Swati", 'it' => "Swati", 'fr' => "Swati"],
        'su' => ['en' => "Sundanese", 'es' => "Sundanés", 'de' => "Sundanesisch", 'pt' => "Sundanês", 'it' => "Sundanese", 'fr' => "Sundanais"],
        'sw' => ['en' => "Swahili", 'es' => "Swahili", 'de' => "Suaheli; Swaheli", 'pt' => "Suaíli", 'it' => "Swahili", 'fr' => "Swahili"],
        'sv' => ['en' => "Swedish", 'es' => "Sueco", 'de' => "Schwedisch", 'pt' => "Sueco", 'it' => "Svedese", 'fr' => "Suédois"],
        'ty' => ['en' => "Tahitian", 'es' => "Tahitiano", 'de' => "Tahitisch", 'pt' => "Taitiano", 'it' => "Thaitiano", 'fr' => "Tahitien"],
        'ta' => ['en' => "Tamil", 'es' => "Tamil", 'de' => "Tamilisch", 'pt' => "Tâmil", 'it' => "Tamil", 'fr' => "Tamoul"],
        'tt' => ['en' => "Tatar", 'es' => "Tataro", 'de' => "Tatarisch", 'pt' => "Tártaro", 'it' => "Tatarico", 'fr' => "Tatar"],
        'te' => ['en' => "Telugu", 'es' => "Telougou", 'de' => "Telugu", 'pt' => "Télugo", 'it' => "Telugu", 'fr' => "Télougou"],
        'tg' => ['en' => "Tajik", 'es' => "Tajiko", 'de' => "Tadschikisch", 'pt' => "Tadjique", 'it' => "Tajik", 'fr' => "Tadjik"],
        'tl' => ['en' => "Tagalog", 'es' => "Tagalo", 'de' => "Tagalog", 'pt' => "Tagalo", 'it' => "Tagalog", 'fr' => "Tagalog"],
        'th' => ['en' => "Thai", 'es' => "Tailandés (thai)", 'de' => "Thai", 'pt' => "Tailandês", 'it' => "Thailandese", 'fr' => "Thaï"],
        'ti' => ['en' => "Tigrinya", 'es' => "Tigrinya", 'de' => "Tigrinja", 'pt' => "Tigrínia", 'it' => "Tigrinya", 'fr' => "Tigrigna"],
        'to' => ['en' => "Tonga (Tonga Islands)", 'es' => "Tonga (Islas Tonga)", 'de' => "Tonga (Tonga-Inseln)", 'pt' => "Tonga", 'it' => "Tonga (Isole Tonga)", 'fr' => "Tongan (Îles Tonga)"],
        'tn' => ['en' => "Tswana", 'es' => "Setchwana", 'de' => "Tswana", 'pt' => "Tsuana", 'it' => "Tswana", 'fr' => "Tswana"],
        'ts' => ['en' => "Tsonga", 'es' => "Tsonga", 'de' => "Tsonga", 'pt' => "Tsonga", 'it' => "Tsonga", 'fr' => "Tsonga"],
        'tk' => ['en' => "Turkmen", 'es' => "Turkmeno", 'de' => "Turkmenisch", 'pt' => "Turcomeno", 'it' => "Turcmeno", 'fr' => "Turkmène"],
        'tr' => ['en' => "Turkish", 'es' => "Turco", 'de' => "Türkisch", 'pt' => "Turco", 'it' => "Turco", 'fr' => "Turc"],
        'tw' => ['en' => "Twi", 'es' => "Tchi", 'de' => "Twi", 'pt' => "Twi", 'it' => "Twi", 'fr' => "Twi"],
        'ug' => ['en' => "Uighur; Uyghur", 'es' => "Uiguro", 'de' => "Uigurisch", 'pt' => "Uigur", 'it' => "Uighur", 'fr' => "Ouïgour"],
        'uk' => ['en' => "Ukrainian", 'es' => "Ukranio", 'de' => "Ukrainisch", 'pt' => "Ucraniano", 'it' => "Ucraino", 'fr' => "Ukrainien"],
        'ur' => ['en' => "Urdu", 'es' => "Urdu", 'de' => "Urdu", 'pt' => "Urdu", 'it' => "Urdu", 'fr' => "Ourdou"],
        'uz' => ['en' => "Uzbek", 'es' => "Uzbeko", 'de' => "Usbekisch", 'pt' => "Uzbeque", 'it' => "Uzbeco", 'fr' => "Ouszbek"],
        've' => ['en' => "Venda", 'es' => "Venda", 'de' => "Venda", 'pt' => "Venda", 'it' => "Venda", 'fr' => "Venda"],
        'vi' => ['en' => "Vietnamese", 'es' => "Vietnamita", 'de' => "Vietnamesisch", 'pt' => "Vietnamita", 'it' => "Vietnamita", 'fr' => "Vietnamien"],
        'vo' => ['en' => "Volapük", 'es' => "Volapük", 'de' => "Volapük", 'pt' => "Volapuque", 'it' => "Volapük", 'fr' => "Volapük"],
        'wa' => ['en' => "Walloon", 'es' => "valón", 'de' => "Wallonisch", 'pt' => "Valão", 'it' => "Vallone", 'fr' => "Wallon"],
        'wo' => ['en' => "Wolof", 'es' => "Wolof", 'de' => "Wolof", 'pt' => "Uólofe", 'it' => "Volof", 'fr' => "Wolof"],
        'xh' => ['en' => "Xhosa", 'es' => "Xhosa", 'de' => "Xhosa", 'pt' => "Xhosa", 'it' => "Xhosa", 'fr' => "Xhosa"],
        'yi' => ['en' => "Yiddish", 'es' => "Yidish", 'de' => "Jiddisch", 'pt' => "Iídiche", 'it' => "Yiddish", 'fr' => "Yiddish"],
        'yo' => ['en' => "Yoruba", 'es' => "Yoruba", 'de' => "Joruba", 'pt' => "Ioruba", 'it' => "Yoruba", 'fr' => "Yoruba"],
        'za' => ['en' => "Zhuang; Chuang", 'es' => "Zhuang; Chuang", 'de' => "Zhuang", 'pt' => "Zhuang; Chuang", 'it' => "Zhuang; Chuang", 'fr' => "Zhuang"],
        'zh' => ['en' => "Chinese", 'es' => "Chino", 'de' => "Chinesisch", 'pt' => "Chinês", 'it' => "Cinese", 'fr' => "Chinois"],
        'zu' => ['en' => "Zulu", 'es' => "Zulu", 'de' => "Zulu", 'pt' => "Zulu", 'it' => "Zulu", 'fr' => "Zoulou"],
    ];

    /**
     * Fetch value options for a Select form element
     * @param string $inLanguage
     * @return string[]
     */
    public function getLanguageNames($inLanguage = 'en')
    {
        if (! isset($inLanguage) || ! in_array($inLanguage, $this->supportedLanguages, true)) {
            $inLanguage = 'en';
        }
        $result = [];
        foreach ($this->languages as $language => $names) {
            $result[$language] = $names[$inLanguage];
        }
        return $result;
    }

    /**
     * Get the name of a language, in the language specified.
     * If the language requested doesn't exist, null is returned
     * @param string $language
     * @param string $inLanguage
     * @throws \InvalidArgumentException
     * @return NULL|string
     */
    public function getLanguageName($language, $inLanguage = 'en')
    {
        if (! isset($language) || ! is_string($language)) {
            throw new \InvalidArgumentException('Language parameter should be a string');
        }
        if (! isset($this->languages[$language])) {
            return null;
        }
        if (! isset($inLanguage) || ! in_array($inLanguage, $this->supportedLanguages, true)) {
            $inLanguage = 'en';
        }
        return $this->languages[$language][$inLanguage];
    }

    /**
     * Return an array of known language codes
     * @return string[]
     */
    public function getValidLanguages()
    {
        return array_keys($this->languages);
    }

    /**
     * Return an associative array mapping language code to
     * another associative array of names keyed by the inLanguage.
     * inLanguage refers to the language in which the language's name is in.
     * @return string[][]
     */
    public function getLanguageNamesData()
    {
        return $this->languages;
    }
}
