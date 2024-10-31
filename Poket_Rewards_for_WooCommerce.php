<?php

/*
Plugin name: Poket Loyalty Rewards For WooCommerce
Plugin URI: https://poket.com
Description: This plugin will connect you to POKET Loyalty system
Version: 2.0
Author: POKET  
Author URI: https://poket.com
License: GPLv2 or Later
Text Domain: Poket Rewards for WooCommerce
*/

add_action( 'activated_plugin', 'poket_merchantSignup');
function poket_merchantSignup()
{
  $data=array();
  $data['method']='store_details';
  $data['installed_on'] = date("Y-m-d h:m:s");

		
        global $wpdb;

        $results = $wpdb->get_row( "select option_value from $wpdb->options where option_name='blogname'");
        $results_storeemail = $wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
        $results_siteurl = $wpdb->get_row( "select option_value from $wpdb->options where option_name='siteurl'");


        $data['name']=$results->option_value;
        $data['email']=$results_storeemail->option_value;
        $data['url']=$results_siteurl->option_value;
		
        create_poket_table();
        $common_endpoint ='https://brands.poket.app/WC/common_functions.php';
        
        $common_response = wp_remote_post( $common_endpoint, array(
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(),
        'body'        => array(
        'method'  => "get_admin_folder_name",
        "email"  => $data['email'],
        ),
        'cookies'     => array()
        )
        );

        
        $admin_folder_name = $common_response['body'];
        //$_SESSION['response'] = $common_response;	
        if($admin_folder_name!=''){
          $response = insert_update_poket_rewards_data($admin_folder_name);			
        } else{ 

          $endpoint ='https://brands.poket.app/WC/receive.php';

          $response = wp_remote_post( $endpoint, array(
          'method'      => 'POST',
          'timeout'     => 45,
          'redirection' => 5,
          'httpversion' => '1.0',
          'blocking'    => true,
          'headers'     => array(),
          'body'        => array(
          'method'  => "store_details",
          "installed_on"  => $data['installed_on'],
          "name" => $data['name'],
          "email"  => $data['email'],
          "url"  => $data['url'],
          ),
          'cookies'     => array()
          )
          );

          
          $email = base64_encode($data['email']); 
          wp_redirect("https://brands.poket.app/trial-supreme-plan/?email=$email&link=woocommerce"); 
          exit;
        }
}


function create_poket_table()
{      
  global $wpdb; 
  $db_table_name = $wpdb->prefix . 'poket_rewards';  // table name
  $charset_collate = $wpdb->get_charset_collate();

 //Check to see if the table exists already, if not, then create it
if($wpdb->get_var( "show tables like '$db_table_name'" ) != $db_table_name ) 
 {
       $sql = "CREATE TABLE $db_table_name (
                id int(11) NOT NULL auto_increment,
                admin_portal_name varchar(50) NULL,                
                UNIQUE KEY id (id)
        ) $charset_collate;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
   add_option( 'test_db_version', $test_db_version );
 }
}

function insert_update_poket_rewards_data($admin_folder_name){	
	
	global $wpdb;     
	$table_name = $wpdb->prefix . 'poket_rewards';	
	
	$wpdb->get_results("SELECT * FROM $table_name");
	$rowcount = $wpdb->num_rows;
	if($rowcount>0){
	$wpdb->query($wpdb->prepare("UPDATE $table_name SET admin_portal_name='$admin_folder_name'"));	
	} else {
	$wpdb->insert($table_name, array('admin_portal_name' => $admin_folder_name)); 
	}
	return $admin_folder_name;
}


function get_admin_folder_name(){
	
	global $wpdb;
	$table_name = $wpdb->prefix . 'poket_rewards';
	$admin_data = $wpdb->get_row("SELECT * FROM  $table_name");
	if($admin_data->admin_portal_name!=''){
		
		return $admin_data->admin_portal_name;
	
	} else {
		
			$data = array();		
			$results_storeemail = $wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
			$data['email']=$results_storeemail->option_value;
			$common_endpoint ='https://brands.poket.app/WC/common_functions.php';
			
			$common_response = wp_remote_post( $common_endpoint, array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(),
			'body'        => array(
			'method'  => "get_admin_folder_name",
			"email"  => $data['email'],
			),
			'cookies'     => array()
			)
			);
			
			$admin_folder_name = $common_response['body'];
			if($admin_folder_name!=''){
				$response = insert_update_poket_rewards_data($admin_folder_name);
				return $response;
			}
	}
	
}



