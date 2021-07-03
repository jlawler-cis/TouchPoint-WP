<?php
namespace tp\TouchPointWP;

if ( ! defined('ABSPATH')) {
    exit(1);
}

use DateTimeImmutable;
use Exception;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Class Involvement - Fundamental object meant to correspond to an Involvement in TouchPoint
 *
 * @package tp\TouchPointWP
 */
abstract class Involvement implements api
{
    use jsInstantiation;

    public string $name;
    public int $invId;
    public int $post_id;
    public string $post_excerpt;
    protected WP_Post $post;

    public const INVOLVEMENT_META_KEY = TouchPointWP::SETTINGS_PREFIX . "invId";
    public const POST_TYPE = TouchPointWP::HOOK_PREFIX . "involvement"; // would be abstract if that was a thing.

    public object $attributes;
    protected array $divisions;

    /**
     * Involvement constructor.
     *
     * @param $object WP_Post|object an object representing the involvement's post.
     *                  Must have post_id and inv id attributes.
     */
    protected function __construct(object $object)
    {
        $this->attributes = (object)[];

        if (gettype($object) === "object" && get_class($object) == WP_Post::class) {
            /** @var $object WP_Post */
            // WP_Post Object
            $this->post = $object;
            $this->name = $object->post_title;
            $this->invId = intval($object->{self::INVOLVEMENT_META_KEY});
            $this->post_id = $object->ID;

        } elseif (gettype($object) === "object") {
            // Sql Object, probably.

            if (!property_exists($object, 'post_id'))
                _doing_it_wrong(
                    __FUNCTION__,
                    esc_html(__('Creating an Involvement object from an object without a post_id is not yet supported.')),
                    esc_attr(TouchPointWP::VERSION)
                );

            /** @noinspection PhpFieldAssignmentTypeMismatchInspection  The type is correct. */
            $this->post = get_post($object, "OBJECT");

            foreach ($object as $property => $value) {
                if (property_exists(self::class, $property)) {
                    $this->$property = $value;
                } // TODO does this deal with properties in inheritors?

                // TODO add an else for nonstandard/optional metadata fields
            }
        }

        $this->registerConstruction();
    }

    /**
     * Whether the involvement is currently joinable.
     *
     * @return bool|string  True if joinable.  Or, a string with why it can't be joined otherwise.
     */
    public function acceptingNewMembers()
    {
        if (get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "groupFull", true) === '1') {
            return __("Currently Full", TouchPointWP::TEXT_DOMAIN);
        }

