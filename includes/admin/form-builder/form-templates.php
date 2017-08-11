<?php

//
// Create a array of all available form builder templates
//
function buddyforms_form_builder_register_templates()
{
    // Get the templates form demo.buddyforms.com as json string
    $response = wp_remote_get( 'http://demo.buddyforms.com/wp-json/buddyforms/v1/all/' );
    
    if ( is_wp_error( $response ) || $response['response']['code'] != 200 ) {
        $response = array();
        $response['body'] = buddyforms_default_form_templates_json();
    }
    
    // Decode the json
    $buddyforms = json_decode( $response['body'] );
    if ( !is_object( $buddyforms ) ) {
        return;
    }
    $sort = array();
    foreach ( $buddyforms as $form_s => $form ) {
        $sort[$form->form_type][$form_s] = $form;
    }
    // Loop all forms from the demo and create the form templates
    foreach ( $sort as $sort_key => $sort_item ) {
        foreach ( $sort_item as $form_slug => $buddyform ) {
            $desc = '';
            foreach ( $buddyform->form_fields as $form_field ) {
                
                if ( empty($desc) ) {
                    $desc .= $form_field->name;
                } else {
                    $desc .= ', ' . $form_field->name;
                }
            
            }
            $buddyforms_templates[$sort_key][$form_slug]['title'] = $buddyform->name;
            $buddyforms_templates[$sort_key][$form_slug]['url'] = 'http://demo.buddyforms.com/remote/remote-create/' . $form_slug;
            $buddyforms_templates[$sort_key][$form_slug]['desc'] = $desc;
            $buddyforms_templates[$sort_key][$form_slug]['json'] = json_encode( $buddyform );
        }
    }
    $templates = array();
    $templates['contact'] = $buddyforms_templates['contact'];
    $templates['registration'] = $buddyforms_templates['registration'];
    $templates['post'] = $buddyforms_templates['post'];
    return apply_filters( 'buddyforms_form_builder_templates', $templates );
}

function buddyforms_form_builder_template_get_dependencies( $template )
{
    $buddyform = json_decode( $template['json'] );
    $dependencies = 'None';
    $deps = '';
    if ( !($buddyform->post_type == 'post' || $buddyform->post_type == 'page' || $buddyform->post_type == 'bf_submissions') ) {
        $deps .= 'BuddyForms Professional';
    }
    if ( isset( $buddyform->form_fields ) ) {
        foreach ( $buddyform->form_fields as $field_key => $field ) {
            if ( $field->slug == 'taxonomy' || $field->slug == 'category' || $field->slug == 'tags' ) {
                $deps .= 'BuddyForms Professional';
            }
        }
    }
    
    if ( $buddyform->post_type == 'product' && !post_type_exists( 'product' ) ) {
        $deps .= ( empty($deps) ? '' : ', ' );
        $deps .= 'WooCommerce';
    }
    
    if ( isset( $buddyform->form_fields ) ) {
        foreach ( $buddyform->form_fields as $field_key => $field ) {
            
            if ( $field->slug == '_woocommerce' ) {
                
                if ( !class_exists( 'bf_woo_elem' ) ) {
                    $deps .= ( empty($deps) ? '' : ', ' );
                    $deps .= 'BuddyForms WooElements';
                }
                
                
                if ( $field->product_type_default == 'auction' && !class_exists( 'bf_woo_simple_auction' ) ) {
                    $deps .= ( empty($deps) ? '' : ', ' );
                    $deps .= 'BuddyForms Simple Auction';
                }
                
                if ( $field->product_type_default == 'auction' ) {
                    
                    if ( !class_exists( 'WooCommerce_simple_auction' ) ) {
                        $deps .= ( empty($deps) ? '' : ', ' );
                        $deps .= 'WC Simple Auctions';
                    }
                
                }
            }
        
        }
    }
    if ( !empty($deps) ) {
        $dependencies = $deps;
    }
    return $dependencies;
}

