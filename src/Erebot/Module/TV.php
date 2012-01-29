<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * \brief
 *      A module that retrieves information on TV programs
 *      from the Internet.
 */
class   Erebot_Module_TV
extends Erebot_Module_Base
{
    /// Fetcher instance to use to retrieve the information.
    protected $_tv;

    /// Maps group names to a list of the TV channels they contain.
    protected $_customMappings = array();

    /// Default group of TV channels to use when retrieving information.
    protected $_defaultGroup = NULL;

    /**
     * A parser that turns dates and times expressed
     * in English into valid Epoch timestamps.
     */
    protected $_dateParser = 'strtotime';


    /// Pattern used to read a time specification in 12 hours format.
    const TIME_12H_FORMAT = '/^(0?[0-9]|1[0-2])[:h\.]?([0-5][0-9])?([ap]m)$/i';

    /// Pattern used to read a time specification in 24 hours format.
    const TIME_24H_FORMAT = '/^([0-1]?[0-9]|2[0-3])[:h\.]?([0-5][0-9])?$/i';


    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function _reload($flags)
    {
        if ($flags & self::RELOAD_MEMBERS) {
            $class = $this->parseString(
                'fetcher_class',
                'Erebot_Module_TV_Fetcher'
            );
            /// @TODO: add extra checks (return codes, exceptions, ...)
            $this->_tv  = new $class(
                $this->parseInt('timeout', 8),
                $this->parseInt('conn_timeout', 3)
            );

            $config         = $this->_connection->getConfig($this->_channel);
            $moduleConfig   = $config->getModule(get_class($this));
            $groups = array_filter(
                $moduleConfig->getParamsNames(),
                array('self', '_isAGroup')
            );
            $this->_customMappings = array();

            foreach ($groups as $param) {
                $group = substr($param, 6);
                $chans = $this->parseString($param);
                $this->_customMappings[$group] =
                    array_map('trim', explode(',', $chans));
            }

            try {
                $this->_defaultGroup = $this->parseString('default_group');
                if (!isset($this->_customMappings[$this->_defaultGroup]))
                    $this->_defaultGroup = NULL;
            }
            catch (Erebot_NotFoundException $e) {
                $this->_defaultGroup = NULL;
            }
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                $this->_connection->removeEventHandler($this->_handler);
                $registry->freeTriggers($this->_trigger, $matchAny);
            }

            $this->registerHelpMethod(array($this, 'getHelp'));
            $trigger        = $this->parseString('trigger', 'tv');
            $this->_trigger = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_trigger === NULL) {
                $fmt = $this->getFormatter(FALSE);
                throw new Exception(
                    $fmt->_('Could not register TV trigger')
                );
            }

            $this->_handler = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleTV')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf(
                        'Erebot_Interface_Event_Base_TextMessage'
                    ),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($trigger, TRUE),
                        new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                    )
                )
            );
            $this->_connection->addEventHandler($this->_handler);
        }
    }

    /**
     * Identifies groups of TV channels
     * from the configuration file.
     *
     * \param $candidate
     *      Name of a parameter that is a potential
     *      group of TV channels.
     *
     * \retval bool
     *      TRUE if the given parameter represents
     *      a group of TV channels, FALSE otherwise.
     */
    static protected function _isAGroup($candidate)
    {
        return !strncasecmp($candidate, "group_", 6);
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Some help request.
     *
     * \param array $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        Erebot_Interface_Event_Base_TextMessage $event,
                                                $words
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $fmt        = $this->getFormatter($chan);
        $trigger    = $this->parseString('trigger', 'tv');

        $moduleName = strtolower(get_class());
        $nbArgs     = count($words);

        if ($nbArgs == 1 && $words[0] == $moduleName) {
            $msg = $fmt->_(
                'Provides the <b><var name="trigger"/></b> command which '.
                'retrieves information about TV schedules off the internet.',
                array('trigger' => $trigger)
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }

        if (count($nbArgs) < 2)
            return FALSE;

        if ($words[1] == $trigger) {
            $msg = $fmt->_(
                "<b>Usage:</b> !<var name='trigger'/> [<u>time</u>] ".
                "[<u>channels</u>]. Returns TV schedules for the given ".
                "channels at the given time. [<u>time</u>] can be expressed ".
                "using either 12h or 24h notation. [<u>channels</u>] can be ".
                "a single channel name, a list of channels (separated by ".
                "commas) or one of the pre-defined groups of channels.",
                array('trigger' => $trigger)
            );
            $this->sendMessage($target, $msg);

            $msg = $fmt->_(
                "If none is given, the default group (<b><var ".
                "name='default'/></b>) is used. The following ".
                "groups are available: <for from='groups' key='group' ".
                "item='dummy'><b><var name='group'/></b></for>.",
                array(
                    'default' => $this->_defaultGroup,
                    'groups' => $this->_customMappings,
                )
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }
    }

    /**
     * Handles a request for information on TV programs.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      A request for TV programs that may include time
     *      and TV channels constraints.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleTV(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $time       = $event->getText()->getTokens(1, 1);
        $getdate    = getdate(call_user_func($this->_dateParser, 'now'));
        $tomorrow   = getdate(
            call_user_func($this->_dateParser, 'midnight +1 day')
        );
        $fmt        = $this->getFormatter($chan);

        do {
            $result     = preg_match(self::TIME_12H_FORMAT, $time, $matches);
            if ($result) {
                $pm         = !strcasecmp($matches[3], 'pm');
                $hours      = ((int) $matches[1]) + ($pm ? 12 : 0);
                $minutes    = isset($matches[2]) ? (int) $matches[2] : 0;
                break;
            }

            $result     = preg_match(self::TIME_24H_FORMAT, $time, $matches);
            if ($result) {
                $hours      = (int) $matches[1];
                $minutes    = isset($matches[2]) ? (int) $matches[2] : 0;
                break;
            }

            $hours      = $getdate['hours'];
            $minutes    = $getdate['minutes'];
        } while (0);

        if ($hours < $getdate['hours'] ||
            ($hours == $getdate['hours'] && $minutes < $getdate['minutes'])) {
            $getdate['mday']    = $tomorrow['mday'];
            $getdate['mon']     = $tomorrow['mon'];
            $getdate['year']    = $tomorrow['year'];
        }

        $timestamp  = mktime(
            $hours, $minutes, 0,
            $getdate['mon'], $getdate['mday'], $getdate['year']
        );
        $channels   = strtolower($event->getText()->getTokens($result ? 2 : 1));

        if (rtrim($channels) == '') {
            if ($this->_defaultGroup)
                $channels   = $this->_customMappings[$this->_defaultGroup];
            else {
                $msg = $fmt->_('No channel given and no default.');
                return $this->sendMessage($target, $msg);
            }
        }
        else if (isset($this->_customMappings[$channels]))
            $channels   = $this->_customMappings[$channels];
        else
            $channels   = explode(',', $channels);

        $ids    = array_filter(
            array_map(array($this->_tv, 'getIdFromChannel'), $channels)
        );

        try {
            $infos  = $this->_tv->getChannelsData($timestamp, $ids);
        }
        catch (HTTP_Request2_Exception $e) {
            $msg = $fmt->_(
                'An error occurred while retrieving '.
                'the information (<var name="error"/>)',
                array('error' => $e->getMessage())
            );
            return $this->sendMessage($target, $msg);
        }

        $programs = array();
        foreach ($infos as $channel => $data) {
            $start      = substr($data['Date_Debut'], -8, -3);
            $end        = substr($data['Date_Fin'], -8, -3);
            $programs[$channel]   = sprintf(
                '%s (%s - %s)',
                $data['Titre'], $start, $end
            );
        }

        if (!count($programs))
            $this->sendMessage(
                $target,
                $fmt->_('No such channel(s)')
            );
        else {
            $cls = $this->getFactory('!Styling_DateTime');
            $msg = $fmt->_(
                'TV programs for <u><var name="date"/></u>: '.
                '<for from="programs" key="channel" item="timetable" '.
                'separator=" - "><b><var name="channel"/></b>: '.
                '<var name="timetable"/></for>',
                array(
                    'date' => new $cls(
                        $timestamp,
                        IntlDateFormatter::LONG,
                        IntlDateFormatter::MEDIUM
                    ),
                    'programs' => $programs,
                )
            );
            $this->sendMessage($target, $msg);
        }
    }
}

