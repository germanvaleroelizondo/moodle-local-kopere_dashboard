<?php
/**
 * User: Eduardo Kraus
 * Date: 14/06/17
 * Time: 05:21
 */

namespace local_kopere_dashboard\output\events;


use local_kopere_dashboard\vo\kopere_dashboard_events;

class SendEvents
{
    /** @var kopere_dashboard_events */
    private $kopere_dashboard_events;

    /** @var \core\event\base */
    private $event;

    private $subject;
    private $message;

    /**
     * @return kopere_dashboard_events
     */
    public function getKopereDashboardEvents ()
    {
        return $this->kopere_dashboard_events;
    }

    /**
     * @param kopere_dashboard_events $kopere_dashboard_events
     */
    public function setKopereDashboardEvents ( $kopere_dashboard_events )
    {
        $this->kopere_dashboard_events = $kopere_dashboard_events;
    }

    /**
     * @return \core\event\base
     */
    public function getEvent ()
    {
        return $this->event;
    }

    /**
     * @param \core\event\base $event
     */
    public function setEvent ( $event )
    {
        $this->event = $event;
    }


    public function send ()
    {
        global $COURSE, $CFG, $DB;

        $this->loadTemplate ();

        echo '<pre>';
        print_r ( $this->event );
        print_r ( $this->kopere_dashboard_events );
        echo '</pre>';

        $this->subject = $this->replaceDate ( $this->subject );
        $this->message = $this->replaceDate ( $this->message );

        $COURSE->link = "<a href=\"{$CFG->wwwroot}\" target=\"_blank\">{$CFG->wwwroot}</a>";

        $this->subject = $this->replaceCourse ( $this->subject, $COURSE, 'moodle' );
        $this->message = $this->replaceCourse ( $this->message, $COURSE, 'moodle' );

        $courseid = 0;
        if ( $this->event->objecttable == 'course' ) {
            $courseid = $this->event->objectid;
            $course   = $DB->get_record ( 'course', array( 'id' => $courseid ) );
            if ( $course ) {
                $course->link = "<a href=\"{$CFG->wwwroot}/course/view.php?id={$courseid}\" target=\"_blank\">{$CFG->wwwroot}/course/view.php?id={$courseid}</a>";

                $this->subject = $this->replaceCourse ( $this->subject, $course, 'course' );
                $this->message = $this->replaceCourse ( $this->message, $course, 'course' );
            }
        }


        // de
        $userFrom = null;
        switch ( $this->kopere_dashboard_events->userfrom ) {
            case 'admin':
                $userFrom = get_admin ();
                break;
        }
        if ( $userFrom == null ) {
            $this->mailError ( '$userFrom NOT FOUND!' );

            return;
        }

        $userFrom->fullname = fullname ( $userFrom );

        $this->subject = $this->replaceCourse ( $this->subject, $userFrom, 'from' );
        $this->message = $this->replaceCourse ( $this->message, $userFrom, 'from' );


        $usersTo = array();
        switch ( $this->kopere_dashboard_events->userto ) {
            case 'admin':
                $usersTo = array( get_admin () );
                break;
            case 'admins':
                $usersTo = get_admins ();
                break;
            case 'teachers':
                if ( $courseid ) {
                    $sql
                             = "SELECT u.* 
                                  FROM {course} c 
                                  JOIN {context} cx ON c.id = cx.instanceid 
                                  JOIN {role_assignments} ra ON cx.id = ra.contextid 
                                                            AND ra.roleid = '3' 
                                  JOIN {user} u ON ra.userid = u.id 
                                 WHERE cx.contextlevel = :contextlevel
                                   AND c.id            = :courseid ";
                    $usersTo = $DB->get_records_sql ( $sql,
                        array( 'contextlevel' => CONTEXT_COURSE,
                               'courseid'     => $courseid
                        ) );
                }
                break;
            case 'student':
                $usersTo = $DB->get_records ( 'user', array( 'id' => $this->event->userid ) );
                break;
        }


        $sendSubject = $this->subject;
        $sendMessage = $this->message;

        foreach ( $usersTo as $userTo ) {
            $userTo->fullname = fullname ( $userTo );

            $sendSubject = $this->replaceCourse ( $this->subject, $userTo, 'to' );
            $sendMessage = $this->replaceCourse ( $this->message, $userTo, 'to' );
        }

        echo "<h1>$sendSubject</h1>";
        echo $sendMessage;
    }