//
// Template HTML Loop the array of all available form builder templates
//
function buddyforms_form_builder_templates()
{
    $buddyforms_templates = buddyforms_form_builder_register_templates();
    ob_start();
    ?>
    <div class="buddyforms_template buddyforms_wizard_types">
        <h5>Choose a pre-configured form template or start a new one:</h5>

		<?php 
    add_thickbox();
    ?>

		<?php 
    foreach ( $buddyforms_templates as $sort_key => $sort_item ) {
        ?>

            <h2><?php 
        echo  strtoupper( $sort_key ) ;
        ?>
 FORMS</h2>

			<?php 
        foreach ( $sort_item as $key => $template ) {
            $dependencies = buddyforms_form_builder_template_get_dependencies( $template );
            $disabled = ( $dependencies != 'None' ? 'disabled' : '' );
            ?>
                <div class="bf-3-tile bf-tile <?php 
            if ( $dependencies != 'None' ) {
                echo  'disabled ' ;
            }
            ?>
">
                    <h4 class="bf-tile-title"><?php 
            echo  $template['title'] ;
            ?>
</h4>
                    <div class="xbf-col-50 bf-tile-desc-wrap">
                        <p class="bf-tile-desc"><?php 
            echo  wp_trim_words( $template['desc'], 15 ) ;
            ?>
</p>
                    </div>
                    <div class="bf-tile-preview-wrap">
                        <p><a href="#TB_inline?width=600&height=550&inlineId=template-<?php 
            echo  $key ;
            ?>
"
                              data-src="<?php 
            echo  $template['url'] ;
            ?>
" data-key="<?php 
            echo  $key ;
            ?>
"
                              title="<?php 
            echo  $template['title'] ;
            ?>
" class="thickbox button bf-preview"><span
                                        class="dashicons dashicons-visibility"></span> Preview</a></p>
                    </div>
					<?php 
            
            if ( $dependencies != 'None' ) {
                ?>
                        <p class="bf-tile-dependencies">Dependencies: <?php 
                echo  $dependencies ;
                ?>
</p>
					<?php 
            } else {
                ?>
                        <button <?php 
                echo  $disabled ;
                ?>
 id="btn-compile-<?php 
                echo  $key ;
                ?>
"
                                                        data-type="<?php 
                echo  $sort_key ;
                ?>
"
                                                        data-template="<?php 
                echo  $key ;
                ?>
"
                                                        class="bf_wizard_types bf_form_template btn btn-primary btn-50"
                                                        onclick="">
                            Use This Template
                        </button>
					<?php 
            }
            
            ?>
                    <div id="template-<?php 
            echo  $key ;
            ?>
" style="display:none;">
                        <div class="bf-tile-desc-wrap">
                            <p class="bf-tile-desc"><?php 
            echo  $template['desc'] ;
            ?>
</p>
                            <button <?php 
            echo  $disabled ;
            ?>
 id="btn-compile-<?php 
            echo  $key ;
            ?>
"
                                                            data-type="<?php 
            echo  $sort_key ;
            ?>
"
                                                            data-template="<?php 
            echo  $key ;
            ?>
"
                                                            class="bf_wizard_types bf_form_template button button-primary"
                                                            onclick="">
                                <!-- <span class="dashicons dashicons-plus"></span>  -->
                                Use This Template
                            </button>
                        </div>
                        <iframe id="iframe-<?php 
            echo  $key ;
            ?>
" width="100%" height="800px" scrolling="yes"
                                frameborder="0" class="bf-frame"
                                style="background: transparent; height: 639px; height: 75vh; margin: 0 auto; padding: 0 5px; width: calc( 100% - 10px );"></iframe>
                    </div>

                </div>
			<?php 
        }
    }
    ?>

    </div>

	<?php 
    $tmp = ob_get_clean();
    return $tmp;
}