        if (get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "groupClosed", true) === '1') {
            return __("Currently Closed", TouchPointWP::TEXT_DOMAIN);
        }

        $now = current_datetime();
        $regStart = get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "regStart", true);
        if ($regStart !== false && $regStart !== '' && $regStart > $now) {
            return __("Registration Not Open Yet", TouchPointWP::TEXT_DOMAIN);
        }

        $regEnd = get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "regEnd", true);
        if ($regEnd !== false && $regEnd !== '' && $regEnd < $now) {
            return __("Registration Closed", TouchPointWP::TEXT_DOMAIN);
        }

        if (intval(get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "regTypeId", true)) === 0) {
            return false; // no online registration available
        }

        return true;
    }

    /**
     * Whether the involvement should link to a registration form, rather than directly joining the org.
     *
     * @return bool
     */
    public function useRegistrationForm(): bool
    {
        return (get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "hasRegQuestions", true) === '1' ||
                intval(get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "regTypeId", true)) !== 1);
    }

    /**
     * @param $exclude
     *
     * @return string[]
     */
    public function getDivisionsStrings(array $exclude): array
    {
        if (!isset($this->divisions)) {
            if (count($exclude) > 1) {
                $mq = ['relation' => "AND"];
            } else {
                $mq = [];
            }

            foreach ($exclude as $e) {
                $mq[] = [
                    'key' => TouchPointWP::SETTINGS_PREFIX . 'divId',
                    'value' => substr($e, 3),
                    'compare' => 'NOT LIKE'
                ];
            }

            $this->divisions = wp_get_post_terms($this->post_id, TouchPointWP::TAX_DIV, ['meta_query' => $mq]);
        }

        $out = [];
        foreach ($this->divisions as $d) {
            $out[] = $d->name;
        }
        return $out;
    }

    /**
     * Query TouchPoint and update Involvements in WordPress.  This function should generally call updateInvolvementPosts
     *
     * @param bool $verbose Whether to print debugging info.
     *
     * @return false|int False on failure, or the number of groups that were updated or deleted.
     */
    public static abstract function updateFromTouchPoint(bool $verbose = false);

    /**
     * Register scripts and styles to be used on display pages.
     */
    public static function registerScriptsAndStyles(): void
    {
        wp_register_script(
            TouchPointWP::SHORTCODE_PREFIX . "knockout",
            "https://ajax.aspnetcdn.com/ajax/knockout/knockout-3.5.0.js",
            [],
            '3.5.0',
            true
        );
    }

    /**
     * Update posts that are based on an involvement.
     *
     * @param string     $postType
     * @param string|int $divs
     * @param array      $options
     * @param bool       $verbose
     *
     * @return false|int  False on failure.  Otherwise, the number of updates.
     */
    protected static function updateInvolvementPosts(string $postType, $divs, $options = [], $verbose = false) {
        $siteTz = wp_timezone();

        set_time_limit(60);

        try {
            $response = TouchPointWP::instance()->apiGet(
                "InvsForDivs",
                array_merge($options, ['divs' => $divs])
            );
        } catch (TouchPointWP_Exception $e) {
            return false;
        }

        if ($response instanceof WP_Error) {
            return false;
        }

        $invData = json_decode($response['body'])->data->invs ?? []; // null coalesce for case where there is no data.

        $postsToKeep = [];

        foreach ($invData as $inv) {
            set_time_limit(15);

            if ($verbose) {
                var_dump($inv);
            }

            $q = new WP_Query(
                [
                    'post_type'  => $postType,
                    'meta_key'   => TouchPointWP::SETTINGS_PREFIX . "invId",
                    'meta_value' => $inv->involvementId
                ]
            );
            $post = $q->get_posts();
            if (count($post) > 0) { // post exists already.
                $post = $post[0];
            } else {
                $post = wp_insert_post(
                    [ // create new
                        'post_type'  => $postType,
                        'post_name'  => $inv->name,
                        'meta_input' => [
                            TouchPointWP::SETTINGS_PREFIX . "invId" => $inv->involvementId
                        ]
                    ]
                );
                $post = get_post($post);
            }

            if ($post instanceof WP_Error) {
                error_log($post->get_error_message());
                continue;
            }

            /** @var $post WP_Post */

            $post->post_content = strip_tags($inv->description, "<p><br><a><em><b><i><u><hr>");

            if ($post->post_title != $inv->name) // only update if there's a change.  Otherwise, urls increment.
            {
                $post->post_title = $inv->name;
            }

            $post->post_status = 'publish';

            wp_update_post($post);

            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "locationName", $inv->location);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "memberCount", $inv->memberCount);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "genderId", $inv->genderId);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "groupFull", ! ! $inv->groupFull);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "groupClosed", ! ! $inv->closed);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "hasRegQuestions", ! ! $inv->hasRegQuestions);
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "regTypeId", intval($inv->regTypeId));

            // Registration start
            if ($inv->regStart !== null) {
                try {
                    $inv->regStart = new DateTimeImmutable($inv->regStart, $siteTz);
                } catch  (Exception $e) {
                    $inv->regStart = null;
                }
            }
            if ($inv->regStart === null) {
                delete_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "regStart");
            } else {
                update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "regStart", $inv->regStart);
            }

            // Registration end
            if ($inv->regEnd !== null) {
                try {
                    $inv->regEnd = new DateTimeImmutable($inv->regEnd, $siteTz);
                } catch  (Exception $e) {
                    $inv->regEnd = null;
                }
            }
            if ($inv->regEnd === null) {
                delete_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "regEnd");
            } else {
                update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "regEnd", $inv->regEnd);
            }


            ////////////////////
            //// SCHEDULING ////
            ////////////////////

            // Establish data model for figuring everything else out.
            if (!is_array($inv->occurrences)) {
                $inv->occurrences = [];
            }

            // TODO deal with frequency on Schedule dates.  That is, occurrences may include non-meeting days.
            // These occurrences will have a type of "S" and should be adjusted forward to the next compliant date.
            // TODO consider removing meeting date/times and only using schedules.

            $upcomingDateTimes = [];
            foreach ($inv->occurrences as $o) {

                if ($o === null || !is_object($o))
                    continue;

                try {
                    $upcomingDateTimes[] = new DateTimeImmutable($o->dt, $siteTz);
                } catch (Exception $e) {
                }
            }

            // Sort.  Hypothetically, this is already done by the api.
            sort($upcomingDateTimes); // The next meeting datetime is now in position 0.

            // Save next meeting metadata
            if (count($upcomingDateTimes) > 0) {
                update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "nextMeeting", $upcomingDateTimes[0]);
                if ($verbose) {
                    echo "<p>Next occurrence: " . $upcomingDateTimes[0]->format('c') . "</p>";
                }
            } else {
                // No upcoming dates.  Remove meta key.
                delete_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "nextMeeting");
                if ($verbose) {
                    echo "<p>No upcoming occurrences</p>";
                }
            }

            // Determine schedule characteristics for stringifying.
            $uniqueTimes = [];
            $days = [];
            $timeFormat = get_option('time_format');
            foreach ($upcomingDateTimes as $dt) {
                /** @var $dt DateTimeImmutable */

                $weekday = "d" . $dt->format('w');

                // days
                if (!isset($days[$weekday])) {
                    $days[$weekday] = [];
                }
                $days[$weekday][] = $dt;

                // times
                $timeStr = $dt->format($timeFormat);
                if (!in_array($timeStr, $uniqueTimes)) {
                    $uniqueTimes[] = $timeStr;
                }
                unset($timeStr, $weekday);
            }

            if (count($uniqueTimes) > 1) {
                // multiple different times of day
                $dayStr = [];
                foreach ($days as $dk => $dta) {
                    $timeStr = [];
                    foreach ($dta as $dt) {
                        /** @var $dt DateTimeImmutable */
                        $timeStr[] = $dt->format($timeFormat);
                    }
                    $timeStr = __('at', TouchPointWP::TEXT_DOMAIN) . " " . TouchPointWP::stringArrayToList($timeStr);

                    if (count($days) > 1) {
                        $dayStr[] = TouchPointWP::getDayOfWeekShortForNumber(intval($dk[1])) . ' ' . $timeStr;
                    } else {
                        $dayStr[] = TouchPointWP::getPluralDayOfWeekNameForNumber(intval($dk[1])) . ' ' . $timeStr;
                    }
                }
                $dayStr = TouchPointWP::stringArrayToList($dayStr);

            } elseif (count($uniqueTimes) == 1) {
                // one time of day.
                if (count($days) > 1) {
                    // more than one day per week
                    $dayStr = [];
                    foreach ($days as $k => $d) {
                        $dayStr[] = TouchPointWP::getDayOfWeekShortForNumber(intval($k[1]));
                    }
                    $dayStr = TouchPointWP::stringArrayToList($dayStr);
                } else {
                    // one day of the week
                    $k = array_key_first($days);
                    $dayStr = TouchPointWP::getPluralDayOfWeekNameForNumber(intval($k[1]));
                }
                $dayStr .= ' ' . __('at', TouchPointWP::TEXT_DOMAIN) . " " . $uniqueTimes[0];
            } else {
                $dayStr = null;
            }
            if ($verbose) {
                echo "<p>Meeting schedule: $dayStr</p>";
            }
            update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "meetingSchedule", $dayStr);

            // Day of week attributes
            $dayTerms = [];
            foreach ($days as $k => $d) {
                $dayTerms[] = TouchPointWP::getDayOfWeekShortForNumber(intval($k[1]));
            }
            wp_set_post_terms($post->ID, $dayTerms, TouchPointWP::TAX_WEEKDAY, false);

            // TODO morning/evening/afternoon term and filter

            ////////////////////////
            //// END SCHEDULING ////
            ////////////////////////

            // Handle leaders  TODO make leaders WP Users
            if (array_key_exists('leadMemTypes', $options) && property_exists($inv, "leaders")) {
                $nameString = Person::arrangeNamesForPeople($inv->leaders);
                update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "leaders", $nameString);
            }

            // Handle locations for involvement types that have hosts
            if (array_key_exists('hostMemTypes', $options)) {

                // Handle locations TODO handle cases other than hosted at home  (Also applies to ResCode)
                if (property_exists($inv, "hostGeo") && $inv->hostGeo !== null) {
                    update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "geo_lat", $inv->hostGeo->lat);
                    update_post_meta($post->ID, TouchPointWP::SETTINGS_PREFIX . "geo_lng", $inv->hostGeo->lng);
                }

                // Handle Resident Code
                if (property_exists($inv, "hostGeo") && $inv->hostGeo !== null && $inv->hostGeo->resCodeName !== null) {
                    wp_set_post_terms($post->ID, [$inv->hostGeo->resCodeName], TouchPointWP::TAX_RESCODE, false);
                } else {
                    wp_set_post_terms($post->ID, [], TouchPointWP::TAX_RESCODE, false);
                }
            }

            // Handle Marital Status
            $maritalTax = [];
            if ($inv->marital_denom > 4) { // only include involvements with at least 4 people with known marital statuses.
                $marriedProportion = (float)$inv->marital_married / $inv->marital_denom;
                if ($marriedProportion > 0.7) {
                    $maritalTax[] = "mostly_married";
                } elseif ($marriedProportion < 0.3) {
                    $maritalTax[] = "mostly_single";
                }
            }
            wp_set_post_terms($post->ID, $maritalTax, TouchPointWP::TAX_INV_MARITAL, false);

            // Handle Age Groups
            if ($inv->age_groups === null) {
                wp_set_post_terms($post->ID, [], TouchPointWP::TAX_AGEGROUP, false);
            } else {
                wp_set_post_terms($post->ID, $inv->age_groups, TouchPointWP::TAX_AGEGROUP, false);
            }

            // Handle divisions
            $divs = [];
            if ($inv->divs !== null) {
                foreach ($inv->divs as $d) {
                    $tid = TouchPointWP::getDivisionTermIdByDivId($d);
                    if ( ! ! $tid) {
                        $divs[] = $tid;
                    }
                }
            }
            wp_set_post_terms($post->ID, $divs, TouchPointWP::TAX_DIV, false);

            if ($verbose) {
                echo "<p>Division Terms:</p>";
                var_dump($divs);
            }

            $postsToKeep[] = $post->ID;

            if ($verbose) {
                echo "<hr />";
            }
        }

        // Delete posts that are no longer current
        $q = new WP_Query(
            [
                'post_type' => $postType,
                'nopaging'  => true,
            ]
        );
        $removals = 0;
        foreach ($q->get_posts() as $post) {
            if ( ! in_array($post->ID, $postsToKeep)) {
                set_time_limit(10);
                wp_delete_post($post->ID, true);
                $removals++;
            }
        }

        return $removals + count($invData);
    }

    /**
     * @param $theDate
     * @param $format
     * @param $post
     *
     * @return string
     *
     * @noinspection PhpUnusedParameterInspection Not used by choice, but need to comply with the api.
     */
    public static function filterPublishDate($theDate, $format, $post = null): string // TODO combine with SG in Involvement
    {
        if ($post == null)
            $post = get_the_ID();

        if (get_post_type($post) === static::POST_TYPE) {
            if (!is_numeric($post))
                $post = $post->ID;
            $theDate = get_post_meta($post, TouchPointWP::SETTINGS_PREFIX . "meetingSchedule", true);
        }
        return $theDate;
    }

    /**
     * Get notable attributes, such as gender restrictions, as strings.
     *
     * @return string[]
     */
    public function notableAttributes(): array
    {
        $ret = [];
        if ($this->attributes->genderId != 0) {
            switch($this->attributes->genderId) {
                case 1:
                    $ret[] = __('Men Only', TouchPointWP::TEXT_DOMAIN);
                    break;
                case 2:
                    $ret[] = __('Women Only', TouchPointWP::TEXT_DOMAIN);
                    break;
            }
        }

        $joinable = $this->acceptingNewMembers();
        if ($joinable !== true) {
            $ret[] = $joinable;
        }

        return $ret;
    }

    /**
     * Returns the html with buttons for actions the user can perform.
     *
     * @return string
     */
    public function getActionButtons(): string
    {
        TouchPointWP::requireScript('swal2-defer');
        TouchPointWP::requireScript('base-defer');
        $this->enqueueForJsInstantiation();

        $text = __("Contact Leaders", TouchPointWP::TEXT_DOMAIN);
        $ret = "<button type=\"button\" data-tp-action=\"contact\">{$text}</button>  ";

        $joinable = $this->acceptingNewMembers();
        if ($joinable === true) {
            if ($this->useRegistrationForm()) {
                $text = __('Register', TouchPointWP::TEXT_DOMAIN);
                switch (get_post_meta($this->post_id, TouchPointWP::SETTINGS_PREFIX . "regTypeId", true)) {
                    case 1:  // Join Involvement (skip other options because this option is common)
                        break;
                    case 6:  // Choose Volunteer Times
                        $text = __('Volunteer', TouchPointWP::TEXT_DOMAIN);
                        break;
                    case 8:  // Online Giving (legacy)
                    case 9:  // Online Pledge (legacy)
                    case 14: // Manage Recurring Giving (legacy)
                        $text = __('Give', TouchPointWP::TEXT_DOMAIN);
                        break;
                    case 15: // Manage Subscriptions
                        $text = __('Manage Subscriptions', TouchPointWP::TEXT_DOMAIN);
                        break;
                    case 18: // Record Family Attendance
                        $text = __('Record Attendance', TouchPointWP::TEXT_DOMAIN);
                        break;
                }
                $link = TouchPointWP::instance()->host() . "/OnlineReg/" . $this->invId;
                $ret  .= "<a class=\"btn button\" href=\"{$link}\">{$text}</a>  ";
            } else {
                $text = __('Join', TouchPointWP::TEXT_DOMAIN);
                $ret  .= "<button type=\"button\" data-tp-action=\"join\">{$text}</button>  ";
            }
        }
        return $ret;
    }

    public static function getJsInstantiationString(): string
    {
        $queue = static::getQueueForJsInstantiation();

        $className = basename(str_replace('\\', '/', static::CLASS));

        if (count($queue) < 1) {
            return "\t// No {$className}s to instantiate.\n";
        }

        $listStr = json_encode($queue);

        return "\ttpvm.addEventListener('{$className}_class_loaded', function() {
        TP_$className.fromArray($listStr);\n\t});\n";
    }

    /**
     * @param WP_Post $post
     *
     * @return Involvement
     */
    public abstract static function fromPost(WP_Post $post): Involvement;

    /**
     * Handles the API call to join an involvement through a 'join' button.
     */
    private static function ajaxInvJoin(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Only POST requests are allowed.']);
            exit;
        }

        $inputData = file_get_contents('php://input');
        if ($inputData[0] !== '{') {
            echo json_encode(['error' => 'Invalid data provided.']);
            exit;
        }

        $data = TouchPointWP::instance()->apiPost('inv_join', json_decode($inputData));

        if ($data instanceof WP_Error) {
            echo json_encode(['error' => $data->get_error_message()]);
            exit;
        }

        echo json_encode(['success' => $data->success]);
        exit;
    }


    /**
     * Handles the API call to send a message through a contact form.
     */
    private static function ajaxInvContact(): void
    {
        header('Content-Type: application/json');
        TouchPointWP::noCacheHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Only POST requests are allowed.']);
            exit;
        }

        $inputData = file_get_contents('php://input');
        if ($inputData[0] !== '{') {
            echo json_encode(['error' => 'Invalid data provided.']);
            exit;
        }

        $data = TouchPointWP::instance()->apiPost('inv_contact', json_decode($inputData));

        if ($data instanceof WP_Error) {
            echo json_encode(['error' => $data->get_error_message()]);
            exit;
        }

        echo json_encode(['success' => $data->success]);
        exit;
    }

    /**
     * Handle API requests
     *
     * @param array $uri The request URI already parsed by parse_url()
     *
     * @return bool False if endpoint is not found.  Should print the result.
     */
    public static function api(array $uri): bool
    {
        switch (strtolower($uri['path'][2])) {
            case "join":
                self::ajaxInvJoin();
                exit;

            case "contact":
                self::ajaxInvContact();
                exit;
        }

        return false;
    }
}