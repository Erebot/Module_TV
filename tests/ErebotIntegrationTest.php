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

require_once(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'testenv' .
    DIRECTORY_SEPARATOR . 'bootstrap.php'
);

class TestTvRetriever
{
    protected $ID_mappings = array('foo' => 42, 'bar' => 69);

    public static function getInstance()
    {
        $c = __CLASS__;
        $instance = new $c();
        return $instance;
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

class ErebotTestModule_Tv
extends Erebot_Module_TV
{
    public function setTvRetriever($tv)
    {
        $this->_tv = $tv;
    }

    public function setCustomMappings($mappings)
    {
        $this->_customMappings = $mappings;
    }

    public function setDefaultGroup($group)
    {
        $this->_defaultGroup = $group;
    }
}

class   ErebotIntegrationTest
extends ErebotModuleTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->_module = new ErebotTestModule_Tv($this->_connection, NULL);
        $this->_module->setTvRetriever(new TestTvRetriever());
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testMissingDefaultGroup()
    {
        $event = new Erebot_Event_PrivateText(
            $this->_connection, 'test',
            '!tv'
        );
        $this->_module->handleTv($event);

        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals(
            "PRIVMSG test :No channel given and no default.",
            $this->_outputBuffer[0]
        );
    }

    public function testUsingDefaultGroupWithChannelOverride()
    {
        $event = new Erebot_Event_PrivateText(
            $this->_connection, 'test',
            '!tv 23h42 foo'
        );
        $this->_module->handleTv($event);

        $pattern =  "/PRIVMSG test :TV programs for \037.*?\037: ".
                    "\002foo\002: foo \\(17:23 - 17:42\\) - ".
                    "\002bar\002: bar \\(17:23 - 17:42\\)/";
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertRegExp($pattern, $this->_outputBuffer[0]);
    }
}

