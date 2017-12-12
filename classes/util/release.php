<?php
/**
 * User: Eduardo Kraus
 * Date: 15/07/17
 * Time: 13:52
 */

namespace local_kopere_dashboard\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Class release
 *
 * @package local_kopere_dashboard\util
 */
class release
{
    /**
     * @return string
     */
    public static function version ()
    {
        global $CFG;
        $releases = explode ( '.', $CFG->release );

        return intval ( $releases[ 0 ] ) . "." . intval ( $releases[ 1 ] );
    }
}