<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __          //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_        //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\   __\       //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// AlleyCat PhotoStation v3.3.0                                        //
// Author: Paul K. Smith (photos@alleycatphoto.net)                    //
// Date: 12/19/2025                                                    //
//*********************************************************************//
// Gotcha PhotoStation v 1.3                                           //
// Author: Paul K. Smith (paul.kelso.smith@gmail.com)                  //
// Date: 07/07/2014                                                    //
// Last Revision 06/03/2015 (PKS)                                      //
// Cart: Action Handler                                                //

include('shopping_cart.class.php');
session_start();
$Cart = new Shopping_Cart('shopping_cart');

if ( !empty($_GET['order_code']) && !empty($_GET['quantity']) ) {
	// When adding/updating from modal, set quantity directly (don't add to existing)
	$Cart->setItemQuantity($_GET['order_code'], $_GET['quantity']);
}

if ( !empty($_GET['quantity']) ) {
	foreach ( $_GET['quantity'] as $order_code=>$quantity ) {
		$Cart->setItemQuantity($order_code, $quantity);
	}
}

if ( !empty($_GET['remove']) ) {
	foreach ( $_GET['remove'] as $order_code ) {
		$Cart->setItemQuantity($order_code, 0);
	}
}

$Cart->save();

header('Location: cart.php');

?>