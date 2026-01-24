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

session_start();

//Get all variables from the loading frame
$queryString = $_SERVER['QUERY_STRING']; 
parse_str($queryString,$queryString);

$dirname = "photos/";
$date_path = date('Y/m/d');
$displayMode = "all";
$cat_raw = file_get_contents(__DIR__ . '/admin/categories.txt'); 
$cat = unserialize($cat_raw); 

if( isset($_POST['display_group']) )
{
    $displayGroup = $_POST['display_group'];
}else{
	if( isset($_GET['g']) ){
		$displayGroup = $_GET['g'];
	}else{
		$displayGroup = "001";
	}	
}

//Check to see type of gallery to display
if( isset($_POST['display_type']) )
{
	if(trim($_POST['display_type'])==""){
		//if blank leave blank
		$displayType = "";
	}else{
	    $displayType = $_POST['display_type'];
	}
}else{
	if( isset($_GET['v']) ){
		//$displayType = $_GET['t'];
		if(trim($_GET['v'])==""){
			//if blank leave blank
			$displayType = "";
		}else{
			$displayType = "";
		}
	}else{
		$displayType = "";
	}
}

if($displayGroup=="001"){
	$images = glob($dirname.$date_path."/numbered/1*.jpg");
}elseif($displayGroup=="002"){
	$images = glob($dirname.$date_path."/numbered/2*.jpg");
}elseif($displayGroup=="003"){
	$images = glob($dirname.$date_path."/numbered/3*.jpg");	
}elseif($displayGroup=="004"){
	$images = glob($dirname.$date_path."/numbered/4*.jpg");
}elseif($displayGroup=="005"){
	$images = glob($dirname.$date_path."/numbered/5*.jpg");
}elseif($displayGroup=="006"){
	$images = glob($dirname.$date_path."/numbered/6*.jpg");
}elseif($displayGroup=="007"){
	$images = glob($dirname.$date_path."/numbered/7*.jpg");
}elseif($displayGroup=="008"){
	$images = glob($dirname.$date_path."/numbered/8*.jpg");				
}elseif($displayGroup=="009"){
	$images = glob($dirname.$date_path."/numbered/9*.jpg");					
}else{
	$images = glob($dirname.$date_path."/numbered/1*.jpg");
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Gallery Navigation</title>
<style type="text/css">
body,td,th {
	font-family: 'Poppins', -apple-system, system-ui, sans-serif;
	color: #CCC;
}
body {
	background-color: #000000;
}
select {
		border: 0 !important;  /*Removes border*/
		background: #696969 url(/public/assets/images/select-arrow.png) no-repeat 90% center;
		width: 270px; /*Width of select dropdown to give space for arrow image*/
		text-indent: 0.01px; /* Removes default arrow from firefox*/
		text-overflow: "";  /*Removes default arrow from firefox*/ /*My custom style for fonts*/
		color: #FFF;
		border-radius: 10px;
		padding: 5px;
		box-shadow: inset 0 0 5px rgba(000,000,000, 0.5);
		font-size: 1.3em;
		font-weight: bold;
	}
select.balck {
		background-color: #000;
	}
input:focus, textarea:focus, select:focus {
    outline: none;
}
span.bold-red {
    color: red;
    font-weight: bold;
	font-size: 1.3em;
	white-space: nowrap;
}
/* Keep the native <select> as a functional fallback for screen readers,
   but visually hide it when JS enhances it. */
.as-buttons {
  position: absolute !important;
  opacity: 0 !important;
  pointer-events: none !important;
  width: 1px !important;
  height: 1px !important;
}

/* Button group wrapper created by JS */
.btn-select {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

/* Individual option buttons */
.btn-option {
  display: inline-block;
  padding: 8px 14px;
  font-family: inherit;
  font-size: 14px;
  line-height: 1;
  border-radius: 10px;
  border: 1px solid #444;
  background: #696969;
  color: #fff;
  cursor: pointer;
  box-shadow: inset 0 0 5px rgba(0,0,0,0.5);
  transition: transform .02s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease;
  user-select: none;
}

.btn-option:hover {
  background: #7a7a7a;
  border-color: #555;
}

.btn-option[aria-checked="true"] {
  background: #b22222;          /* matches your red accent vibe */
  border-color: #c22;
  box-shadow: 0 0 0 2px rgba(178,34,34,0.25), inset 0 0 5px rgba(0,0,0,0.4);
}

.btn-option:active {
  transform: translateY(1px);
}

/* Make long labels wrap nicely */
.btn-option {
  white-space: nowrap;
  font-weight: 700;
}

</style>
<script language="javascript" type="text/javascript">
  document.oncontextmenu=RightMouseDown;
  document.onmousedown = mouseDown; 

  function mouseDown(e) {
      if (e.which==3) {//righClick
      //alert("Disabled - do whatever you like here..");
   }
}
function RightMouseDown() { return false;}
</script>

</head>
<body onload="document.frm_time.submit()">
<table width="100%" border="0">
  <tr>
    <td><img src="/public/assets/images/alley_logo_xsm.png" height="70" alt="Alley Cat Photo : Choose Adventure" /></td>
    <td align="left" valign="middle" style="white-space: nowrap;">
	<form action="gallery_nav.php" method="post" target="menu" name="frm_group">
	<span class="bold-red">CHOOSE ADVENTURE: </span><input type="hidden" name="display_type" value="<?php echo $displayType; ?>" />
      <select name="display_group" class="as-buttons" onchange='this.form.submit()'>
    <?php
	foreach($cat as $key=>$value)
	{
		if(trim($value)<>""){
			?>
        <option value="<?php echo $key;?>"<?php if ($displayGroup == $key) { echo " selected"; } ?>><?php echo $value;?></option>    
            <?php
		}
	}	
	?>      
      </select>
      <noscript><input type="submit" value="Submit"></noscript>
 </form>
 </td>
    <td>&nbsp;&nbsp;&nbsp;</td>
    <td><span class="bold-red">CHOOSE TIME: </br></span>
<form action="gallery<?php echo $displayType; ?>.php?" method="post" target="content" name="frm_time">
<input type="hidden" name="display_group" value="<?php echo $displayGroup; ?>" />
<select name="display_time" class="as-buttons" onchange='this.form.submit()'>
<?php
//Determine the current hour
$curHour=date("G");
$j=0;
$j0=0;
$j1=0;
$j2=0;
$j3=0;
$j4=0;
$j5=0;
$j6=0;

foreach($images as $image) {
	$filedate=date("H:i:s",filemtime($image));

	if (strtotime($filedate) >= strtotime("08:00:00") && strtotime($filedate) <= strtotime($i."09:59:59")) {
		$j++;
		$j0++;
	};
	if (strtotime($filedate) >= strtotime("10:00:00") && strtotime($filedate) <= strtotime($i."11:59:59")) {
		$j++;
		$j1++;
	};
	if (strtotime($filedate) >= strtotime("12:00:00") && strtotime($filedate) <= strtotime($i."13:59:59")) {
		$j++;
		$j2++;
	};
	if (strtotime($filedate) >= strtotime("14:00:00") && strtotime($filedate) <= strtotime($i."15:59:59")) {
		$j++;
		$j3++;
	};
	if (strtotime($filedate) >= strtotime("16:00:00") && strtotime($filedate) <= strtotime($i."17:59:59")) {
		$j++;
		$j4++;
	};
	if (strtotime($filedate) >= strtotime("18:00:00") && strtotime($filedate) <= strtotime($i."19:59:59")) {
		$j++;
		$j5++;
	};
	if (strtotime($filedate) >= strtotime("20:00:00") && strtotime($filedate) <= strtotime($i."21:59:59")) {
		$j++;
		$j6++;
	};			
}
if($j==0){
	//No images found
}else{
	//Render the dropdown and see if the hour should be selected (current hour)

	if($j6!=0){
		if($curHour==20 || $curHour == 21){
			$strSelectTime=$strSelectTime."<option value=\"20:00:00\" selected=\"selected\">08:00PM to 10:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"20:00:00\">08:00PM to 10:00PM</option>";
		}
	}		
	if($j5!=0){
		if($curHour==18 || $curHour == 19){
			$strSelectTime=$strSelectTime."<option value=\"18:00:00\" selected=\"selected\">06:00PM to 08:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"18:00:00\">06:00PM to 08:00PM</option>";
		}
	}	
	if($j4!=0){
		if($curHour==16 || $curHour == 17){
			$strSelectTime=$strSelectTime."<option value=\"16:00:00\" selected=\"selected\">04:00PM to 06:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"16:00:00\">04:00PM to 06:00PM</option>";
		}
	}
	if($j3!=0){
		if($curHour==14 || $curHour == 15){
			$strSelectTime=$strSelectTime."<option value=\"14:00:00\" selected=\"selected\">02:00PM to 04:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"14:00:00\">02:00PM to 04:00PM</option>";
		}
	}
	if($j2!=0){
		if($curHour==12 || $curHour == 13){
			$strSelectTime=$strSelectTime."<option value=\"12:00:00\" selected=\"selected\">12:00PM to 02:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"12:00:00\">12:00PM to 02:00PM</option>";
		}
	}			
	if($j1!=0){
		if($curHour==10 || $curHour == 11){
			$strSelectTime=$strSelectTime."<option value=\"10:00:00\" selected=\"selected\">10:00AM to 12:00PM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"10:00:00\">10:00AM to 12:00PM</option>";
		}
	}
	if($j0!=0){
		if($curHour==8 || $curHour == 9){
			$strSelectTime=$strSelectTime."<option value=\"08:00:00\" selected=\"selected\">08:00AM to 10:00AM</option>";
		}else{
			$strSelectTime=$strSelectTime."<option value=\"08:00:00\">08:00AM to 10:00AM</option>";
		}
	}	
}
echo $strSelectTime;
?></select><noscript><input type="submit" value="Submit"></noscript></form>
    </td>
  </tr>
</table>
<script>
(function () {
  function enhanceSelect(select) {
    // Create the button group container
    const group = document.createElement('div');
    group.className = 'btn-select';
    group.setAttribute('role', 'radiogroup');
    // Mirror the name for debugging/ARIA context
    if (select.name) group.setAttribute('aria-label', select.name.replace(/_/g, ' '));

    // Build a button for each option
    Array.from(select.options).forEach(function (opt, idx) {
      if (opt.disabled || opt.value === '') return; // skip empties/disabled if you want

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn-option';
      btn.textContent = opt.text;
      btn.setAttribute('role', 'radio');
      btn.dataset.value = opt.value;

      const isSelected = opt.selected;
      btn.setAttribute('aria-checked', isSelected ? 'true' : 'false');

      btn.addEventListener('click', function () {
        // Update native select’s value
        select.value = opt.value;

        // Update visual "selected" state
        Array.from(group.querySelectorAll('.btn-option')).forEach(b => b.setAttribute('aria-checked', 'false'));
        btn.setAttribute('aria-checked', 'true');

        // Fire the native change event so existing onchange="this.form.submit()" still works
        const evt = new Event('change', { bubbles: true });
        select.dispatchEvent(evt);
      });

      // Basic keyboard support (arrow nav, space/enter to select)
      btn.addEventListener('keydown', function (e) {
        const buttons = Array.from(group.querySelectorAll('.btn-option'));
        const i = buttons.indexOf(btn);
        let target = null;

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
          target = buttons[(i + 1) % buttons.length];
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
          target = buttons[(i - 1 + buttons.length) % buttons.length];
        } else if (e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          btn.click();
          return;
        }
        if (target) {
          e.preventDefault();
          target.focus();
        }
      });

      group.appendChild(btn);
    });

    // Insert after the select
    select.insertAdjacentElement('afterend', group);
  }

  function init() {
    document.querySelectorAll('select.as-buttons').forEach(enhanceSelect);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

</body>
</html>