add_action( 'woocommerce_thankyou', 'poket_purchaseTracking' );

function poket_purchaseTracking( $order_id ) {

  
  $data=array();
  $data['method']='order_details'; 

    $order = wc_get_order( $order_id );
    $order_data = $order->get_data();
    $total = $order->get_total();
    $currency = get_woocommerce_currency();
    $subtotal = $woocommerce->cart->subtotal;
    $coupons = $order->get_used_coupons();
    $coupon_code = '';
    $discount = $order->get_total_discount();
    foreach ($coupons as $coupon){
        $coupon_code = $coupon;
    } 
    $data['tracking'] = 'OrderID='.$order.'&ITEMx=[ItemSku]&AMTx=[AmountofItem]&QTYx=[Quantity]&CID=1529328&OID=[OID]&TYPE=385769&AMOUNT='. $total .'&DISCOUNT='. $discount .'&CURRENCY='. $currency .'&COUPON='. $coupon_code .'';
    //echo $tracking;
    $data['order_id'] = $order_id;
    $data['order_total'] = $order_data['total'];
    $data['coupon_code'] = $coupon_code;
    $data['order_billing_name'] = $order_data['billing']['first_name']." ".$order_data['billing']['last_name'];
    $data['order_customer_id'] = $order_data['customer_id']; 
     
    
    $purchased_product_ids = [];

    // Loop through the order items and get product IDs
    foreach ($order->get_items() as $item_id => $item) {
    $product_id = $item->get_product_id();  // Get the product ID
    $purchased_product_ids[] = $product_id; // Add product ID to the array
    }
 
        $customer_id = $order_data['customer_id'];


         global $wpdb;

         $results_storename = $wpdb->get_row( "select option_value from $wpdb->options where option_name='blogname'");
         $results_siteurl = $wpdb->get_row( "select option_value from $wpdb->options where option_name='siteurl'");
         $results_storeemail = $wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
         $results_consumerid = $wpdb->get_row( "select user_email from $wpdb->users where id=$customer_id");

     
         $data['name']=$results_storename->option_value;
  
            
         $data['url']=$results_siteurl->option_value;
  
         $data['email']=$results_storeemail->option_value;

         $data['consumer_email']=$results_consumerid->user_email;
  
  $endpoint ='https://brands.poket.app/WC/receive.php';

  $response = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        'method'  => "order_details",
        "tracking"  => $data['tracking'],
        "order_id" => $order_id,
        "order_total"  => $order_data['total'],
        "coupon_code"  => $coupon_code,
        "order_billing_name"  => $data['order_billing_name'],
        "customer_id"  => $order_data['customer_id'],
        "name" => $data['name'],
        "url" => $data['url'],
        "email" => $data['email'],
        "consumer_email" => $data['consumer_email'],
        "purchased_product_ids" => $purchased_product_ids
    ),
    'cookies'     => array()
    )
);

}

add_action('woocommerce_created_customer', 'poket_customerRegister', 10 , 3);
        function poket_customerRegister($customer_id, $new_customer_data, $password_generated){
			
			
			
 /* Configure your remote DB settings here */
       $data=array();
       $data['method']='customer_details';
            $uname = $new_customer_data['user_login'];  //user name
      $data['uname'] = $new_customer_data['user_login'];
            $data['uemail'] = $new_customer_data['user_email']; //login email
       $uemail = $new_customer_data['user_email'];
            
     
         global $wpdb;

         $results_storename = $wpdb->get_row( "select option_value from $wpdb->options where option_name='blogname'");
         $results_siteurl = $wpdb->get_row( "select option_value from $wpdb->options where option_name='siteurl'");
         $results_storeemail = $wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
         $results_consumerid = $wpdb->get_row( "select id from $wpdb->users where user_email='$uemail'");

         $data['name']=$results_storename->option_value;

          $data['url']=$results_siteurl->option_value;

          $data['email']=$results_storeemail->option_value;

          $data['custid']=$results_consumerid->id;
		  
		 
          $custid=$results_consumerid->id;

          $results_phone = $wpdb->get_row( "select meta_value from $wpdb->usermeta where user_id='$custid' and meta_key='billing_phone'");

          $data['cphone']=$results_phone->meta_value;
      

$endpoint ='https://brands.poket.app/WC/receive.php';
  $response = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        'method'  => "customer_details",
        "uname"  => $new_customer_data['user_login'],
        "uemail" => $new_customer_data['user_email'],
        "name"  => $results_storename->option_value,
        "url"  => $results_siteurl->option_value,
        "email"  => $results_storeemail->option_value, 
        "custid"  => $results_consumerid->id,
   

    ),
    'cookies'     => array()
    )
);



}


