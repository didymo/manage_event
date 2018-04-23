# manage_event

Asset_manage is part of the service clubs package.
The project base can be found at https://github.com/didymo/service_clubs_package.

The manage_event module handles the creation, editing, and deletion
of events. By using this module, you can create public events, such as
festivals or protests, that allows the user to detail its location 
(and even provide map functionality for easy viewing), 
to list any important items or assets that will be used for the
event (such as gas canisters or food trucks), as well as the ability to specify
start and end date/time for the organisers and workers separately from the 
start and end date/time of the general public. These events can then be edited
or deleted should any needs or changes arise.

This module is especially useful, as it can be used as a tool for
easy event planning, but also provides the event information in a
clean formatted layout that is easily viewable for both organisers
and the general public. 

This means it can be used as a method of also
advertising the event, instead of the planner then being forced to publish
the exact same information on a separate website, like most existing tools
do today. For those who want to organise a public event but don't have the
resources to maintain their own site for the event can use this module to 
accomplish this.

It also provides emailing functionality to perform tasks that event 
organisers would otherwise be forced to accomplish themselves. Should
an event get cancelled, instead of an event handler being forced to send
out possibly hundreds of emails to those interested, the system will send
out an email to all on the mailing list for the event automatically after
event cancellation to alert the other participants. 

It will also allow for the recipient to confirm that they have received the email, 
allowing for the organiser to easily check who has and hasn't received 
notification about event cancellation, allowing them to easily act accordingly.

# REQUIRED MODULES
* service-project-base

#RECOMMENDED MODULES
* service-club-base
* assetmanage
* userprofiles

#INSTALLATION
Refer to INSTALL.txt.

#CONFIGURATION
The module has no menu or modifiable settings. There is no
configuration. 

If disabled, the site will no longer be able to provide the
creation of new events, or the editing and deletion of existing
ones. The automated emailing system will also no longer be available.

#FAQ
Frequenctly asked questions in the issue queue

#MAINTAINERS
Current maintainers:
* Tahlya M. Maling

This project is thanks to:
* Didymo Designs
* Illawarra RSL
