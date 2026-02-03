<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __          //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_        //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\   __\       //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// AlleyCat PhotoStation v2.0.1                                        //
// Author: Paul K. Smith (paul.kelso.smith@gmail.com)                  //
// Date: 01/13/2021                                                    //
// Last Revision 01/14/2021 (PKS)                                      //
// Administration: Manual Importer UI                                  //
// Enable Error Reporting and increase memory/time limits              //
// error_reporting(E_ALL);                                             //
// ini_set('display_errors', 1);                                       //
//*********************************************************************//

ini_set('memory_limit', '-1');
set_time_limit(0);
$thiscount=0;

$timestamp = time();
$token=md5('unique_salt' . $timestamp);

$dirname = "../incoming/import/";
$date_path = date('Y/m/d');
$images = glob($dirname."*.[jJ][pP]*");

$cat_raw = file_get_contents(__DIR__ . '/../categories.txt'); 
$cat = unserialize($cat_raw);


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>PhotoStation Administration : Manual Import</title>

    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="/public/assets/importer/css/bootstrap.min.css" >

    <!-- Custom styles -->
    <link href="/public/assets/importer/css/jquery.dm-uploader.css" rel="stylesheet">
    <link href="/public/assets/importer/css/styles.css" rel="stylesheet">
  </head>

  <body>

    <main role="main" class="container">

      <div align="center">
        <p><img src="/public/assets/images/alley_admin_header.png" width="550" height="169" alt="Administration Header" /><br />
        <a href="/admin/admin_categories.php">CATEGORY MANAGEMENT</a> - MANUAL IMPORT</p>
      Select the destination for your files below and click 'select files' button.  <br />
      Choose the file(s) you wish to import and hit okay to begin the process. <br />
      <br /><font color=yellow>IMPORT MAY TAKE A FEW MINUTES PLEASE BE PATIENT</font><br /><br />
      <img src="/public/assets/images/ring_loader.gif" id="spinner" width="50" height="50" alt="Importing..." style="display:none" />
      <br />
      <form action="/admin/admin_import_proc.php" method="post" name="frmImport" id="frmImport">
      <input type="hidden" name="token" id="token" value="<?php echo $token;?>" />
          <table border="0">
              <tr>
                <td align="center">
                  <div id="chooser_group"><B>CHOOSE DESTINATION:</B><br />
              <select class="chooser" name="custom_target">
      <?php
      foreach($cat as $key=>$value)
      {
        if(trim($value)<>""){
          //echo "Found: ".$key." in category: ".$value."<br />";
          ?>
        <option value="<?php echo $key;?>"<?php if ($displayGroup == $key) { echo " selected"; } ?>><?php echo $value;?></option>    
          <?php
        }
      }	
      ?>    
                </select></div>
          </td>
                <td width=50% align="center"><div id="chooser_time">
                  <strong>CHOOSE TIME:</strong><br />
                  <select class="chooser" name="selTime" id="selTime">
                    <option value="8:00:01"<?php if(date('H:i')>='08:00' && date('H:i')<='09:59'){echo "selected";};?>>08:00AM - 10:00AM</option>
                    <option value="10:00:01"<?php if(date('H:i')>='10:00' && date('H:i')<='11:59'){echo "selected";};?>>10:00AM - 12:00PM</option>
                    <option value="12:00:01"<?php if(date('H:i')>='12:00' && date('H:i')<='13:59'){echo "selected";};?>>12:00PM - 02:00PM</option>
                    <option value="14:00:01"<?php if(date('H:i')>='14:00' && date('H:i')<='15:59'){echo "selected";};?>>02:00PM - 04:00PM</option>
                    <option value="16:00:01"<?php if(date('H:i')>='16:00' && date('H:i')<='17:59'){echo "selected";};?>>04:00PM - 06:00PM</option>
                    <option value="18:00:01"<?php if(date('H:i')>='18:00' && date('H:i')<='19:59'){echo "selected";};?>>06:00PM - 08:00PM</option>
                    <option value="20:00:01"<?php if(date('H:i')>='20:00' && date('H:i')<='21:59'){echo "selected";};?>>08:00PM - 10:00PM</option>                                             
                  </select></div></td>
            </tr>
        <tr>
          <td colspan="2"><br />
            <br /></td>
        </tr>
        <tr>
          <td colspan="2" align="center">
        </form>
          <form>
          <div id="queue"></div>
          
      </form>

      <div class="row">
        <div class="col-md-6 col-sm-12">
          
          <!-- Our markup, the important part here! -->
          <div id="drag-and-drop-zone" class="dm-uploader p-5">
            <h3 class="mb-5 mt-5 text-muted">Drag &amp; drop files here</h3>

            <div class="btn btn-primary btn-block mb-5">
                <span>Open the file Browser</span>
                <input type="file" title='Click to add Files' />
                <input type="hidden" id="token" value="<?php echo $token; ?>" />
                <input type="hidden" id="timestamp" value="<?php echo $timestamp; ?>" />
            </div>
          </div><!-- /uploader -->

        </div>
        <div class="col-md-6 col-sm-12">
          <div class="card h-100">
            <div class="card-header">
              File List
            </div>

            <ul class="list-unstyled p-2 d-flex flex-column col" id="files">
              <li class="text-muted text-center empty">No files uploaded.</li>
            </ul>
          </div>
        </div>
      </div><!-- /file list -->
<!--
      <div class="alert alert-info" role="alert">
        Something: <a href="https://x">X</a>
      </div>
    -->
      <div class="row">
        <div class="col-12">
           <div class="card h-100">
            <div class="card-header">
              Debug Messages
            </div>

            <ul class="list-group list-group-flush" id="debug">
              <li class="list-group-item text-muted empty">Loading photo importer....</li>
            </ul>
          </div>
        </div>
      </div> <!-- /debug -->

    </main> <!-- /container -->

    <footer class="text-center">
        <p>&copy; Alley Cat &middot; <a href="https://www.alleycatphoto.com">alleycatphoto.com</a></p>
    </footer>

    <script src="/public/assets/importer/js/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
    <script src="/public/assets/importer/js/bootstrap.min.js" integrity="sha384-a5N7Y/aK3qNeh15eJKGWxsqtnX/wWdSZSKp+81YjTmS15nvnvxKHuzaWwXHDli+4" crossorigin="anonymous"></script>

    <script src="/public/assets/importer/js/jquery.dm-uploader.js"></script>
    <script src="/public/assets/importer/js/ui.js"></script>
    <script src="/public/assets/importer/js/conf.js"></script>

    <!-- File item template -->
    <script type="text/html" id="files-template">
      <li class="media">
        <div class="media-body mb-1">
          <p class="mb-2">
            <strong>%%filename%%</strong> - Status: <span class="text-muted">Waiting</span>
          </p>
          <div class="progress mb-2">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
              role="progressbar"
              style="width: 0%" 
              aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            </div>
          </div>
          <hr class="mt-1 mb-1" />
        </div>
      </li>
    </script>

    <!-- Debug item template -->
    <script type="text/html" id="debug-template">
      <li class="list-group-item text-%%color%%"><strong>%%date%%</strong>: %%message%%</li>
    </script>
  </body>
</html>
