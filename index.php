<title>Extension Status</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon"/>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.0/css/jquery.dataTables.css">
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.css">
<style>
.white-popup {
  position: relative;
  background: #FFF;
  padding: 20px;
  width:auto;
  max-width: 500px;
  margin: 20px auto;
}
.aligncenter {
    text-align: center;
}
table {
  width: 100%;
}
tr:hover {
  background-color: #f5f5f5;
}
th {
  height: 50px;
  vertical-align: middle;
  background-color: #66CCFF;
  color: black;
  width: 10%;
}
th, td {
  padding: 15px;
  border-bottom: 1px solid #ddd;
  text-align: center;
}
</style>
<script src="https://code.jquery.com/jquery-3.3.1.js" integrity="sha256-2Kok7MbOyxpgUVvAk/HJ2jigOSYS2auK4Pfzbm7uH60=" crossorigin="anonymous"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.12.0/js/jquery.dataTables.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js"></script>
<script type='text/javascript'>
$(document).ready(function(){
// Use Javascript to get the clicked/current button row's cell data (values) from myTable and toggle its color.
$("#myTable").on('click','.btnSelect',function(){
  // get the current row
  var currentRow=$(this).closest("tr");
  // Toggle button color
  if($(this).css('background-color')=="rgb(245, 245, 245)")
      $(this).css('background-color', '#6ACDFD');
  else {
      $(this).css('background-color', '#f5f5f5');
  }
  var ext=currentRow.find("td:eq(0)").text();         // get current row 1st TD value
  // Send an AJAX request to index.php with the extension number as data to be used 
  $.ajax({
    url: 'index.php',
    type: 'POST',
    data: {"ext":ext},
    datatype: 'json',
    success: function(response)
            {
              var jsonData = JSON.parse(response);
              //console.log(response);                // for debugging
              if (jsonData.success == "1")
              {
                alert('DND for extension '+ext+' Activated!');
              }
              else
              {
                  alert('DND for extension '+ext+' De-activated!');
              }
            }
});
});

// Scrollable table with Stickey header
var table = new DataTable('#myTable', {
    scrollY: 600,
    scrollCollapse: true,
    paging: false,
    searching: false,
    ordering: true,
    info: false
    });

// Added so as when i resize the browser window, the table will resize as well
window.addEventListener('resize', function(event) {
  table.columns.adjust().draw();
}, true);

});
</script>

<?php
/**** Set our global variables ****/
// My variables, used to control the color of the RTT field inside the table cells and change it to RED
$rtt_limit = 60;

// Make	sure we	are logged in to FreePBX as a security. 
session_start();
if (!$_SESSION['AMP_user']) {
        die('Not logged in! Please log in to your FreePBX dashboard before opening this page...');
}

// Load FreePBX bootstrap environment and initialize required Module's classes
include '/etc/freepbx.conf';
$fcore = FreePBX::Core();                                     // to access the AMI global variable.
$dnd = FreePBX::Donotdisturb();                               // to use the FPBX functions for donotdisturbe class

// Load AMI ... the php-asmanager.php
global $astman;

/**** If this is a POST/AJAX for DND then reply to the request and do the necessary ****/
$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
/* Sets the status of DND on a specific extension number using the donotdisturb Class module */
if ($request_method === 'POST') {
  $ext = filter_input(INPUT_POST, 'ext', FILTER_SANITIZE_NUMBER_INT);
  if (empty($dnd->getStatusByExtension($ext))) {
    $dnd->setStatusByExtension($ext,'YES');                    // Activate DND
    ob_clean();
    echo json_encode(array('success' => 1));
  } else {
    $dnd->setStatusByExtension($ext);                         // Deactivate DND
    ob_clean();
    echo json_encode(array('success' => 0));
  }
  exit;
}

/**** Getting raw data for PJSIP ****/
/******** Get registered PJSIP extensions ****/
$pjsip_results = $astman->PJSIPShowRegistrationInboundContactStatuses();
$pjsip_registered_aors = array_column($pjsip_results,'AOR');
// get all AORs == PJSIP endpoints
$pjsip_all_aors = $astman->PJSIPShowAors();
$pjsip_all_aors = array_column($pjsip_all_aors,'ObjectName');  // This includes Trunks as well.
// Extract un-registered PJSIP extensions/trunks
$pjsip_unregistered_aors = array_diff($pjsip_all_aors, $pjsip_registered_aors); 
$pjsip_unregistered_aors = array_values($pjsip_unregistered_aors);

