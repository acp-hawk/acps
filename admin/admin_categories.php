<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __          //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_        //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\   __\       //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// AlleyCat PhotoStation v2.0.4                                        //
// Author: Paul K. Smith (photos@alleycatphoto.net)                    //
// Date: 12/09/2024                                                    //
// Last Revision 12/21/2024 (PKS)                                      //
// Administration: Manual Importer UI                                  //
// Enable Error Reporting and increase memory/time limits              //
// ------------------------------------------------------------------- //
//*********************************************************************//

require_once "config.php";

$categoriesFile = __DIR__ . "/categories.txt";

if (file_exists($categoriesFile)) {
	// Reading existing category data 
	$cat_raw = file_get_contents($categoriesFile);
	$cat = unserialize($cat_raw);

} else {
	// Create fresh array for category file
	$store = array(
		'001' => 'Zip Line',
		'002' => 'Snow Tubing',
		'003' => 'Family Photos',
		'004' => '',
		'005' => '',
		'006' => '',
		'007' => '',
		'008' => '',
		'009' => ''

	);

	// Write the file and create blank array 
	$fp = fopen($categoriesFile, 'w');
	fwrite($fp, serialize($store));
	$cat_raw = file_get_contents($categoriesFile);
	$cat = unserialize($cat_raw);
}


foreach ($cat as $key => $value) {
	if (trim($value) <> "") {
		//echo "Found: ".$key." in category: ".$value."<br />";
	}
}

// If form submitted build new array and save as file
if (isset($_POST['txtCat001'])) {
	// Create populated array for category file
	$store = array(
		'001' => trim($_POST['txtCat001']),
		'002' => trim($_POST['txtCat002']),
		'003' => trim($_POST['txtCat003']),
		'004' => trim($_POST['txtCat004']),
		'005' => trim($_POST['txtCat005']),
		'006' => trim($_POST['txtCat006']),
		'007' => trim($_POST['txtCat007']),
		'008' => trim($_POST['txtCat008']),
		'009' => trim($_POST['txtCat009'])
	);
	// Write the file and create blank array 
	$fp = fopen('categories.txt', 'w');
	fwrite($fp, serialize($store));
	$cat_raw = file_get_contents('categories.txt');
	$cat = unserialize($cat_raw);

	$saveMsg = "<br /><font color=yellow>Your categories have been saved!</font><br />";
}
?>
<!DOCTYPE html
	PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $locationName; ?> PhotoStation Administration : Category Manager</title>
	<link rel="stylesheet" href="/public/assets/importer/css/bootstrap.min.css">
	<link href="/public/assets/importer/css/styles.css" rel="stylesheet">
</head>

<body>
	<div align="center">
		<p><img src="/public/assets/images/alley_admin_header.png" width="550" height="169" alt="Administration Header"
				style="zoom: .70;" /><br />
			<a href="/admin/index.php">MANUAL IMPORT</a> - CATEGORY MANAGEMENT
		</p>
		<?php if (isset($_POST['txtCat001'])) {
			echo $saveMsg;
		}
		; ?>
		<p>To make changes to category names use the form below. If you leave the<br />
			category name blank, it will not appear inside the PhotoStation.</p>
		<form id="frmCategories" name="frmCategories" method="post" action="/admin/admin_categories.php">
			<table width="550" border="0" cellspacing="3" cellpadding="2">
				<tr>
					<td bgcolor="#333333">Category Name</td>
					<td bgcolor="#333333">&nbsp;</td>
					<td bgcolor="#333333">Number Assignment</td>
				</tr>
				<?php
				for ($i = 1; $i <= 9; $i++) {
					?>
					<tr>
						<td>
							<input name="txtCat00<?php echo $i; ?>" type="text" id="txtCat00<?php echo $i; ?>" size="50"
								value="<?php echo $cat['00' . $i]; ?>" />
						</td>
						<td>&nbsp;</td>
						<td><?php echo $i; ?>0000-<?php echo $i; ?>9999</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td><input type="image" src="/public/assets/images/btn_save.png" name="submit" /></td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
			</table>
		</form>
		<p>&nbsp;</p>
	</div>
</body>

</html>