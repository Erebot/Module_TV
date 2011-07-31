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

class Erebot_Module_TV_Fetcher
{
    const TARGET_URL    = 'http://television.telerama.fr/tele/grille.php';

    protected $_timeout;
    protected $_connTimeout;
    protected $_mapping;

    public function __construct($timeout, $connTimeout)
    {
        $this->_timeout = (int) $timeout;
        $this->_connTimeout = (int) $connTimeout;
        $this->updateIds();
    }

    public function updateIds()
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
            return;

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
    }

    public function getSupportedChannels()
    {
        return array_keys($this->_mapping);
    }

    public function getIdFromChannel($channel)
    {
        $channel = strtolower(trim($channel));
        if (!isset($this->_mapping[$channel]))
            return NULL;
        return $this->_mapping[$channel];
    }

    public function getChannelsData($timestamp, $ids)
    {
        if (!is_array($ids))
            $ids = array($ids);

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

