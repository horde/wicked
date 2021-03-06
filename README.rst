=================
 What is Wicked?
=================

:Contact: horde@lists.horde.org

.. contents:: Contents
.. section-numbering::

Wicked is a wiki application for Horde.

This software is OSI Certified Open Source Software. OSI Certified is a
certification mark of the `Open Source Initiative`_.

.. _`Open Source Initiative`: http://www.opensource.org/


Goals
=====

1. To Facilitate Maintaining a Knowledge Base
---------------------------------------------

In order to facilitate maintaining a knowledge base, Wicked must make it as
easy as possible to enter information.  If the user encounters impediments
when entering information, he or she will be less likely to enter information.
The goal as Wicked evolves, then, is to remove as many impediments to entry as
possible.

Here are some technological impediments we should be mindful of:

- Requiring complete documents or validatation which prevents saving.
- Requiring lots of information related to pages (categories, etc.).
- Complicated markup which has to be learned.

We should be mindful that not all impediments are strictly technological -
there are psychological impediments as well.  As a simple example, different
fonts and ways of rendering a page may make it appear more "professional" or
"perfect", and this might discourage a user from adding his thoughts somewhere
near the middle.  Another example: a strongly implied organizational structure
for a wiki might discourage someone from contributing some discussion that
does not fit into any of the predefined slots.

One of the most important factors that will encourage a person to add
information to a knowledge base is whether he or she find useful information
in it.  If a user keeps coming back to it to search for useful information,
that user is more likely to contribute.

2. To Facilitate Inter-connecting Knowledge
-------------------------------------------

The value of a large knowledge base isn't solely in the amount of information
it contains.  There are many large knowledge bases which are difficult to
navigate, and seem to prevent you from finding things that you know must be in
there.  One of the ways to avoid this is by facilitating the
inter-connectedness of knowledge - if we make linking between pages easy, don't
prevent (and even encourage) "dangling" and "accidental" links, and provide
mechanisms so that synonymous terms can refer to the same page, knowledge in
the knowledge base will be much more accessible.

3. To Facilitate Peer Review and Discussion of Content
------------------------------------------------------

The openness of a wiki is one of its great strengths; it allows anyone to add
their opinions or references to facts or whatever.  One of the most unique
goals of Wicked is to find new mechanisms to facilitate this.  For example,
allowing people to subscribe to a page, area, or the entire wiki to receive
change notifications keeps people coming back to topics that they are
interested in, and adding their opinions.

Adding an "invite" feature which allows a user to solicit input from a third
party increases traffic and discussion on a topic and also gives Wicked a sort
of one-shot single-topic mailing list feature.  It also provides a mechanism
for publishing information, for example to release a memo in an organization.

4. To Facilitate Accuracy Through Accountability
------------------------------------------------

There are only two ways to know whether information is accurate: 1) to have an
idea of the accuracy of the source of that information or 2) to test it.  In
most document-management-type solutions, permissions systems are used to
ensure that only authorized users can modify documents, but in wiki we
recognize that permissions are an impediment to contribution.

Although Wicked does support setting permissions for pages, accuracy in wiki
is provided by tracking changes made to pages so that we always know where
the information came from.  This also increases the quality of the content by
providing accountability.

5. To Facilitate Paper Publishing
---------------------------------

Once the collaboration on some piece of knowledge is done, Wicked should
support printing it in a "final form" useful for consumption.


Features
========

1. Change Tracking and Revision History
---------------------------------------

2. Full-Text Searching
----------------------

3. Support for Multiple Content Types
-------------------------------------

Not implemented yet.

4. Page and Site Subscriptions and Invitations
----------------------------------------------

Not implemented yet.

5. Typesetting to PDF
---------------------

Not implemented yet. Exporting wiki pages to Latex is already possible
though. It is still planned to export complete wikis or "books" (sets of wiki
pages).


Obtaining Wicked
================

Further information on Wicked and the latest version can be obtained at

  http://www.horde.org/apps/wicked


Documentation
=============

The following documentation is available in the Wicked distribution:

:README_:           This file
:LICENSE_:          Copyright and license information
:`doc/CHANGES`_:    Changes by release
:`doc/CREDITS`_:    Project developers
:`doc/INSTALL`_:    Installation instructions and notes
:`doc/TODO`_:       Development TODO list


Installation
============

Instructions for installing Wicked can be found in the file INSTALL_ in the
``doc/`` directory of the Wicked distribution.


Assistance
==========

If you encounter problems with Wicked, help is available!

The Horde Frequently Asked Questions List (FAQ), available on the Web at

  http://wiki.horde.org/FAQ

Horde LLC runs a number of mailing lists, for individual applications
and for issues relating to the project as a whole. Information, archives, and
subscription information can be found at

  http://www.horde.org/community/mail

Lastly, Horde developers, contributors and users also make occasional
appearances on IRC, on the channel #horde on the Freenode Network
(irc.freenode.net).


Licensing
=========

For licensing and copyright information, please see the file LICENSE_ in the
Wicked distribution.

Thanks,

The Wicked team


.. _README: README.rst
.. _LICENSE: http://www.horde.org/licenses/gpl
.. _doc/CHANGES: doc/CHANGES
.. _doc/CREDITS: doc/CREDITS.rst
.. _INSTALL:
.. _doc/INSTALL: doc/INSTALL.rst
.. _doc/TODO: doc/TODO.rst
