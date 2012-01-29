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
 *      Fetcher for telerama's TV programs.
 *
 * This class is mostly useful to retrieve TV programs
 * for french TV channels, even though a few other
 * (international) channels are also supported.
 */
class Erebot_Module_TV_Fetcher
{
    /// URL to query to retrieve the information.
    const TARGET_URL = 'http://television.telerama.fr/tele/grille.php';

    /// Timeout for the whole request (in seconds).
    protected $_timeout;

    /// Timeout for the connection (in seconds).
    protected $_connTimeout;

    /// Maps TV channels to their assigned ID (this is specific to telerama).
    protected $_mapping;

    /**
     * Creates a new instance of the fetcher.
     *
     * \param int $timeout
     *      How many seconds the whole request has
     *      in order to complete before it is considered
     *      a failure.
     *
     * \param int $connTimeout
     *      How many seconds it may take up for the
     *      connection to telerama's server to be successful.
     */
    public function __construct($timeout, $connTimeout)
    {
        $this->_timeout = (int) $timeout;
        $this->_connTimeout = (int) $connTimeout;
        $this->_updateIds();
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
    protected function _updateIds()
    {
        $request = new HTTP_Request2(
            self::TARGET_URL,
            HTTP_Request2::METHOD_GET,
            array(
                'follow_redirects'  => TRUE,
                'ssl_verify_peer'   => FALSE,
                'ssl_verify_host'   => FALSE,
                'timeout'           => $this->_timeout,
                'connect_timeout'   => $this->_connTimeout,
            )
        );
        $url = $request->getUrl();
        $url->setQueryVariable('grille', 'telerama');

        $response = $request->send();
        $source = $response->getBody();
        $source =   '<html><head><meta http-equiv="Content-Type" '.
                    'content="text/html; charset=utf-8"/></head>'.
                    '<body>'.$source.'</body></html>';

        $internal   = libxml_use_internal_errors(TRUE);
        $domdoc     = new DOMDocument();
        $domdoc->validateOnParse        = FALSE;
        $domdoc->preserveWhitespace     = FALSE;
        $domdoc->strictErrorChecking    = FALSE;
        $domdoc->substituteEntities     = FALSE;
        $domdoc->resolveExternals       = FALSE;
        $domdoc->recover                = TRUE;
        $domdoc->loadHTML($source);

        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $select     = $domdoc->getElementsByTagName('select')->item(0);
        if ($select === NULL)
            return FALSE;

        $this->_mapping = array();
        $select->normalize();

        foreach ($select->childNodes as $child) {
            if ($child->nodeType != XML_ELEMENT_NODE ||
                $child->nodeName != 'option')
                continue;

            $valueNode  = $child->attributes->getNamedItem('value');
            if ($valueNode === NULL)
                continue;

            $value      = $valueNode->nodeValue;
            if (!ctype_digit($value))
                continue;

            $value      = (int) $value;
            $channel    = strtolower($child->nodeValue);
            $this->_mapping[$channel] = $value;
            $this->_mapping[str_replace(' ', '', $channel)] = $value;
        }
        return TRUE;
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
        return array_keys($this->_mapping);
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
        if (!isset($this->_mapping[$channel]))
            return NULL;
        return $this->_mapping[$channel];
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
        $request = new HTTP_Request2(
            self::TARGET_URL,
            HTTP_Request2::METHOD_POST,
            array(
                'follow_redirects'  => TRUE,
                'ssl_verify_peer'   => FALSE,
                'ssl_verify_host'   => FALSE,
                'timeout'           => $this->_timeout,
                'connect_timeout'   => $this->_connTimeout,
            )
        );
        $request->addPostParameter(
            array(
                'xajax'     => 'chargerProgramme',
                'xajaxargs' => array(
                    date('Y-m-d H:i:s', $timestamp),
                    implode(',', $ids),
                ),
                'xajaxr'    => time(),
            )
        );

        $response = $request->send();
        $sxml = simplexml_load_string($response->getBody());
        $source = '';
        foreach ($sxml->cmd as $cmd) {
            if (!isset($cmd['n']) || (string) $cmd['n'] != 'as')
                continue;

            $source = (string) $cmd;
            break;
        }

        $internal = libxml_use_internal_errors(TRUE);

        $domdoc = new DOMDocument();
        $domdoc->validateOnParse        = FALSE;
        $domdoc->preserveWhitespace     = FALSE;
        $domdoc->strictErrorChecking    = FALSE;
        $domdoc->substituteEntities     = FALSE;
        $domdoc->resolveExternals       = FALSE;
        $domdoc->recover                = TRUE;
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
            if ($idNode === NULL ||
                strncmp($idNode->nodeValue, 'data_', 5))
                continue;

            $json = trim($div->nodeValue);
            $data = json_decode($json, TRUE);
            if ($data === FALSE)
                continue;

            $data = array_map('trim', $data);

            $channel = $data['Chaine_Nom'];
            if (strtotime($data['Date_Debut']) <= $timestamp &&
                $timestamp <= strtotime($data['Date_Fin']))
                $infos[$channel] = $data;
        }
        return $infos;
    }
}