    private function loadTemplate ()
    {
        global $CFG;
        $template        = "{$CFG->dirroot}/local/kopere_dashboard/assets/mail/" . get_config ( 'local_kopere_dashboard', 'notificacao-template' );
        $templateContent = file_get_contents ( $template );

        $this->subject = $this->kopere_dashboard_events->subject;
        $this->message = str_replace ( '{[message]}', $this->kopere_dashboard_events->message, $templateContent );
    }


    /**
     * @param string $text
     *
     * @return string
     */
    private function replaceDate ( $text )
    {
        if ( strpos ( $text, '{[date' ) === false )
            return $text;

        preg_match_all ( "/\{\[date\.(\w+)\]\}/", $text, $textKeys );

        //echo '<pre>';
        //print_r ( $textKeys );
        //echo '</pre>';

        $itens = array(
            'year'   => 'Y',
            'month'  => 'm',
            'day'    => 'd',
            'hour'   => 'H',
            'minute' => 'i',
            'second' => 's'
        );

        foreach ( $textKeys[ 1 ] as $key => $textKey ) {

            if ( isset( $itens[ $textKey ] ) ) {
                $data      = date ( $itens[ $textKey ] );
                $strSearch = $textKeys[ 0 ][ $key ];
                $text      = str_replace ( $strSearch, $data, $text );
            }
        }

        return $text;
    }


    /**
     * @param string $text
     *
     * @return string
     */
    private function replaceCourse ( $text, $course, $keyName )
    {
        if ( strpos ( $text, "{[{$keyName}" ) === false )
            return $text;

        preg_match_all ( "/\{\[{$keyName}\.(\w+)\]\}/", $text, $textKeys );

        //echo '<pre>';
        //print_r ( $textKeys );
        //echo '</pre>';

        foreach ( $textKeys[ 1 ] as $key => $textKey ) {

            if ( isset( $course->$textKey ) ) {
                $data      = $course->$textKey;
                $strSearch = $textKeys[ 0 ][ $key ];
                $text      = str_replace ( $strSearch, $data, $text );
            }
        }

        return $text;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function replaceUser ( $text, $user, $keyName )
    {
        if ( strpos ( $text, "{[{$keyName}" ) === false )
            return $text;

        preg_match_all ( "/\{\[{$keyName}\.(\w+)\]\}/", $text, $textKeys );

        //echo '<pre>';
        //print_r ( $textKeys );
        //echo '</pre>';

        foreach ( $textKeys[ 1 ] as $key => $textKey ) {

            if ( isset( $user->$textKey ) ) {
                $data      = $user->$textKey;
                $strSearch = $textKeys[ 0 ][ $key ];
                $text      = str_replace ( $strSearch, $data, $text );
            }
        }

        return $text;
    }

    private static function mailError ( $message )
    {
        //
    }
}

/*
{[moodle.fullname]}
{[moodle.shortname]}
Copyright © {[date.year]} {[moodle.fullname]}
{[manager]}
*/
/*
Olá {[to.fullname]},

Você foi cadastrado com sucesso no {[course.fullname]}. Agora, você já pode fazer o login na área do aluno para começar estudar quando e onde quiser.

É com imensa satisfação que o {[moodle.fullname]} lhe dá as boas-vindas.

Acesse {[course.link]}, e bons estudos.

Dúvidas estamos a disposição.

Cordialmente,
Equipe de Suporte
 */

/*
Olá {[to.fullname]},

Uma conta foi criado para você no site {[moodle.fullname]}.

Agora, convido você para fazer o login na área do aluno com os seguintes dados:

Site: {[moodle.link]}
Login: {[to.email]}
Senha: {[to.password]}

Dúvidas estamos a disposição.

Cordialmente,
Equipe de Suporte
*/