/******** Getting and Preparing data for SIP ****/
$result_SIPpeerstatus = $astman->SIPpeerstatus();
$Peer_col = array_column($result_SIPpeerstatus, 'Peer');       // array contains extensions in the form of SIP/xxx
//start stripping the SIP/ part from the extension name.
$Peer_col_new = array();
for ($x = 0; $x < count($Peer_col); $x++) {
  preg_match('/\/(.*)/', $Peer_col[$x], $match);
  $Peer_col_new[$x] = $match[1];
  unset($match);
}
$Peer_col = $Peer_col_new;                                    // array of SIP Peers extension numbers.
$ext_details = array();                                       // Used inside the HTML Table
$ext_more_det = array();                                      // used only to add more details from $astman->SIPshowpeer
for ($x = 0; $x < count($Peer_col); $x++) {
  $ext_more_det = $astman->SIPshowpeer($Peer_col[$x]);
  $ext_details[$x]['peer'] = $Peer_col[$x];
  if ($ext_more_det['Status'] <> "UNKNOWN") {
    preg_match('/OK \((?<time>\d+) ms\)/', $ext_more_det['Status'], $match);
    $ext_details[$x]['status'] = "Reachable";
    $ext_details[$x]['Time'] = $match['time'];
  } else {
    $ext_details[$x]['status'] = 'Unknown';
    $ext_details[$x]['Time'] = '';
  }
  unset($match);
  preg_match('/"(?<name>.*?)"/', $ext_more_det['Callerid'], $match);                              //$ext_details[$x]['callerid'] = $ext_more_det['Callerid'];
  $ext_details[$x]['callerid'] = $match['name'];
  unset($match);
  $ext_details[$x]['wan_ip'] = $ext_more_det['Address-IP'];
  preg_match('/@(?<lan>\d+.\d+.\d+.\d+):(?<lport>\d+)/', $ext_more_det['Reg-Contact'], $match);  //$ext_details[$x]['reg_contact'] = $ext_more_det['Reg-Contact'];
  $ext_details[$x]['lan_ip'] = $match['lan'];
  $ext_details[$x]['lan_port'] = $match['lport'];
  unset($match);
  $ret_info = get_device_info($ext_more_det['SIP-Useragent']);
  $ext_details[$x]['brand'] = $ret_info['brand'];
  $ext_details[$x]['model'] = $ret_info['model'];
  $ext_details[$x]['fw'] = $ret_info['firmware'];
  $ext_details[$x]['dnd'] = (!empty($dnd->getStatusByExtension($Peer_col[$x])))? 'YES':'NO';
  unset($ret_info);
  $ext_details[$x]['context'] = $ext_more_det['Context'];
}

/****** don't draw the table unless we have something to show! ******/
if (!empty($pjsip_results) || !empty($ext_details) || !empty($pjsip_unregistered_aors)) {
  include './extensions_header.php';
} else {
  // Show something on front end that indicates that there are no extensions to show here..
}

/**** Show PJSIP Data ****/
if (!empty($pjsip_results)){
  // Show PJSIP Registered Extensions First
  foreach ($pjsip_results as $data) {
    echo '    <tr>' . "\n";
    // The extension
    echo '      <td>' . $data['AOR'] . '</td>' . "\n";
    // Extension Display Name
    // For FreePBX <=15 aka PHP 5
    if ((substr($data['AOR'],0,2) === '90') || (substr($data['AOR'],0,2) === '98')) {
    // For FreePBX 16 aka PHP 7+
    //if (str_starts_with($data['AOR'],'90') || str_starts_with($data['AOR'],'98')) {
      $user=$fcore->getUser(substr($data['AOR'],2));
    } else {
      $user=$fcore->getUser($data['AOR']);
    }
    $user['dnd'] = (!empty($dnd->getStatusByExtension($user['extension'])))? 'YES':'NO';
    echo '         <td>' . $user['name'] . '</td>' . "\n";
    if ($showuri) { echo '      <td>' . $data['URI'] . '</td>' . "\n"; }
    // The user agent contains information about the device. Break it into pieces as Brand/Model/Firmware
    $ret_info = get_device_info($data['UserAgent']);
    echo '      <td>' . $ret_info['brand'] . '</td>' . "\n";
    echo '      <td>' . $ret_info['model'] . '</td>' . "\n";
    echo '      <td>' . $ret_info['firmware'] . '</td>' . "\n";
    // show the Status
    echo '      <td style="text-align: center">' . $data['Status'] . '</td>' . "\n";
    echo '      <td style="text-align: center;color: #333FFF">PJSIP</td>' . "\n";
    // Show RTT times in milliseconds
    if (is_numeric($data['RoundtripUsec'])) {
      echo '	<td style="text-align: center;color: '. ((($data['RoundtripUsec'] / 1000) > $rtt_limit)?'red':'') .';">' . ((($data['RoundtripUsec'] / 1000) > $rtt_limit)?'<b>':'') . $data['RoundtripUsec'] / 1000 . ((($data['RoundtripUsec'] / 1000) > $rtt_limit)?'</b>':'') . (empty($data['RoundtripUsec'])?' </td>': ' ms</td>') . "\n";
    } else {
      echo '	<td>-</td>' . "\n";
    }
    preg_match('/@\d+.\d+.\d+.\d+:(?<lport>([+-]?(?=\.\d|\d)(?:\d+)?(?:\.?\d*))(?:[eE]([+-]?\d+))?)/', $data['URI'], $match);
    $lport = $match['lport'];
    unset($match);
    $viaaddress = explode(':',$data['ViaAddress']);
    $uri = explode(':',end(explode('@',$data['URI'])));
    echo '      <td style="text-align: left">' . "\n";
    if (!filter_var($uri[0], FILTER_VALIDATE_IP)) { $uri[0] = 'Not an IP'; }
    if (!filter_var($viaaddress[0], FILTER_VALIDATE_IP)) { $viaaddress[0] = 'Not an IP'; }
    if (!filter_var($callid, FILTER_VALIDATE_IP)) { $callid = 'Not an IP'; }
    echo '        <b>WAN:</b> ' . $uri[0] . '<br />' . "\n";
    echo '        <b>LAN:</b> ' . $viaaddress[0] . '<br />' . "\n";
    echo '        <b>Port:</b> ' . $lport . '<br />' . "\n";
    echo '      </td>' . "\n";
    // Check if this extension has another device assigned to it as we are doing PJSIP now
    // accordingly show or not show the DND button.
    if ($user['extension'] <> $data_old) {
      echo '      <td><button class="btnSelect" style="background-color:' . ($user['dnd'] == 'YES'?'#6ACDFD':'#f5f5f5') .'">DND Toggle</button></td>' . "\n";
    }
    if ($user['extension'] == $data_old) {
      echo '      <td></td>' . "\n";
    }
    $data_old = $user['extension'];
    echo '    </tr>' . "\n";
  }
}

