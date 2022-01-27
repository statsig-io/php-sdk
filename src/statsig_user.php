<?php

namespace Statsig;

class StatsigUser {
    private $userID;
    private $email;
    private $ip;
    private $userAgent;
    private $country;
    private $locale;
    private $appVersion;
    private $custom;
    private $privateAttributes;
    private $customIDs;

    private $statsigEnvironment;

    function __construct($userID) {
        $this->userID = $userID;
    }

    function setEmail($email) {
        $this->email = $email;
        return $this;
    }

    function setIP($ip) {
        $this->ip = $ip;
        return $this;
    }

    function setUserAgent($user_agent) {
        $this->userAgent = $user_agent;
        return $this;
    }

    function setLocale($locale) {
        $this->locale = $locale;
        return $this;
    }

    function setCountry($country) {
        $this->country = $country;
        return $this;
    }

    function setAppVersion($appVersion) {
        $this->appVersion = $appVersion;
        return $this;
    }

    function setCustom($custom) {
        $this->custom = $custom;
        return $this;
    }

    function setPrivateAttributes($private) {
        $this->privateAttributes = $private;
        return $this;
    }

    function setCustomIDs($custom_ids) {
        $this->customIDs = $custom_ids;
        return $this;
    }

    function setStatsigEnvironment($environment) {
        $this->statsigEnvironment = $environment;
        return $this;
    }

    function toLogDictionary() {
        $dict = $this->toEvaluationDictionary();
        unset($dict["privateAttributes"]);
        return $dict;
    }

    function toEvaluationDictionary() {
        $user = [
            "userID" => $this->userID, // only required field
            "email" => $this->email,
            "ip" => $this->ip,
            "userAgent" => $this->userAgent,
            "country" => $this->country,
            "locale" => $this->locale,
            "appVersion" => $this->appVersion,
            "custom" => $this->custom,
            "privateAttributes" => $this->privateAttributes,
            "customIDs" => $this->customIDs,
            "statsigEnvironment" => $this->statsigEnvironment,
        ];

        return array_filter($user);
    }
}