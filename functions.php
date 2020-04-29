<?php
/*
PB Project Demo - LINEBOT
:license MPL 2.0
(c) 2020 SuperSonic(https://github.com/supersonictw)
*/

require_once "normalize-url/normalize-url.php";

function error_report($data)
{
    $content = "PB Project Demo - LINEBOT\nError:\n%s";
    error_log($data);
    return sprintf($content, $data);
}

function analytics_connect($data)
{
    global $analytics_host;
    $data_string = json_encode($data);

    $client = curl_init();
    curl_setopt($client, CURLOPT_POST, true);
    curl_setopt($client, CURLOPT_URL, $analytics_host);
    curl_setopt($client, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($client, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $client,
        CURLOPT_HTTPHEADER,
        array(
            "Content-Type: application/json",
            "Content-Length: " . strlen($data_string),
        )
    );
    $result = curl_exec($client);
    curl_close($client);

    return json_decode($result);
}

function analytics($message_text)
{
    preg_match_all(
        "#(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))#",
        strtolower($message_text),
        $match
    );
    if (count($match) < 2 or empty($match[0])) {
        return false;
    }

    $results = array();
    $ext_msg = "";

    foreach ($match[0] as $origin_url) {
        $url = normalizeUrl($origin_url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $result = analytics_connect([
                "version" => 1,
                "url" => $url,
            ]);
            if (is_null($result)) {
                $msg = "Couldn't handshake to PBP_A";
                return error_report($msg);
            }
            switch ($result->status) {
                case 200:
                    array_push($results, $result->trust_score);
                    break;

                case 403:
                case 404:
                    array_push($results, 100);
                    break;

                default:
                    array_push($results, 200);
                    $msg = sprintf("PBP_A Return An Unknown StatusCode: %s", $result->status);
                    $ext_msg .= "\n\n[Debug]\n" . error_report($msg);
            }
        }
    }

    if (in_array(100, $results)) {
        $ext_msg .= "\n\n[Notification]\nPBP_A couldn't visit some URL(s).";
    }

    if (min($results) < 0.5) {
        return "[Warning]
                    The URL(s) was marked as blacklist by PBP Network." . $ext_msg;
    } elseif (min($results) == 0.5) {
        return "[Notification]
                    The URL(s) has/have been scanned and reported as warning target.
                    Check it is safe or not before click in." . $ext_msg;
    } elseif (min($results) < 1) {
        return "[Unknown]
                    The URL(s) was noticed by PBP Network, but we don't known what happened." . $ext_msg;
    } elseif (min($results) == 1) {
        return "[Safe]
                    The URL(s) was passed scans." . $ext_msg;
    }
}
