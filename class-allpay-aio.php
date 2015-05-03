<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Allpay AIO Payment Gateway
 * Plugin Name: Allpay AIO for Woocommerce
 * Plugin URI: http://innovext.com
 * Description: Woocommerce 歐付寶全方位金流模組
 * Version: 1.0.3
 * Author URI: contact@innovext.com
 * Author: 因創科技
 */
add_action('plugins_loaded', 'innovext_allpay_aio_gateway_init', 100 );

function innovext_allpay_aio_gateway_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_innovext_allpay_aio extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {

            $this->id = 'innovext_allpay_aio';
            $this->icon = apply_filters('inno_woocommerce_allpay_icon', plugins_url('icon/allpay-logo.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = '歐付寶全方位金流';

            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instruction_succ = $this->get_option('instruction_succ');
            $this->instruction_on_hold = $this->get_option('instruction_on_hold');
            $this->expire_date = $this->get_option('ExpireDate');
            $this->exclude_payments = $this->get_option('exclude_payments');
            $this->order_prefix = $this->get_option('order_prefix');
            $this->min_amount = $this->get_option('min_amount');
            $this->query_allpay_trade_info = $this->get_option('query_allpay_trade_info');
            $this->payment_method_label = $this->get_option('payment_method_label');
            $this->mer_id = $this->get_option('MerchantID');
            $this->hash_key = $this->get_option('hash_key');
            $this->hash_iv = $this->get_option('hash_iv');
            $this->testmode = $this->get_option('testmode');
            $this->admin_mode = $this->get_option('admin_mode');
            $this->debug = $this->get_option('debug');

            $this->payment_type = 'aio';
            $this->notify_url = WC()->api_request_url( 'WC_innovext_allpay_aio' );

            // Test Gateway
            $this->live_gateway = 'https://payment.allpay.com.tw/Cashier/AioCheckOut';
            $this->test_gateway = 'http://payment-stage.allpay.com.tw/Cashier/AioCheckOut';

            // debug log
            if ( 'yes' == $this->debug ) {
                $this->log = new WC_Logger();
            }

            if ( 'yes' == $this->testmode ) {
            	$this->title .= '|測試';
            }

            if ( 'yes' == $this->admin_mode ) {
            	$this->title .= '|管理員';
            }

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            //add_action( 'woocommerce_thankyou_'. $this->id, array( $this, 'thankyou_page' ) );
            add_action( 'woocommerce_receipt_'. $this->id, array( $this, 'receipt_page' ) );
            add_action( 'woocommerce_api_wc_'. $this->id, array( $this, 'check_allpay_response' ) );
            add_action( 'valid_allpay_ipn_request', array( $this, 'successful_request' ) );
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'allpay_admin_order_data_after_billing_address' ) , 10, 1 );
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_order_received_text' ), 10, 2 );

            // add query order metabox
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_allpay_query_trade_info_meta_box' ) , 10, 1 );

            // Customer Emails
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2 );


        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {

            $this->form_fields = array(
                'enabled'         => array(
                    'title'       => '啟用/關閉',
                    'type'        => 'checkbox',
                    'label'       => '啟動 歐付寶全方位金流',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => '標題',
                    'type'        => 'text',
                    'description' => '客戶在結帳時所看到的標題',
                    'default'     => '歐付寶全方位金流',
                    'desc_tip'    => true,
                ),
                'MerchantID' => array(
                    'title'       => '商店代號',
                    'type'        => 'text',
                    'description' => '請填入您的歐付寶商店代號，測試模式請填入<code>2000132</code>',
                    'default'     => '',
                ),
                'hash_key' => array(
                    'title'       => 'Hash Key',
                    'type'        => 'text',
                    'description' => '請填入您歐付寶廠商後台系統的Hash Key，測試模式請填入<code>5294y06JbISpM5x9</code>',
                    'default'     => '',
                ),
                'hash_iv' => array(
                    'title'       => 'Hash IV',
                    'type'        => 'text',
                    'description' => '請填入您歐付寶廠商後台系統的Hash IV，測試模式請填入<code>v77hoKGq4kWxNNIS</code>', 
                    'default'     => '',
                ),
                'description'     => array(
                    'title'       => '客戶訊息',
                    'type'        => 'textarea',
                    'description' => '在這裡輸入下訂前，客戶會看到的訊息',
                    'default'     => '歐付寶全方位金流 - 儲值支付帳戶、歐付寶餘額、信用卡、WebATM線上匯款、ATM櫃員機匯款、超商代碼、超商條碼、支付寶付款、財付通付款。',
                    'desc_tip'    => true,
                ),
                'instruction_succ' => array(
                    'title'       => '成功付款訂單指示',
                    'type'        => 'textarea',
                    'description' => '在這裡輸入下訂後成功付款，客戶會看到的訊息',
                    'default'     => '謝謝您，已經成功收到您的付款，我們會儘快進行出貨的動作。',
                    'desc_tip'    => true,
                ),
                'instruction_on_hold' => array(
                    'title'       => '待付款訂單指示',
                    'type'        => 'textarea',
                    'description' => '在這裡輸入下訂後尚未付款，客戶會看到的訊息，使用在銀行虛擬帳號、超商代碼、超商條碼',
                    'default'     => '請注意，我們尚未收到您的付款。使用歐付寶ATM繳款(虛擬帳號)、超商代碼繳款的顧客，已將繳款訊息寄至您的信箱，若您忘記或移失繳款資訊，請再重新下訂。請依歐付寶所提供的資訊進行付款，若您成功付款，系統會自動接收已付款訊息，我們會儘快進行出貨的動作。',
                    'desc_tip'      => true,
                ),
                'ExpireDate' => array(
                    'title'       => 'ATM繳費期限(天)',
                    'type'        => 'number',
                    'placeholder' => 3,
                    'description' => 'ATM繳款的允許繳費有效天數，最長60天，最短1天，不填則預設為3天',
                    'default'     => '',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min'  => 1,
                        'max'  => 60,
                        'step' => 1
                    ),
                    'css'         => 'width:60px;',
                ),
                'exclude_payments' => array(
                    'title'         => '排除付款方式',
                    'type'          => 'multiselect',
                    'class'         => 'chosen_select',
                    'css'           => 'width: 450px;',
                    'default'       => '',
                    'description'   => '使用者在歐付寶付款介面不會看見該付款方式，可留白',
                    'desc_tip'      => true,
                    'options'       => self::get_main_payment_type_args(),
                    'custom_attributes' => array(
                        'data-placeholder' => '選擇排除付款方式'
                    ),
                ),
                'payment_method_label' => array(
                    'title'       => '付款標題顯示方式',
                    'type'        => 'select',
                    'description' => '選擇在訂單列表或電子郵件時付款方式標題如何顯示，主要付款方式的標題會顯示成，例:網路ATM，細項付款方式標題會顯示成，例:台新WebATM',
                    'default'     => 'default',
                    'desc_tip'    => true,
                    'options'     => array(
                        'default'         => '預設標題',
                        'main_label'     => '主要付款標題',
                        'detailed_label' => '細項付款標題'
                    )
                ),
                'order_prefix' => array(
                    'title'       => '訂單編號前綴',
                    'type'        => 'text',
                    'description' => '訂單編號的前綴，建議只使用英文，不建議使用數字，不可包含特殊符號，可留白。如果有設前綴的話，那訂單編號會像是"WC123"',
                    'desc_tip'    => true,
                    'default'     => 'WC',
                ),
                'min_amount' => array(
                    'title'       => '最低訂單金額',
                    'type'        => 'number',
                    'placeholder' => wc_format_localized_price( 0 ),
                    'description' => '顧客訂單金額必需大於此金額才可使用歐付寶結帳，0 為不限制',
                    'default'     => '0',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min'  => 0,
                        'step' => 1
                    ),
                    'css'         => 'width:60px;',
                ),
                'query_allpay_trade_info' => array(
                    'title'       => '顯示歐付寶訂單資訊',
                    'type'        => 'checkbox',
                    'label'       => '啟用查詢訂單',
                    'default'     => 'no',
                    'description' => '有別於在WooCommerce帳單資訊欄位顯示訂單狀態，這項功能可以讓您了解歐付寶所儲存訂單的當前資訊',
                    'desc_tip'    => true,
                ),
                'cron_frequency_min' => array(
                    'title'       => '檢查過期訂單',
                    'type'        => 'number',
                    'placeholder' => 0,
                    'description' => '檢查未付款訂單的頻率(分鐘)，像是超商條碼、代碼付款、ATM付款等有付款期限的訂單，讓它們在到期又未付款的話，自動更改訂單狀態為"取消"，0 為不啟用',
                    'default'     => '360',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min'  => 0,
                        'step' => 1
                    ),
                    'css'         => 'width:60px;',
                    'autoload'          => false
                ),
                'testmode' => array(
                    'title'       => '歐付寶測試模式',
                    'type'        => 'checkbox',
                    'label'       => '啟用測試模式',
                    'default'     => 'no',
                    'description' => '歐付寶測試模式請填入測試商店代號、Hash Key、Hash IV',
                ),
                'admin_mode' => array(
                    'title'       => '管理員模式',
                    'type'        => 'checkbox',
                    'label'       => '啟用管理員測試模式',
                    'default'     => 'no',
                    'description' => '開啟這項選項，可以只讓管理員看到歐付寶結帳方式',
                    'desc_tip'    => true,
                ),
                'debug' => array(
                    'title'       => '除錯紀錄',
                    'type'        => 'checkbox',
                    'label'       => '啟用除錯紀錄',
                    'default'     => 'no',
                    'description' => sprintf( '紀錄除錯/回應訊息，檔案存放於<code>%s</code>', wc_get_log_file_path( 'allpay_aio' ) ),
                )
            );
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @access public
         * @return void
         */
        public function admin_options() {

            echo '<h3>歐付寶全方位金流</h3>';

           	$this->display_allpay_admin_notice();

            echo '<table class="form-table">';

            // Generate the HTML For the settings form.
            $this->generate_settings_html();

            echo '</table>';

        }

        /**
         * Display admin notice if option is set to admin mode or test mode
         *
         * @access public
         * @return void
         */        
        function display_allpay_admin_notice() {

            if( $this->testmode == 'yes' ) {
                echo '<div class="error">';
                echo '<p>現正開啟測試模式</p>';
                echo '</div>';
            }

            if( $this->admin_mode == 'yes' ) {
                echo '<div class="error">';
                echo '<p>現正開啟管理員模式</p>';
                echo '</div>';
            }
        }

        /**
         * Get allpay Args for passing to allpay
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_allpay_args($order) {

            if( $this->order_prefix ){
                $order_id = $this->order_prefix . $order->id;
            }else{
                $order_id = $order->id;
            }

            if( 'yes' == $this->debug ){
                $this->log->add('allpay_aio', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . $this->notify_url);
            }
            
            $total_amount = round($order->get_total());

            // Get order items
            if ( sizeof( $order->get_items() ) > 0 ) {

                $item_names = '';

                foreach ( $order->get_items() as $item ) {
                    if ( $item['qty'] ) {

                        /**
                         * because the product line will be splitted by # after submmited to allpay,
                         * so we have to replace the hash '#' to muilti byte one if the product title
                         * contains #. And also convert special characters to prevent any error.
                         */
                        $item_name = self::convert_special_char_to_multibyte( $item['name'] );
                        $item_names .= $item_name . ' x ' . $item['qty'].'#';
                    }
                }
                $item_names = rtrim( $item_names, '#' );
            }

            // get excluded payment methods
            $ignore_payment = '';
            if ( $this->exclude_payments ) {
                foreach( $this->exclude_payments as $exclude_payment ) {
                    $ignore_payment .= $exclude_payment . '#';
                }
                $ignore_payment = rtrim( $ignore_payment, '#' );
            }

            $expire_date = $this->expire_date;

            if( 'yes' == $this->debug ) {
            	$this->log->add('allpay_aio','Item Name :'.$item_names);
            }

            $mer_id = $this->mer_id;
            $payment_type = $this->payment_type;
            $return_url = $this->get_return_url($order);
            $notify_url = $this->notify_url;

            $allpay_args = array(
                'MerchantID'        => $mer_id,
                'MerchantTradeNo'   => $order_id,
                'MerchantTradeDate' => date('Y/m/d H:i:s', time()),
                'PaymentType'       => $payment_type,
                'TotalAmount'       => $total_amount,
                'TradeDesc'         => get_bloginfo('name'),
                'ItemName'          => $item_names,
                'ChoosePayment'     => 'ALL',
                'ReturnURL'         => $notify_url, // reply url
                'PaymentInfoURL'    => $notify_url, // CVS, Barcode reply url
                'ClientBackURL'     => $return_url, 
                'OrderResultURL'    => $return_url, // return to store
            );

            // set ignore payment
            if( $ignore_payment ) {
                $allpay_args['IgnorePayment'] = $ignore_payment;
            }

            // set expire date
            if( $expire_date > 0 && $expire_date <= 60 ) {
                $allpay_args['ExpireDate'] = $this->expire_date;
            }

            $allpay_args = apply_filters( 'innovext_allpay_aio_args', $allpay_args );

            return $allpay_args;
        }

        /**
         * Convert special character to multibyte
         *
         * @param  $string the string contains any character include special character
         * @return string
         */
        static function convert_special_char_to_multibyte( $string ) {

            $char    = array( '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '=', '+' );
            $mb_char = array( '～', '！', '＠', '＃', '＄', '％', '︿', '＆', '＊', '（', '）', '—', '＝', '＋' );

            return str_replace( $char, $mb_char, $string );
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @param mixed $order_id
         * @return void
         */
        function thankyou_page( $order_id ) {

            $return_code = get_post_meta( $order_id, '_RtnCode', true );

            if( $return_code ) {
                if( $return_code === '1' && $this->instruction_succ ) {
                    echo wpautop( wptexturize( $this->instruction_succ ) );
                } elseif( ( $return_code === '2' || $return_code === '10100073' ) && $this->instruction_on_hold ) {
                    echo wpautop( wptexturize( $this->instruction_on_hold ) );
                } else {
                    echo '<p style="padding: 5px 15px;background-color: #FFD0D0;border: 1px solid #E30000;">錯誤代碼 : '.$return_code.'</p>';                    
                }
            } else {
                echo '<p style="padding: 5px 15px;background-color: #FFD0D0;border: 1px solid #E30000;">發生錯誤，無法取得訂單狀態，若您已付款，請通知商店管理員。</p>';
            }
        }

        /**
         * Thank you order received text
         * 
         * @param  string $message the original message
         * @param  object $order   WC Order
         * @return mixed
         */
        function thankyou_order_received_text( $text, $order ) {

            $order_id = $order->id;

            $return_code = get_post_meta( $order_id, '_RtnCode', true );

            if ( $order->payment_method != 'innovext_allpay_aio' ) {
                return $text;
            }

            if( $return_code ) {
                if( $return_code === '1' && $this->instruction_succ ) {
                    return wpautop( wptexturize( $this->instruction_succ ) );
                } elseif( ( $return_code === '2' || $return_code === '10100073' ) && $this->instruction_on_hold ) {
                    return wpautop( wptexturize( $this->instruction_on_hold ) );
                } else {
                    return '<p style="padding: 5px 15px;background-color: #FFD0D0;border: 1px solid #E30000;">錯誤代碼 : '.$return_code.'</p>';
                }
            } else {
                return '<p style="padding: 5px 15px;background-color: #FFD0D0;border: 1px solid #E30000;">發生錯誤，無法取得訂單狀態，若您已付款，請通知商店管理員。</p>';
            }

            return $text;
        }

        /**
         * Get check mac value. Check mac value is the validation mechanism of allpay
         * to check the post value from/to allpay to prevent the value been falsified.
         *
         * @param array $args
         * @return string
         */        
        function get_check_mac_value( $args ){

            ksort($args);
            $hash_key = $this->hash_key;
            $hash_iv = $this->hash_iv;
            $args_hash_key = array_merge( array( 'HashKey'=> $hash_key ), $args, array( 'HashIV' => $hash_iv ) );

            $args_string = '';
            foreach( $args_hash_key as $v => $k ){
                $args_string .= $v .'='. $k .'&';
            }

            $args_string = rtrim( $args_string, "&" );
            $args_urlencode = urlencode( $args_string );
            $args_to_lower = strtolower( $args_urlencode );
            $check_mac_value = md5( $args_to_lower );

            return $check_mac_value;
        }

        /**
         * Generate the allpay button link (POST method)
         *
         * @access public
         * @param string $order_id
         * @return string
         */
        function generate_allpay_form( $order_id ) {

            global $woocommerce;

            $order = new WC_Order($order_id);

            $allpay_args = $this->get_allpay_args($order);

            if( 'yes' == $this->testmode ){
            	$allpay_gateway  = $this->test_gateway;
            }else{
            	$allpay_gateway  = $this->live_gateway;
            }

            $check_mac_value = $this->get_check_mac_value( $allpay_args );

            wc_enqueue_js( '
                $.blockUI({
                        message: "<img src=\"' . esc_url(apply_filters('woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif')) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />感謝您的訂購，接下來畫面將導向付款頁面",
                        baseZ: 99999,
                        overlayCSS:
                        {
                            background: "#fff",
                            opacity: 0.6
                        },
                        css: {
                            padding:        "20px",
                            zindex:         "9999999",
                            textAlign:      "center",
                            color:          "#555",
                            border:         "3px solid #aaa",
                            backgroundColor:"#fff",
                            cursor:         "wait",
                            lineHeight:     "24px",
                        }
                    });
                jQuery("#allpay_payment_form").submit();
            ' );

            $output = '<form method="POST" id="allpay_payment_form" action="'.$allpay_gateway.'">';

            foreach( $allpay_args as $k => $v ){
                $output .= '<input type="hidden" name="'.$k.'" value="'.$v.'" >';
            }

            $output .= '<input type="hidden" name="CheckMacValue" value="'.$check_mac_value.'" >';
                
            $output .= '</form>';

            return $output;
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page( $order_id ) {

            global $woocommerce;

            echo '<p>感謝您的訂購，接下來將導向付款頁面，請稍後</p>';

            // Clear cart
            $woocommerce->cart->empty_cart();

            $order = new WC_Order( $order_id );

            echo $this->generate_allpay_form( $order_id );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @return void
         */
        function email_instructions( $order, $sent_to_admin ) {

            if ( $order->payment_method !== $this->id ) {
                return;
            }

            $order_id = $order->id;
            $payment_type = get_post_meta( $order_id, '_PaymentType', true );

            if( $payment_type ){
                $payment_type_label = self::parse_payment_type( $payment_type );
            }

            if ( $order->status == 'processing' ) {

                if( $sent_to_admin ){
                    echo "已收到付款，歐付寶付款方式 - ".$payment_type_label;
                }else{
                    echo wpautop( wptexturize( $this->instruction_succ ) ). PHP_EOL;
                }

            } elseif( $order->status == 'on-hold' ) {

                if( $sent_to_admin ) {
                    echo "尚未付款，歐付寶付款方式 - ".$payment_type_label;
                } else {
                    echo wpautop( wptexturize( $this->instruction_on_hold ) ). PHP_EOL;
                    // vaccount detail, cvs detail
                    $this->print_order_meta( $order );
                }

            }
        }

        /**
         * Print order meta for webatm, atm, cvs in email notification
         *
         * @access public
         * @return void
         */        
        function print_order_meta( $order ) {

            if( $this->email_return_code ) {
                $email_return_code = $this->email_return_code;
            } else {
                $email_return_code = '';
            }

            if( '2' == $email_return_code ) { ?>
                <h2>歐付寶付款資訊</h2>
                <table class="shop_table order_details allpay_details">
                    <tbody>
                        <tr><th>付款方式</th><td><?php echo $this->email_payment_type_label ?></td></tr>
                        <tr><th>銀行代碼</th><td><?php echo $this->email_bank_code ?></td></tr>
                        <tr><th>繳費帳號</th><td><?php echo $this->email_vaccount ?></td></tr>
                        <tr><th>繳費期限</th><td><?php echo $this->email_expire_date ?></td></tr>
                    </tbody>
                </table>
        <?php } elseif( '10100073' == $email_return_code && 'CVS' == $this->email_payment_method ) { ?>
                <h2>歐付寶付款資訊</h2>
                <table class="shop_table order_details allpay_details">
                    <tbody>
                        <tr><th>付款方式</th><td><?php echo $this->email_payment_type_label ?></td></tr>
                        <tr><th>繳費代碼</th><td><?php echo $this->email_payment_no ?></td></tr>
                        <tr><th>繳費期限</th><td><?php echo $this->email_expire_date ?></td></tr>
                    </tbody>
                </table>
         <?php }
        }

        /**
         * Check allpay response
         *
         * @access public
         * @return void
         */
        function check_allpay_response() {

            @ob_clean();
            $ipn_response = ! empty( $_POST ) ? $_POST : false;
            $is_valid_request = @$this->check_ipn_response_is_valid( $ipn_response );

            if ( $is_valid_request  ) {
                header( 'HTTP/1.1 200 OK' );
                do_action( "valid_allpay_ipn_request", $ipn_response );
            } else {
                die( "0|ErrorMessage" );
            }
        }

        /**
         * Receive ipn response from allpay, update order status if succeeded.
         *
         * @param string $ipn_response , encrypted string
         * @return void
         */
        function successful_request( $ipn_response ) {

            $ipn_order_id = $ipn_response['MerchantTradeNo'];

            // check if order_prefix exists
            $order_prefix = $this->order_prefix;

            $length = strlen($order_prefix);

            if( !empty( $order_prefix ) && substr($ipn_order_id, 0, $length) === $order_prefix ){
                $order_id_arr = explode( $order_prefix, $ipn_order_id );
                $order_id = $order_id_arr[1];
            }else{
                $order_id = intval($ipn_order_id);
            }

            update_post_meta( $order_id, '_MerchantTradeNo', $ipn_order_id );

            $order = new WC_Order( $order_id );

            $return_code = $ipn_response['RtnCode'];

            //$old_return_code = get_post_meta( $order_id, '_RtnCode', true ); // if previous is 2 or 10100073

            $payment_type = $ipn_response['PaymentType'];
            $payment_method = explode( '_', $ipn_response['PaymentType'] );

            if( $payment_type ) {
                update_post_meta( $order_id, '_PaymentType', $payment_type );
            }

            if( $return_code ) {
                update_post_meta( $order_id, '_RtnCode', $return_code );
            }

            if( 'yes' == $this->debug ) {
            	$this->log->add( 'allpay_aio', 'successful-request... RtnCode:'.$return_code.' PaymentType:'.$payment_type);
            }

            // parse allpay ipn payment type label
            $payment_type_label = self::parse_payment_type( $payment_type );

            $payment_method_title = get_post_meta( $order_id, '_payment_method_title', true );

            // change payment method label
            if( $this->payment_method_label == 'main_label' ) {
                $change_payment_type_label = self::parse_payment_type( $payment_type, true );
            } else if( $this->payment_method_label == 'detailed_label' ) {
                $change_payment_type_label = self::parse_payment_type( $payment_type );
            } else {
                $change_payment_type_label = $this->title; // default method title
            }

            if( $this->payment_method_label != 'default' && $payment_method_title != $change_payment_type_label ) {
                if ( 'yes' == $this->testmode ) {
                    $change_payment_type_label .= '|測試';
                }
                if ( 'yes' == $this->admin_mode ) {
                    $change_payment_type_label .= '|管理員';
                }
                update_post_meta( $order_id, '_payment_method_title', $change_payment_type_label );
            }

            // return code === 1 means the payment has been completed
            if( $return_code  === '1' ) {

                $order->payment_complete();
                $order->add_order_note( '歐付寶已收到顧客付款，付款方式 "'.$payment_type_label.'"' );

                // send email if paid
                /*
                if( $old_return_code === '2' || $old_return_code === '10100073' ) {
                    global $woocommerce;
                    $woocommerce->mailer->emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
                    $woocommerce->mailer->emails['WC_Email_New_Order']->trigger( $order_id );                    
                }
                */

                die("1|OK");
            } elseif( $return_code === '2' || $return_code === '10100073' ) {

                // return code '2' means the customer choosed the ATM, '10100073' means convenient store. the orders will be waiting for the payment. If the customer completed the payment, the allpay system will send the post value containing return code '1' to notify the WC API.

                // save the ExpireDate to let admin know when will the order be expired
                update_post_meta( $order_id, '_ExpireDate', $ipn_response['ExpireDate'] );

                if( $return_code === '2' ){
                    $this->email_return_code = '2';
                    $this->email_payment_type_label = $payment_type_label;
                    $this->email_bank_code = $ipn_response['BankCode'];
                    $this->email_vaccount = $ipn_response['vAccount'];
                    $this->email_expire_date = $ipn_response['ExpireDate'];
                } else if( $return_code === '10100073' && $payment_method[0] === 'CVS' ){
                    $this->email_return_code = '10100073';
                    $this->email_payment_method = $payment_method[0];
                    $this->email_payment_type_label = $payment_type_label;
                    $this->email_payment_no = $ipn_response['PaymentNo'];
                    $this->email_expire_date = $ipn_response['ExpireDate'];
                } else if( $return_code === '10100073' && $payment_method[0] === 'APPBARCODE' ) {
                    $this->email_return_code = '10100073';
                    $this->email_payment_method = $payment_method[0];
                    $this->email_payment_type_label = $payment_type_label;
                    $this->email_expire_date = $ipn_response['ExpireDate'];
                }

                $order->update_status('on-hold', '已選擇歐付寶付款方式 "' .$payment_type_label. '" ，等待顧客付款。' );

                die("1|OK");

            } else {
                if( 'yes' == $this->debug ) {
                    $this->log->add( 'allpay_aio','error, return code: '.$return_code);
                }
                $order->update_status( 'cancelled', '取消訂單，錯誤代碼 : "' . $return_code );
                die("0|ErrorMessage");
            }

        }

        /**
         * Compare the returned allpay CheckMacValue with local CheckMacValue
         *
         * @param array $ipn_response
         * @return bool
         */
        function check_ipn_response_is_valid( $ipn_response ) {

            $ipn_check_mac_value = strtolower( $ipn_response['CheckMacValue'] );

            unset( $ipn_response['CheckMacValue'] );

            $my_check_mac_value = $this->get_check_mac_value( $ipn_response );

            if( 'yes' == $this->debug ){
                $this->log->add( 'allpay_aio', 'ipn check mac:'.$ipn_check_mac_value.' my_check_mac_value:'.$my_check_mac_value);
            }

            if( $ipn_check_mac_value == $my_check_mac_value ){
                return true;
            }else{
                return false;
            }
        }


        /**
         * Display allpay info in admin order detail
         *
         * @param object $order
         * @return void
         */
        function allpay_admin_order_data_after_billing_address( $order ) {

            if ( $order->payment_method != 'innovext_allpay_aio' || $order->status == 'cancelled' ) {
                return;
            }

            $order_id           = $order->id;
            $payment_type       = get_post_meta( $order_id, '_PaymentType', true );
            $payment_type_label = self::parse_payment_type( $payment_type );
            $return_code        = get_post_meta( $order_id, '_RtnCode', true );
            $merchant_trade_no  = get_post_meta( $order_id, '_MerchantTradeNo', true );
            $expire_date        = get_post_meta( $order_id, '_ExpireDate', true );

            echo '<h4>歐付寶資訊</h4>';

            echo '<table class="allpay-aio-info">';
            if( $merchant_trade_no ){
                echo '<tr><th>訂單編號</th><td>'. $merchant_trade_no . '</td></tr>';
            }

            if( $payment_type ){
                echo '<tr><th>付款方式</th><td>'. $payment_type_label . '</td></tr>';
            }

            if( $return_code ) {
                echo '<tr><th>付款狀態</th><td>';
                if( $return_code === '1' ){
                    echo '已付款';
                }elseif( $return_code === '2' || $return_code === '10100073' ){
                    echo '尚未付款';
                }
                echo '</td></tr>';
            }

            if( $expire_date ){
                echo '<tr><th>繳費期限</th><td>'. $expire_date . '</td></tr>';
            }

            echo '</table>';
        }

        /**
         * Add meta box for allpay query info
         *
         * @param object $order
         * @return void
         */
        function add_allpay_query_trade_info_meta_box( $order ) {

            if ( $order->payment_method != 'innovext_allpay_aio' ) {
                return;
            }

            if( $this->query_allpay_trade_info != 'yes' ){
                return;
            }

            add_meta_box(
                'allpay-queried-order-info',
                '歐付寶訂單資訊',
                array( &$this, 'display_allpay_query_trade_info' ),
                'shop_order',
                'advanced',
                'high',
                $order
            );
        }

        /**
         * Display allpay trade info callback
         *
         * @param object $order
         * @return void
         */
        function display_allpay_query_trade_info( $order ){

            $order_id = $order->ID;

            $queried_args = $this->get_allpay_query_trade_info_args( $order_id );

            if( ! $queried_args ) {
                echo '無交易資訊';
                return;
            }

            // ignore check mac value which is unnecessary to display
            if( isset( $queried_args['CheckMacValue'] ) ){
            	unset( $queried_args['CheckMacValue'] );
            }

            // parse the payment type to Chinese
            if( isset( $queried_args['PaymentType'] ) ){
                $payment_type = $queried_args['PaymentType'];
                $queried_args['PaymentType'] = self::parse_payment_type( $payment_type );
            }

            echo '<table>';
            foreach( $queried_args as $k => $v ){
                $label = self::parse_allpay_query_trade_info_args_label( $k );
                echo '<tr><th>'.$label.'</th><td>'.$v.'</td></tr>';
            }
            echo '<tr><th>備註</th><td>交易狀態代碼 "1" 為已付款，"2" 或 "10100073" 為待付款，其餘為交易失敗，交易狀態代碼請由歐付寶廠商後台查尋。</td></tr>';
            echo '</table>';
        }

        /**
         * Get allpay query trade info args label, translate english args label to chinese
         * @param string $label
         * @return mixed
         */
        static function parse_allpay_query_trade_info_args_label( $label ) {

            $args = array(
                'HandlingCharge' => '手續費合計',
                'ItemName' => '商品名稱',
                'MerchantID' => '商店代號',
                'MerchantTradeNo' => '訂單編號',
                'PaymentDate' => '交易日期',
                'PaymentType' => '付款方式',
                'PaymentTypeChargeFee' => '通路費',
                'TradeAmt' => '交易金額',
                'TradeDate' => '訂單成立時間',
                'TradeNo' => '歐付寶交易編號',
                'TradeStatus' => '交易狀態',
                'CheckMacValue' => '檢查碼',
                );

            if( $label ) {
                if( isset( $args[$label] ) ) {
                    return $args[$label];
                } else {
                    return $label;
                }
            } else {
                return $args;
            }

        }

        /**
         * Get allpay query trade info args
         *
         * @param string $order_id
         * @return mixed
         */
        function get_allpay_query_trade_info_args( $order_id ) {

            $mer_id = $this->mer_id;
            $mer_trade_no = get_post_meta( $order_id, '_MerchantTradeNo', true );

            if( empty( $mer_trade_no ) ) {
                return false;
            }

            $time_stamp = time();

            $args = array(
                'MerchantID' => $mer_id,
                'MerchantTradeNo' => $mer_trade_no,
                'TimeStamp' => $time_stamp
                );

            $check_mac_value = $this->get_check_mac_value( $args );

            $args = array_merge( $args, array( 'CheckMacValue' => $check_mac_value ) );

            if( $this->testmode == 'yes' ) {
                $form_action = 'http://payment-stage.allpay.com.tw/Cashier/QueryTradeInfo';
            } else {
                $form_action = 'https://payment.allpay.com.tw/Cashier/QueryTradeInfo';
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $form_action);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            $output = curl_exec($ch);
            curl_close($ch);

            if( empty( $output ) ) {
            	return false;
            }

            $return_args = explode( '&', $output );
            $args = array();

            foreach( $return_args as $arg ) {
                $arg_explode = explode( '=',  $arg );
                $key = $arg_explode[0];
                $value = $arg_explode[1];
                $args[$key] = $value;
            }

            return $args;
        }

        static function get_main_payment_type_args( $method = '' ) {

            $methods = array(
                'Credit'    => '信用卡',
                'WebATM'    => '網路ATM',
                'ATM'       => 'ATM自動櫃員機',
                'CVS'       => '超商代碼',
                'BARCODE'   => '超商條碼',
                'Alipay'    => '支付寶',
                'Tenpay'    => '財付通',
                'TopUpUsed' => '儲值消費',
                'APPBARCODE' => '全家條碼立即儲',
                );

            if( $method ) {
                if( array_key_exists( $method, $methods ) ) {
                    return $methods[$method];
                } else {
                    return $method;
                }                
            } else {
                return $methods;
            }
        }

        /**
         * Get payment type args
         *
         * @param string $method choose method
         * @return array
         */
        static function get_payment_type_args( $method = '' ) {

            $payment_type_args = array(

                'WebATM'=> array(
                    'TAISHIN'    => '台新WebATM',
                    'ESUN'       => '玉山WebATM',
                    'HUANAN'     => '華南銀行WebATM',
                    'BOT'        => '台灣銀行WebATM',
                    'FUBON'      => '台北富邦WebATM',
                    'CHINATRUST' => '中國信託WebATM',
                    'FIRST'      => '第一銀行WebATM',
                    'CATHAY'     => '國泰世華WebATM',
                    'MEGA'       => '兆豐銀行WebATM',
                    'YUANTA'     => '元大銀行WebATM',
                    'LAND'       => '土地銀行WebATM',
                    ),
                'ATM' => array(
                    'TAISHIN'    => '台新銀行ATM',
                    'ESUN'       => '玉山銀行ATM',
                    'HUANAN'     => '華南銀行ATM',
                    'BOT'        => '台灣銀行ATM',
                    'FUBON'      => '台北富邦ATM',
                    'CHINATRUST' => '中國信託ATM',
                    'FIRST'      => '第一銀行ATM',
                    'ESUN_Counter' => '玉山銀行臨櫃繳款',
                    ),
                'CVS' => array(
                    'CVS'        => '超商代碼繳款',
                    'OK'         => 'OK 超商代碼繳款',
                    'FAMILY'     => '全家超商代碼繳款',
                    'HILIFE'     => '萊爾富超商代碼繳款',
                    'IBON'       => '7-11 ibon 代碼繳款',
                    ),
                'BARCODE' => array(
                    'BARCODE'    => '超商條碼繳款',
                    ),
                'Alipay' => array(
                    'Alipay'     => '支付寶',
                    ),
                'Tenpay' => array(
                    'Tenpay'     => '財付通',
                    ),
                'Credit' => array(
                    'CreditCard' =>'信用卡_MasterCard_JCB_VISA',
                    ),
                'TopUpUsed' => array(
                    'Allpay'     => '儲值/餘額消費_歐付寶',
                    'ESUN'       => '儲值/餘額消費_玉山',
                    ),
                'APPBARCODE' => array(
                    'FAMI'       => '全家條碼立即儲',
                    )
                );

            if( $method ) {
                if( array_key_exists( $method, $payment_type_args ) ) {
                    return $payment_type_args[$method];
                } else {
                    return $method;
                }                
            } else {
                return $payment_type_args;
            }
        }

        /**
         * Parse allpay payment type string to chinese eg: WebATM_TAISHIN to 台新WebATM
         * If specified $main_payment_type, will return the main payment type args
         *
         * @param  string $payment_type Payment type
         * @param  bool   $main_payment_type Main payment type 
         * @return string
         */
        static function parse_payment_type( $payment_type, $main_payment_type = false ) {


            $payment_type_arr = explode( '_', $payment_type, 2 );

            $payment_type_args = self::get_payment_type_args();

            if( !isset( $payment_type_arr[0] ) || !isset( $payment_type_arr[1] ) ) {
                return $payment_type;
            } 

            $method = $payment_type_arr[0];
            $agent = $payment_type_arr[1];

            if( $main_payment_type ) {
                return self::get_main_payment_type_args( $method );
            }

            if( isset( $payment_type_args[$method][$agent] ) ) {
                return $payment_type_args[$method][$agent];
            } else {
                return $payment_type;
            }

        }

        /**
         * Process the payment and redirect to pay page
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url( true )
            );
        }

        /**
         * Payment form on checkout page
         *
         * @access public
         * @return void
         */
        function payment_fields() {

            if ( $this->description ){
                echo wpautop( wptexturize( $this->description ) );
            }
        }

        /**
         * Is available. Put some condition here to turn on or off the availability on checkout.
         *
         * @access public
         * @return bool
         */
        function is_available() {

            global $woocommerce;

            // admin mode
            if( $this->admin_mode == 'yes' && !current_user_can( 'manage_woocommerce' ) ) {
                return false;
            }

            $subtotal = $woocommerce->cart->subtotal ;
            $min_amount = $this->min_amount;

            $shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );
            $return_to  = apply_filters( 'woocommerce_continue_shopping_redirect', $shop_page_url );

            // amount condition
            if ( $min_amount > 0 && $min_amount > $subtotal ) {
                wc_print_notice( sprintf( '購物滿 %s 元，即可使用 %s 付款！<a class="button" href="%s">繼續購物&raquo;</a>', $min_amount, $this->title, $return_to ), 'notice' );
                return false;
            }

            return parent::is_available();
        }
    }


    /**
     * Add the gateway to WooCommerce
     *
     * @access public
     * @param array $methods
     * @package WooCommerce/Classes/Payment
     * @return array
     */
    function add_innovext_allpay_aio_gateway($methods) {
        $methods[] = 'WC_innovext_allpay_aio';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_innovext_allpay_aio_gateway');

    /**
     * Display allpay info in order detail
     *
     * @param object $order
     * @return void
     */
    function allpay_order_info( $order ) {

        if ( $order->payment_method !== 'innovext_allpay_aio' ) return;

        $order_id = $order->id;
        $return_code = get_post_meta( $order_id, '_RtnCode', true );
        $payment_type = get_post_meta( $order_id, '_PaymentType', true );
        $payment_type_label = WC_innovext_allpay_aio::parse_payment_type( $payment_type );
        $merchant_trade_no = get_post_meta( $order_id, '_MerchantTradeNo', true );

        if( $return_code === '1' ){ ?>
            <h3>歐付寶資訊</h3>
            <table id="allpay-received-payment" class="shop_table" >
                <tr><th>訂單編號</th><td><?php echo $merchant_trade_no ?></td></tr>
                <tr><th>付款方式</th><td><?php echo $payment_type_label ?></td></tr>
                <tr><th>付款狀態</th><td>已收到付款</td></tr>
            </table>

        <?php }

        if( ($return_code === '2' || $return_code === '10100073') && $order->status !== 'cancelled' ){ ?>
            <h3>歐付寶資訊</h3>
            <table id="allpay-awaiting-payment" class="shop_table" >
                <tr><th>訂單編號</th><td><?php echo $merchant_trade_no ?></td></tr>
                <tr><th>付款方式</th><td><?php echo $payment_type_label ?></td></tr>
                <tr><th>付款狀態</th><td>尚未付款</td></tr>
            </table>

        <?php }

    }
    add_action( 'woocommerce_order_details_after_order_table', 'allpay_order_info', 10 );

    /**
     * Display allpay order received/on-hold description in view order page
     *
     * @param object $order
     * @return void
     */
    function allpay_woocommerce_view_order( $order_id ) {

        $order = new WC_Order( $order_id );

        if( $order->payment_method !== 'innovext_allpay_aio' ) {
            return;
        }

        // Get order instructions
        $allpay_aio_settings = get_option( 'woocommerce_innovext_allpay_aio_settings' );

        if( $allpay_aio_settings ) {
            $allpay_instruction_succ = $allpay_aio_settings['instruction_succ'];
            $allpay_instruction_on_hold = $allpay_aio_settings['instruction_on_hold'];
            $return_code = get_post_meta( $order_id, '_RtnCode', true );            
        } else {
            return;
        }

        if( $return_code === '1' && $order->status == 'processing' ) {
            if( $allpay_instruction_succ ) {
                echo '<div style="background-color:#C8DFFB;padding: 10px 15px;border: 1px solid #A5B4D2;border-radius: 2px;">';                
                echo wpautop( wptexturize( $allpay_instruction_succ ) );
                echo '</div>';                
            }   
        } elseif( ( $return_code === '2' || $return_code === '10100073' ) && $order->status == 'on-hold' ) {
            if ( $allpay_instruction_on_hold ) {
                echo '<div style="background-color: #FFF9E8;padding: 10px 15px;border: 1px solid #E0B23E;border-radius: 2px;">';
                echo wpautop( wptexturize( $allpay_instruction_on_hold ) );
                echo '</div>';
            }                    
        }
    }
    add_action( 'woocommerce_view_order', 'allpay_woocommerce_view_order' );

    // WC API action http://localhost/?wc-api=wc_auto_update_allpay_aio_order
    add_action( 'woocommerce_api_wc_auto_update_allpay_aio_order', 'woocommerce_allpay_aio_cancel_unpaid_orders' );

    add_action( 'allpay_aio_auto_update_order' , 'woocommerce_allpay_aio_cancel_unpaid_orders' );

    /**
     * Check the orders if their Allpay AIO orderstatus payment deadline is expired,
     * 
     * @return void
     */
    function woocommerce_allpay_aio_cancel_unpaid_orders() {

        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array( 'wc-on-hold' ),
            'order'          => 'ASC', // make sure update from the oldest order
            'orderby'        => 'date',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key' => '_payment_method',
                    'value' => array(
                        'innovext_allpay_aio',
                    ),
                    'compare' => 'IN'
                ),
            )
        );

        $posts = get_posts( $args );

        if( empty( $posts ) ) {
            return;
        }

        foreach( $posts as $post ) {

            $order_id = $post->ID;

            $expiredate  = get_post_meta( $order_id, '_ExpireDate', true );

            if( empty( $expiredate ) ) {
                continue;
            }

            $order = new WC_Order( $order_id );

            $parse_date = date_parse( $expiredate );

            // the format of ATM expire date is YYYY-mm-dd, which doesn't contain time.
            // so we need to +1 day to make sure the order is expired at the end of the given day
            if( isset( $parse_date['hour'] ) && $parse_date['hour'] === false ) {
                $expiredate = $expiredate . '+1 day';
            }

            $deadline_timestamp = strtotime( $expiredate );
            $now_timestamp      = strtotime( 'now' );

            // if expired, cancel the WC order
            if( $now_timestamp > $deadline_timestamp ) {
                $order->update_status( 'cancelled', '訂單超過繳費期限' );
            }
        }
        // die(); // use die to get the output
    }

    /* Add cron schedules interval */
    add_filter( 'cron_schedules', 'innovext_allpay_aio_add_schedule', 10 ); 
    function innovext_allpay_aio_add_schedule( $schedules ) {

        $allpay_aio_settings = get_option( 'woocommerce_innovext_allpay_aio_settings' );

        if( isset( $allpay_aio_settings['cron_frequency_min'] ) && ! empty( $allpay_aio_settings['cron_frequency_min'] ) ) {
            $cron_frequency_min = $allpay_aio_settings['cron_frequency_min'];
        } else {
            $cron_frequency_min = 360;
        }

        $schedules['woocommerce_allpay_aio_cron_frequency'] = array(
            'interval' => $cron_frequency_min * 60, // X minutes * 60 seconds, default is 1 hour
            'display' => __( '歐付寶全方位金流檢查過期訂單')
        );

        return $schedules;
    }

    /* schedule the event */
    add_action( 'init', 'innovext_allpay_aio_auto_update_order_schedule' );
    function innovext_allpay_aio_auto_update_order_schedule() {

        $allpay_aio_settings = get_option( 'woocommerce_innovext_allpay_aio_settings' );

        if( isset( $allpay_aio_settings['cron_frequency_min'] ) && $allpay_aio_settings['cron_frequency_min'] > 0 ) {
            $enable_cron = 'yes';
        } else {
            $enable_cron = 'no';
        }

        if( $enable_cron != 'yes' ) {
            return;
        }   

        if( function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') ) {

            $now_timestmp = strtotime( 'now' );

            if( !wp_next_scheduled( 'allpay_aio_auto_update_order' ) ) {
                wp_schedule_event( $now_timestmp, 'woocommerce_allpay_aio_cron_frequency', 'allpay_aio_auto_update_order' );
            }
        }
    }
}
?>