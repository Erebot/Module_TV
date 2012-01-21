Configuration
=============

..  _`configuration options`:

Options
-------

This module provides several configuration options.

..  table:: Options for |project|

    +---------------+-----------+---------------+-------------------------------+
    | Name          | Type      | Default       | Description                   |
    |               |           | value         |                               |
    +===============+===========+===============+===============================+
    | fetcher_class | string    | "|fetcher|"   | The class to use to retrieve  |
    |               |           |               | TV schedules. The default is  |
    |               |           |               | fine unless you have specific |
    |               |           |               | needs for something else.     |
    |               |           |               | This class should implement   |
    |               |           |               | the |fetcherIface|_           |
    |               |           |               | interface.                    |
    +---------------+-----------+---------------+-------------------------------+
    | |groups|      | string    | n/a           | A list of comma-separated TV  |
    |               |           |               | channel names, that form a    |
    |               |           |               | common group.                 |
    |               |           |               | The "*name*" part of the      |
    |               |           |               | parameter is used as the name |
    |               |           |               | of the group. This option may |
    |               |           |               | be used several times (with   |
    |               |           |               | varying "*name*" parts) to    |
    |               |           |               | create additional groups.     |
    |               |           |               | This parameter is optional.   |
    +---------------+-----------+---------------+-------------------------------+
    | default_group | string    | n/a           | If no TV channel has been     |
    |               |           |               | given to the bot when         |
    |               |           |               | requesting TV schedules, it   |
    |               |           |               | will retrieve schedules for   |
    |               |           |               | channels in this group        |
    |               |           |               | instead. This parameter is    |
    |               |           |               | optional.                     |
    +---------------+-----------+---------------+-------------------------------+
    | trigger       | string    | "tv"          | The command to use to display |
    |               |           |               | TV schedules.                 |
    +---------------+-----------+---------------+-------------------------------+

..  warning::
    The trigger should only contain alphanumeric characters (in particular,
    do not add any prefix, like "!" to that value).

Example
-------

In this example, we use a custom fetching class called ``My_TV_Fetcher``
and we define a group called "``hertzien``" which will contain the
7 basic french TV channels available using classical terrestrial TV technology.
This will also be the default group if the bot is queried for TV schedules
without any additional parameter.

..  parsed-code:: xml

    <?xml version="1.0"?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="0.20"
      language="fr-FR"
      timezone="Europe/Paris">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <module name="|project|">
          <!-- Override the default fetcher. -->
          <param name="fetcher_class"    value="My_TV_Fetcher" />
          <!-- Create a group called "hertzien". -->
          <param name="group_hertzien"   value="TF1,France2,France3,Canal+,France5,M6,Arte" />
          <!-- And use it as the default group. -->
          <param name="default_group"    value="hertzien" />
        </module>
      </modules>
    </configuration>


..  |fetcher|       replace:: Erebot_Module_TV_Fetcher
..  |fetcherIface|  replace:: Erebot_Module_TV_Fetcher_Interface
..  _`fetcherIface`:
    https://buildbot.erebot.net/doc/api/Erebot_Module_TV/html/404
..  |groups|        replace:: :samp:`group_{name}`

.. vim: ts=4 et