add_action('woocommerce_before_cart_contents', 'poket_singlepageReward' );
function poket_singlepageReward()
{
$admin_folder_name = get_admin_folder_name();

	
$a="ari";
global $woocommerce;

$data = array();
$data['amount'] = $woocommerce->cart->cart_contents_total;

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;
$data['consumer_email'] = $uemail;


global $wpdb;

$results_useremail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$merchant_email=$results_useremail->option_value;
$data['merchant_email'] = $merchant_email;
$data['senter_type']='woocommerce';


$endpoint ='https://'.$admin_folder_name.'/ecommerce/points_for_currect_transaction';

$response = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        'amount'  => $data['amount'],
        "consumer_email"  => $uemail,
        "merchant_email" => $merchant_email,
        "senter_type"  => "woocommerce",
       
    ),
    'cookies'     => array()
    )
);

//if($response['body']!="")
echo '<span style="background-color: #e7e7e7; color: black;" pos>You are being rewarded<b>!</b> This order will earn <b> '.$response['body'].'</b> points </span>';

}

//add_action('woocommerce_before_cart', 'poket_beforecartReward' );
 function poket_beforecartReward() {
 
global $wpdb;


$admin_folder_name = get_admin_folder_name();	



$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

global $wpdb;

$results_useremail=$wpdb->get_row( "select id from $wpdb->users where user_email='$uemail'");
$current_user_id=$results_useremail->id;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;


echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>'; 

 if($current_user_id>0)
 {
$endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];

}
else
{
  $response='0';
}




$mer_domain = "poket";



echo '<div id="pop" style="display:none;overflow:hidden"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';
 



?>
<style>
  .other {background-color: #e7e7e7; color: black;}

	.container { 
    right: 0px;
    left:500px;
    bottom: 40px;
    height: 100px;
  width: auto;
       
}

.vertical-center {
  margin: 0;
  position: absolute;
  top: 50%;
  -ms-transform: translateY(-50%);
  transform: translateY(-50%);
}



</style>

<script type="text/javascript">


function poket_openpop(){
   
    document.getElementById('pop').style.display="block"; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}




</script>
<?php
	
 }

add_action('wp_head', 'poket_headerReward' );
function poket_headerReward() {	
global $wpdb;

$admin_folder_name = get_admin_folder_name();
$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;
$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

$endpoint ='https://brands.poket.app/WC/wp_page_settings.php';
$result = wp_remote_post($endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
	'body'        => array(       
        "merchant_email" => $admin_email 
             
    ),
    'cookies'     => array()
    )
	);
$page_settings = $result['body'];
$json = json_decode($page_settings);
$button_text =  $json->button_text;
$position =  $json->position;
$launcher_color =  $json->launcher_color;
$icon_class =  $json->icon_class;
$button_type =  $json->button_type;
$enable =  $json->enable;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body']; 




			

}
else
{
  $response='0';
}



