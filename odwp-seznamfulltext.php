<?php
/**
 * Plugin Name: Seznam Fulltext Plugin
 * Plugin URI: https://bitbucket.com/ondrejd/odwp-seznamfulltext
 * Description: Plugin pro WordPress, který umožňuje snadnější registraci obsahu v rámci fulltext vyhledávače Seznam.cz.
 * Version: 0.1.0
 * Author: Ondřej Doněk
 * Author URI: http://ondrejdonek.blogspot.cz/
 * Requires at least: 4.3
 * Tested up to: 4.3.1
 *
 * Text Domain: odwp-seznamfulltext
 * Domain Path: /languages/
 *
 * @author Ondřej Doněk, <ondrejd@gmail.com>
 * @link https://bitbucket.com/ondrejd/odwp-seznamfulltext for the canonical source repository
 * @license https://www.mozilla.org/MPL/2.0/ Mozilla Public License 2.0
 * @package odwp-seznamfulltext
 */


// Disable direct calling...
if (!defined('WPINC')) {
	die;
}


defined('ODWP_SEZNAMFULLTEXT') || define('ODWP_SEZNAMFULLTEXT', 'odwp-seznamfulltext');
defined('ODWP_SEZNAMFULLTEXT_FILE') || define('ODWP_SEZNAMFULLTEXT_FILE', __FILE__);
defined('ODWP_SEZNAMFULLTEXT_URL') || define('ODWP_SEZNAMFULLTEXT_URL', plugin_dir_url(__FILE__));
defined('ODWP_SEZNAMFULLTEXT_VERSION') || define('ODWP_SEZNAMFULLTEXT_VERSION', '0.1.0');

// Initialize the plugin
require_once (plugin_dir_path(__FILE__) . 'src/ODWP_SeznamFulltext.php');
add_action('plugins_loaded', array('ODWP_SeznamFulltext', 'get_instance'));
