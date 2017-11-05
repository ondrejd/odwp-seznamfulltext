/**
 * Seznam Fulltext Plugin
 *
 * @author Ondřej Doněk, <ondrejd@gmail.com>
 * @link https://bitbucket.com/ondrejd/odwp-seznamfulltext for the canonical source repository
 * @license https://www.mozilla.org/MPL/2.0/ Mozilla Public License 2.0
 */

jQuery(document).ready( function() {
  jQuery('#' + seznamfulltextAjax.slug + '-register_btn').click(function() {
    var post_id = jQuery(this).attr('data-post_id');
    var nonce = jQuery(this).attr('data-nonce');

    jQuery(this).prop('disabled', true);
    jQuery('#' + seznamfulltextAjax.slug + '-button_box .spinner').css('visibility', 'visible');

    jQuery.ajax({
      type: 'post',
      dataType: 'json',
      url: seznamfulltextAjax.ajaxurl,
      data: {
        action: 'seznamfulltext_register_content',
        post_id: post_id,
        nonce: nonce
      },
      success: function(response) {
        if(response.type === 'success') {
          jQuery('#' + seznamfulltextAjax.slug + '-registrations_count').html(response.count);
        } else {
          alert(seznamfulltextAjax.msg);
        }

        jQuery('#' + seznamfulltextAjax.slug + '-button_box .spinner').css('visibility', 'hidden');
        jQuery(this).removeProp('register');
      }
    });
  });
});
