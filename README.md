# Advanced-Extensions-List
An advanced extension List with DND toggle per extension
The aim of this script is to show all extensions under a specific FreePBX instance whether they are SIP or PJSIP (technology-wise) and show more details like (DND status, reachability, device info, IPs, …, etc.)

The script relies mainly on Asterisk AMI and the FreePBX Big Module Object “BMO” which in turn connects to Asterisk via the AGI_AsteriskManager Class in '/var/www/html/admin/libraries/php-asmanager.php’. The current AGI_AsteriskManager Class functions has been found incomplete in terms of AMI actions that can be called. We had to add the following functions to catch AMI events for the following.

            1-      SIPpeerstatus

            2-      SIPpeers

            3-      SIPshowpeer

            4-      PJSIPShowAors

All in all, these functions were used in combination to get full visibility about an extension status and the associated device information.

The script has some JavaScripts/jQuery libraries to help enrich the front-end user interface with some interactivity. Among those,

            1-      Users can just click on the “DND Toggle” button and that click will send an AJAX call to the server to activate/deactivate a specific extension and will change the “DND” button color.

            2-      Users can sort the table of extensions by any column.

            3-      Table headers has been fixed and table rows has been made to scroll.

            4-      Table resizes with any browse window resize event.

How the module works:

Copy all files into a subfolder under your webroot except for the file called ‘php-asmanager.php’. You should copy that file to your FreePBX installation path under the ‘admin/libraries/’ path replacing the one there. Log-in to your FreePBX GUI first and then point your browser to your PBX FQDN, appending '/subfolder_name' to the end of that URL. Enjoy!
