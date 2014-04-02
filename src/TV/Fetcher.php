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

namespace Erebot\Module\TV;

/**
 * \brief
 *      Fetcher for telerama's TV programs.
 *
 * This class is mostly useful to retrieve TV programs
 * for french TV channels, even though a few other
 * (international) channels are also supported.
 */
class Fetcher
{
    /// URL to query to retrieve the information.
    const TARGET_URL = 'http://television.telerama.fr/tele/grille.php';

    /// Timeout for the whole request (in seconds).
    protected $timeout;

    /// Maps TV channels to their assigned ID (this is specific to telerama).
    protected $mapping;

    /**
     * Creates a new instance of the fetcher.
     *
     * \param int $timeout
     *      How many seconds the whole request has
     *      in order to complete before it is considered
     *      a failure.
     */
    public function __construct($timeout)
    {
        $this->timeout = (int) $timeout;
        $this->updateIds();
    }

    /**
     * Updates the internal mapping.
     *
     * \retval bool
     *      Returns TRUE on success, FALSE on failure.
     *
     * \post
     *      After this method completes successfully,
     *      each TV channel recognized by Telerama is
     *      associated with its ID.
     */
    protected function updateIds()
    {
        $response = \Requests::request(
            self::TARGET_URL,
            array(),
            array('grille', 'telerama'),
            array(
                'follow_redirects'  => true,
                'timeout'           => $this->timeout,
            )
        );

        $source = $response->body;
        $source =   '<html><head><meta http-equiv="Content-Type" '.
                    'content="text/html; charset=utf-8"/></head>'.
                    '<body>'.$source.'</body></html>';

        $internal   = libxml_use_internal_errors(true);
        $domdoc     = new \DOMDocument();
        $domdoc->validateOnParse        = false;
        $domdoc->preserveWhitespace     = false;
        $domdoc->strictErrorChecking    = false;
        $domdoc->substituteEntities     = false;
        $domdoc->resolveExternals       = false;
        $domdoc->recover                = true;
        $domdoc->loadHTML($source);

        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $select     = $domdoc->getElementsByTagName('select')->item(0);
        if ($select === null) {
            return false;
        }

        $this->mapping = array();
        $select->normalize();

        foreach ($select->childNodes as $child) {
            if ($child->nodeType != XML_ELEMENT_NODE || $child->nodeName != 'option') {
                continue;
            }

            $valueNode  = $child->attributes->getNamedItem('value');
            if ($valueNode === null) {
                continue;
            }

            $value      = $valueNode->nodeValue;
            if (!ctype_digit($value)) {
                continue;
            }

            $value      = (int) $value;
            $channel    = strtolower($child->nodeValue);
            $this->mapping[$channel] = $value;
            $this->mapping[str_replace(' ', '', $channel)] = $value;
        }
        return true;
    }

    /**
     * Returns a list with the names of all
     * supported TV channels.
     *
     * \retval list
     *      The names of all TV channels that may
     *      be queried through this fetcher.
     */
    public function getSupportedChannels()
    {
        return array_keys($this->mapping);
    }

    /**
     * Returns the internal ID associated
     * with some channel.
     *
     * \param string $channel
     *      Some TV channel whose internal ID
     *      we're interested in.
     *
     * \retval mixed
     *      Internal ID for that channel (as an integer),
     *      or NULL if the given channel is not supported.
     */
    public function getIdFromChannel($channel)
    {
        $channel = strtolower(trim($channel));
        if (!isset($this->mapping[$channel])) {
            return null;
        }
        return $this->mapping[$channel];
    }

    /**
     * Returns information on the programs
     * for some TV channels.
     *
     * \param int $timestamp
     *      Return information on TV programs
     *      that will be airing on this precise
     *      point in time.
     *
     * \param list $ids
     *      A list with the IDs of the TV channels
     *      to query.
     *
     * \retval array
     *      Information about the TV programs airing
     *      at the given time on the given channels.
     */
    public function getChannelsData($timestamp, $ids)
    {
        $response = \Requests::request(
            self::TARGET_URL,
            array(),
            array(
                'xajax'     => 'chargerProgramme',
                'xajaxargs' => array(
                    date('Y-m-d H:i:s', $timestamp),
                    implode(',', $ids),
                ),
                'xajaxr'    => time(),
            ),
            \Requests::POST,
            array(
                'follow_redirects'  => true,
                'timeout'           => $this->timeout,
            )
        );

        $sxml = simplexml_load_string($response->body);
        $source = '';
        foreach ($sxml->cmd as $cmd) {
            if (!isset($cmd['n']) || (string) $cmd['n'] != 'as') {
                continue;
            }

            $source = (string) $cmd;
            break;
        }

        $internal   = libxml_use_internal_errors(true);
        $domdoc     = new \DOMDocument();
        $domdoc->validateOnParse        = false;
        $domdoc->preserveWhitespace     = false;
        $domdoc->strictErrorChecking    = false;
        $domdoc->substituteEntities     = false;
        $domdoc->resolveExternals       = false;
        $domdoc->recover                = true;
        $source =   '<html><head><meta http-equiv="Content-Type" '.
                    'content="text/html; charset=utf-8"/></head>'.
                    '<body>'.$source.'</body></html>';
        $domdoc->loadHTML($source);

        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $divs   = $domdoc->getElementsByTagName('div');
        $infos  = array();
        foreach ($divs as $div) {
            $idNode = $div->attributes->getNamedItem('id');
            if ($idNode === null || strncmp($idNode->nodeValue, 'data_', 5)) {
                continue;
            }

            $json = trim($div->nodeValue);
            $data = json_decode($json, true);
            if ($data === false) {
                continue;
            }

            $data = array_map('trim', $data);

            $channel = $data['Chaine_Nom'];
            if (strtotime($data['Date_Debut']) <= $timestamp &&
                $timestamp <= strtotime($data['Date_Fin'])) {
                $infos[$channel] = $data;
            }
        }
        return $infos;
    }
}
