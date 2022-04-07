<?php

namespace tp\TouchPointWP\Utilities;

use tp\TouchPointWP\api;
use tp\TouchPointWP\ExtraValueHandler;
use tp\TouchPointWP\Person;
use tp\TouchPointWP\TouchPointWP;
use tp\TouchPointWP\TouchPointWP_Settings;

if ( ! defined('ABSPATH')) {
    exit(1);
}

require_once __DIR__ . '\..\api.php';

/**
 * Cleanup - Used for data cleanliness tasks.
 *
 * @package tp\TouchPointWP
 */
abstract class Cleanup implements api
{
    public const CRON_HOOK = TouchPointWP::HOOK_PREFIX . "cleanup_cron_hook";
    private const CACHE_TTL = 24 * 7; // How long things should live before they're cleaned up.  Hours.

    /**
     * Called by the cron task. (and also by ::api() )
     *
     * @see api()
     */
    public static function cronCleanup(): void
    {
        try {
            self::cleanMemberTypes();
        } catch (\Exception $e) {
            error_log("Cleanup encountered error: " . $e->getMessage());
        }

        try {
            self::cleanupPersonEVs();
        } catch (\Exception $e) {
            error_log("Cleanup encountered error: " . $e->getMessage());
        }

        echo "Success";
    }

    /**
     * Handle API requests - mostly, just a way to forcibly trigger the cleanup process
     *
     * @param array $uri The request URI already parsed by parse_url()
     *
     * @return bool False if endpoint is not found.  Should print the result.
     */
    public static function api(array $uri): bool
    {
        self::cronCleanup();
        exit;
    }

    /**
     * Clean up Member Types that have been cached for a while.
     *
     * @return ?bool True if cleaning was successful, False if cleaning failed, null if cleaning was not needed.
     */
    protected static function cleanMemberTypes(): ?bool
    {
        $mtObj = TouchPointWP_Settings::instance()->get('meta_memberTypes');
        $needsUpdate = false;

        if ($mtObj === false) {
            $needsUpdate = true;
            $mtObj = (object)[];
        } else {
            $mtObj = (array)json_decode($mtObj);
            foreach ($mtObj as $key => $val) {
                if (strtotime($val->_updated) < time() - 3600 * self::CACHE_TTL) {
                    $needsUpdate = true;
                    unset($mtObj[$key]);
                }
            }
            $mtObj = (object)$mtObj;
        }

        if ($needsUpdate) {
            return TouchPointWP_Settings::instance()->set('meta_memberTypes', json_encode($mtObj));
        }
        return null;
    }

    /**
     * Clean up Person Extra Values that are no longer intended for import.
     *
     * @return int|false Number of rows if successful (including 0), false on failure.
     */
    protected static function cleanupPersonEVs()
    {
        global $wpdb;
        $conditions = ["uMeta.meta_key LIKE '" . Person::META_PEOPLE_EV_PREFIX . "%'"];
        foreach (TouchPointWP::instance()->getPersonEvFields(TouchPointWP::instance()->settings->people_ev_custom) as $field) {
            $name = Person::META_PEOPLE_EV_PREFIX . ExtraValueHandler::standardizeExtraValueName($field->field);
            $conditions[] = "uMeta.meta_key <> '$name'";
        }
        $conditions = implode(" AND ", $conditions);

        return $wpdb->query("DELETE FROM `$wpdb->usermeta` AS uMeta WHERE $conditions");
    }
}