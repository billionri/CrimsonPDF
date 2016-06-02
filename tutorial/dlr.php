<?php
#-----------------------------------------------
##   Author: Rashid Alam 
##   Email: rashid.alam@netcore.co.in
##   Description: This script accepts xml request from client API 
##   convert into get request and send request to stats API 
##   which will return the xml string to the client.
##-----------------------------------------------

date_default_timezone_set("Asia/Kolkata");
require('../fpdf.php');
require('all_func.inc');

#ini_set('display_errors',1);
#error_reporting(E_ALL);

class CorpBankCustomDlr
{
    /**
     ** @var string URL to be executed
     **/
    private $url;

    /**
     ** @var string Request id from client Api 
     **/
    private $reqid;

    /**
     *
     ** @var string Feedid id from client Api 
     **/
    private $feedid;

    /**
     *
     ** @var string Feedid id from client Api 
     **/
    private $date;

    /**
     ** @var string Unique id from client Api 
     **/
    private $uniqid;

    /**
     ** @var string param array from client Api 
     **/
    private $dtxnid;

    /**
     ** @var string param array from codemaps Api 
     **/
    private $code_maps;

    /**
     ** @var string param array from name of file Api 
     **/
    private $dwnfilename;

    /**
     ** @var string param array from name of file Api 
     **/
    private $clientPostXMLdata;

    /**
     * Create a new Job instance that will be used to build
     * a new job
     */
    public function __construct()
    {
        # Generate Unique id for each request
        $this->uniqid = uniqid();

        # Get Raw post xml input (string)
        if (! isset($_POST['UserRequest'])) {
            $this->log_and_die("UserRequest post variable value not found.");
        }
        $postdata = trim($_POST['UserRequest']);

        try 
        {
            $xml = simplexml_load_string($postdata);
            $pdf = new PDF();
            foreach($xml as $key=>$a){
                 header('Content-Type: application/pdf');
                         header('Content-Disposition: attachment; filename="tokens.pdf"');
                $title = '20000 Leagues Under the Seas';
                #$pdf->$key();
                $pdf->Output();
                //var_dump($key);
            }
           exit;
            # Convert Raw post input into XML
            $this->clientPostXMLdata = $xml;
        }
        catch (Exception $e) 
        { 
            $this->log_and_die("No Post data found.");
        }

        $this->code_maps = array(
            '000' => 'delivered',
            '001' => 'Internal Error, please try again later',
            '002' => 'undelivered',
            '003' => 'absentsubscriber',
            '004' => 'invalidsubscriber',
            '005' => 'expired',
            '006' => 'submittedtonetwork',
            '007' => 'DATE or REQID is not valid',
            '008' => 'pending',
            '009' => 'dropped',
            '010' => 'duplicatemsgdrop',
            '011' => 'force',
            '012' => 'blacklist',
            '013' => 'internationalroaming',
            '014' => 'ndncreject',
            '015' => 'pricenotset',
            '016' => 'submittedtonm',
            '017' => 'insufficientcredit',
            '018' => 'msgdrop',
            '019' => 'messageinboxfull',
            'stats_url' => 'http://stats.mytoday.com/cgi-bin/DLR_API/bin/dlr_api.pl?',
        );


        # Declare filename for download csv file coming from stats
        $this->dwnfilename = 'downloaded.csv';
    }

    /**
     * Returns the msg Field list
     * 
     * @return msg Field list
     */
    public function add_log() {
        $logname = 'CORP BNK DLR';
        $file    = '/var/log/apps/handler/pdf.log';
        $func_params = func_get_args();
        $msg     = date("Y-m-d H:i:s")." |$logname [$this->uniqid] ". implode('|', $func_params) . "\n";
        file_put_contents($file, $msg, FILE_APPEND | LOCK_EX );
        return $msg;
    }   

    /**
     * Inserts log and die 
     * 
     */
    public function log_and_die($send_response) {
         $this->add_log("-==START==-");
         $this->add_log($send_response);
         echo $send_response;
         $this->add_log("-==END==-");
         exit;
    }   
    /**
     * Returns the xml Field list
     * 
     * @return xml Field list
     */
    public function parseXmlToArray($response) 
    {
        $xml = new simplexmlelement($response);
        $xml = (array)$xml;
        return $xml;
    }

