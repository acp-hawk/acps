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
// Displays the images and handles paging for a specific time frame.   //
//*********************************************************************//

session_start();

//Get all variables from the loading frame
$queryString = $_SERVER['QUERY_STRING']; 
parse_str($queryString,$queryString);

$opt_f='20';
$opt_r='150';
$opt_z='0.25';
$opt_l='0.725';
$opt_p='0.755';
$opt_c='16';
$opt_d='60';

if($opt_f!=""){$fontSize = $opt_f;}else{$fontSize = '20';};
if($opt_r!=""){$refreshTime = $opt_r;}else{$refreshTime = '150';};
if($opt_z!=""){$zoomLevel = $opt_z;}else{$zoomLevel = '0.25';};
if($opt_l!=""){$zoomLevelL = $opt_l;}else{$zoomLevelL = '0.725';};
if($opt_p!=""){$zoomLevelP = $opt_p;}else{$zoomLevelP = '0.775';};
if($opt_c!=""){$photoCount = $opt_c;}else{$photoCount = '16';};
if($opt_d!=""){$cellDivide = $opt_d;}else{$cellDivide = '60';};

$dirname = "photos/";
$date_path = date('Y/m/d');

if( isset($_POST['display_group']) ) {
    $displayGroup = $_POST['display_group'];
} else {
	if( isset($_GET['g']) ){
		$displayGroup = $_GET['g'];
	}else{
		$displayGroup = "001";
	}
}

if( isset($_POST['display_time']) ) {
    $displayTime = $_POST['display_time'];
} else {
	if( isset($_GET['t']) ){
		$displayTime = $_GET['t'];
	}else{
		$displayTime = -1;
	}		
}

if( isset($_GET['pg']) ){
	$thisPage = $_GET['pg'];
}else{
	$thisPage = 1;
}		

$thisDisplay = substr($displayGroup, 2, 1);
$images = glob($dirname.$date_path."/numbered/".$thisDisplay."*.jpg");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Photo Gallery</title>
<style type="text/css">
/* Dynamic font size from PHP */
body.gallery-page,
body.gallery-page td,
body.gallery-page th {
	font-size: <?php echo $fontSize ?>px;
}
</style>
<link rel="stylesheet" type="text/css" href="/public/assets/css/acps.css">
<script src="/public/assets/js/jquery-3.2.1.min.js"></script>
<script src="/public/assets/js/acps_modal.js"></script>
<script type="text/javascript">
//*********************************************************************//
// Gallery Functions - Load and display large images
//*********************************************************************//

/**
 * Loads a large version of the clicked thumbnail
 * @param {string} pid - Photo ID/path
 * @param {string} zoom - Zoom level (legacy, not used)
 * @param {string} zoomType - Zoom type (legacy, not used)
 */
function loadLarge(pid, zoom, zoomType) {
	var large_image = document.getElementById('large_image');
	var cart_image = document.getElementById('cart_image');
	var cartURL = 'cart_add.php?p=' + pid + '&<?php echo is_array($queryString) ? http_build_query($queryString) : $queryString; ?>';
	
	// Display large image with click handler to open cart modal
	large_image.innerHTML = '<div class="gallery-image-wrapper">' +
		'<img src="' + pid + '" class="imgborder gallery-image-clickable" ' +
		'data-cart-url="' + cartURL + '" ' +
		'style="cursor:pointer;" ' +
		'title="Click to add to cart" />' +
		'</div>';
	
	// Add to cart button
	var buttonsHTML = 
		'<button onclick="window.top.openCartModal(\'' + cartURL + '\')" class="btn-choice btn-green gallery-cart-btn">' +
		'<span class="btn-title">🛒 ADD TO SHOPPING CART</span>' +
		'</button>';
	
	cart_image.innerHTML = buttonsHTML;
}

//*********************************************************************//
// Auto-refresh functionality
//*********************************************************************//
(function(seconds) {
    var refresh,       
        intvrefresh = function() {
            clearInterval(refresh);
            refresh = setTimeout(function() {
			   document.forms["refreshForm"].submit();
            }, seconds * 1000);
        };

    $(document).on('keypress, click', function() { intvrefresh() });
    intvrefresh();

}(<?php echo $refreshTime; ?>));

//*********************************************************************//
// Disable right-click context menu
//*********************************************************************//
document.oncontextmenu = RightMouseDown;
document.onmousedown = mouseDown; 
function mouseDown(e) {
    if (e.which == 3) { }
}
function RightMouseDown() { return false; }
</script>
</head>

<body class="gallery-page" bgcolor="#000000" link="#FFFFFF" vlink="#FFFFFF" alink="#FFFFFF">
<center>
<table width="100%" border="0" cellspacing="0" cellpadding="10">
  <tr>
    <td align="center" valign="top" width="<?php echo $cellDivide; ?>%">
