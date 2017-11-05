<?php
/**
 * Seznam Fulltext Plugin
 *
 * @author Ondřej Doněk, <ondrejd@gmail.com>
 * @link https://bitbucket.com/ondrejd/odwp-seznamfulltext for the canonical source repository
 * @license https://www.mozilla.org/MPL/2.0/ Mozilla Public License 2.0
 * @package odwp-seznamfulltext
 */

if (!class_exists('ODWP_SeznamFulltext')):

/**
 * Main class of the plug-in.
 *
 * @since 0.1.0
 */
class ODWP_SeznamFulltext {
  /**
   * Modes used to identify which posts/pages should be registered.
   */
  const MODE_ALL    = 'all';
  const MODE_POSTS  = 'posts';
  const MODE_PAGES  = 'pages';
  const MODE_CUSTOM = 'custom';
  const MODE_NOAUTO = 'noauto';

  /**
   * URL of registration page of Seznam fulltext engine.
   */
  const SEZNAM_URL  = '';

  /**
   * Unique identifier for the plugin.
   * @var string $plugin_slug
   */
  protected $plugin_slug = ODWP_SEZNAMFULLTEXT;

  /**
   * Holds URL to the plugin.
   * @var string $plugin_url
   */
  protected $plugin_url = ODWP_SEZNAMFULLTEXT_URL;

  /**
   * Version of the the plugin.
   * @var string $plugin_version
   */
  protected $plugin_version = ODWP_SEZNAMFULLTEXT_VERSION;

  /**
   * Default plugin options.
   * @var array $default_options
   */
  protected $default_options = array(
    // Registration mode, one of 'all', 'posts', 'pages', 'custom', 'noauto'.
    'mode' => 'all',
    // Custom registration mode "posts by category", comma-separated IDs of categories.
    'mode_custom_pbc' => '',
    // Custom registration mode "posts by tags", comma-separated tags
    'mode_custom_pbt' => '',
    // Custom registration mode "pages by parent", comma-separated IDs of pages
    'mode_custom_pbp' => '',
    // Count of repeats of registration attempts.
    'repeats'  => 3,
    // Registration mode for WooCommerce, just `true` or `false`.
    'mode_wc' => false
  );

  /**
   * Holds instance of class self. Part of singleton implementation.
   * @var ODWP_SeznamFulltext $instance
   */
  private static $instance;

  /**
   * Returns instance of class self. Part of singleton implementation.
   *
   * @return ODWP_SeznamFulltext
   */
  public static function get_instance() {
    if (is_null(self::$instance)) {
      self::$instance = new ODWP_SeznamFulltext();
    }

    return self::$instance;
  } // end get_instance()