    /**
     * Returns the curl response Field list
     * 
     * @return curl response list
     */
    public function curlCall($done_stats = null) 
    {
        $ch = curl_init();
        //Send HTTP request and close the handle
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        if($done_stats) {
            $st = $response;
            $fd = fopen($this->dwnfilename, 'w');
            fwrite($fd, $st);
            fclose($fd); 
        }
        # loop through the each node of molecule
        $curl_http_code = $curlInfo['http_code'];
        $retry_count = 0;
        while ($curl_http_code != 200) {
            if ($retry_count < 3) {   //Retry count is less than 3
                $this->add_log("Retrying url request Count: ".$retry_count);
                $retry_count ++;
                sleep(2);
                continue;
            }
            $this->add_log("API is down, url : ".$this->url);
            break;
        }
        return $response;
    }

    /**
     * Gets the invalid  Request id or date from client Api of a field
     * 
     */
    public function invalidReqOrDate() 
    {
        $msg = "DATE or REQID not found";
        $this->add_log($msg);
        header('Content-Type: application/xml'); # Parse output to raw xml
        $send_response = $this->tryAgainXMLMsg('007', $this->code_maps['007']);
        echo $send_response;
        $this->add_log(sprintf("Response %s", trim(str_replace(" ", "",str_replace("\n", "", $send_response)))));
        $this->add_log("-==END==-");
        exit;
    }

    /**
     * Gets the client Request id from client Api of a field
     * 
     */
    public function readClientRequest() 
    {
        $response = $this->clientPostXMLdata;
        $this->add_log(str_replace("\n","",sprintf("Client Request : %s", $response)));
        $xml          = (array)$response;
    #   if (isset($xml[0])) {
    #       if (!$xml[0] or $xml[0] == "null") {
    #           header('Content-Type: application/xml'); # Parse output to raw xml
    #           $send_response = $this->tryAgainXMLMsg('011', $this->code_maps['011']);
    #           echo $send_response;
    #           exit; 
    #       }
    #   }
        if (!isset($xml['REQID']) || !isset($xml['DATE'])) {
            $this->invalidReqOrDate();
        }
        $this->reqid  = trim($xml['REQID']);
        $this->date   = trim($xml['DATE']);
        if ( $this->reqid == 0 || $this->date == '' ) {
            $this->invalidReqOrDate();
        }
        $xml          = (array)$xml['ACCOUNT'];
        $this->feedid = trim($xml['ID']);
        $this->add_log(sprintf("Client Reqid : %d, Feedid : %d, Date : %s", $this->reqid, $this->feedid, $this->date));
    }

    /**
     * Send try again response 
     * @return false
     */
    public function tryAgainXMLMsg($code, $desc) 
    {
        $send_response = "<?xml version='1.0' encoding='UTF-8'?>
            <STATUSREQUEST>
            <REQUEST>
            <REQID>$this->reqid</REQID>
            <CODE>$code</CODE>
            <DESC>$desc</DESC>
            </REQUEST>
            </STATUSREQUEST>";
        return $send_response;
    }

