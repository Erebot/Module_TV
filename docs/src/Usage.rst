Usage
=====

This section assumes default values are used for all triggers.
Please refer to :ref:`configuration options <configuration options>`
for more information on how to customize triggers.


Provided commands
-----------------

This module provides the following commands:

..  table:: Commands provided by |project|

    +---------------------------+-------------------------------------------+
    | Command                   | Description                               |
    +===========================+===========================================+
    | ``!tv``                   | Displays information about currently      |
    |                           | airing TV programs for the default        |
    |                           | channels group.                           |
    +---------------------------+-------------------------------------------+
    | :samp:`!tv {time}`        | Displays information about TV programs    |
    |                           | for the default channels group at the     |
    |                           | given *time*.                             |
    |                           | *time* may be given in either 12h or 24h  |
    |                           | format.                                   |
    +---------------------------+-------------------------------------------+
    | |tv|                      | Displays TV schedules for the given       |
    |                           | *channels* at the given *time*.           |
    |                           | You may also use a                        |
    |                           | :ref:`channel group <channel groups>`     |
    |                           | in place of *channels*.                   |
    |                           | *time* may be given in either 12h or 24h  |
    |                           | format.                                   |
    +---------------------------+-------------------------------------------+

..  _`channel groups`:
..  note::
    A list of valid channel groups can be retrieved using ``!help tv``.


Example
-------

..  sourcecode:: irc

    20:58:13 <@Clicky> !tv
    20:58:20 < Erebot> Programmes TV du January 17, 2012 8:58:00 PM : TF1 : Les experts : Manhattan (20:50 - 21:35) - France 2 : Le cinquième élément (20:35 -
                       22:35) - France 3 : Famille d'accueil (20:35 - 21:30) - Canal+ : Another Year (20:55 - 23:00) - France 5 : Une pieuvre nommée Bercy
                       (20:35 - 21:45) - Arte : L'effet domino (20:40 - 22:15) - M6 : Cauchemar en cuisine (20:50 - 22:05)

    20:58:29 <@Clicky> !tv 22h
    20:58:33 < Erebot> Programmes TV du January 17, 2012 10:00:00 PM : TF1 : Les experts : Manhattan (21:35 - 22:25) - France 2 : Le cinquième élément (20:35 -
                       22:35) - France 3 : Famille d'accueil (21:30 - 22:25) - Canal+ : Another Year (20:55 - 23:00) - France 5 : Le monde en face (21:45 -
                       22:15) - Arte : L'effet domino (20:40 - 22:15) - M6 : Cauchemar en cuisine (20:50 - 22:05)

    21:28:56 <@Clicky> !tv 23h TF1
    21:29:02 < Erebot> Programmes TV du January 17, 2012 11:00:00 PM : TF1 : Les experts : Manhattan (22:25 - 23:20)


..  |tv| replace:: :samp:`!tv {time} {channels...}`

..  vim: ts=4 et
