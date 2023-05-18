<?php

require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

if(!isset($_POST['name']) || !isset($_POST['num']) || !isset($_POST['keyword']) || !isset($_POST['center'])){
    header("Location: login.html");  // Redirect to the login page
    exit();  // Stop further execution
}

if (isset($_SESSION['expiry_time']) && time() > $_SESSION['expiry_time']) {
    // Session has expired, perform a redirect
    session_destroy();  // Clear the session data
    header("Location: login.html");  // Redirect to the login page
    exit();  // Stop further execution
}

// Update the session's expiry time
if(!isset($_SESSION['expiry_time']))
    $_SESSION['expiry_time'] = time() + (60 * 10);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Start a new session
    session_start();
}
$_SESSION['name'] = $_POST['name'];
$_SESSION['num'] = $_POST['num'];
$_SESSION['keyword'] = $_POST['keyword'];
$_SESSION['center'] = $_POST['center'];
// $_SESSION['date'] = $_POST['date'];

// var_dump($_SESSION);

$client = new Client([
    // Base URI is used with relative requests
    'base_uri' => 'https://onlinebusiness.icbc.com/deas-api/v1/',
    // You can set any number of default request options.
    'timeout'  => 2.0,
]);
if (!isset($_SESSION['token'])){
    $_SESSION['token'] = login($client);
}
$response = search($client, $_SESSION['token']);

if ($response->getStatusCode() != 200) {
    $_SESSION['token'] = login($client);
    $response = search($client, $_SESSION['token']);
} 

$results = json_decode($response->getBody()->getContents());

$resultStr = '';

foreach($results as $result) {
    $newDate = new DateTime($result->appointmentDt->date);
    // if ($newDate < $dateObj){
        // lock($client, $_SESSION['token'], $result);
        $resultStr .= $result->appointmentDt->date . ', ' . $result->appointmentDt->dayOfWeek . ': ' . $result->startTm . ' - ' . $result->endTm . '<br/>';
        // break;
    // }
        
       
}

if($resultStr === '')
    echo 'No new earlier dates.';
else
    echo $resultStr;



function login($client) {
    // login and get token
    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => '*/*',
        'Connection' => 'keep-alive',
        'DNT' => '1',
        'User-Agent' => '"Chromium";v="112", "Google Chrome";v="112", "Not:A-Brand";v="99"'
    ];

    $body = [
        'drvrLastName' => $_SESSION['name'],
        'licenceNumber' => $_SESSION['num'],
        'keyword' => $_SESSION['keyword']
    ];

    try {
        $response = $client->put('webLogin/webLogin', [
            'headers' => $headers,
            'json' => $body,
        ]);
        // echo $response->getStatusCode();
        $token = $response->getHeader('Authorization')[0];
        $_SESSION['date'] = json_decode($response->getBody()->getContents())->eligibleExams[0]->eed->date;
        return $token;
    } catch (RequestException $e) {
        echo $e->getMessage();
    }
}

function search($client, $token){
    // search for dates
    $headers = [
        'Authorization' => $token,
        'Content-Type' => 'application/json',
        'Accept' => '*/*',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'DNT' => '1',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36'    
    ];

    $body = [
        'aPosID' => $_SESSION['center'],
        'examType' => '7-R-1',
        'examDate' => $_SESSION['date'],
        'ignoreReserveTime' => false,
        'prfDaysOfWeek' => '[0,1,2,3,4,5,6]',
        'prfPartsOfDay' => '[0,1]',
        'lastName' => $_SESSION['name'],
        'licenseNumber' => $_SESSION['num']
    ];

    try {
        $response = $client->post('web/getAvailableAppointments', [
            'headers' => $headers,
            'json' => $body,
        ]);

        return $response;
    } catch (RequestException $e) {
        echo $e->getMessage();
    }
}

function lock($client, $token, $timeslot){
    // search for dates
    $headers = [
        'Authorization' => $token,
        'Content-Type' => 'application/json',
        'Accept' => '*/*',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'DNT' => '1',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36'    
    ];

    // $body = [
    //     'aPosID' => 274,
    //     'examType' => '7-R-1',
    //     'examDate' => '2023-07-29',
    //     'ignoreReserveTime' => false,
    //     'prfDaysOfWeek' => '[0,1,2,3,4,5,6]',
    //     'prfPartsOfDay' => '[0,1]',
    //     'lastName' => 'CHUNG',
    //     'licenseNumber' => 9669639
    // ];

    $body = json_encode($timeslot);



    try {
        $response = $client->put('web/lock', [
            'headers' => $headers,
            'json' => $body,
        ]);

        return $response;
    } catch (RequestException $e) {
        echo $e->getMessage();
    }
}