    /**
     * Send reqid to the stats Api 
     * 
     */
    public function callStatsApi() 
    {
        # $param_array will contain default params to stats curl req 
        $param_array['format']    = 'csv';
        $param_array['feedid']    = $this->feedid;
        $param_array['date']      = $this->date;
        $param_array['requestid'] = $this->reqid;
        $status = '';
        $local_dtxnid = '';
        $retry_counter = 1;
        while(true)
        {
            # Check if the stats req status is fetching
            if($this->dtxnid == 'FETCHING' && $retry_counter < 7)
            {
                # Check if Retry counter exceed 6
                if ($retry_counter == 6) {
                    $this->add_log("Internal Error, please try again later");
                    header('Content-Type: application/xml'); # Parse output to raw xml
                    $send_response = $this->tryAgainXMLMsg('001', $this->code_maps['001']);
                    echo $send_response;
                    return false; 
                } # if Retry is greater than 5 send tryAgain
                $params    = http_build_query($param_array);
                $this->url = $this->code_maps['stats_url'] . $params; //set rl
                $response  = $this->curlCall();
                $xml = $this->parseXmlToArray($response);
                # Check if we get correct xml response from stats
                if(isset($xml[0])) 
                {
                    $this->dtxnid = $xml[0];
                    $this->add_log(sprintf("Retrying count : %d, Stats Request URL : %s, Stats URL Response f:  %s", $retry_counter, $this->url, $this->dtxnid));
                } 

                # Check if the stats req status is done 
                if($this->dtxnid == 'DONE') 
                {
                    # Add ack params option in curl req 
                    $param_array['ack'] = 1;
                    $params    = http_build_query($param_array);
                    $this->url = $this->code_maps['stats_url'] . $params; //set rl
                    $this->add_log(sprintf("Stats URL Request dn: '%s'", $this->url));
                    $response  = $this->curlCall(1);
                    return true; 
                }

                # Check if we get incorrect xml response from stats
                if(!isset($xml[0])) 
                {
                    $xml = $this->parseXmlToArray($response);
                    $xml_error = (array)$xml['ERROR'];
                    $xml_code  =  $xml_error['CODE'];
                    $xml_desc  =  $xml_error['DESCRIPTION'];
                    $this->add_log(sprintf("Retrying count : %d, No XML: %s", $retry_counter, $response));
                    header('Content-Type: application/xml'); # Parse output to raw xml
                    $send_response = $this->tryAgainXMLMsg($xml_code, $xml_desc);
                    echo $send_response;

                    # If there is error while parsing - response in xml 
                    return false; 
                }
                sleep(8); # Hold execution of script for 70 secs
                $retry_counter++;
                continue;
            } else {
                $this->add_log("Gone in Else part of fetching Response : ".$this->dtxnid);
                if ($this->dtxnid) 
                {
                    $param_array['dtxnid'] = $this->dtxnid;
                    $local_dtxnid = $this->dtxnid;
                }
                $params    = http_build_query($param_array);
                $this->url = $this->code_maps['stats_url'] . $params; //set url

                $response  = $this->curlCall();
                $this->add_log("Stats URL Request:- ".$this->url);
                $xml       = $this->parseXmlToArray($response);
                if(isset($xml[0])) 
                {
                    $this->dtxnid = $xml[0];
                } else {
                    $xml = $this->parseXmlToArray($response);
                    $xml_error = (array)$xml['ERROR'];
                    $xml_code  =  $xml_error['CODE'];
                    $xml_desc  =  $xml_error['DESCRIPTION'];
                    $this->add_log(sprintf("Error : %s", $response));
                    header('Content-Type: application/xml'); # Parse output to raw xml
                    $send_response = $this->tryAgainXMLMsg($xml_code, $xml_desc);
                    echo $send_response;

                    return false;
                }
                sleep(8); # Hold execution of script for 70 secs
            }
        }
    }

    /**
     * Gets the client Request id from client Api of a field
     * 
     */
    public function readDownloadedCsv() 
    {
        $this->add_log("Starts Reading Csv file");
        # Print response in XML to client
        header('Content-Type: application/xml'); # Parse output to raw xml
        echo "<?xml version='1.0' encoding='UTF-8'?><STATUSREQUEST>";
        $file = fopen($this->dwnfilename,"r");
        $row=1;
        while(! feof($file))
        {
            if($row == 1)
            {
                $entriesq    = fgetcsv($file);
                $row++; 
                continue; 
            }
            $entries = fgetcsv($file);
            if($entries) 
            {
                $req_id     = trim($entries[6]);
                $dlr_status = trim($entries[2]);
                # search for code in array
                $code       = array_search(strtolower(trim(str_replace(' ', '', $dlr_status))), $this->code_maps);
                $send_response = 
                    "<REQUEST>
                    <REQID>$req_id</REQID>
                    <CODE>$code</CODE>
                    <DESC>$dlr_status</DESC>
                    </REQUEST>";

                echo $send_response;
            }
            $row++;
        }
        echo "</STATUSREQUEST>";
        fclose($file);
        $this->add_log("Ends Reading Csv file");
    }

    /**
     * Main program.
     *
     * where the main execution of script starts
     */
    public static function main()
    {
        $cbcd = new CorpBankCustomDlr();
        $cbcd->add_log("-==START==-");
        $cbcd->readClientRequest();
        if($cbcd->callStatsApi() != false) {
            $cbcd->readDownloadedCsv();
        }
        $cbcd->add_log("-==END==-");
    }
}

CorpBankCustomDlr::main();