if($enable=="yes"){

$referral_value = isset($_GET['poket_ref']) ? $_GET['poket_ref'] : null; 

if ($referral_value != null) {

    $iframe_url = "https://$admin_folder_name/ecommerce/referral_popup?poket_ref=$referral_value"; 

    echo '<div id="poket_referral_div" style="position: fixed; right: 20px; bottom: 20px; height: 324px; z-index: 2; background: transparent; border: 0px; border-radius: 17px; border-bottom-left-radius: 0px; border-bottom-right-radius: 0px;">
        <iframe src="' . $iframe_url . '" width="100%" height="100%" frameborder="0"></iframe>

        <!-- Close button -->
        <span id="pkPopClose" style="color: #000000; z-index: 50; position: fixed; bottom: 295px; right: 40px; cursor: pointer; background-color: transparent; border: 0px solid; padding: 5px; width: 27px; height: 27px; font-size: 15px; text-align: center; line-height: 17px; transition: background-color 0.3s;">
            X
        </span>
    </div>

    <script>        
        document.getElementById("pkPopClose").onclick = function() {
            document.getElementById("poket_referral_div").style.display = "none";
        };
    </script>';

    


} else{
echo '<!DOCTYPE html>
<html lang="en">
<head>
<style type="text/css">
iframe{
width:360px !important;
bottom: calc(95px)!important;
right: 25px!important;
border: 0px!important;
border-radius: 0px!important;
border-bottom-left-radius: 0px!important;
border-bottom-right-radius: 0px!important;
z-index: 2245454!important;
background: transparent!important;
}

#pop_checkout span{
    color: #000000!important;
    z-index: 2645454!important;
    position: fixed!important;
	bottom: 580px!important;
	right:45px!important;
	cursor: pointer!important;
    border: 0px solid!important;
    border-radius: 86%!important;
	width: 27px!important; 
	font-size: 15px!important;
    text-align: center!important;
}

#rwd_btn_checkout{
	z-index: 2245454!important;
	border-radius: 30px!important;
    line-height: 1.5!important;
	min-width: 60px !important;
    font-size: 16px !important;
    cursor: pointer !important;
	text-transform: none !important;
	padding: 11px 20px!important;
}
</style>
<meta charset="utf-8">
</head>
<body> 
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed;'.$position.': 0px; bottom: 33px;background:'.$launcher_color.'; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top:font-size: 16px !important;"><div class="launcher-content-container">';

if($button_type==1){
echo'<i class="'.$icon_class.'"style="cursor:pointer;font-size: 18px;color: white;margin-left: 10px;" aria-hidden="true"></i><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer;"><b>'.$button_text.'</b></div></div></div>';	
} else if($button_type==2){
echo'<i class="'.$icon_class.'"style="cursor:pointer;font-size: 18px;color: white;margin-left: 10px;" aria-hidden="true"></i><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer;"></div></div></div>';		
}else{
echo'<div class="" style="display: inline-block;margin-left: 5px;vertical-align: top; cursor:pointer;"><b>'.$button_text.'</b></div></div></div>';		
}


 
echo'</div>
</div>
</body>
</html>';

$mer_domain = "poket";
if($position=='right'){
echo '<div id="pop_checkout" style="display:none;"><span style="color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';		
}else{
echo '<div id="pop_checkout" style="display:none;"><span style="color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; left:280px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;left: 5px;bottom: 0px; min-width:310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';	
}
}
}


?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_before_main_content', 'poket_beforecontentReward' );

 function poket_beforecontentReward() {

 global $wpdb;

$admin_folder_name = get_admin_folder_name();


$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];


}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px;background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; font-size: 16px !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_account_dashboard', 'poket_dashboardReward' );

 function poket_dashboardReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();	


$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];
  

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto;" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>



<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_before_single_product', 'poket_singleprodReward' );

 function poket_singleprodReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();

$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_cart_is_empty', 'poket_emptycartReward' );

 function poket_emptycartReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();

$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];

 

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_register_form_end', 'poket_registerReward' );

 function poket_registerReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();

$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];
 

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_order_items_table', 'poket_orderitemReward' );
 function poket_orderitemReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();

$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {

  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];

  

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';


$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}

//add_action('woocommerce_no_products_found', 'poket_noproductReward' );

 function poket_noproductReward() {

 global $wpdb;
 
 $admin_folder_name = get_admin_folder_name();

$results_storeemail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$admin_email=$results_storeemail->option_value;
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;

$data=array();
$data['merchant_email']=$admin_email;
$data['custid']=$current_user_id;

if($current_user_id>0)
 {
  $endpoint ='https://brands.poket.app/WC/get_consumer_id.php';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "custid"  => $current_user_id,
        "merchant_email" => $admin_email,
        "custemail" => $uemail,
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];

 

}
else
{
  $response='0';
}

 echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