<p>
<?php 
$i=0;
$display_images = array();
foreach($images as $image) {
	$filedate=date("H:i:s",filemtime($image));
	$endTime=strtotime($displayTime)+7200;
	if (strtotime($filedate) >= strtotime($displayTime) && strtotime($filedate) <= $endTime) {
		$display_images[] = $image;
		$i++;	
	}
}
$imageCount=$i;
$pageCount=ceil($imageCount/$photoCount);
$startCount=(($thisPage-1)*$photoCount)+1;
$endCount=($thisPage)*$photoCount;
if($endCount>=$imageCount){ $endCount=$imageCount; }
if($pageCount>>1) {
	// Modern pagination with max 10 visible pages
	$maxVisible = 10;
	$startPage = max(1, min($thisPage - floor($maxVisible / 2), $pageCount - $maxVisible + 1));
	$endPage = min($pageCount, $startPage + $maxVisible - 1);
	if ($endPage - $startPage + 1 < $maxVisible) {
		$startPage = max(1, $endPage - $maxVisible + 1);
	}
	$baseURL = "gallery.php?t=$displayTime&s=$thisPageSize&g=$displayGroup&opt_f=$fontSize&opt_r=$refreshTime&opt_z=$zoomLevel&opt_l=$zoomLevelL&opt_p=$zoomLevelP&opt_c=$photoCount&opt_d=$cellDivide";
?>
</p>
<div class="gallery-pagination">
	<span class="gallery-pagination-label">Page:</span>
	
	<?php if($thisPage > 1): ?>
		<a href="<?php echo $baseURL; ?>&pg=<?php echo $thisPage - 1; ?>" target="content" class="page-nav-btn" title="Previous Page">◀</a>
	<?php else: ?>
		<span class="page-nav-btn disabled">◀</span>
	<?php endif; ?>
	
	<?php if($startPage > 1): ?>
		<a href="<?php echo $baseURL; ?>&pg=1" target="content" class="page-num">1</a>
		<?php if($startPage > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
	<?php endif; ?>
	
	<?php for($i = $startPage; $i <= $endPage; $i++): ?>
		<?php if($i == $thisPage): ?>
			<span class="page-num active"><?php echo $i; ?></span>
		<?php else: ?>
			<a href="<?php echo $baseURL; ?>&pg=<?php echo $i; ?>" target="content" class="page-num"><?php echo $i; ?></a>
		<?php endif; ?>
	<?php endfor; ?>
	
	<?php if($endPage < $pageCount): ?>
		<?php if($endPage < $pageCount - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
		<a href="<?php echo $baseURL; ?>&pg=<?php echo $pageCount; ?>" target="content" class="page-num"><?php echo $pageCount; ?></a>
	<?php endif; ?>
	
	<?php if($thisPage < $pageCount): ?>
		<a href="<?php echo $baseURL; ?>&pg=<?php echo $thisPage + 1; ?>" target="content" class="page-nav-btn" title="Next Page">▶</a>
	<?php else: ?>
		<span class="page-nav-btn disabled">▶</span>
	<?php endif; ?>
</div>
<?php } ?>
</h4>
<?php
for($i=$startCount-1; $i<=$endCount-1; $i++) {
	list($width, $height, $type, $attr) = getimagesize($display_images[$i]);
	if($width > $height){
		if($i==$startCount-1){
			$thisImage = $display_images[$i];
			$thisZoom = $zoomLevelL;
			$thisZoomType = "L";
		}
		echo '<a href="#" onclick="loadLarge(\''.$display_images[$i].'\',\''.$zoomLevelL .'\',\''.$thisZoomType.'\');"><img src="'.$display_images[$i].'" style="zoom: '.$zoomLevel.'" class="wrap"/></a>';
	}else{
		if($i==$startCount-1){		
			$thisImage = $display_images[$i];
			$thisZoom = $zoomLevelP;
			$thisZoomType = "P";
		}
		echo '<a href="#" onclick="loadLarge(\''.$display_images[$i].'\',\''.$zoomLevelP .'\',\''.$thisZoomType.'\');"><img src="'.$display_images[$i].'" style="max-height: 600px; zoom: '.$zoomLevel.'" class="wrap"/></a>';
	}
}
if($imageCount==0){
?>
<br /><br /><br /><br /><br />
<div style="text-align:center; vertical-align:top; padding:20px; margin:auto"><h1><b>Your photos will be available shortly.</b></h1></div>
<?php }else{ ?>
<script type="text/javascript">
// Initialize gallery on page load
window.onload = function(){ 
	loadLarge('<?php echo $thisImage; ?>','<?php echo $thisZoom; ?>','<?php echo $thisZoomType; ?>');
};
</script>
<?php } ?>
<form id="refreshForm" name="refreshForm" method="post" action="gallery_nav.php?g=<?php echo $displayGroup; ?>&opt_f=<?php echo $fontSize; ?>&opt_r=<?php echo $refreshTime; ?>&opt_z=<?php echo $zoomLevel; ?>&opt_l=<?php echo $zoomLevelL; ?>&opt_p=<?php echo $zoomLevelP; ?>&opt_c=<?php echo $photoCount; ?>&opt_d=<?php echo $cellDivide; ?>" target="menu">
<input type="hidden" name="display_time" id="display_time" value="<?php echo $displayTime; ?>">
<input type="hidden" name="display_type" id="display_type" value="">
</form>    
    </td>
    <td align="center" valign="middle" width="<?php echo 100-$opt_d; ?>%">
	<p style="color:#ffe600; font-size:25px;"><b>THANK YOU FOR <span style="font-size:30px; color:red;">NOT</span> TAKING PHOTOS OF THE SCREENS!</b></p>
    <div id="large_image"></div><br />
    <div id="cart_image" class="cart_image"></div>

    </td>
  </tr>
</table>
</center>

</body>
</html>

