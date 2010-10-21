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

if (!defined('__DIR__')) {
  class __FILE_CLASS__ {
    function  __toString() {
      $X = debug_backtrace();
      return dirname($X[1]['file']);
    }
  }
  define('__DIR__', new __FILE_CLASS__);
} 

include_once(__DIR__.'/testenv/connectionStub.php');
include_once(__DIR__.'/testenv/configStub.php');

include_once(__DIR__.'/../TV.php');

class TestTvRetriever
{
    protected $ID_mappings = array('foo' => 42, 'bar' => 69);
    static protected $instance;

    public static function getInstance()
    {
        if (self::$instance === NULL) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    public function getSupportedChannels()
    {
        return array_keys($this->ID_mappings);
    }

    public function getIdFromChannel($channel)
    {
        $channel = strtolower(trim($channel));
        if (!isset($this->ID_mappings[$channel]))
            return NULL;
        return $this->ID_mappings[$channel];
    }

    public function getChannelsData($timestamp, $ids)
    {
        if (!is_array($ids))
            $ids = array($ids);

        return array(
            'foo' => array(
                'Date_Debut' => "2010-09-02 17:23:00",
                'Date_Fin' => "2010-09-02 17:42:00",
                'Titre' => 'foo',
            ),
            'bar' => array(
                'Date_Debut' => "2010-09-03 17:23:00",
                'Date_Fin' => "2010-09-03 17:42:00",
                'Titre' => 'bar',
            ),
        );
    }
}

interface iErebotEventMessageText
{
}

interface iErebotEventTextPrivate
{
}

interface iErebotEventPrivate
{
}

class PrivateMessage
implements  iErebotEventMessageText,
            iErebotEventTextPrivate,
            iErebotEventPrivate
{
    public function __construct($text)
    {
        $this->text = $text;
    }

    public function getSource()
    {
        return 'test';
    }
}

class FakeConnection
{
    
}

class   ErebotIntegrationTest
extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->module = new ErebotModule_TV();
        $this->modules->connection = new FakeConnection();
        $this->module->reload(
            $this->module->RELOAD_MEMBERS |
            $this->module->RELOAD_INIT
        );
    }

    public function testMissingDefaultGroup()
    {
        $event = new PrivateMessage();
        $this->module->handleTv($event);
        $output = $connection->getSendQueue();
        $this->assertEquals(1, count($output));
        $this->assertEquals(
            "PRIVMSG test :No channel given and no default.",
            $output[0]
        );
    }

    public function testUsingDefaultGroupWithChannelOverride()
    {
        $event = new PrivateMessage('!tv 23h42 foo');
        $this->module->handleTv($event);
        $output = $connection->getSendQueue();

        $pattern =  "/PRIVMSG test :TV programs for \037.*?\037: ".
                    "\002foo\002: foo \\(17:23 - 17:42\\) - ".
                    "\002bar\002: bar \\(17:23 - 17:42\\)";
        $this->assertEquals(1, count($output));
        $this->assertRegExp($pattern, $output[0]);
    }
}

?>