  /**
   * Initializes the plugin.
   *
   * @return void
   * @uses add_action()
   * @uses add_filter()
   * @uses is_admin()
   * @uses register_activation_hook()
   * @uses register_deactivation_hook()
   * @uses wp_get_theme()
   */
  private function __construct() {
    add_action('init', array($this, 'load_plugin_textdomain'));

    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));

    if (is_admin()) {
      add_action('admin_print_styles', array($this, 'admin_css'));
      add_action('admin_print_scripts', array($this, 'admin_js'));
      add_action('admin_menu', array($this, 'admin_menu'));

      // Posts listing
      add_filter('manage_posts_columns', array($this, 'custom_column_header'));
      add_action('manage_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
      add_filter('manage_pages_columns', array($this, 'custom_column_header'));
      add_action('manage_pages_custom_column', array($this, 'custom_column_content'), 10, 2);
      //add_filter('manage_edit-post_sortable_columns', array($this, 'post_table_sorting'));
      //add_filter('request', array($this, 'is_registered_column_orderby'));
      //add_action('restrict_manage_posts', array($this, 'post_table_filtering'));

      // Add/edit posts
      add_action('add_meta_boxes', array($this, 'add_meta_box'), 11);
      add_action('save_post', array($this, 'save_post'), 10, 2);
      add_action('wp_ajax_seznamfulltext_register_content', array($this, 'register_content_ajax'));
    }
  } // end __construct()

  /**
   * Load the plugin text domain for translation.
   *
   * @return void
   * @uses get_locale()
   * @uses load_plugin_textdomain
   */
  public function load_plugin_textdomain() {
    load_plugin_textdomain(
      $this->plugin_slug,
      false,
      $this->plugin_slug . '/languages'
    );
  } // end load_plugin_textdomain()

  /**
   * Activates the plugin.
   * 
   * @returns void
   */
  public function activate() {
    // Ensure that plugin's options are initialized
    $options = $this->get_options();
  } // end activate()

  /**
   * Deactivates the plugin.
   * 
   * @returns void
   */
  public function deactivate() {
    // Nothing to do now...
  } // end deactivate()
  
  /**
   * Returns plugin's options
   *
   * @return array
   * @uses get_option()
   * @uses update_option()
   */
  function get_options() {
    $options = get_option($this->plugin_slug . '-options');
    $need_update = false;

    if ($options === false) {
      $need_update = true;
      $options = array();
    }

    foreach ($this->default_options as $key => $value) {
      if (!array_key_exists($key, $options)) {
        $options[$key] = $value;
      }
    }

    if (!array_key_exists('latest_used_version', $options)) {
      $options['latest_used_version'] = $this->plugin_version;
      $need_update = true;
    }

    if($need_update === true) {
      update_option($this->plugin_slug . '-options', $options);
    }

    return $options;
  } // end get_options()

  /**
   * Returns count how many times was the content registered.
   *
   * @param integer $post_id
   * @return integer
   * @since 0.1.0
   * @uses get_post_meta()
   */
  public function get_registrations_count($post_id) {
    $count = (int) get_post_meta($post_id, 'is_registered_count', true);
    return $count;
  } // end get_registrations_count($post_id)

  /**
   * Returns if content should be skipped from registration.
   *
   * @param integer $post_id
   * @return boolean
   * @since 0.1.0
   * @uses get_post_meta()
   *
   * @todo Returns `TRUE` also if content should be skipped according to user settings.
   */
  public function get_skip_registration($post_id) {
    $skip = (bool) get_post_meta($post_id, 'skip_registration', true);
    return $skip;
  } // end get_skip_registration($post_id)

  /**
   * Append our script to the WordPress administration.
   *
   * @return void
   * @since 0.1.0
   * @uses wp_register_script()
   * @uses wp_localize_script()
   * @uses wp_enqueue_script()
   */
  public function admin_js() {
    $script_id = $this->plugin_slug . '_script';

    wp_register_script(
      $script_id,
      $this->plugin_url . '/js/admin.js',
      array('jquery')
    );

    wp_localize_script(
      $script_id,
      'seznamfulltextAjax',
      array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'slug' => $this->plugin_slug,
        'msg' => __('Při registraci obsahu nastala chyba.', $this->plugin_slug)
      )
    );

    wp_enqueue_script('jquery');
    wp_enqueue_script($script_id);
  } // end admin_js()

  /**
   * Append our stylesheet to the WordPress administration.
   *
   * @return void
   * @since 0.1.0
   * @uses wp_enqueue_style()
   */
  public function admin_css() {
    wp_enqueue_style('admin_css', $this->plugin_url . '/css/admin.css');
  } // end admin_css()

  /**
   * Register plugin options page in WordPress admin.
   *
   * @return void
   * @since 0.1.0
   * @uses add_options_page()
   */
  public function admin_menu() {
      add_options_page(
        __('Nastavení pluginu Seznam Fulltext', $this->plugin_slug),
        __('Seznam Fulltext', $this->plugin_slug),
        'manage_options',
        $this->plugin_slug . '-options',
        array($this, 'admin_options_page')
      );
  } // end admin_menu()

  /**
   * Register plugin options page in WordPress admin.
   *
   * @return void
   * @since 0.1.0
   * @uses wp_verify_nonce()
   * @uses update_option()
   * @uses wp_nonce_field()
   *
   * @todo Move JavaScript to `js/admin.js`.
   */
  public function admin_options_page() {
    $options = $this->get_options();
    $need_update = false;

    if (
      filter_input(INPUT_POST, $this->plugin_slug . '_submit') &&
      (bool) wp_verify_nonce(filter_input(INPUT_POST, $this->plugin_slug . '_nonce')) === true
    ) {
      $need_update = true;

      // overall mode
      $mode = filter_input(INPUT_POST, $this->plugin_slug . '_mode');
      switch ($mode) {
        case self::MODE_ALL:
        case self::MODE_POSTS:
        case self::MODE_PAGES:
          $options['mode'] = $mode;
          $options['mode_custom_pbc'] = '';
          $options['mode_custom_pbt'] = '';
          $options['mode_custom_pbp'] = '';
          break;

        case self::MODE_CUSTOM:
          $options['mode'] = $mode;
          // posts by category
          if (filter_input(INPUT_POST, $this->plugin_slug . '_mode_custom_pbc_ch') == 'on') {
            $options['mode_custom_pbc'] = join(',', $_POST[$this->plugin_slug . '_mode_custom_pbc']);
          } else {
            $options['mode_custom_pbc'] = '';
          }
          // posts by tag
          if (filter_input(INPUT_POST, $this->plugin_slug . '_mode_custom_pbt_ch') == 'on') {
            $options['mode_custom_pbt'] = join(',', $_POST[$this->plugin_slug . '_mode_custom_pbt']);
          } else {
            $options['mode_custom_pbt'] = '';
          }
          // pages by parent
          if (filter_input(INPUT_POST, $this->plugin_slug . '_mode_custom_pbp_ch') == 'on') {
            $options['mode_custom_pbp'] = join(',', $_POST[$this->plugin_slug . '_mode_custom_pbp']);
          } else {
            $options['mode_custom_pbp'] = '';
          }
          break;
      }

      // count of repeats
      $options['repeats'] = filter_input(INPUT_POST, $this->plugin_slug . '_repeats');

      // register WooCommerce products?
      if (filter_input(INPUT_POST, $this->plugin_slug . '_wc') == 'on') {
        $options['mode_wc'] = true;
      } else {
        $options['mode_wc'] = false;
      }
    }

    if ($need_update === true) {
      update_option($this->plugin_slug . '-options', $options);
    }
?>
<div class="wrap">
  <h1><?= __('Nastavení pluginu Seznam Fulltext', $this->plugin_slug)?></h1>
  <?php if ($need_update === true):?>
  <div id="'.ODWP_WC_SIMPLESTATS.'_message2" class="updated notice is-dismissible">
    <p><?= __('Nastavení pluginu bylo úspěšně aktualizováno.', ODWP_WC_SIMPLESTATS)?></p>
  </div>
  <?php endif?>
  <form name="<?= $this->plugin_slug?>_form" id="<?= $this->plugin_slug?>_form" action="<?= esc_url(admin_url('options-general.php?page=' . $this->plugin_slug . '-options'))?>" method="post" novalidate>
    <?= wp_nonce_field(-1, $this->plugin_slug . '_nonce', true, false)?>
    <h3 class="title"><?= __('Hlavní volby', $this->plugin_slug)?></h3>
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">
            <label for="<?= $this->plugin_slug?>_mode"><?= __('Registrovat obsah', $this->plugin_slug)?></label>
          </th>
          <td>
            <fieldset>
              <legend><!-- class="screen-reader-text"-->
                <span><?= __('Vyberte obsah, který má podléhat registraci u fultextového vyhledávače <b>Seznam.cz</b>.', $this->plugin_slug)?></span>
              </legend>
              <div>
                <label title="<?= __('Všechny příspěvky i stránky', $this->plugin_slug)?>" class="register_mode">
                  <input type="radio" name="<?= $this->plugin_slug?>_mode" id="<?= $this->plugin_slug?>_mode" value="all"<?= ($options['mode'] == self::MODE_ALL) ? ' checked="checked"' : ''?>>
                  <?= __('Všechny příspěvky i stránky', $this->plugin_slug)?>
                  <p class="inner-description-para">
                    <span class="description"><?= __('Registraci u vyhledávače budou podléhat příspěvky i stránky.', $this->plugin_slug)?></span>
                  </p>
                </label>
              </div>
              <div>
                <label title="<?= __('Pouze příspěvky', $this->plugin_slug)?>" class="register_mode">
                  <input type="radio" name="<?= $this->plugin_slug?>_mode" value="posts"<?= ($options['mode'] == self::MODE_POSTS) ? ' checked="checked"' : ''?>>
                  <?= __('Pouze příspěvky', $this->plugin_slug)?>
                  <p class="inner-description-para">
                    <span class="description"><?= __('Registraci u vyhledávače budou podléhat pouze příspěvky.', $this->plugin_slug)?></span>
                  </p>
                </label>
              </div>
              <div>
                <label title="<?= __('Pouze stránky', $this->plugin_slug)?>" class="register_mode">
                  <input type="radio" name="<?= $this->plugin_slug?>_mode" value="pages"<?= ($options['mode'] == self::MODE_PAGES) ? ' checked="checked"' : ''?>>
                  <?= __('Pouze stránky', $this->plugin_slug)?>
                  <p class="inner-description-para">
                    <span class="description"><?= __('Registraci u vyhledávače budou podléhat pouze stránky.', $this->plugin_slug)?></span>
                  </p>
                </label>
              </div>
              <!-- _mode_custom -->
              <div>
                <?php $custom_disabled = ($options['mode'] != self::MODE_CUSTOM) ? ' disabled' : '';?>
                <label title="<?= __('Stránky či příspěvky odpovídající filtru:', $this->plugin_slug)?>" class="register_mode">
                  <input type="radio" name="<?= $this->plugin_slug?>_mode" value="custom"<?= ($options['mode'] == self::MODE_CUSTOM) ? ' checked="checked"' : ''?>>
                  <?= __('Stránky či příspěvky odpovídající filtru:', $this->plugin_slug)?>
                  <div style="padding-left: 25px;" class="register_mode_custom">
                    <div>
                      <label title="<?= __('Příspěvky v rubrikách: ', $this->plugin_slug)?>">
                        <input type="checkbox" name="<?= $this->plugin_slug?>_mode_custom_pbc_ch"<?= $custom_disabled?><?= ($options['mode'] == self::MODE_CUSTOM && !empty($options['mode_custom_pbc'])) ? ' checked="checked"' : ''?>>
                        <?= __('Příspěvky v rubrikách: ', $this->plugin_slug)?>
                        <div class="inputs-subarea">
                          <span class="screen-reader-text"><?= __('vyberte rubriku', $this->plugin_slug)?></span>
                          <label class="screen-reader-text" for="<?= $this->plugin_slug?>_mode_custom_pbc"><?= __('Rubriky', $this->plugin_slug)?></label>
                          <?php
                          $post_cats = get_terms('category');
                          $custom_disabled_pbc = (empty($options['mode_custom_pbc']) || $custom_disabled === ' disabled') ? ' disabled' : '';
                          ?>
                          <select name="<?= $this->plugin_slug?>_mode_custom_pbc[]" id="<?= $this->plugin_slug?>_mode_custom_pbc" multiple style="width: 320px;"<?= $custom_disabled_pbc?>>
                            <?php foreach ($post_cats as $post_cat):?>
                            <option value="<?= $post_cat->term_id?>"<?= (in_array($post_cat->term_id, explode(',', $options['mode_custom_pbc']))) ? ' selected="selected"' : ''?>><?= $post_cat->name?></option>
                            <?php endforeach?>
                          </select>
                        </div>
                      </label>
                    </div>
                    <div style="vertical-align: top;">
                      <label title="<?= __('Příspěvky se štítky: ', $this->plugin_slug)?>">
                        <input type="checkbox" name="<?= $this->plugin_slug?>_mode_custom_pbt_ch"<?= $custom_disabled?><?= ($options['mode'] == self::MODE_CUSTOM && !empty($options['mode_custom_pbt'])) ? ' checked="checked"' : ''?>>
                        <?= __('Příspěvky se štítky: ', $this->plugin_slug)?>
                        <div class="inputs-subarea">
                          <span class="screen-reader-text"><?= __('vyberte štítek', $this->plugin_slug)?></span>
                          <label class="screen-reader-text" for="<?= $this->plugin_slug?>_mode_custom_pbt"><?= __('Štítky', $this->plugin_slug)?></label>
                          <?php
                          $post_tags = get_terms('post_tag');
                          $custom_disabled_pbt = (empty($options['mode_custom_pbt']) || $custom_disabled === ' disabled') ? ' disabled' : '';
                          ?>
                          <select name="<?= $this->plugin_slug?>_mode_custom_pbt[]" id="<?= $this->plugin_slug?>_mode_custom_pbt" multiple style="width: 320px;"<?= $custom_disabled_pbt?>>
                            <?php foreach ($post_tags as $post_tag):?>
                            <option value="<?= $post_tag->term_id?>"<?= (in_array($post_tag->term_id, explode(',', $options['mode_custom_pbt']))) ? ' selected="selected"' : ''?>><?= $post_tag->name?></option>
                            <?php endforeach?>
                          </select>
                        </div>
                      </label>
                    </div>
                    <div>
                      <label title="<?= __('Stránky, které jsou podstránkou stránky: ', $this->plugin_slug)?>">
                        <input type="checkbox" name="<?= $this->plugin_slug?>_mode_custom_pbp_ch"<?= $custom_disabled?><?= ($options['mode'] == self::MODE_CUSTOM && !empty($options['mode_custom_pbp'])) ? ' checked="checked"' : ''?>>
                        <?= __('Stránky, které jsou podstránkou stránky: ', $this->plugin_slug)?>
                        <div class="inputs-subarea">
                          <span class="screen-reader-text"><?= __('vyberte stránku', $this->plugin_slug)?></span>
                          <label class="screen-reader-text" for="<?= $this->plugin_slug?>_mode_custom_pbp"><?= __('Název nadřazené stránky', $this->plugin_slug)?></label>
                          <?php
                          $all_pages = get_pages(array(
                              'hierarchical' => true,
                              'parent'       => -1,
                              'post_type'    => 'page',
                              'sort_column'  => 'post_title',
                              'sort_order'   => 'asc',
                          ));
                          $custom_disabled_pbp = (empty($options['mode_custom_pbp']) || $custom_disabled === ' disabled') ? ' disabled' : '';
                          ?>
                          <select name="<?= $this->plugin_slug?>_mode_custom_pbp[]" id="<?= $this->plugin_slug?>_mode_custom_pbp" multiple style="width: 320px;"<?= $custom_disabled_pbp?>>
                            <?php foreach ($all_pages as $page):?>
                            <option value="<?= $page->ID?>"<?= (in_array($page->ID, explode(',', $options['mode_custom_pbp']))) ? ' selected="selected"' : ''?>><?= $page->post_title?></option>
                            <?php endforeach;?>
                          </select>
                        </div>
                      </label>
                    </div>
                  </div>
                </label>
              </div>
              <!-- //_mode_custom -->
              <div>
                <label title="<?= __('Pouze na vyžádání', $this->plugin_slug)?>" class="register_mode">
                  <input type="radio" name="<?= $this->plugin_slug?>_mode" value="noauto">
                  <?= __('Pouze na vyžádání', $this->plugin_slug)?><br>
                  <p class="inner-description-para">
                    <span class="description"><?= __('Registrace obsahu u vyhledávače bude pouze na přímou žádost přes tlačítko v editaci dané stránky či příspěvku.', $this->plugin_slug)?></span>
                  </p>
                </label>
              </div>
            </fieldset>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="<?= $this->plugin_slug?>_repeats"><?= __('Počet opakování', $this->plugin_slug)?></label>
          </th>
          <td>
            <label title="<?= __('Počet opakování registrace', $this->plugin_slug)?>">
              <input type="number" name="<?= $this->plugin_slug?>_repeats" id="<?= $this->plugin_slug?>_repeats" value="<?= $options['repeats']?>" min="1" max="99"><br>
              <p class="description"><?= __('Počet, kolikrát se má registrace opakovat. Nezadávejte příliš vysoké číslo, jinak může hrozit, že vyhledávač bude vaše žádosti o registraci obsahu odmítat.', $this->plugin_slug)?></p>
            </label>
          </td>
      </tbody>
    </table>
    <script type="text/javascript">
jQuery(document).ready(function($) {
  var p = '<?= $this->plugin_slug?>';
  $('.register_mode').click(function() {
    if (document.forms.namedItem(p + '_form')[p + '_mode'].value === 'custom') {
      // Enable all checkboxes
      $('.register_mode_custom input[type="checkbox"]').removeProp('disabled');

      if (document.forms.namedItem(p + '_form')[p + '_mode_custom_pbc_ch'].checked === true) {
        $('#' + p + '_mode_custom_pbc').removeProp('disabled');
      } else {
        $('#' + p + '_mode_custom_pbc').prop('disabled', true);
      }

      if (document.forms.namedItem(p + '_form')[p + '_mode_custom_pbt_ch'].checked === true) {
        $('#' + p + '_mode_custom_pbt').removeProp('disabled');
      } else {
        $('#' + p + '_mode_custom_pbt').prop('disabled', true);
      }

      if (document.forms.namedItem(p + '_form')[p + '_mode_custom_pbp_ch'].checked === true) {
        $('#' + p + '_mode_custom_pbp').removeProp('disabled');
      } else {
        $('#' + p + '_mode_custom_pbp').prop('disabled', true);
      }
    } else {
      // Disable all checkboxes
      $('.register_mode_custom input[type="checkbox"]').prop('disabled', true);
      $('.register_mode_custom select').prop('disabled', true);
    }
  });
});
    </script>
<?php

    // Additional options (only when WooCommerce) is installed
    if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins', array()))) {
?>
    <h3 class="title"><?= __('WooCommerce', $this->plugin_slug)?></h3>
    <p class="description"><?= __('Byl nalezen plugin <b>WooCommerce</b> - pokud chcete použít <b>Seznam Fulltext Plugin</b> i pro jeho produkty, zaškrtněte políčko níže.', $this->plugin_slug)?></p>
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">
            <label for="<?= $this->plugin_slug?>_mode">
              <?= __('WooCommerce', $this->plugin_slug)?></label>
          </th>
          <td style="padding-top: 20px;">
            <label title="<?= __('Produkty definované pluginem WooCommerce', $this->plugin_slug)?>">
              <input type="checkbox" name="<?= $this->plugin_slug?>_wc" id="<?= $this->plugin_slug?>_wc"<?= ($options['mode_wc'] === true) ? ' checked="checked"' : ''?>>
              <?= __('Registrovat stránky produktů', $this->plugin_slug)?>
              <p class="inner-description-para">
                <span class="description"><?= __('Registraci u vyhledávače budou podléhat také produkty definované pluginem <b>WooCommerce</b>.', $this->plugin_slug)?></span>
              </p>
            </label>
          </td>
        </tr>
      </tbody>
    </table>
<?php
    }