if (!empty($pjsip_unregistered_aors)) {
  // Show PJSIP UN-Registered Extensions
  foreach ($pjsip_unregistered_aors as $key => $value) {
    // check if we are dealing with a trunk entry
    $k = $astman->PJSIPShowEndpoint($value);
    if ($k[0]['Context'] <> 'from-internal') { continue ; }
    echo '    <tr style="background-color: #D5D8DC;">' . "\n";
    // The extension
    echo '      <td>' . $value . '</td>' . "\n";
    // Extension Display Name
    $user=$fcore->getUser($value);
    $user['dnd'] = (!empty($dnd->getStatusByExtension($user['extension'])))? 'YES':'NO';
    echo '         <td>' . $user['name'] . '</td>' . "\n";
    echo '      <td></td>' . "\n";
    echo '      <td></td>' . "\n";
    echo '      <td></td>' . "\n";
    // show the Status
    echo '      <td style="text-align: center">' . 'Unknown' . '</td>' . "\n";
    echo '      <td style="text-align: center;color: #333FFF">PJSIP</td>' . "\n";
    echo '	    <td></td>' . "\n";
    echo '      <td style="text-align: left">' . "\n";
    echo '        <b>WAN:</b> ' . '' . '<br />' . "\n";
    echo '        <b>LAN:</b> ' . '' . '<br />' . "\n";
    echo '        <b>Port:</b> ' . '' . '<br />' . "\n";
    echo '      </td>' . "\n";
    echo '      <td><button class="btnSelect" style="background-color:' . ($user['dnd'] == 'YES'?'#6ACDFD':'#f5f5f5') .'">DND Toggle</button></td>' . "\n";
    echo '    </tr>' . "\n";
  }
}

/**** Now show SIP Data ****/
if (!empty($ext_details)){
  // Show SIP Peers
  foreach ($ext_details as $data) {
    // check if we are dealing with a trunk entry
    if ($data['context'] <> 'from-internal') { continue ; }
    echo '    <tr' . (($data['status'] == 'Unknown') ? ' style="background-color: #D5D8DC;"' :'') . '>' . "\n";
    // The extension
    echo '      <td>' . $data['peer'] . '</td>' . "\n";
    echo '      <td>' . $data['callerid'] . '</td>' . "\n";
    echo '      <td>' . (($data['brand']=="Unknown")?'' : $data['brand']) . '</td>' . "\n";
    echo '      <td>' . $data['model'] . '</td>' . "\n";
    echo '      <td>' . $data['fw'] . '</td>' . "\n";
    // show the Status
    echo '      <td style="text-align: center">' . $data['status'] . '</td>' . "\n";
    echo '      <td style="text-align: center"><strong>SIP</strong></td>' . "\n";
    // Show RTT times in milliseconds
    echo '	  <td style="text-align: center;color: '. (($data['Time'] > $rtt_limit)?'red':'') .';">' . (($data['Time'] > $rtt_limit)?'<b>':'') . $data['Time'] . (($data['Time'] > $rtt_limit)?'</b>':'') . (empty($data['Time'])?' </td>': ' ms</td>') . "\n"; 
    echo '      <td style="text-align: left">' . "\n";
    echo '        <b>Wan:</b> ' . $data['wan_ip'] . '<br />' . "\n";
    echo '        <b>LAN:</b> ' . $data['lan_ip'] . '<br />' . "\n";
    echo '        <b>Port:</b> ' . $data['lan_port'] . '<br />' . "\n";
    if ($data['context'] == 'from-internal'){
      echo '	  <td><button class="btnSelect" style="background-color:' . ($data['dnd'] == 'YES'?'#6ACDFD':'#f5f5f5') .'">DND Toggle</button></td>' . "\n";
    } else {
      echo '	  <td></td>' . "\n";
    }
    echo '      </td>' . "\n";
  }
}

