<?php
/**
 * Plugin Name: Prayer 24-7
 * Description: This plugin provides a sign-up system for a period of 24/7 prayer.
 * Version: 2.2.1
 * Author: David Thompson
 * Author URI: https://gracechurchtogether.org.uk
 * Text Domain: prayer247
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class GCPrayer247 {

    public $saveconfirm;
    
    /*
     * Constructor
     */
    public function __construct() {		
        add_action('init', array($this, 'create_post_type'));
        add_action('init', array($this, 'p247updatedate'));

        add_action('admin_menu', array($this, 'p247setupmenu'));
        add_action( 'plugins_loaded', array($this, 'p247settextdomain') );
    
        add_shortcode('p247DrawSlots', array($this, 'p247_draw_slots'));
        add_shortcode('p247DataBase', array($this, 'printp247dbase'));
        
        $this->dtrun();       
        add_option( 'p247startdatetime', "2020-07-20 10:00:00" );
        add_option( 'p247enddatetime', "2020-07-27 09:00:00" );
        add_option( 'p247sitename', get_bloginfo('name') );
        add_option( 'p247siteemail', get_bloginfo('admin_email') );
        $this->saveconfirm = 0;
        
        $p247colors = array (
            'empty' => '#71ff8d',
            'chosen' => '#ffa103',
            'selected' => '#a2d8e8',
            'expanded' => '#fff9c9',
        );
        add_option( 'p247colors', $p247colors );
 	}
	    
    /*
     * Action hooks
     */
    public function dtrun() {     
        // Enqueue plugin styles and scripts
//        add_action( 'plugins_loaded', array( $this, 'enqueue_p247_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_p247_scripts' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'mw_enqueue_color_picker' ) );
        
        // Setup Ajax action hooks
        add_action( 'wp_ajax_submit_selection', array( $this, 'submit_selection' ) );
        add_action( 'wp_ajax_nopriv_submit_selection', array( $this, 'submit_selection' ) );
       
//        add_filter( 'wp_mail_from', array( $this, 'wpb_sender_email' ) );
//        add_filter( 'wp_mail_from_name', array( $this, 'wpb_sender_name' ) );
    }
    
    /**
     * Set text domain for language translation.
     */
    public function p247settextdomain() {
        load_plugin_textdomain( 'prayer247', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
    
    /**
     * Add admin submenu item. This allows the user to set the start date and time for the grid of prayer slots.
     */
    public function p247setupmenu() {
        add_submenu_page( 'edit.php?post_type=p247-allocation', 'P24-7 Settings', __('Settings', 'prayer247'), 'manage_options', 'p247-menu', array( $this, 'p247optionspage') );
    }
    
    /**
     * Add admin settings page. This allows the user to set the start and end, date and time for the grid of prayer slots.
     */
    public function p247optionspage() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'prayer247' ) );
        }
        
        $months = array(
            __('January', 'prayer247'), 
            __('February', 'prayer247'), 
            __('March', 'prayer247'), 
            __('April', 'prayer247'), 
            __('May', 'prayer247'), 
            __('June', 'prayer247'), 
            __('July', 'prayer247'), 
            __('August', 'prayer247'), 
            __('September', 'prayer247'), 
            __('October', 'prayer247'), 
            __('November', 'prayer247'), 
            __('December', 'prayer247')
        );
        
        $plugin_data = get_plugin_data( __FILE__ );
        $plugin_name = $plugin_data['Name'];

        echo '<h1>' . esc_attr( $plugin_name ) . '</h1><p>';
        echo esc_attr__('Welcome to the 24/7 prayer website plugin by Grace Church, Chichester UK.', 'prayer247') . '<br><br>'; 
        echo esc_attr__('Select the start date and time and end date and time for the period of prayer (below).', 'prayer247') . '<br>';
        echo esc_attr__('Then add the following shortcode to a page on the website:', 'prayer247') . '<strong> [p247DrawSlots]</strong>.<br><br>';
        echo esc_attr__('And away you go!', 'prayer247') . '<br><br><br></p>';
        
   		$pageURL = 'http';
   		if ($_SERVER["HTTPS"] == "on") $pageURL .= "s";
   		$pageURL .= "://";
   		if ($_SERVER["SERVER_PORT"] != "80") $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
   		else $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
   		$current_url = $pageURL;
        
        $stdt = get_option( 'p247startdatetime' );
        $sdt = date_create($stdt);
        if ( !$sdt ) {
            $stdt = "2020-01-01 08:00:00";
            update_option( 'p247startdatetime', $stdt );
            $sdt = date_create($stdt);            
        }
        $endt = get_option( 'p247enddatetime' );        
        $edt = date_create($endt);
        if ( !$edt ) {
            $endt = "2020-01-01 08:00:00";
            update_option( 'p247enddatetime', $endt );
            $edt = date_create($endt);
        }
        
        $sname = get_option( 'p247sitename' );
        $semail = get_option( 'p247siteemail' );
        if ( !$sname ) {
            $sname = get_bloginfo('name');
            update_option( 'p247sitename', $sname );
        }
        if ( !$semail ) {
            $semail = get_bloginfo('admin_email');
            update_option( 'p247siteemail', $semail );
        }
        $p247colors = get_option( 'p247colors' );
        if ( !$p247colors ) {
            $p247colors = array (
                'empty' => '#71ff8d',
                'chosen' => '#ffa103',
                'selected' => '#a2d8e8',
                'expanded' => '#fff9c9',
            );
            update_option( 'p247colors', $p247colors );
        }
        
        $allowedline = array(
            'option'    => array( 'value' => array(), 'selected' => array() ),
        );

        ?>
        <div class="p247dateselect">
            <form method="POST" action="<?php echo esc_url($current_url); ?>">
                <?php _e('Select initial time slot:', 'prayer247'); ?>
                <br>
                <br>
                <label for="sdate"><?php _e('Date:', 'prayer247'); ?></label>
                <select id="sdate" name="sdate">
                    <?php
                        for ( $i = 1; $i <= 31; $i++ ) {
                        $line = "<option value=\"d" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $sdt->format( 'd' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . strval($i) . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="smonth"><?php _e('Month:', 'prayer247'); ?></label>
                <select id="smonth" name="smonth">
                    <?php
                        for ( $i = 1; $i <= 12; $i++ ) {
                        $line = "<option value=\"m" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $sdt->format( 'm' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . $months[$i-1] . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="syear"><?php _e('Year:', 'prayer247'); ?></label>
                <select id="syear" name="syear">
                    <?php
                        for ( $i = 20; $i <= 30; $i++ ) {
                        $line = "<option value=\"y" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $sdt->format( 'y' )) {
                            $line .= ' selected';
                        }
                        $line .= ">20" . strval($i) . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="stime"><?php _e('Time:', 'prayer247'); ?></label>
                <select id="stime" name="stime">
                    <?php
                        for ( $i = 0; $i <= 23; $i++ ) {
                        $line = "<option value=\"h" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $sdt->format( 'G' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . ":00h</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <br>
                <br>
                <br>

                <?php _e('Select final time slot:', 'prayer247'); ?>
                <br>
                <br>
                <label for="edate"><?php _e('Date:', 'prayer247'); ?></label>
                <select id="edate" name="edate">
                    <?php
                        for ( $i = 1; $i <= 31; $i++ ) {
                        $line = "<option value=\"d" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $edt->format( 'd' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . strval($i) . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="emonth"><?php _e('Month:', 'prayer247'); ?></label>
                <select id="emonth" name="emonth">
                    <?php
                        for ( $i = 1; $i <= 12; $i++ ) {
                        $line = "<option value=\"m" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $edt->format( 'm' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . $months[$i-1] . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="eyear"><?php _e('Year:', 'prayer247'); ?></label>
                <select id="eyear" name="eyear">
                    <?php
                        for ( $i = 20; $i <= 30; $i++ ) {
                        $line = "<option value=\"y" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $edt->format( 'y' )) {
                            $line .= ' selected';
                        }
                        $line .= ">20" . strval($i) . "</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <label for="etime"><?php _e('Time:', 'prayer247'); ?></label>
                <select id="etime" name="etime">
                    <?php
                        for ( $i = 0; $i <= 23; $i++ ) {
                        $line = "<option value=\"h" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . "\"";
                        if ( strval($i) == $edt->format( 'G' )) {
                            $line .= ' selected';
                        }
                        $line .= ">" . str_pad(strval($i), 2, '0', STR_PAD_LEFT) . ":00h</option>";
                        echo wp_kses( $line, $allowedline );
                    }
                    ?>
                </select>
                <br>
                <br>
                <br>
                <br>
                <label for="sname" style="display: inline-block; width:100px;"><?php _e('Church Name', 'prayer247'); ?></label>
                <input type="text" id="sname" name="sname" style="width:250px;" value="<?php echo esc_attr($sname); ?>"><br><br>
                <label for="semail" style="display: inline-block; width:100px;"><?php _e('Sender Email', 'prayer247'); ?></label>
                <input type="text" id="semail" name="semail" style="width:250px;" value="<?php echo esc_attr($semail); ?>">
                <br>
                <br>
                <br>
                <?php _e('Select the colours to use in your prayer time grid:', 'prayer247'); ?>
                <br>
                <br>
                <label for="colorempty" style="display: inline-block; width:300px;"><?php _e('Empty timeslots', 'prayer247'); ?></label>
                <input type="text" value="<?php echo esc_attr($p247colors['empty']); ?>" name="colorempty" id="colorempty" class="p247-color-field" /><br><br>
                <label for="colorchosen" style="display: inline-block; width:300px;"><?php _e('Chosen timeslots', 'prayer247'); ?></label>
                <input type="text" value="<?php echo esc_attr($p247colors['chosen']); ?>" name="colorchosen" id="colorchosen" class="p247-color-field" /><br><br>
                <label for="colorselected" style="display: inline-block; width:300px;"><?php _e('Currently selected timeslots', 'prayer247'); ?></label>
                <input type="text" value="<?php echo esc_attr($p247colors['selected']); ?>" name="colorselected" id="colorselected" class="p247-color-field" /><br><br>
                <label for="colorexpanded" style="display: inline-block; width:300px;"><?php _e('Days that are expanded into hour timeslots', 'prayer247'); ?></label>
                <input type="text" value="<?php echo esc_attr($p247colors['expanded']); ?>" name="colorexpanded" id="colorexpanded" class="p247-color-field" /><br><br>
                <button class="p247button centered" id="p247restorecolorsbtnid" type="button" onclick="RestoreColors()"><?php _e('Restore defaults', 'prayer247'); ?></button>
                <br>
                <br>
                <input type="hidden" name="p247action" value="p247update"/>
                <input type=hidden name="p247_nonce" value="<?php echo esc_attr(wp_create_nonce( 'p247-nonce-key' )); ?>"/>

                <input type="submit" id="settingssavebtn" class="btn btn-primary" value="<?php _e('Save', 'prayer247'); ?>">
            </form>
            <?php
            switch ( $this->saveconfirm ) {
                case 1:
                    $msg = __('Your settings have been saved!', 'prayer247');
                    break;
                case 2:
                    $msg = __('Your end date/time was invalid, but other settings have been saved.', 'prayer247');
                    break;
                case 3:
                    $msg = __('Your email address was invalid, but other settings have been saved.', 'prayer247');
                    break;
                case 4:
                    $msg = __('There was a problem with some colours, but other settings have been saved.', 'prayer247');
                    break;
                default: 
                    $msg = __('Unknown message code!', 'prayer247');
            }
            if ( $this->saveconfirm == 1 ) : ?>
                <p style="color:green; padding-top:30px;"><?php echo esc_attr($msg); ?></p>
            <?php
                $this->saveconfirm = 0;
            elseif ( $this->saveconfirm > 1 ) : ?>
                <p style="color:red; padding-top:30px;"><?php echo esc_attr($msg); ?></p>
            <?php
                $this->saveconfirm = 0;
            endif;
            ?>
        </div>
        <?php
    }
    
    /**
     * Process the admin settings when saved
     */
    public function p247updatedate() {
        if ( isset($_POST['p247action']) ) {
            $p247action = sanitize_text_field( $_POST['p247action'] );

            if (!is_user_logged_in() || $p247action != 'p247update' )
                return;

            if ( wp_verify_nonce($_POST['p247_nonce'], 'p247-nonce-key') ) {
                if ( isset( $_POST['sdate'] ) ) {
                    $sdate = substr( sanitize_text_field( $_POST['sdate'] ), -2 );
                }
                if ( isset( $_POST['smonth'] ) ) {
                    $smonth = substr( sanitize_text_field( $_POST['smonth'] ), -2 );
                }
                if ( isset( $_POST['syear'] ) ) {
                    $syear = substr( sanitize_text_field( $_POST['syear'] ), -2 );
                }
                if ( isset( $_POST['stime'] ) ) {
                    $stime = substr( sanitize_text_field( $_POST['stime'] ), -2 );
                }

                $sdt = '20' . $syear . '-' . $smonth . '-' . $sdate . ' ' . $stime . ':00:00';
                update_option( 'p247startdatetime', $sdt );

                if ( isset( $_POST['edate'] ) ) {
                    $edate = substr( sanitize_text_field( $_POST['edate'] ), -2 );
                }
                if ( isset( $_POST['emonth'] ) ) {
                    $emonth = substr( sanitize_text_field( $_POST['emonth'] ), -2 );
                }
                if ( isset( $_POST['eyear'] ) ) {
                    $eyear = substr( sanitize_text_field( $_POST['eyear'] ), -2 );
                }
                if ( isset( $_POST['etime'] ) ) {
                    $etime = substr( sanitize_text_field( $_POST['etime'] ), -2 );
                }

                $edt = '20' . $eyear . '-' . $emonth . '-' . $edate . ' ' . $etime . ':00:00';

                // Do not allow end date to be before start date
                $sdate = new DateTime( $sdt );
                $edate = new DateTime( $edt );
                if ( $edate < $sdate ) {
                    $edate = $sdate->modify( '+1 day' );            // Set the end date to be start date plus one day
                    $edt = $edate->format( 'Y-m-d H:i:s' );
                    $this->saveconfirm = 2;
                }
                update_option( 'p247enddatetime', $edt );

                if ( isset( $_POST['sname'] ) ) {
                    update_option( 'p247sitename', sanitize_text_field( $_POST['sname'] ) );
                }
                if ( isset( $_POST['semail'] ) ) {
                    $semail = sanitize_email( $_POST['semail'] );
                    if ( $semail ) {
                        update_option( 'p247siteemail', $semail );
                    }
                    else {
                        $this->saveconfirm = 3;
                    }
                }

                $colors = get_option( 'p247colors' );
                if ( isset( $_POST['colorempty'] ) ) {
                    $empty = sanitize_hex_color( $_POST['colorempty'] );
                    if ( $this->isokcolor($empty) ) {
                        $colors['empty'] = $empty;
                    }
                    else {
                        $this->saveconfirm = 4;
                    }
                }
                if ( isset( $_POST['colorchosen'] ) ) {
                    $chosen = sanitize_hex_color( $_POST['colorchosen'] );
                    if ( $this->isokcolor($chosen) ) {
                        $colors['chosen'] = $chosen;
                    }
                    else {
                        $this->saveconfirm = 4;
                    }
                }
                if ( isset( $_POST['colorselected'] ) ) {
                    $selected = sanitize_hex_color( $_POST['colorselected'] );
                    if ( $this->isokcolor($selected) ) {
                        $colors['selected'] = $selected;
                    }
                    else {
                        $this->saveconfirm = 4;
                    }
                }
                if ( isset( $_POST['colorexpanded'] ) ) {
                    $expanded = sanitize_hex_color( $_POST['colorexpanded'] );
                    if ( $this->isokcolor($expanded) ) {
                        $colors['expanded'] = $expanded;
                    }
                    else {
                        $this->saveconfirm = 4;
                    }
                }
                update_option( 'p247colors', $colors );

                // If no error code has been set, change code to 'Everything OK' so that message is displayed on page load
                if ( $this->saveconfirm == 0 ) {
                    $this->saveconfirm = 1;
                }
            }
        }
    }
    
    /**
     * Check if string is a valid hex color code
     */
    public function isokcolor( $color ) {
        //Check for a hex color string '#c1c2b4'. Preceding # is optional.
        
        $hexok = false;
        if(preg_match('/^#[a-f0-9]{6}$/i', $color)) //hex color is valid
        {
            //Verified hex color
            $hexok = true;
        } 

        //Check for a hex color string without hash 'c1c2b4'
        elseif(preg_match('/^[a-f0-9]{6}$/i', $color)) //hex color is valid
        {
            //Verified hex color (no preceding #)
            $hexok = true;
        }
            
        return $hexok;
    }
    
/********************************************************************************** 
***  ACTIVE. Register the customer post type for prayer sign-up.                ***
**********************************************************************************/ 
    public function create_post_type() {
      register_post_type( 'p247-allocation',
        array(
          'labels' => array(
            'name' => __( 'Prayer 24-7' ),
            'singular_name' => __( 'Prayer 24-7' ),
            'all_items' => __( 'All Sign-ups', 'prayer247' ),
          ),
          'public' => true,
          'has_archive' => true,
          'supports' => array( 'title', 'editor', 'custom-fields','thumbnail' ),//      'taxonomies' => array( 'category' ),
          'menu_icon' => 'dashicons-forms',
        )
      );
    
      register_taxonomy( 'p247_category', // register custom taxonomy - category
            'p247-allocation',
            array(
                'hierarchical' => true,
                'labels' => array(
                    'name' => 'P247 Category',
                    'singular_name' => 'P247 Category',
                )
            )
        );
    }
    
    
    /**
     * Enqueues plugin-specific scripts.
     */
    public function enqueue_p247_scripts() {        
        $ajax_url   = admin_url( 'admin-ajax.php' );        // Localized AJAX URL

        wp_register_script( 'p247-script', plugins_url( 'js/prayer247.js', __FILE__ ), array('jquery'), '1.0', true );
    
        // Localise AJAX variables. Do NOT use ajax_obj as var name, ie, make it unique to this context.
        wp_localize_script( 'p247-script', 'p247_ajax_obj', array( 
            'ajax_url' => $ajax_url,
            'security' => wp_create_nonce( 'p247-security-nonce' ) )        // for nonce, ensure that script is enqueued/registered prior to localize
        );

        $stdatetime = date_create( get_option( 'p247startdatetime' ) );
        $endatetime = date_create( get_option( 'p247enddatetime' ) );
        $dataToBePassed = array(
            'sdate'            => $stdatetime->format( 'd' ),
            'smonth'           => $stdatetime->format( 'm' ),
            'syear'            => $stdatetime->format( 'Y' ),
            'shour'            => $stdatetime->format( 'H' ),
            'edate'            => $endatetime->format( 'd' ),
            'emonth'           => $endatetime->format( 'm' ),
            'eyear'            => $endatetime->format( 'Y' ),
            'ehour'            => $endatetime->format( 'H' ),
            'admin'            => is_user_logged_in(),
            'colors'           => get_option( 'p247colors' )
        );
        wp_localize_script( 'p247-script', 'p247_vars', $dataToBePassed );
        
        $translate = array (
            'areyousure'        => esc_attr__( 'Are you sure you want to commit to praying at the following times?', 'prayer247' ),
            'thankyou'          => esc_attr__( 'Thank you!', 'prayer247' ),
            'reg_plu'           => esc_attr__( 'Your selected prayer slots have been registered.', 'prayer247' ),
            'reg_sng'           => esc_attr__( 'Your selected prayer slot has been registered.', 'prayer247' ),
            'emailsent'         => esc_attr__( 'We have sent you an email to confirm.', 'prayer247' ),
            'close'             => esc_attr__( 'Close', 'prayer247' ),
            'name'              => esc_attr__( 'Name', 'prayer247' ),
            'email'             => esc_attr__( 'Email', 'prayer247' ),
            'confirm'           => esc_attr__( 'Confirm', 'prayer247' ),
            'cancel'            => esc_attr__( 'Cancel', 'prayer247' ),
        );
        wp_localize_script( 'p247-script', 'p247_translate', $translate );
        
        wp_enqueue_script( 'p247-script' );
      
        wp_register_style( 'p247-style', plugins_url( 'css/prayer247.css', __FILE__ ) );
        wp_enqueue_style( 'p247-style' ); 
    }   
 
    
    function mw_enqueue_color_picker( $hook_suffix ) {
        // First check that $hook_suffix is appropriate for your admin page.
        // To find out which hook applies to the current admin page, add this temp php to page: print_r(get_current_screen());
        if ( $hook_suffix != 'p247-allocation_page_p247-menu' ) {
            return;
        }
        
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'p247-admin-handle', plugins_url('js/p247-admin.js', __FILE__ ), array( 'wp-color-picker' ), false, true );

        wp_register_style( 'p247-admin-style', plugins_url( 'css/p247-admin.css', __FILE__ ) );
        wp_enqueue_style( 'p247-admin-style' ); 
    }

    
    // Function to change email address
    public function wpb_sender_email( $original_email_address ) {
        return 'info@gracechurchtogether.org.uk';
    }

    // Function to change sender name
    public function wpb_sender_name( $original_email_from ) {
        return 'Grace Church';
    } 
    
    public function getstartofweek( $stdatetime ) {
        $stday = clone $stdatetime;
        $dy = date_format($stday,"l");
        while ( $dy !== 'Monday' ) {
            date_sub($stday,date_interval_create_from_date_string("24 hours"));
            $dy = date_format($stday,"l");
        }
        return $stday;
    }
    
    
    /**
     * Render the prayer allocation timeslots in HTML. This is a shortcode function.
     */
    public function p247_draw_slots() {
        $locale = get_user_locale();
        $english = strtolower($locale) == 'en_gb' || strtolower($locale) == 'en-gb' || strtolower($locale) == 'en_us' || strtolower($locale) == 'en-us';

        // The IntlDateFormatter class requires PHP v7 (or Intl extension) to be installed, so fallback gracefull to English if this doesn't work
        if ( class_exists('IntlDateFormatter') ) {
            $formatterdy = new IntlDateFormatter( $locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT );
            $formatterdy->setPattern('EEEE');
            $formatterdt = new IntlDateFormatter( $locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT );
            $formatterdt->setPattern('d MMM');
        }
        else {
            $english = true;
        }
        
        $idx = 0;
        ?>
        <div class="p247container">

            <div class="p247xgrid" id="p24xgridid">
                <?php
                $stdatetime = date_create( get_option( 'p247startdatetime' ) );
                $startofweek = $this->getstartofweek( $stdatetime );
                $endatetime = date_create( get_option( 'p247enddatetime' ) );
                $endstr = date_format($endatetime, "jS M");

                $wkid = 1;
                $thisday = clone $startofweek;
                $reachedend = false;
        
                $allowedts = array(
                    'br' => array()
                );

                do {
                    ?>
                    <div class="p24xweekgrid">
                        <?php
                        for ($dy = 1; $dy <= 7; $dy++) :
                            $stpart = date_format($thisday,"jS M");
                            ?>
                            <div class="p24xgriditem" id="<?php echo esc_attr('wk'.$wkid.'dy'.$dy); ?>">
                                <?php 
                                    if ( $english ) {
                                        // Write explicitly so we can include the date ordinal (st, nd, th)
                                        echo wp_kses((date_format($thisday,"l") . '<br>' . $stpart), $allowedts);
                                    }
                                    else {
                                        // Not English/US locale, so translate date
                                        echo wp_kses( $formatterdy->format($thisday) . '<br>' . $formatterdt->format($thisday), $allowedts );
                                    }
                                ?>
                            </div>
                            <?php
                            if ($stpart == $endstr) {
                                $reachedend = true;
                            }
                            date_add($thisday,date_interval_create_from_date_string("24 hours"));
                        endfor;
                        $wkid++;
                        ?>
                    </div>
                    <?php
                } while (!$reachedend);
                ?>
            </div>

            <div class="p247summary">
                <div class="p247summtext">
                </div>
                <div class="p247summlist" id="p247summlistid">

                </div>
                <div class="p247formdiv" id="p247formid">
                </div>
            </div>
            <button class="p247button p247selectbtn" id="p247selectbtnid" type="button" disabled><?php _e( 'Select', 'prayer247' ); ?></button>

        </div>

        <?php
    }
 
    
    /**
     * Ajax handler function
     */
    public function submit_selection() {
        // Ensure we have the data we need to continue
        if( ! isset( $_POST ) || empty( $_POST ) ) {
            wp_send_json_error( __('Could Not Verify POST Values.', 'prayer247') );
            wp_die();
        }
        
        // Check security nonce
        if ( ! check_ajax_referer( 'p247-security-nonce', 'security' ) ) {
            wp_send_json_error( __('Invalid security token sent.', 'prayer247') );
            wp_die();
        }

        // Sanitize our user meta value
        $p24mode = sanitize_text_field( $_POST['ajax_mode'] );
        if( $p24mode !== '10' && $p24mode !== '20' ) {
            wp_send_json_error( __('Unallowed mode.', 'prayer247') );
            wp_die();
        }
        
        if( $p24mode == '20' ) {
            // This mode is for adding new prayer slots
            $p247name = sanitize_text_field( $_POST['ajax_name'] );      
            $p247email = sanitize_text_field( $_POST['ajax_email'] );      
//          $p247slots = json_decode( $_POST['ajax_slots'] );      
            $p247slots = sanitize_text_field( $_POST['ajax_slots'] );      

            // Create post
            $pargs = array (
                'post_title'    => wp_strip_all_tags( $p247name ),
                'post_type'     => 'p247-allocation',
                'meta_input'    => array (
                    'p247_name'    => $p247name,
                    'p247_email'    => $p247email,
                    'p247_slots'   => $p247slots)
            );
            // Insert the post into the database
            $pid = wp_insert_post( $pargs );
            
            // Send confirmation email
            $sanitized_to = sanitize_email( $p247email );
            if ( $sanitized_to != '' ) {
                $sname = get_option( 'p247sitename' );
                $semail = get_option( 'p247siteemail' );
                $subject = $sname . ' Prayer 24/7 Committment';

                $body = 'Dear ' . $p247name . '<br>' . '<br>' . __('Thank you for agreeing to commit to pray in the following hour time slot(s):', 'prayer247') . '<br><br>';

                $slotsbyname = $this->getslotdesc( $p247slots );
                foreach ( $slotsbyname as $tsn ) {
                    $body .= $tsn . '<br>';
                }
                $body .= '<br>' . __('Many thanks!', 'prayer247') . '<br><br>' . $sname . '<br><br>';
                $headers[] = "Content-Type: text/html; charset=UTF-8";
                
                // Set email 'From' field according to saved settings
                $from = "From: " . $sname . " <" . $semail . ">\r\n";
                $headers[] = $from;

                wp_mail( $sanitized_to, $subject, $body, $headers );   
            } 

        }
        
        // Whatever the mode, return the latest version of the grid
        $stdatetime = date_create( get_option( 'p247startdatetime' ) );
        $endatetime = date_create( get_option( 'p247enddatetime' ) );
        $diff = date_diff( $stdatetime, $endatetime )->format('%a') + 1;
        $slots = array_fill( 0, $diff, array_fill( 0, 24, 0 ) );                // Initialise the array as if no slots have been selected
        $slots = $this->construct_grid( $slots, $stdatetime, $endatetime );     // Update array based on database entries between the start and end dates
        
        // Encode array and return to browser
        echo json_encode( $slots );
        exit;        
    }
    
    /**
     * Query database and build array of slot selections
     */
    public function construct_grid( $slots, $stdatetime, $endatetime ) {        
        $args = array(  
            'post_type' => 'p247-allocation',
            'post_status' => array( 'publish', 'draft' ),
            'posts_per_page' => -1, 
        );

        $loop = new WP_Query( $args ); 
        
        while ( $loop->have_posts() ) : $loop->the_post(); 
            $pid = get_the_ID();
            $pnm = get_post_meta( $pid, 'p247_name', true );    // we don't actually need the person's name here
            $pem = get_post_meta( $pid, 'p247_email', true );   // we don't actually need the person's email here
            $psl = get_post_meta( $pid, 'p247_slots', true );   // we do need the array of slots that they selected
        
            $psla = explode( ',', trim($psl, '[]') );
        
            foreach ($psla as $thisslot) {
                $ts = date_create( $thisslot );

                if ( $ts >= $stdatetime && $ts <= $endatetime ) {
                    //  This slot allocation lies within the start and end date (which could have been changed since this slot 
                    //  was added by the user.)
                    $hour = intval( date_format($ts, "G"));

                    $copystart = clone $stdatetime;
                    $copystart->setTime(0,0,0,0);
                    $ts->setTime(0,0,0,0);
                    $diff = date_diff( $copystart, $ts );
                    $diffdays = $diff->format('%a');
                    $day = intval( $diffdays );
                    $slots[ $day ][ $hour ]++;
                }
            }
        endwhile;
        return $slots;
    }
    
    /**
     * Echo contents of database
     */
    public function printp247dbase() {
        if ( !is_user_logged_in() ) {
            return;
        }
        
        $allowedtags = array(
            'br' => array()
        );
        
        $args = array(  
            'post_type' => 'p247-allocation',
            'post_status' => array( 'publish', 'draft' ),
            'posts_per_page' => -1, 
        );

        $ploop = new WP_Query( $args ); 

        while ( $ploop->have_posts() ) : 
            $ploop->the_post(); 
            $pid = get_the_ID();
            $pnm = get_post_meta( $pid, 'p247_name', true );    
            $pem = get_post_meta( $pid, 'p247_email', true );   
            $psl = get_post_meta( $pid, 'p247_slots', true );   
        
            $psla = explode( ',', trim($psl, '[]') );
        
            echo esc_html($pnm . ", " . $pem . ", ");
            foreach ($psla as $thisslot) {
                echo esc_html($thisslot . ", ");
            }
            echo wp_kses("<br>", $allowedtags);
        endwhile;
        return;
    }
    
    /**
     * Get a date-ordered array of timeslot descriptions
     */
    public function getslotdesc( $p247slots ) {
        $locale = get_user_locale();
        $english = strtolower($locale) == 'en_gb' || strtolower($locale) == 'en-gb' || strtolower($locale) == 'en_us' || strtolower($locale) == 'en-us';
        
        // The IntlDateFormatter class requires PHP v7 (or Intl extension) to be installed, so fallback gracefull to English if this doesn't work
        if ( class_exists('IntlDateFormatter') ) {
            $formatlong = new IntlDateFormatter( $locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT );
            $formatlong->setPattern('EEEE d MMM, ha');
            $formatshort = new IntlDateFormatter( $locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT );
            $formatshort->setPattern('ha');
        }
        else {
            $english = true;
        }

        
        $idxs = explode( ',', trim($p247slots, '[]') );

        $slotdesc = array();
        $n = 0;
        foreach ( $idxs as $idx ) {
            $dtobj = date_create( $idx );
            $enobj = clone $dtobj;
            date_add( $enobj, date_interval_create_from_date_string( "1 hours" ) );
            if ( $english ) {
                $slotdesc[$n++] = date_format($dtobj, "l jS M, ga") . ' to ' . date_format($enobj, "ga");
            }
            else {
                $slotdesc[$n++] = $formatlong->format($dtobj) . ' ' . __('to', 'prayer247') . ' ' . $formatshort->format($enobj);
            }
        }
        return $slotdesc;            
    }
    
 }      // END of plugin class definition

$GCPrayer247 = new GCPrayer247;

?>