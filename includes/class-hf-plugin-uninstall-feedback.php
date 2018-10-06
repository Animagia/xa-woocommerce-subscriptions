<?php
if (!class_exists('HF_Subscription_Uninstall_Feedback')) :

    /**
     * Class for catch Feedback on uninstall
     */
    class HF_Subscription_Uninstall_Feedback {

        public function __construct() {
            add_action('admin_footer', array($this, 'deactivate_scripts'));
            add_action('wp_ajax_hfsubscription_submit_uninstall_reason', array($this, "send_uninstall_reason"));
        }

        private function get_uninstall_reasons() {

            $reasons = array(
                array(
                    'id' => 'could-not-understand',
                    'text' => __('I couldn\'t understand how to make it work', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => __('Would you like us to assist you?', 'xa-woocommerce-subscriptions')
                ),
                array(
                    'id' => 'found-better-plugin',
                    'text' => __('I found a better plugin', 'xa-woocommerce-subscriptions'),
                    'type' => 'text',
                    'placeholder' => __('Which plugin?', 'xa-woocommerce-subscriptions')
                ),
                array(
                    'id' => 'not-have-that-feature',
                    'text' => __('The plugin is great, but I need specific feature that you don\'t support', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => __('Could you tell us more about that feature?', 'xa-woocommerce-subscriptions')
                ),
                array(
                    'id' => 'is-not-working',
                    'text' => __('The plugin is not working', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => __('Could you tell us a bit more whats not working?', 'xa-woocommerce-subscriptions')
                ),
                array(
                    'id' => 'looking-for-other',
                    'text' => __('It\'s not what I was looking for', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => 'Could you tell us a bit more?'
                ),
                array(
                    'id' => 'did-not-work-as-expected',
                    'text' => __('The plugin didn\'t work as expected', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => __('What did you expect?', 'xa-woocommerce-subscriptions')
                ),
                array(
                    'id' => 'other',
                    'text' => __('Other', 'xa-woocommerce-subscriptions'),
                    'type' => 'textarea',
                    'placeholder' => __('Could you tell us a bit more?', 'xa-woocommerce-subscriptions')
                ),
            );

            return $reasons;
        }

        public function deactivate_scripts() {

            global $pagenow;
            if ('plugins.php' != $pagenow) {
                return;
            }
            $reasons = $this->get_uninstall_reasons();
            ?>
            <div class="hfsubscriptions-modal" id="hfsubscriptions-hfsubscriptions-modal">
                <div class="hfsubscriptions-modal-wrap">
                    <div class="hfsubscriptions-modal-header">
                        <h3><?php _e('If you have a moment, please let us know why you are deactivating:', 'xa-woocommerce-subscriptions'); ?></h3>
                    </div>
                    <div class="hfsubscriptions-modal-body">
                        <ul class="reasons">
                            <?php foreach ($reasons as $reason) { ?>
                                <li data-type="<?php echo esc_attr($reason['type']); ?>" data-placeholder="<?php echo esc_attr($reason['placeholder']); ?>">
                                    <label><input type="radio" name="selected-reason" value="<?php echo $reason['id']; ?>"> <?php echo $reason['text']; ?></label>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                    <div class="hfsubscriptions-modal-footer">
                        <a href="#" class="dont-bother-me"><?php _e('I rather wouldn\'t say', 'xa-woocommerce-subscriptions'); ?></a>
                        <button class="button-primary hfsubscriptions-model-submit"><?php _e('Submit & Deactivate', 'xa-woocommerce-subscriptions'); ?></button>
                        <button class="button-secondary hfsubscriptions-model-cancel"><?php _e('Cancel', 'xa-woocommerce-subscriptions'); ?></button>
                    </div>
                </div>
            </div>

            <style type="text/css">
                .hfsubscriptions-modal {
                    position: fixed;
                    z-index: 99999;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    left: 0;
                    background: rgba(0,0,0,0.5);
                    display: none;
                }
                .hfsubscriptions-modal.modal-active {display: block;}
                .hfsubscriptions-modal-wrap {
                    width: 50%;
                    position: relative;
                    margin: 10% auto;
                    background: #fff;
                }
                .hfsubscriptions-modal-header {
                    border-bottom: 1px solid #eee;
                    padding: 8px 20px;
                }
                .hfsubscriptions-modal-header h3 {
                    line-height: 150%;
                    margin: 0;
                }
                .hfsubscriptions-modal-body {padding: 5px 20px 20px 20px;}
                .hfsubscriptions-modal-body .input-text,.hfsubscriptions-modal-body textarea {width:75%;}
                .hfsubscriptions-modal-body .reason-input {
                    margin-top: 5px;
                    margin-left: 20px;
                }
                .hfsubscriptions-modal-footer {
                    border-top: 1px solid #eee;
                    padding: 12px 20px;
                    text-align: right;
                }
            </style>
            <script type="text/javascript">
                (function ($) {
                    $(function () {
                        var modal = $('#hfsubscriptions-hfsubscriptions-modal');
                        var deactivateLink = '';
                        $('#the-list').on('click', 'a.hfsubscriptions-deactivate-link', function (e) {
                            e.preventDefault();
                            modal.addClass('modal-active');
                            deactivateLink = $(this).attr('href');
                            modal.find('a.dont-bother-me').attr('href', deactivateLink).css('float', 'left');
                        });
                        modal.on('click', 'button.hfsubscriptions-model-cancel', function (e) {
                            e.preventDefault();
                            modal.removeClass('modal-active');
                        });
                        modal.on('click', 'input[type="radio"]', function () {
                            var parent = $(this).parents('li:first');
                            modal.find('.reason-input').remove();
                            var inputType = parent.data('type'),
                                    inputPlaceholder = parent.data('placeholder'),
                                    reasonInputHtml = '<div class="reason-input">' + (('text' === inputType) ? '<input type="text" class="input-text" size="40" />' : '<textarea rows="5" cols="45"></textarea>') + '</div>';

                            if (inputType !== '') {
                                parent.append($(reasonInputHtml));
                                parent.find('input, textarea').attr('placeholder', inputPlaceholder).focus();
                            }
                        });

                        modal.on('click', 'button.hfsubscriptions-model-submit', function (e) {
                            e.preventDefault();
                            var button = $(this);
                            if (button.hasClass('disabled')) {
                                return;
                            }
                            var $radio = $('input[type="radio"]:checked', modal);
                            var $selected_reason = $radio.parents('li:first'),
                                    $input = $selected_reason.find('textarea, input[type="text"]');

                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'hfsubscription_submit_uninstall_reason',
                                    reason_id: (0 === $radio.length) ? 'none' : $radio.val(),
                                    reason_info: (0 !== $input.length) ? $input.val().trim() : ''
                                },
                                beforeSend: function () {
                                    button.addClass('disabled');
                                    button.text('Processing...');
                                },
                                complete: function () {
                                    window.location.href = deactivateLink;
                                }
                            });
                        });
                    });
                }(jQuery));
            </script>
            <?php
        }

        public function send_uninstall_reason() {

            global $wpdb;

            if (!isset($_POST['reason_id'])) {
                wp_send_json_error();
            }

            //$current_user = wp_get_current_user();

            $data = array(
                'reason_id' => sanitize_text_field($_POST['reason_id']),
                'plugin' => "hfsubscriptions",
                'auth' => 'hfsubscriptions_uninstall_1234#',
                'date' => gmdate("M d, Y h:i:s A"),
                'url' => home_url(),
                'user_email' => '', //$current_user->user_email,
                'reason_info' => isset($_REQUEST['reason_info']) ? trim(stripslashes($_REQUEST['reason_info'])) : '',
                'software' => $_SERVER['SERVER_SOFTWARE'],
                'php_version' => phpversion(),
                'mysql_version' => $wpdb->db_version(),
                'wp_version' => get_bloginfo('version'),
                'wc_version' => (!defined('WC_VERSION')) ? '' : WC_VERSION,
                'locale' => get_locale(),
                'multisite' => is_multisite() ? 'Yes' : 'No',
                'hfsubscriptions_version' => HFORCE_WC_SUBSCRIPTION_VERSION
            );
            // Write an action/hook here in webtoffe to recieve the data
            $resp = wp_remote_post('http://feedback.webtoffee.com/wp-json/hfsubscriptions/v1/uninstall', array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => false,
                'headers' => array('user-agent' => 'hfsubscriptions/' . md5(esc_url(home_url())) . ';'),
                'body' => $data,
                'cookies' => array()
                    )
            );

            wp_send_json_success();
        }

    }
    new HF_Subscription_Uninstall_Feedback();

endif;