?>
    <p class="submit">
      <input type="submit" value=" <?php echo __('Uložit změny', $this->plugin_slug)?> " name="<?= $this->plugin_slug . '_submit'?>" class="button button-primary">
    </p>
  </form>
</div>
<?php
  } // end admin_options_page()

  /**
   * Add custom column to the WordPress admin posts lists.
   *
   * @param array $defaults
   * @return array
   * @since 0.1.0
   */
  public function custom_column_header($defaults) {
    $defaults['is_registered'] = '' .
      '<span>' .
        '<span class="is_registered-column-icon" title="' . __('Registrováno u fulltextového vyhledávače Seznam.cz?', $this->plugin_slug) . '">' .
          '<span class="screen-reader-text">' . __('Registrováno', $this->plugin_slug) . '</span>' .
          '</span>' .
      '</span>';

    return $defaults;
  } // end custom_column_header($defaults)

  /**
   * Renders content for our custom column.
   *
   * @return void
   * @since 0.1.0
   */
  public function custom_column_content($column_name, $post_id) {
    if ($column_name == 'is_registered') {
      $html = '<abbr style="color: %s;" title="%s">%s</abbr>';
      $skip = $this->get_skip_registration($post_id);

      if ($skip === true) {
        printf(
          $html,
          'darkorange',
          __('Obsah nemá být u vyhledávače Seznam.cz registrován.', $this->plugin_slug),
          __('P', $this->plugin_slug)
        );

        return;
      }

      $options = $this->get_options();
      $repeats = $options['repeats'];
      $current = $this->get_registrations_count($post_id);

      if ($current == 0) {
        $color = 'red';
        $title = __('Obsah není zatím u vyhledávače Seznam.cz registrován.', $this->plugin_slug);
        $char  = __('N', $this->plugin_slug);
      }
      else if ($current < $repeats) {
        $color = 'darkred';
        $title = __('Obsah je u vyhledávače Seznam.cz částečně registrován (není dosažen limit opakování).', $this->plugin_slug);
        $char  = __('Č', $this->plugin_slug);
      }
      else {
        $color = 'green';
        $title = __('Obsah je již u vyhledávače Seznam.cz registrován.', $this->plugin_slug);
        $char  = __('A', $this->plugin_slug);
      }

      printf($html, $color, $title, $char);
    }
  } // end custom_column_content($column_name, $post_id)

  /**
   * Enable sorting in posts listing by our `is_registered` column.
   *
   * @param array $columns
   * @return array
   * @since ?.?.?
   * /
  public function post_table_sorting($columns) {
    $columns['is_registered'] = 'is_registered';
    return $columns;
  } // end post_table_sorting($columns)
  */

  /**
   * Sort posts by our `is_registered` column.
   *
   * @param array $vars
   * @return array
   * @since ?.?.?
   *
   * @todo Implement this in the next version!
   * /
  public function is_registered_column_orderby($vars) {
    if (isset($vars['orderby']) && 'is_registered' == $vars['orderby']) {
      $vars = array_merge($vars, array(
        'meta_key' => '_' . $this->plugin_slug . '_meta_is_registered',
        'orderby' => 'meta_value'
      ));
    }

    return $vars;
  } // end is_registered_column_orderby($vars)
  */

  /**
   * Render control for posts table filtering.
   *
   * @return void
   * @since ?.?.?
   * /
  public function post_table_filtering() {
?>
<label class="screen-reader-text" for="filter-by-is_registered"><?= __('Filtrovat dle registrace u vyhledávačů', $this->plugin_slug)?></label>
<select id="filter-by-is_registered" name="<?= $this->plugin_slug?>-ir">
  <option value="0" selected="selected"><?= __('— Registrace —', $this->plugin_slug)?></option>
  <option value="registered"><?= __('Registrované', $this->plugin_slug)?></option>
  <option value="not_registered"><?= __('Neregistrované', $this->plugin_slug)?></option>
</select>
<?php
  } // end post_table_filtering()
  */

  /**
   * Add meta box on post add/edit page.
   *
   * @return void
   * @since 0.1.0
   * @uses add_meta_box()
   */
  public function add_meta_box() {
    add_meta_box(
      $this->plugin_slug . '_is_registered',
      __('Registrovat u vyhledávačů', $this->plugin_slug),
      array($this, 'render_meta_box'),
      null,//post
      'side', // 'normal','side','advanced'
      'default',
      null
    );
  } // end add_meta_box()

  /**
   * Render meta box on post add/edit page.
   *
   * @param object $post
   * @return void
   * @since 0.1.0
   * @uses wp_create_nonce()
   * 
   * @todo When content is already registered show message and disable button.
   * @todo Show label with count how many times were done attempts to register content.
   * @todo Disable button while content is just about to be created and doesn't have slug yet.
   */
  public function render_meta_box($post) {
    $is_new = ($post->post_status == 'auto-draft');
    $count = $this->get_registrations_count($post->ID);
    $skip = $this->get_skip_registration($post->ID);
    $nonce = wp_create_nonce($this->plugin_slug . '_nonce');
?>
<div class="is_registered-box<?= ($is_new === true) ? ' is-new-post' : ''?>">
  <input type="hidden" name="<?= $this->plugin_slug . '_nonce'?>" value="<?= $nonce?>">
  <?php if ($is_new !== true):?>
  <div class="description-box">
    <p><?= sprintf(__('Počet dosavadních registrací: <b id="%s">%s</b>.', $this->plugin_slug), $this->plugin_slug . '-registrations_count', $count)?></p>
  </div>
  <?php endif?>
  <div class="skip_registration-box">
    <label for="<?= $this->plugin_slug?>-skip_registration">
      <input type="checkbox" name="<?= $this->plugin_slug?>-skip_registration"<?= ($skip === true) ? ' checked="checked"' : ''?>>
      <?= __('Přeskočit registraci u Seznam.cz', $this->plugin_slug)?>
    </label>
  </div>
  <?php if ($is_new !== true):?>
  <div id="<?= $this->plugin_slug . '-button_box'?>" class="button-box">
    <div class="left-part">
      <a href="#" title="<?= __('Resetovat čítač registrací.', $this->plugin_slug)?>" data-post_id="<?= $post->ID?>" data-nonce="<?= $nonce?>">
        <?= __('Resetovat', $this->plugin_slug)?>
      </a>
    </div>
    <div class="right-part">
      <span class="spinner"></span>
      <button name="<?= $this->plugin_slug . '-register_btn'?>" id="<?= $this->plugin_slug . '-register_btn'?>" class="button button-primary button-large" title="<?= __('Zaregistrovat obsah ručně.', $this->plugin_slug)?>" type="button" data-post_id="<?= $post->ID?>" data-nonce="<?= $nonce?>">
        <?= __('Zaregistrovat', $this->plugin_slug)?>
      </button>
    </div>
    <div class="clear"></div>
  </div>
  <?php endif?>
</div>
<?php
  } // end render_meta_box($post)

  /**
   * Saves our metadata.
   *
   * @param integer $post_id
   * @param object $post
   * @uses wp_verify_nonce()
   * @uses get_post_type_object()
   * @uses current_user_can()
   * @uses update_post_meta()
   */
  public function save_post($post_id, $post) {
    if ((bool) wp_verify_nonce(filter_input(INPUT_POST, $this->plugin_slug . '_nonce')) !== true) {
      return $post_id;
    }

    $post_type = get_post_type_object($post->post_type);

    if (!current_user_can($post_type->cap->edit_post, $post_id)) {
      return $post_id;
    }

    $new_meta_value = isset($_POST[$this->plugin_slug . '-skip_registration']);
    $old_meta_value = $this->get_skip_registration($post_id);

    if ($new_meta_value != $old_meta_value) {
      update_post_meta($post_id, 'skip_registration', $new_meta_value);
    }
  } // end save_post($post_id, $post)
  
  /**
   * Register content with given ID at Seznam.cz fulltext search engine.
   *
   * @param integer $post_id
   * @param boolean $override (Optional). Override count limit.
   * @return integer Returns regustrations count.
   * @since 0.1.0
   * @uses get_permalink()
   * @uses update_post_meta()
   *
   * @todo Check registrations count limit!
   */
  public function register_content($post_id, $override = false) {
    $count = $this->get_registrations_count($post_id) + 1;
    $post_url = get_permalink($post_id);

    update_post_meta($post_id, 'is_registered_count', $count);

    file_get_contents(sprintf(self::SEZNAM_URL, $post_url));

    return $count;
  } // end register_content($post_id, $override = false) {

  /**
   * Handler for Ajax-requests for content registration from WordPress administration.
   *
   * @return void
   * @since 0.1.0
   */
  public function register_content_ajax() {
    if ((bool) wp_verify_nonce($_REQUEST['nonce'], $this->plugin_slug . '_nonce') !== true) {
      exit(__('Operace není povolena!', $this->plugin_slug));
    }

    $post_id = (int) filter_input(INPUT_REQUEST, 'post_id');
    $count = $this->register_content($post_id, true);
    $result = array(
      'type' => 'success',
      'count' => $count
    );

    if (
      !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
      echo json_encode($result);
    } else {
      header('Location: ' . $_SERVER['HTTP_REFERER']);
    }

    die();
  } // end register_content_ajax() {
} // End of ODWP_SeznamFulltext

endif;