// close out HTML table and div tags
echo '  </tbody>' . "\n";
echo '</table>' . "\n";
echo '</div>' . "\n";
echo '</div>' . "\n";

// Function to extract the device details according to Brand from the URI
function get_device_info($ua) {
  global $showdebug;
  $ua_arr = preg_split("/[\s\/]/", $ua, 2);
  if ($showdebug) {
    echo "<br />BEGIN RESULTS DUMP<br />\r\n<pre>\r\n";
    var_dump($ua_arr[1]);
    echo "\r\n</pre><br />\r\nEND RESULTS DUMP\r\n";
  }
  switch ($ua_arr[0]) {
    case "Yealink":
    case "Zulu":
    case "Z":
      $mod_firm_arr = preg_split("/[\s]/", preg_replace("/^SIP[\s-]/","",$ua_arr[1]));
      $device_info = ["brand" => $ua_arr[0], "model" => $mod_firm_arr[0], "firmware" => $mod_firm_arr[1]];
      break;
    case "Grandstream":
    case "OBIHAI":
    case "Fanvil":
    case "Acrobits":
    case "Cisco":
      $mod_firm_arr = preg_split("/[\s-]/", $ua_arr[1]);
      $device_info = ["brand" => $ua_arr[0], "model" => $mod_firm_arr[0], "firmware" => $mod_firm_arr[1]];
      break;
    case "Sangoma":
      $mod_firm_arr = preg_split("/[\/]/", $ua_arr[1]);
      $device_info = ["brand" => $ua_arr[0], "model" => $mod_firm_arr[0], "firmware" => $mod_firm_arr[1]];
      break;
    case "Zoiper":
    case "MicroSIP":
    case "Telephone":
      $device_info = ["brand" => $ua_arr[0], "model" => "", "firmware" => $ua_arr[1]];
      break;
    case "snomPA1":
      $device_info = ["brand" => "Snom", "model" => "PA1", "firmware" => $ua_arr[1]];
      break;
    case "LinphoneiOS":
      $mod_firm_arr = preg_split("/[\s]/", $ua_arr[1]);
      $device_info = ["brand" => $ua_arr[0], "model" => "", "firmware" => $mod_firm_arr[0]];
      break;
    case "Linphone": //Linphone Desktop
      $mod_firm_arr = preg_split("/[\s\/]/", preg_replace('/\(|\)/','',$ua_arr[1]));
      $device_info = ["brand" => $ua_arr[0] . " " . $mod_firm_arr[0], "model" => $mod_firm_arr[2], "firmware" => $mod_firm_arr[1]];
      break;
    case "Clearly": //Clearly IP
      preg_match('/(?<model>CIP\d+)(?<ver>V\d) V(?<fw>([0-9]+(\.[0-9]+)+)R\d+)/', $ua_arr[1], $matches);
      $device_info = ["brand" => "Clearly IP", "model" => $matches["model"]. " Ver. ". $matches["ver"], "firmware" => $matches["fw"]];
      break;
    default:
      // Messy, will look into it after more Poly devices are tested
      if (substr($ua_arr[0],0,7) == "Polycom") {
        $mod_firm_arr = preg_split("/[-]/", $ua_arr[0]);
        $device_info = ["brand"	=> "Polycom", "model" => preg_replace('/_/',' ',$mod_firm_arr[1]), "firmware" => $ua_arr[1]];
      // Jitsi on Windows does not have a split character.
      } elseif (substr($ua_arr[0],0,5) == "Jitsi" ) {
        $regexp='/(\D+)([\d\.]+)(\D+.*)/';
       	preg_match($regexp, $ua, $ua_jitsi);
        $device_info = ["brand" => $ua_jitsi[1], "model" => $ua_jitsi[3], "firmware" => $ua_jitsi[2]];
      }	else {
        $device_info = ["brand" => "Unknown", "model" => "", "firmware" => ""];
      }
  }
  return $device_info;
}
exit;
?>
