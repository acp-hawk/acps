<?php
//*********************************************************************//
// ACPS90 - AlleyCat PhotoStation Cart Engine v9.0                    //
// Author: Paul K. Smith                                               //
// Date: January 23, 2026                                              //
//*********************************************************************//
class Shopping_Cart {
	var $cart_name;
	var $items = [];
	var $base_prices = [
		'4x6'  => 8.00,
		'5x7'  => 12.00,
		'8x10' => 20.00,
		'EML'  => 15.00
	];

	function __construct($name){
		$this->cart_name = $name;
		$this->items = isset($_SESSION[$this->cart_name]) ? $_SESSION[$this->cart_name] : [];
	}

	function setItemQuantity($order_code, $quantity){
		$this->items[$order_code] = max(0, intval($quantity));
	}

	function getItemPrice($order_code){
		list($prod_code, $photo_id) = explode('-', $order_code);
		$price = $this->base_prices[$prod_code] ?? 0.00;

		// --- EMAIL pricing logic ---
		if($prod_code == 'EML'){
			$total_emails = $this->getTotalEmails();

			// if attached to any print, it's a $3 add-on
			$hasPrint = false;
			foreach($this->items as $code => $qty){
				list($pcode, $pid) = explode('-', $code);
				if($pid == $photo_id && $pcode != 'EML' && $qty > 0){
					$hasPrint = true;
					break;
				}
			}

			if($hasPrint){
				$price = 3.00;
			} elseif($total_emails >= 5){
				$price = 7.00;
			} else {
				$price = 15.00;
			}
		}

		// --- PRINT pricing logic ---
		// Base unit price only; bundle discounts are handled externally (cart.php)
		if($prod_code == '4x6') $price = $this->base_prices['4x6'];
		if($prod_code == '5x7') $price = $this->base_prices['5x7'];
		if($prod_code == '8x10') $price = $this->base_prices['8x10'];

		return $price;
	}

	private function countType($type){
		$c = 0;
		foreach($this->items as $code=>$q){
			if(strpos($code, $type.'-')===0) $c += $q;
		}
		return $c;
	}

	function getTotalEmails(){
		$c = 0;
		foreach($this->items as $code=>$q){
			if(strpos($code,'EML-')===0) $c += $q;
		}
		return $c;
	}

	function getItemName($order_code){
		list($prod_code,$photo_id)=explode('-',$order_code);
		$names = ['4x6'=>'4x6 Print','5x7'=>'5x7 Print','8x10'=>'8x10 Print','EML'=>'Digital Email'];
		$name = $names[$prod_code] ?? $prod_code;
		if($this->items[$order_code]>1 && $prod_code!='EML') $name .= 's';
		return $name;
	}

	function getImageName($order_code){
		$parts = explode('-', $order_code);
		return trim($parts[1]) . '.jpg';
	}

	function getImageID($order_code){
		$parts = explode('-', $order_code);
		return trim($parts[1] ?? '');
	}

	function getItems(){ return $this->items; }
	function hasItems(){ return !empty($this->items); }
	function getItemQuantity($order_code){ return isset($this->items[$order_code]) ? (int)$this->items[$order_code] : 0; }

	function getAllQuantity(){
		$t = 0;
		foreach($this->items as $c=>$q){ if($q>0) $t += $q; else unset($this->items[$c]); }
		return $t;
	}

	function clean(){
		foreach($this->items as $c=>$q){ if($q<1) unset($this->items[$c]); }
	}

	function clearCart(){
		$this->items = [];
		$_SESSION[$this->cart_name] = [];
	}

	function save(){
		$this->clean();
		$_SESSION[$this->cart_name] = $this->items;
	}
}
?>
