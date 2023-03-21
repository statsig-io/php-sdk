<?php

namespace Statsig;

use Statsig\Exceptions\StatsigUserException;

class StatsigUser
{
    private ?string $user_id;
    private ?string $email = null;
    private ?string $ip = null;
    private ?string $user_agent = null;
    private ?string $country = null;
    private ?string $locale = null;
    private ?string $app_version = null;
    private ?array $custom = null;
    private ?array $private_attributes = null;
    private ?array $custom_ids = null;
    private ?array $statsig_environment = null;

    static function withUserID(string $user_id): StatsigUser
    {
        return new StatsigUser($user_id);
    }

    static function withCustomIDs(array $custom_ids): StatsigUser
    {
        return new StatsigUser(null, $custom_ids);
    }

    function setUserID(?string $user_id): StatsigUser
    {
        $this->user_id = $user_id;
        return $this;
    }

    function getUserID(): ?string
    {
        return $this->user_id;
    }

    function setEmail(?string $email): StatsigUser
    {
        $this->email = $email;
        return $this;
    }

    function setIP(?string $ip): StatsigUser
    {
        $this->ip = $ip;
        return $this;
    }

    function setUserAgent(?string $user_agent): StatsigUser
    {
        $this->user_agent = $user_agent;
        return $this;
    }

    function setLocale(?string $locale): StatsigUser
    {
        $this->locale = $locale;
        return $this;
    }

    function setCountry(?string $country): StatsigUser
    {
        $this->country = $country;
        return $this;
    }

    function setAppVersion(?string $app_version): StatsigUser
    {
        $this->app_version = $app_version;
        return $this;
    }

    function setCustom(?array $custom): StatsigUser
    {
        $this->custom = $custom;
        return $this;
    }

    function setPrivateAttributes(?array $private): StatsigUser
    {
        $this->private_attributes = $private;
        return $this;
    }

    function setCustomIDs(?array $custom_ids): StatsigUser
    {
        $this->custom_ids = $custom_ids;
        return $this;
    }

    function getCustomIDs(): ?array
    {
        return $this->custom_ids;
    }

    function setStatsigEnvironment(?array $environment): StatsigUser
    {
        $this->statsig_environment = $environment;
        return $this;
    }

    function toLogDictionary(): array
    {
        $dict = $this->toEvaluationDictionary();
        unset($dict["privateAttributes"]);
        return $dict;
    }

    function toEvaluationDictionary(): array
    {
        $user = [
            "userID" => $this->user_id,
            "email" => $this->email,
            "ip" => $this->ip,
            "userAgent" => $this->user_agent,
            "country" => $this->country,
            "locale" => $this->locale,
            "appVersion" => $this->app_version,
            "custom" => $this->custom,
            "privateAttributes" => $this->private_attributes,
            "customIDs" => $this->custom_ids,
            "statsigEnvironment" => $this->statsig_environment,
        ];

        return array_filter($user, function ($v) {
            return $v !== null;
        });
    }

    /**
     * @throws StatsigUserException - User must have a userID or customID for the server SDK to work.
     */
    function assertUserIsIdentifiable()
    {
        $is_user_empty = $this->user_id === null || trim($this->user_id) === '';
        $is_customer_ids_empty = $this->custom_ids === null || count($this->custom_ids) === 0;
        if ($is_user_empty && $is_customer_ids_empty) {
            throw new StatsigUserException("User must have a userID or customID for the server SDK to work. See https://docs.statsig.com/messages/serverRequiredUserID/ for more details.");
        }
    }

    private function __construct(?string $user_id = null, ?array $custom_ids = null)
    {
        $this->user_id = $user_id;
        $this->custom_ids = $custom_ids;

        $this->assertUserIsIdentifiable();
    }
}