</head>
<body>
<div id="rwd_btn_checkout" onclick="poket_openpop()" style="position: fixed; right: 0px; bottom: 33px; height: 42px; background: black; z-index: 100; color: white; text-align: center; border-radius: 10px; padding-top: 10px; width: 144px !important; font-size: 16px !important; text-transform: uppercase !important;"><div class="launcher-content-container">

    <img src="'.plugin_dir_url( __FILE__ ).'assets/gift1.svg"'.'" class="" alt="" role="presentation" style="width: 20px;display: inline-block;color:white; vertical-align: top"><div class="" style="display: inline-block;margin-left: 10px;vertical-align: top; cursor:pointer">Rewards</div></div></div>
 
  </div>
  </div>
</body>
</html>';

$mer_domain = "poket";

echo '<div id="pop_checkout" style="display:none;"><span style="    color: #bfb8b8;    z-index: 3;    position: fixed;    bottom:500px; right:25px;    cursor: pointer;    border: 1px solid;    border-radius: 86%;    padding: 4px;  width: 30px;    font-size: 15px;    text-align: center;" onclick="poket_closepop();" >X</span> <iframe scrolling="yes" style="position: fixed;right: 5px;bottom: 0px; min-width:310px; width: 310px;height: 525px;z-index: 2;background: transparent;border: 0px;border-radius: 17px;border-bottom-left-radius: 0px;border-bottom-right-radius: 0px;overflow-y:auto" src="https://'.$admin_folder_name.'/ecommerce/DisplayRewards/'.$admin_email.'/'.$response.'/woocommerce?sId='.$response.'"></iframe></div>';

?>

<script type="text/javascript">
  function poket_openpop(){
   
    document.getElementById('pop_checkout').style.display=""; 
    document.getElementById('rwd_btn_checkout').style.display="none"; 
    
}

function poket_closepop(){
   
    document.getElementById('pop_checkout').style.display="none"; 
    document.getElementById('rwd_btn_checkout').style.display=""; 
    
}
</script>
<?php
}


add_action('woocommerce_before_add_to_cart_form','poket_pointsearnAlert');
function poket_pointsearnAlert()
{

$data = array();
global $woocommerce;

$admin_folder_name = get_admin_folder_name();

$currency = get_woocommerce_currency_symbol();
$price = get_post_meta( get_the_ID(), '_price', true);
// $price = $product->get_price();
$data['amount'] = $price;

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;
$uemail = $current_user->user_email;
$data['consumer_email'] = $uemail;

global $wpdb; 

$results_useremail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$merchant_email=$results_useremail->option_value;
$data['merchant_email'] = $merchant_email;
$data['senter_type']='woocommerce';

$endpoint ='https://'.$admin_folder_name.'/ecommerce/points_for_currect_transaction';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "amount"  => $price,
        "merchant_email" => $merchant_email,
        "consumer_email" => $uemail,
        "senter_type" => "woocommerce",
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];


//if($response!=""){
  echo '<span style="background-color: #e7e7e7; color: black;" pos>You are being rewarded<b>!</b> This order will earn <b> '.$response.'</b> points </span><br><br>';
//}


}

add_action('woocommerce_review_order_before_payment', 'poket_mypointsRemainder' );

 function poket_mypointsRemainder() {

   $data=array();
   
   $admin_folder_name = get_admin_folder_name();

  $current_user = wp_get_current_user();

  $uname = $current_user->user_login;
$uemail = $current_user->user_email;

global $wpdb;

$results_useremail=$wpdb->get_row( "select option_value from $wpdb->options where option_name='admin_email'");
$merchant_email=$results_useremail->option_value;
$data['merchant_email'] = $merchant_email;



$data['consumer_email']=$uemail;
$data['senter_type']="woocommerce";


$endpoint ='https://'.$admin_folder_name.'/ecommerce/currect_balance_for_customer_card';

$result = wp_remote_post( $endpoint, array(
    'method'      => 'POST',
    'timeout'     => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(),
    'body'        => array(
        "amount"  => $price,
        "merchant_email" => $merchant_email,
        "consumer_email" => $uemail,
        "senter_type" => "woocommerce",
             
    ),
    'cookies'     => array()
    )
);

$response = $result['body'];



$points = explode(".", $response); 

if($points[0]=='' || $points[0]<=0){
	
	$points[0]=0;
}

  if($uname!=''){
	    echo '<span class="other" pos style="background:black; color:white;">'.ucfirst($uname).', you have '.$points[0].' points to use! </span>';
  }

  ?>
  <style>
    .other {background-color: #e7e7e7; color: black;}
  </style>
  <?php
}
?>
