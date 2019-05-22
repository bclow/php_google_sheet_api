#!/usr/bin/env php 
<?php

// 1. https://developers.google.com/sheets/api/quickstart/php#step_3_set_up_the_sample
// 2. https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/append
//
// 3. if you want to download google sheet as tsv, we need to use drive api : 
//    https://developers.google.com/drive/api/v3/manage-downloads



// Load the Google API PHP Client Library.
require_once __DIR__ . '/../../../composer/vendor/autoload.php';


// https://developers.google.com/identity/protocols/OAuth2ServiceAccount#delegatingauthority
// https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/append
// https://stackoverflow.com/questions/46256676/google-sheets-api-v4-method-spreadsheets-values-append 
//
function downloadSheet($sheetID) {
    $client         = getClientHeadless();
    $service        = new Google_Service_Drive($client);
    $response       = $service->files->export($sheetID, "text/tab-separated-values", [ "alt" => "media" ]);
    $content = $response->getBody()->getContents();
    
    echo $content;
}


function sendData($spreadsheetId, $sheet, $value) {
    //  // The ID of the spreadsheet to update.
    //  Example
    //  $spreadsheetId  = "xxxxxxxxxxx";
    //  
    //  $value          = [
    //      ["Door", "$15", "2", "3/15/2016"],
    //      ["Engine", "$100", "1", "3/20/2016"],
    //  ];

    $client         = getClientHeadless();
    $service        = new Google_Service_Sheets($client);
    $requestBody    = new Google_Service_Sheets_ValueRange();
    $requestBody->setValues($value);

    $response       = $service->spreadsheets_values->append($spreadsheetId, $sheet, $requestBody, [ "valueInputOption" => "USER_ENTERED"]);

    echo var_export($response, true), "\n";
}



function getClientHeadless() {
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.__DIR__.'/xxxxxxxxx.json');
    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    return $client;
}


function removeComment($line) {
    $pos            = strpos($line, "#");
    if($pos === false) {
        return $line;
    }

    return substr($line, 0, $pos);
}

function appendSheet($spreadSheetID, $sheet, $infile) {
    global $argv;
    if( !($spreadSheetID && $sheet && $infile) ) {
        trigger_error("{$argv[0]} <spreadSheetID> <sheet> <infile>");
        return false;
    }
    trigger_error("running as {$argv[0]} $spreadSheetID $sheet $infile");

    $fh             = fopen($infile, "r");
    if(!$fh) {
        trigger_error("unable open infile ($infile) for read");
        return false;
    }

    $nLine          = 0;
    $data           = [];
    while(true) {
        ++$nLine;
        $line       = rtrim(fgets($fh));
        if($line === false) {
            break;
        }

        $line       = removeComment($line);
        if(strlen($line)<1) {
            break;
        }

        $fields     = explode("\t", $line);
        $data[]     = $fields;
        if(count($data)>100) {
            sendData($spreadSheetID, $sheet, $data);
            $data   = [];
        }
    }

    if(count($data)>0) {
        sendData($spreadSheetID, $sheet, $data);
    }


    fclose($fh);
    return true;
}

function main() {
    global $argv;
    $spreadSheetID  = (isset($argv[1]) && $argv[1]) ? $argv[1] : "xxxxxxxxxx";
    $sheet          = $argv[2] ?? "";
    $infile         = $argv[3] ?? "";


    appendSheet($spreadSheetID, $sheet, $infile);

}

main();
