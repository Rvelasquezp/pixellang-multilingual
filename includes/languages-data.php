<?php
/**
 * Catalog of languages that can be activated.
 *
 * @package PixelLang_Multilingual
 */

defined( 'ABSPATH' ) || exit;

/**
 * Master list of languages offered in the Languages screen.
 *
 * Keys are ISO 639-1 codes. `label` is the native name, `english` the English
 * name (used for search), `rtl` flags right-to-left scripts. `flag` is kept for
 * backward compatibility with code reading this array; the admin UI no longer
 * relies on emoji flags.
 *
 * @return array<string, array{label:string, locale:string, english:string, flag:string, rtl:bool}>
 */
function wpm_get_available_languages() {
	static $languages = null;
	if ( null !== $languages ) {
		return $languages;
	}
	$languages = array(
		'fr' => array( 'label' => 'Français', 'locale' => 'fr_FR', 'english' => 'French', 'flag' => '🇫🇷', 'rtl' => false ),
		'en' => array( 'label' => 'English', 'locale' => 'en_US', 'english' => 'English', 'flag' => '🇺🇸', 'rtl' => false ),
		'es' => array( 'label' => 'Español', 'locale' => 'es_ES', 'english' => 'Spanish', 'flag' => '🇪🇸', 'rtl' => false ),
		'de' => array( 'label' => 'Deutsch', 'locale' => 'de_DE', 'english' => 'German', 'flag' => '🇩🇪', 'rtl' => false ),
		'it' => array( 'label' => 'Italiano', 'locale' => 'it_IT', 'english' => 'Italian', 'flag' => '🇮🇹', 'rtl' => false ),
		'pt' => array( 'label' => 'Português', 'locale' => 'pt_PT', 'english' => 'Portuguese', 'flag' => '🇵🇹', 'rtl' => false ),
		'nl' => array( 'label' => 'Nederlands', 'locale' => 'nl_NL', 'english' => 'Dutch', 'flag' => '🇳🇱', 'rtl' => false ),
		'ru' => array( 'label' => 'Русский', 'locale' => 'ru_RU', 'english' => 'Russian', 'flag' => '🇷🇺', 'rtl' => false ),
		'zh' => array( 'label' => '中文', 'locale' => 'zh_CN', 'english' => 'Chinese', 'flag' => '🇨🇳', 'rtl' => false ),
		'ja' => array( 'label' => '日本語', 'locale' => 'ja', 'english' => 'Japanese', 'flag' => '🇯🇵', 'rtl' => false ),
		'ko' => array( 'label' => '한국어', 'locale' => 'ko_KR', 'english' => 'Korean', 'flag' => '🇰🇷', 'rtl' => false ),
		'ar' => array( 'label' => 'العربية', 'locale' => 'ar', 'english' => 'Arabic', 'flag' => '🇸🇦', 'rtl' => true ),
		'hi' => array( 'label' => 'हिन्दी', 'locale' => 'hi_IN', 'english' => 'Hindi', 'flag' => '🇮🇳', 'rtl' => false ),
		'tr' => array( 'label' => 'Türkçe', 'locale' => 'tr_TR', 'english' => 'Turkish', 'flag' => '🇹🇷', 'rtl' => false ),
		'pl' => array( 'label' => 'Polski', 'locale' => 'pl_PL', 'english' => 'Polish', 'flag' => '🇵🇱', 'rtl' => false ),
		'sv' => array( 'label' => 'Svenska', 'locale' => 'sv_SE', 'english' => 'Swedish', 'flag' => '🇸🇪', 'rtl' => false ),
		'da' => array( 'label' => 'Dansk', 'locale' => 'da_DK', 'english' => 'Danish', 'flag' => '🇩🇰', 'rtl' => false ),
		'nb' => array( 'label' => 'Norsk', 'locale' => 'nb_NO', 'english' => 'Norwegian', 'flag' => '🇳🇴', 'rtl' => false ),
		'fi' => array( 'label' => 'Suomi', 'locale' => 'fi', 'english' => 'Finnish', 'flag' => '🇫🇮', 'rtl' => false ),
		'cs' => array( 'label' => 'Čeština', 'locale' => 'cs_CZ', 'english' => 'Czech', 'flag' => '🇨🇿', 'rtl' => false ),
		'sk' => array( 'label' => 'Slovenčina', 'locale' => 'sk_SK', 'english' => 'Slovak', 'flag' => '🇸🇰', 'rtl' => false ),
		'hu' => array( 'label' => 'Magyar', 'locale' => 'hu_HU', 'english' => 'Hungarian', 'flag' => '🇭🇺', 'rtl' => false ),
		'ro' => array( 'label' => 'Română', 'locale' => 'ro_RO', 'english' => 'Romanian', 'flag' => '🇷🇴', 'rtl' => false ),
		'bg' => array( 'label' => 'Български', 'locale' => 'bg_BG', 'english' => 'Bulgarian', 'flag' => '🇧🇬', 'rtl' => false ),
		'hr' => array( 'label' => 'Hrvatski', 'locale' => 'hr', 'english' => 'Croatian', 'flag' => '🇭🇷', 'rtl' => false ),
		'uk' => array( 'label' => 'Українська', 'locale' => 'uk', 'english' => 'Ukrainian', 'flag' => '🇺🇦', 'rtl' => false ),
		'el' => array( 'label' => 'Ελληνικά', 'locale' => 'el', 'english' => 'Greek', 'flag' => '🇬🇷', 'rtl' => false ),
		'he' => array( 'label' => 'עברית', 'locale' => 'he_IL', 'english' => 'Hebrew', 'flag' => '🇮🇱', 'rtl' => true ),
		'fa' => array( 'label' => 'فارسی', 'locale' => 'fa_IR', 'english' => 'Persian', 'flag' => '🇮🇷', 'rtl' => true ),
		'id' => array( 'label' => 'Bahasa Indonesia', 'locale' => 'id_ID', 'english' => 'Indonesian', 'flag' => '🇮🇩', 'rtl' => false ),
		'ms' => array( 'label' => 'Bahasa Melayu', 'locale' => 'ms_MY', 'english' => 'Malay', 'flag' => '🇲🇾', 'rtl' => false ),
		'th' => array( 'label' => 'ภาษาไทย', 'locale' => 'th', 'english' => 'Thai', 'flag' => '🇹🇭', 'rtl' => false ),
		'vi' => array( 'label' => 'Tiếng Việt', 'locale' => 'vi', 'english' => 'Vietnamese', 'flag' => '🇻🇳', 'rtl' => false ),
		'ca' => array( 'label' => 'Català', 'locale' => 'ca', 'english' => 'Catalan', 'flag' => '🏳️', 'rtl' => false ),
		'eu' => array( 'label' => 'Euskara', 'locale' => 'eu', 'english' => 'Basque', 'flag' => '🏳️', 'rtl' => false ),
		'gl' => array( 'label' => 'Galego', 'locale' => 'gl_ES', 'english' => 'Galician', 'flag' => '🏳️', 'rtl' => false ),
		'lt' => array( 'label' => 'Lietuvių', 'locale' => 'lt_LT', 'english' => 'Lithuanian', 'flag' => '🇱🇹', 'rtl' => false ),
		'lv' => array( 'label' => 'Latviešu', 'locale' => 'lv', 'english' => 'Latvian', 'flag' => '🇱🇻', 'rtl' => false ),
		'et' => array( 'label' => 'Eesti', 'locale' => 'et', 'english' => 'Estonian', 'flag' => '🇪🇪', 'rtl' => false ),
		'sr' => array( 'label' => 'Српски', 'locale' => 'sr_RS', 'english' => 'Serbian', 'flag' => '🇷🇸', 'rtl' => false ),
		'sl' => array( 'label' => 'Slovenščina', 'locale' => 'sl_SI', 'english' => 'Slovenian', 'flag' => '🇸🇮', 'rtl' => false ),
		'sq' => array( 'label' => 'Shqip', 'locale' => 'sq', 'english' => 'Albanian', 'flag' => '🇦🇱', 'rtl' => false ),
		'mk' => array( 'label' => 'Македонски', 'locale' => 'mk_MK', 'english' => 'Macedonian', 'flag' => '🇲🇰', 'rtl' => false ),
		'af' => array( 'label' => 'Afrikaans', 'locale' => 'af', 'english' => 'Afrikaans', 'flag' => '🇿🇦', 'rtl' => false ),
		'sw' => array( 'label' => 'Kiswahili', 'locale' => 'sw', 'english' => 'Swahili', 'flag' => '🇰🇪', 'rtl' => false ),
		'bn' => array( 'label' => 'বাংলা', 'locale' => 'bn_BD', 'english' => 'Bengali', 'flag' => '🇧🇩', 'rtl' => false ),
		'ur' => array( 'label' => 'اردو', 'locale' => 'ur', 'english' => 'Urdu', 'flag' => '🇵🇰', 'rtl' => true ),
		'ta' => array( 'label' => 'தமிழ்', 'locale' => 'ta_IN', 'english' => 'Tamil', 'flag' => '🇮🇳', 'rtl' => false ),
	);

	/**
	 * Filters the catalog of languages offered in the admin.
	 *
	 * @param array $languages Catalog keyed by language code.
	 */
	$languages = apply_filters( 'wpm_available_languages', $languages );
	return $languages;
}