//
// json string of the form export top generate the Form from template
//
add_action( 'wp_ajax_buddyforms_form_template', 'buddyforms_form_template' );
function buddyforms_form_template()
{
    global  $post, $buddyform ;
    $post->post_type = 'buddyforms';
    $buddyforms_templates = buddyforms_form_builder_register_templates();
    $forms = array();
    foreach ( $buddyforms_templates as $type => $form_temps ) {
        foreach ( $form_temps as $forms_slug => $form ) {
            $forms[$forms_slug] = $form;
        }
    }
    $buddyforms_templates = $forms;
    $buddyform = $buddyforms_templates[$_POST['template']];
    $buddyform = json_decode( $buddyform['json'], true );
    ob_start();
    buddyforms_metabox_form_elements( $post, $buddyform );
    $formbuilder = ob_get_clean();
    // Add the form elements to the form builder
    $json['formbuilder'] = $formbuilder;
    ob_start();
    ?>
    <div class="buddyforms_accordion_notification">
        <div class="hidden bf-hidden"><?php 
    wp_editor( 'dummy', 'dummy' );
    ?>
</div>

		<?php 
    buddyforms_mail_notification_screen();
    ?>

        <div class="bf_show_if_f_type_post bf_hide_if_post_type_none">
			<?php 
    buddyforms_post_status_mail_notification_screen();
    ?>
        </div>
    </div>
	<?php 
    $mail_notification = ob_get_clean();
    $json['mail_notification'] = $mail_notification;
    // Unset the form fields
    unset( $buddyform['form_fields'] );
    unset( $buddyform['mail_submissions'] );
    // Add the form setup to the json
    $json['form_setup'] = $buddyform;
    echo  json_encode( $json ) ;
    die;
}

