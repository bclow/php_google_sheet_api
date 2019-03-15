#!/usr/bin/php -q
<?php

//ref: 
// 1. Analytics Reporting API v4- quick start for service account : https://developers.google.com/analytics/devguides/reporting/core/v4/quickstart/service-php
// 2. cohorts report only available on api v4: https://stackoverflow.com/questions/36477520/ga-how-to-access-cohort-analysis-via-analytics-api
// 3. official cohorts report document: https://developers.google.com/analytics/devguides/reporting/core/v4/advanced
// 4. dimension/metrics explorer: https://developers.google.com/analytics/devguides/reporting/core/dimsmets 
// 5. ga report (Android): https://analytics.google.com/analytics/web/#report/app-visitors-cohort/a42050352w100674649p104573341/%3FcohortTab-cohortOption.cohortBaseDate%3D20180328%26cohortTab-cohortOption.hasLoaded%3Dtrue%26cohortTab-cohortOption.granularity%3DMONTHLY%26cohortTab-cohortOption.dateRange%3D3%26cohortTab-cohortOption.selectedMetric%3Danalytics.cohortRetentionRate%26cohortTab-cohortOption.selectedDimension%3Danalytics.firstVisitDate/
// 6. ga report (IOS): https://analytics.google.com/analytics/web/#report/app-visitors-cohort/a42050352w100670538p104573838/%3FcohortTab-cohortOption.cohortBaseDate%3D20180328%26cohortTab-cohortOption.hasLoaded%3Dtrue%26cohortTab-cohortOption.granularity%3DMONTHLY%26cohortTab-cohortOption.dateRange%3D3%26cohortTab-cohortOption.selectedMetric%3Danalytics.cohortRetentionRate%26cohortTab-cohortOption.selectedDimension%3Danalytics.firstVisitDate/




// Load the Google API PHP Client Library.
require_once __DIR__ . '/../../composer/vendor/autoload.php';

$viewid         = [ "Android"  => 'XXXXXXXXXXXXXXX', 
                   "ios"      => 'XXXXXXXXXXXXX',
                  ];
$nMonths        = 12; // cohort report max. 12 months.


print("running on ". date('Y-m-d H:i:s'). "\n\n");
$analytics      = initializeAnalytics();
foreach($viewid as $prop => $id) {
    print("{$prop}:");
    $response       = getReport($analytics, $id, getLastNMonth($nMonths));
    printResults($response);
    print("\n");
}

function initializeAnalytics()
{

  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = __DIR__ . '/privatekey/XXXXXXXXXXXXXXXXXXXXXXXX.json';

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName("Feebee cohort analysis Analytics Reporting");
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  $analytics = new Google_Service_AnalyticsReporting($client);

  return $analytics;
}

function getLastNMonth($n) {
    // $n = 3 : 0, -1, -2, -3
    $results    = [];

    // $i=1 : always start from previous month
    for($i=1; $i<=$n; ++$i) {
        $mstr       = date('Y-m', strtotime("-{$i} months", strtotime(date('Y-m-01'))));
        $results[]  = mkDateRange($mstr);
    }
    return $results;
}

function mkDateRange($yyyy_mm) {
    $startDate  = "{$yyyy_mm}-01";
    $endDate    = date("Y-m-t", strtotime($startDate));
    return [ $startDate, $endDate ];
}

function mkDateRanges($yyyy_mm_array) {
    $dates          = [];

    foreach($yyyy_mm_array as $yyyy_mm) {
        $dates[]    = mkDateRange($yyyy_mm);
    }
    return $dates;


}
function getMetric($name, $alias=null) {
    $metric   = new Google_Service_AnalyticsReporting_Metric();
    $metric->setExpression($name);
    
    return $metric;
}

function getDimension($name) {
    $dimension  = new Google_Service_AnalyticsReporting_Dimension();
    $dimension->setName($name);
    return $dimension;
}

function getCohort($dateStart, $dateEnd) {
    $cohort     = new Google_Service_AnalyticsReporting_Cohort();
    $cohort->setName("{$dateStart} - {$dateEnd}");
    $cohort->setType("FIRST_VISIT_DATE");

    $dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange->setStartDate($dateStart);
    $dateRange->setEndDate($dateEnd);
    $cohort->setDateRange($dateRange);
    return $cohort;
}

function getCohortGroup($dateArray) {
    $cg         = new Google_Service_AnalyticsReporting_CohortGroup();
    $cohorts    = [];
    foreach($dateArray as $dateRange) {
        $sdate      = $dateRange[0];
        $edate      = $dateRange[1];
        $cohorts[]  = getCohort($sdate, $edate);
    }
    $cg->setCohorts($cohorts);
    return $cg;
}

/**
 * Queries the Analytics Reporting API V4.
 *
 * @param service An authorized Analytics Reporting API V4 service object.
 * @return The Analytics Reporting API V4 response.
 */
function getReport($analytics, $viewid, $cohortDates) {

  // Replace with your view ID, for example XXXX.
  $VIEW_ID = $viewid;

  // Create the ReportRequest object.
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setMetrics( [ getMetric("ga:cohortRetentionRate"), getMetric("ga:cohortActiveUsers") ]);
  $request->setCohortGroup(getCohortGroup($cohortDates));
  $request->setDimensions( [ getDimension("ga:cohort"), getDimension("ga:cohortNthMonth") ]);

//  var_dump($request);

  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
  $body->setReportRequests( array( $request) );
  return $analytics->reports->batchGet( $body );
}


/**
 * Parses and prints the Analytics Reporting API V4 response.
 *
 * @param An Analytics Reporting API V4 response.
 */
function printResults($reports) {
  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
    $report = $reports[ $reportIndex ];
    $header = $report->getColumnHeader();
    $dimensionHeaders = $header->getDimensions();
    $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
    $rows = $report->getData()->getRows();

    for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
      $row = $rows[ $rowIndex ];
      $dimensions = $row->getDimensions();
      $metrics = $row->getMetrics();
      if($dimensions[1] === "0000") {
          print("\n");
          print($dimensions[0]."\t");
      }
//      for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
//        print("[dimension]-" . $dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
//      }

      for ($j = 0; $j < count($metrics); $j++) {
        $values = $metrics[$j]->getValues();
        for ($k = 0; $k < count($values); $k++) {
          $entry = $metricHeaders[$k];
//          print("[metric]-".$entry->getName() . ": " . $values[$k] . "\n");
//        var_dump($entry->getType());
          if($entry->getType() == "PERCENT") {
              printf("%.02f%%\t", round($values[$k],2));
          } else {
              print($values[$k]."\t");
          }
        }
      }
    }
    print("\n");
  }
}





?>