function buddyforms_default_form_templates_json()
{
    return '{"become-a-vendor":{"form_fields":{"a40912e1a5":{"type":"user_login","slug":"user_login","name":"Username","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"82abe39ed2":{"type":"user_email","slug":"user_email","name":"eMail","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"611dc33cb2":{"type":"user_pass","slug":"user_pass","name":"Password","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"636c12a746":{"type":"text","name":"Shop Name","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","slug":"pv_shop_name","custom_class":"","display":"no","hook":""},"dfc114e960":{"type":"text","name":"PayPal E-mail (required)","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","slug":"pv_paypal","custom_class":"","display":"no","hook":""},"df44e14ace":{"type":"textarea","name":"Seller Info","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","slug":"pv_seller_info","custom_class":"","generate_textarea":"","display":"no","hook":""},"fce05b6cd3":{"type":"textarea","name":"Shop description","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","slug":"pv_shop_description","custom_class":"","generate_textarea":"","display":"no","hook":""}},"layout":{"cords":{"a40912e1a5":"1","82abe39ed2":"1","611dc33cb2":"1","636c12a746":"1","dfc114e960":"1","df44e14ace":"1","fce05b6cd3":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"registration","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"User Registration Successful! Please check your eMail Inbox and click the activation link to activate your account.","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"none","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"212","activation_message_from_subject":"Vendor Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\r\\nGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n<br>\\r\\n<b>Click the link below to activate your account.<\\/b>\\r\\n<br>\\r\\n[activation_link]\\r\\n<br><br>\\r\\n[blog_title]","activation_message_from_name":"[blog_title]","activation_message_from_email":"nireplay@noreplay.com","new_user_role":"vendor"},"hierarchical":{"hierarchical_name":"Children","hierarchical_singular_name":"Child","display_child_posts_on_parent_single":"none"},"profile_visibility":"any","moderation_logic":"default","moderation":{"label_submit":"Submit","label_save":"Save","label_review":"Submit for moderation","label_new_draft":"Create new Draft","label_no_edit":"This Post is waiting for approval and can not be changed until it gets approved"},"name":"Become a Vendor","slug":"become-a-vendor"},"contact-full-name":{"form_fields":{"92f6e0cb6b":{"type":"user_first","slug":"user_first","name":"First Name","description":"","validation_error_message":"This field is required.","custom_class":""},"8ead289ca0":{"type":"user_last","slug":"user_last","name":"Last Name","description":"","validation_error_message":"This field is required.","custom_class":""},"87e0afb2d7":{"type":"user_email","slug":"user_email","name":"Email","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"210ef7d8a8":{"type":"subject","slug":"subject","name":"Subject","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":""},"0a256db3cb":{"type":"message","slug":"message","name":"Message","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":""}},"layout":{"cords":{"92f6e0cb6b":"2","8ead289ca0":"2","87e0afb2d7":"1","210ef7d8a8":"1","0a256db3cb":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"SEND","button_width":"blockmobile","button_alignment":"left","button_size":"xlarge","button_class":"button btn btn-primary","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"contact","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Your message has been submitted successfully.","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"153","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","mail_submissions":{"830e6d7716":{"mail_trigger_id":"830e6d7716","mail_from_name":"user_first","mail_from_name_custom":"","mail_from":"submitter","mail_from_custom":"","mail_to_address":"","mail_to":["submitter","admin"],"mail_to_cc_address":"","mail_to_bcc_address":"","mail_subject":"You Got Mail From Your Contact Form","mail_body":""}},"public_submit":["public_submit"],"public_submit_login":"none","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"remote":["remote"],"name":"Contact Full Name","slug":"contact-full-name"},"simple-contact-form":{"form_fields":{"92f6e0cb6b":{"type":"user_first","slug":"user_first","name":"Your Name","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"87e0afb2d7":{"type":"user_email","slug":"user_email","name":"Your Email","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"0a256db3cb":{"type":"message","slug":"message","name":"Your Message","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"10","validation_maxlength":"110","custom_class":""}},"layout":{"cords":{"92f6e0cb6b":"1","87e0afb2d7":"1","0a256db3cb":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"SEND","button_width":"blockmobile","button_alignment":"left","button_size":"xlarge","button_class":"button btn btn-primary","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"contact","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Thanks! Your message is on the way! ","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"153","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","mail_submissions":{"e8818ef3a1":{"mail_trigger_id":"e8818ef3a1","mail_from_name":"user_first","mail_from_name_custom":"","mail_from":"submitter","mail_from_custom":"","mail_to_address":"","mail_to":["submitter","admin"],"mail_to_cc_address":"","mail_to_bcc_address":"","mail_subject":"Contact Form Submission","mail_body":""}},"public_submit":["public_submit"],"public_submit_login":"none","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"remote":["remote"],"name":"Contact Simple","slug":"simple-contact-form"},"post-form-all-fields":{"form_fields":{"6930e161aa":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"24da67e1d1":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""},"1360c37280":{"type":"category","name":"Categories","description":"","taxonomy":"category","taxonomy_placeholder":"Select an Option","taxonomy_order":"ASC","taxonomy_include":[""],"taxonomy_exclude":[""],"create_new_tax":["user_can_create_new"],"validation_error_message":"This field is required.","slug":"categories","custom_class":""},"3567df4099":{"type":"tags","name":"Tags","description":"","taxonomy":"post_tag","taxonomy_placeholder":"Select an Option","taxonomy_order":"ASC","taxonomy_include":[""],"taxonomy_exclude":[""],"create_new_tax":["user_can_create_new"],"validation_error_message":"This field is required.","slug":"tags","custom_class":""},"f6a731c6f7":{"slug":"featured_image","type":"featured_image","name":"Featured Image","description":""}},"layout":{"cords":{"6930e161aa":"1","24da67e1d1":"1","1360c37280":"1","3567df4099":"1","f6a731c6f7":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"post","status":"publish","comment_status":"open","singular_name":"","attached_page":"153","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"hierarchical":{"hierarchical_name":"Children","hierarchical_singular_name":"Child","display_child_posts_on_parent_single":"none"},"profile_visibility":"any","moderation_logic":"default","moderation":{"label_submit":"Submit","label_save":"Save","label_review":"Submit for moderation","label_new_draft":"Create new Draft","label_no_edit":"This Post is waiting for approval and can not be changed until it gets approved"},"remote":["remote"],"name":"Post Form All Fields","slug":"post-form-all-fields"},"simple-post-form":{"form_fields":{"6930e161aa":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"24da67e1d1":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""}},"layout":{"cords":{"6930e161aa":"1","24da67e1d1":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"post","status":"publish","comment_status":"open","singular_name":"","attached_page":"89","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"name":"Simple Post Form","slug":"simple-post-form"},"registration-profile":{"form_fields":{"a40912e1a5":{"type":"user_login","slug":"user_login","name":"Username","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"9674ece814":{"type":"user_first","slug":"user_first","name":"First Name","description":"","validation_error_message":"This field is required.","custom_class":""},"ba773278a2":{"type":"user_last","slug":"user_last","name":"Last Name","description":"","validation_error_message":"This field is required.","custom_class":""},"f2aa3973d5":{"type":"user_bio","slug":"user_bio","name":"Bio","description":"","validation_error_message":"This field is required.","custom_class":""},"fe289c9548":{"type":"user_website","slug":"website","name":"Website","description":"","validation_error_message":"This field is required.","custom_class":""},"82abe39ed2":{"type":"user_email","slug":"user_email","name":"eMail","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"611dc33cb2":{"type":"user_pass","slug":"user_pass","name":"Password","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""}},"layout":{"cords":{"a40912e1a5":"1","9674ece814":"1","ba773278a2":"1","f2aa3973d5":"1","fe289c9548":"1","82abe39ed2":"1","611dc33cb2":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"registration","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"User Registration Successful! Please check your eMail Inbox and click the activation link to activate your account.","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"none","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\r\\nGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n<br>\\r\\n<b>Click the link below to activate your account.<\\/b>\\r\\n<br>\\r\\n[activation_link]\\r\\n<br><br>\\r\\n[blog_title]","activation_message_from_name":"[blog_title]","activation_message_from_email":"dfg@dfg.fr","new_user_role":"author"},"name":"Registration Profile","slug":"registration-profile"},"simple-registration-form":{"form_fields":{"a40912e1a5":{"type":"user_login","slug":"user_login","name":"Username","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"82abe39ed2":{"type":"user_email","slug":"user_email","name":"eMail","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"611dc33cb2":{"type":"user_pass","slug":"user_pass","name":"Password","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""}},"layout":{"cords":{"a40912e1a5":"1","82abe39ed2":"1","611dc33cb2":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"registration","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"User Registration Successful! Please check your eMail Inbox and click the activation link to activate your account.","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"none","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\r\\nGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n<br>\\r\\n<b>Click the link below to activate your account.<\\/b>\\r\\n<br>\\r\\n[activation_link]\\r\\n<br><br>\\r\\n[blog_title]","activation_message_from_name":"[blog_title]","activation_message_from_email":"dfg@dfg.fr","new_user_role":"author"},"name":"Simple Registration Form","slug":"simple-registration-form"},"user-support":{"form_fields":{"92f6e0cb6b":{"type":"user_first","slug":"user_first","name":"First Name","description":"","validation_error_message":"This field is required.","custom_class":""},"8ead289ca0":{"type":"user_last","slug":"user_last","name":"Last Name","description":"","validation_error_message":"This field is required.","custom_class":""},"2910663d7e":{"type":"dropdown","name":"Support Type","description":"Please Select the Kind of Support you are looking for","options":{"1":{"label":"Help Me","value":"Help"},"2":{"label":"Presell Question","value":"Presell Question"},"3":{"label":"Refund Request","value":"Refund Request"}},"validation_error_message":"This field is required.","slug":"support-type","custom_class":""},"210ef7d8a8":{"type":"subject","slug":"subject","name":"Subject","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":""},"87e0afb2d7":{"type":"user_email","slug":"user_email","name":"Email","description":"","required":["required"],"validation_error_message":"This field is required.","custom_class":""},"0a256db3cb":{"type":"message","slug":"message","name":"Message","description":"","required":["required"],"validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":""}},"layout":{"cords":{"92f6e0cb6b":"2","8ead289ca0":"2","2910663d7e":"1","210ef7d8a8":"1","87e0afb2d7":"1","0a256db3cb":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"SEND","button_width":"blockmobile","button_alignment":"left","button_size":"xlarge","button_class":"button btn btn-primary","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"contact","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Your message has been submitted successfully.","post_type":"bf_submissions","status":"publish","comment_status":"open","singular_name":"","attached_page":"none","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","mail_submissions":{"830e6d7716":{"mail_trigger_id":"830e6d7716","mail_from_name":"user_first","mail_from_name_custom":"","mail_from":"submitter","mail_from_custom":"","mail_to_address":"","mail_to":["submitter","admin"],"mail_to_cc_address":"","mail_to_bcc_address":"","mail_subject":"You Got Mail From Your Contact Form","mail_body":""}},"public_submit":["public_submit"],"public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"name":"User Support","slug":"user-support"},"wc-grouped-product":{"form_fields":{"8f01be94a6":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"56e497c021":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""},"3b8bd90f1d":{"name":"WooCommerce","slug":"_woocommerce","type":"woocommerce","product_type_hidden":["hidden"],"product_type_default":"grouped","product_sales_price":"hidden","product_sales_price_dates":"hidden","product_sku":"none","product_manage_stock_qty":"","product_allow_backorders":"no","product_stock_status":"instock","product_sold_individually":"yes","product_shipping_hidden_weight":"","product_shipping_hidden_dimension_length":"","product_shipping_hidden_dimension_width":"","product_shipping_hidden_dimension_height":"","product_shipping_hidden_shipping_class":"-1","purchase_notes":"","menu_order":"0","enable_review_orders":"yes","_auction_item_condition":"display","_auction_type":"display","_auction_proxy":"display","_auction_start_price":"none","_auction_bid_increment":"none","_auction_reserved_price":"none","_regular_price":"none","auction_dates_from":["required"],"auction_dates_to":["required"]},"25f5c2fa53":{"type":"product-gallery","name":"Product Gallery","description":"","validation_error_message":"This field is required.","slug":"product-gallery","custom_class":""},"9c89c15480":{"slug":"featured_image","type":"featured_image","name":"FeaturedImage","description":""}},"layout":{"cords":{"8f01be94a6":"1","56e497c021":"1","3b8bd90f1d":"1","25f5c2fa53":"1","9c89c15480":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"product","status":"publish","comment_status":"open","singular_name":"","attached_page":"127","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"name":"WC Grouped Product","slug":"wc-grouped-product"},"wc-product-all-fields":{"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"product","status":"publish","comment_status":"open","singular_name":"","attached_page":"127","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"layout":{"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":"","cords":{"8f01be94a6":"1","56e497c021":"1","3b8bd90f1d":"1","25f5c2fa53":"1","9c89c15480":"1"}},"form_fields":{"8f01be94a6":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"56e497c021":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""},"3b8bd90f1d":{"name":"WooCommerce","slug":"_woocommerce","type":"woocommerce","product_type_default":"simple","product_sales_price":"hidden","product_sales_price_dates":"hidden","product_sku":"none","product_manage_stock_qty":"","product_allow_backorders":"no","product_stock_status":"instock","product_sold_individually":"yes","product_shipping_hidden_weight":"","product_shipping_hidden_dimension_length":"","product_shipping_hidden_dimension_width":"","product_shipping_hidden_dimension_height":"","product_shipping_hidden_shipping_class":"-1","purchase_notes":"","menu_order":"0","enable_review_orders":"yes","_auction_item_condition":"display","_auction_type":"display","_auction_proxy":"display","_auction_start_price":"none","_auction_bid_increment":"none","_auction_reserved_price":"none","_regular_price":"none","auction_dates_from":["required"],"auction_dates_to":["required"]},"25f5c2fa53":{"type":"product-gallery","name":"Product Gallery","description":"","validation_error_message":"This field is required.","slug":"product-gallery","custom_class":""},"9c89c15480":{"slug":"featured_image","type":"featured_image","name":"FeaturedImage","description":""}},"name":"WC Product All Fields","slug":"wc-product-all-fields"},"simple-auction":{"form_fields":{"891c6e1fbd":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"7c202f5ad3":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""},"9716540c31":{"name":"WooCommerce","slug":"_woocommerce","type":"woocommerce","product_type_hidden":["hidden"],"product_type_default":"auction","product_sales_price":"hidden","product_sales_price_dates":"hidden","product_sku":"none","product_manage_stock_qty":"","product_allow_backorders":"no","product_stock_status":"instock","product_sold_individually":"yes","product_shipping_hidden_weight":"","product_shipping_hidden_dimension_length":"","product_shipping_hidden_dimension_width":"","product_shipping_hidden_dimension_height":"","product_shipping_hidden_shipping_class":"-1","product_up_sales":["hidden"],"product_cross_sales":["hidden"],"product_grouping":["hidden"],"attributes_hide_tab":["hidden"],"variations_hide_tab":["hidden"],"hide_purchase_notes":["hidden"],"purchase_notes":" ","hide_menu_order":["hidden"],"menu_order":"0","hide_enable_review_orders":["hidden"],"enable_review_orders":"yes","_auction_item_condition":"display","_auction_type":"display","_auction_proxy":"display","_auction_start_price":"none","_auction_bid_increment":"none","_auction_reserved_price":"none","_regular_price":"none","auction_dates_from":["required"],"auction_dates_to":["required"]},"bdfb5fefe2":{"type":"product-gallery","name":"Product Gallery","description":"","validation_error_message":"This field is required.","slug":"product-gallery","custom_class":""},"f98885a27a":{"slug":"featured_image","type":"featured_image","name":"FeaturedImage","description":""}},"layout":{"cords":{"891c6e1fbd":"1","7c202f5ad3":"1","9716540c31":"1","bdfb5fefe2":"1","f98885a27a":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"product","status":"publish","comment_status":"open","singular_name":"","attached_page":"127","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"name":"WC Simple Auction","slug":"simple-auction"},"wc-simple-product":{"form_fields":{"8f01be94a6":{"type":"title","slug":"buddyforms_form_title","name":"Title","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"","custom_class":"","generate_title":""},"56e497c021":{"type":"content","slug":"buddyforms_form_content","name":"Content","description":"","validation_error_message":"This field is required.","validation_minlength":"0","validation_maxlength":"0","custom_class":"","generate_content":""},"3b8bd90f1d":{"name":"WooCommerce","slug":"_woocommerce","type":"woocommerce","product_type_hidden":["hidden"],"product_type_default":"simple","product_sales_price":"hidden","product_sales_price_dates":"hidden","product_sku":"none","product_manage_stock_qty":"","product_allow_backorders":"no","product_stock_status":"instock","product_sold_individually":"yes","product_shipping_hidden_weight":"","product_shipping_hidden_dimension_length":"","product_shipping_hidden_dimension_width":"","product_shipping_hidden_dimension_height":"","product_shipping_hidden_shipping_class":"-1","purchase_notes":"","menu_order":"0","enable_review_orders":"yes","_auction_item_condition":"display","_auction_type":"display","_auction_proxy":"display","_auction_start_price":"none","_auction_bid_increment":"none","_auction_reserved_price":"none","_regular_price":"none","auction_dates_from":["required"],"auction_dates_to":["required"]},"25f5c2fa53":{"type":"product-gallery","name":"Product Gallery","description":"","validation_error_message":"This field is required.","slug":"product-gallery","custom_class":""},"9c89c15480":{"slug":"featured_image","type":"featured_image","name":"FeaturedImage","description":""}},"layout":{"cords":{"8f01be94a6":"1","56e497c021":"1","3b8bd90f1d":"1","25f5c2fa53":"1","9c89c15480":"1"},"labels_layout":"inline","label_font_size":"","label_font_color":{"style":"auto","color":""},"label_font_style":"bold","desc_font_size":"","desc_font_color":{"color":""},"field_padding":"15","field_background_color":{"style":"auto","color":""},"field_border_color":{"style":"auto","color":""},"field_border_width":"","field_border_radius":"","field_font_size":"15","field_font_color":{"style":"auto","color":""},"field_placeholder_font_color":{"style":"auto","color":""},"field_active_background_color":{"style":"auto","color":""},"field_active_border_color":{"style":"auto","color":""},"field_active_font_color":{"style":"auto","color":""},"submit_text":"Submit","button_width":"blockmobile","button_alignment":"left","button_size":"large","button_class":"","button_border_radius":"","button_border_width":"","button_background_color":{"style":"auto","color":""},"button_font_color":{"style":"auto","color":""},"button_border_color":{"style":"auto","color":""},"button_background_color_hover":{"style":"auto","color":""},"button_font_color_hover":{"style":"auto","color":""},"button_border_color_hover":{"style":"auto","color":""},"custom_css":""},"form_type":"post","after_submit":"display_message","after_submission_page":"none","after_submission_url":"","after_submit_message_text":"Form Submitted Successfully","post_type":"product","status":"publish","comment_status":"open","singular_name":"","attached_page":"127","edit_link":"all","list_posts_option":"list_all_form","list_posts_style":"list","public_submit_login":"above","registration":{"activation_page":"none","activation_message_from_subject":"User Account Activation Mail","activation_message_text":"Hi [user_login],\\r\\n\\t\\t\\tGreat to see you come on board! Just one small step left to make your registration complete.\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t<b>Click the link below to activate your account.<\\/b>\\r\\n\\t\\t\\t<br>\\r\\n\\t\\t\\t[activation_link]\\r\\n\\t\\t\\t<br><br>\\r\\n\\t\\t\\t[blog_title]\\r\\n\\t\\t","activation_message_from_name":"[blog_title]","activation_message_from_email":"[admin_email]","new_user_role":"subscriber"},"name":"WC Simple Product","slug":"wc-simple-product"}}';
}