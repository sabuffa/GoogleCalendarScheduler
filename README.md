Google Calendar scheduler service

Requires php 5.3+ and google php service api version 1.1.1+

README:

There are multiple ways for you to run this code.  Either:

1. include the file in another php file (it will then run automatically, procedural style)
2. define OOP (define('OOP', true)) and then include the file and then call RequestHandler::HandleRequest() (OO style) (see index.php as example)
3. call the file directly like in the browser (localhost://RSGCRequest.php?<xml> (it will run automatically, procedural style)

When OOP is not defined, the php file will run in procedural style.  This is preferable for things like AJAX requests.  (see index.html as example of ajax request)
When sending an ajax GET request (all requests must be GET requests for it to work) the code will return 'OK' if successful.  
Otherwise will return error string with error enclosed in xml tags '<error> </error>'

You can either put the xml into the url at the end or turn on the USE_FILE_REQUESTS and load an xml file instead.  Defaulted to read from the url currently

You will need to define TEST_SERVICE_ACCOUNT_EMAIL and TEST_SERVICE_ACCOUNT_PKCS12_FILE_PATH for the system to work (see info below about defines)

The defines at the top of the file are important:

define('TEST_SERVICE_ACCOUNT_EMAIL', '1324354657687-13243546576879807968ahsjdkfury46@developer.gserviceaccount.com');
This is the service account that must be set up to be able to access the google api

define('TEST_SERVICE_ACCOUNT_PKCS12_FILE_PATH', 'C:\location\My Project-ahsg3647ehdg.p12');
This is the file path on your server to the .p12 file that is used for authentication when accessing the google api

define('CALENDAR_SCOPE', 'https://www.googleapis.com/auth/calendar');
This tells the system that you want to interact with the google calendar service

define('DEBUG_MODE', false);
When set to true this will output debug data to the browser screen (if running from browser) using echo

define('OFFLINE_MODE', false);
When set to true this will allow testing of parsing without attempting to access google service

define('SERVICE_MODE', true);
When set to true whis will allow system to build service object for accessing google service

define('USE_FILE_REQUESTS', false);
When set to true this will cause the system to parse the request from an xml file instead of the uri.  (This is commented out because it is set in the index.php file currently)

define('TEST_REQUEST_FILE', 'testRequest.xml');
This is the file path on your server to the xml file that is to be parsed when performing a request


Note: when not using test request files (using either AJAX or url directly) the uri should be encoded beforehand

- All requests should (but not required) be contained inside a <request> </request> tag
- the supported tag types are as follows:
	- <request>
	- <type>
	- <name>
	- <date>
	- <duration>
	- <location>
	- <description>

-<type> tags are to identify the request type.  The following request types are allowed:
	- allocate
	- free
	
- each request can have only one <type> </type> tag.  In other words, only one request type per request

- allocate will add one event to one calendar 

- free will remove one event to one calendar

- allocation and freeing will appropriately check for boundary conditions and handle merging/splitting empty space appropriately

- the system checks the validity of all data within tags to a reasonable extent (i.e. name is a properly formed email address, date has a properly formed date, etc.)

- any extra tags in a request will be parsed but not used. 
- if the xml is not well-formed the parser will return an error

- errors will always be returned enclosed in <error> </error> tags.

- each call into the api will only return a single error at a time, the first error it encounters

- each request must have one and only one <name> </name> tag.  The name is the id of the calendar that is being operated on.  It should always be of the form *@group.calendar.google.com.  If a given name is not matched to an id in google calendars the system will give an error

- all dates must conform to the RFC3339 standard or they will be considered invalid.  The RFC3339 standard states that each date will be of the form yyyy-mm-ddThh:mm:ss(+/-)hh:mm.  The time zone offset is required.  Time is in 24hr time (i.e. 4pm == 16:00)

- if request type is allocate, or free there can only be one <date> </date> tag.  The time is required.

- it is expected that all calendars being operated on by the api will be of the proper form (no empty space because of manual changes) or the system will return an error

- any missing, already existing/overlapping, or dates that extend past 8PM or before 8AM found in a calendar will return an error

- allocation must have only one <date> </date> tag and it is required

- allocation must have only one <duration> </duration> tag and it is required.  It is in a unit of minutes

- duration tag can only be on an allocate request

- allocation requests optionally can have a single <description> </description> tag.  The contents of this tag will be put in the description field of the calendar event

- description can only be on an allocate request

- allocation requests optionally can have a single <location> </location> tag.  The contents of this tag will be put in the title and location fields of the calendar event

- location can only be on an allocate request

- when an event is created the account it was created on will get a notification about the event by default

- all events should be at least 30 minutes long and any empty space between events should be at least 15 minutes long to minimize the drawing problem in google calendars.  These durations are not enforced by the api

- when freeing an event, the time part of the date only needs to be within the duration of the event being freed for the system to find the event and remove it, but it would be best to use the start time of the event

- Errors will be returned (not echoed to screen/browser) as a string

- every error string will start with <error> and end with </error>, so it can be converted to xml if wanted


The other errors returned are either in the following list or are system (not defined by me) errors:

global errors:

- bad request
- invalid request
- invalid request object
- failed to build service object

	Line 212: 							return $errorResult.'<error>Unable to contact calendar API because of: ' . $query . '</error>';
	Line 249: 											return $errorResult.'<error>Failed to perform request properly: ' . $e->getMessage() . '</error>';
	Line 255: 											return $errorResult.'<error>Google or generic exception caught when trying to perform request.  Calendar possibly changed. Exception was: ' . $e->getMessage() . '</error>';
	Line 262: 										return $errorResult.'<error>failed to build service object.  Calendar not changed.</error>';
	Line 269: 									return $errorResult.'<error>Exception caught when trying to build service.  Calendar not changed. Exception was: ' . $e->getMessage() . '</error>';
	Line 276: 								return $errorResult.'<error>invalid request object. Calendar not changed.</error>';
	Line 283: 							return $errorResult.'<error>invalid request.  Calendar not changed.</error>';
	Line 290: 						return $errorResult.'<error>Failed to parse request.  Calendar not changed. Error: ' . $parseError->getMessage() . '</error>';
	Line 296: 						return $errorResult.'<error>Exception caught when trying to parse request. Calendar not changed.  Exception: ' . $e->getMessage() . '</error>';
	Line 306: 				return $errorResult.'<error>bad request from setmore.  Calendar not changed.</error>';
	Line 313: 		   return $errorResult.'<error>_SERVER not set. Calendar not changed</error>';

The content of <exception> will usually be a hard-coded string from the followng lists:

	Line 573: 				throw new ParsingException('only one request type tag allowed per request');
	Line 585: 						throw new ParsingException('Invalid request type');
	Line 589: 				throw new ParsingException('Empty request type');
	Line 593: 				throw new ParsingException('only one name tag allowed per request');
	Line 599: 				throw new ParsingException('empty calendar name');
	Line 604: 		throw new ParsingException('only one email tag allowed per request');
	Line 610: 		throw new ParsingException('invalid email set');
	Line 615: 		throw new ParsingException('only one color tag allowed per request');
	Line 622: 		throw new ParsingException('invalid color set');
	Line 626: 				throw new ParsingException('event type only allowed for allocate requests');
	Line 630: 				throw new ParsingException('only one event type tag allowed per allocate request');
	Line 645: 						throw new ParsingException('Invalid event type');
	Line 651: 			   throw new ParsingException('description only allowed on allocate requests');
	Line 655: 			   throw new ParsingException('only one description tag allowed per allocate request');
	Line 664: 				throw new ParsingException('jobticket_url only allowed on allocate requests');
	Line 668: 				throw new ParsingException('only one jobticket_url tag allowed per allocate request');
	Line 676: 				throw new ParsingException('uneven matching of dates to durations for an allocate request.  That is not allowed');
	Line 680: 			   throw new ParsingException('location only allowed on allocate requests');
	Line 684: 			   throw new ParsingException('only one location tag allowed per allocate request');
	Line 697: 						throw new ParsingException('only one date is allowed per request for allocate, and free requests');
	Line 706: 			   throw new ParsingException('duration only allowed on allocate requests');
	Line 710: 				throw new ParsingException('only one duration is allowed per allocate request');
	Line 723: 					throw new ParsingException('empty date');
	Line 730: 					throw new ParsingException('invalid date format');
	Line 775: 						throw new ParsingException('morning event cannot have a start time in the afternoon');
	Line 785: 						throw new ParsingException('afternoon event cannot have a start time in the morning');
	Line 795: 						throw new ParsingException('cannot insert an event into calendar that occurs in the past.  Change your time.');
	Line 804: 							throw new ParsingException('no duration to match with date!');
	Line 808: 							throw new ParsingException('invalid duration specified');
	Line 821: 								throw new OtherException('received a movable event with a start time outside normal business hours.  Internal error');
	Line 824: 									throw new ParsingException('start time for allocation not within valid time range of ' . startOfDay()->format(DATE_RFC3339) . ' to ' . endOfDay()->format(DATE_RFC3339));
	Line 841: 								throw new ParsingException('time for freeing not within valid time range of ' . startOfDay()->format(DATE_RFC3339) . ' to ' . endOfDay()->format(DATE_RFC3339));
	Line 849: 						throw new ParsingException('Invalid RequestType set!  Internal Error.');
	Line 1009: 					  throw new OtherException('algorithm failed to find an available slot for an existing event.');
	Line 1116: 				    throw new OtherException('event trying to be added overlaps with an existing event that cannot be moved.');
	Line 1283: 					throw new OtherException('Internal error: attempted to perform request with no resources.  Nothing was changed on calendar.');
	Line 1312: 						throw new OtherException('no calendar found that matches the id provided.  Calendar not changed.');
	Line 1346: 								throw new OtherException('Problem found before freeing.  Calendar not changed. More than one event found during the specified time.  This is not allowed.  Manually correct this in calendar');
	Line 1368: 								throw new OtherException('After freeing, the event that should have been deleted in Google Calendar was not. Calendar changed.');
	Line 1376: 							throw new OtherException('No event to be freed.  Request from setmore was bad. Calendar not changed.');
	Line 1490: 										//in this case, continue inserting anyway, don't throw exception for now
	Line 1521: 									throw new OtherException('After allocate, the number of events in the calendar are wrong. Calendar changed.');;
	Line 1566: 										throw new OtherException('After allocate delete, the number of events in the calendar are wrong. Calendar changed.');;
	Line 1595: 										throw new OtherException('After allocate delete, the event added did not match with what was tried to be added. Calendar changed.');
	Line 1619: 							   throw new OtherException('Internal error: no events to insert.  Calendar not changed.');
	Line 1650: 								throw new OtherException('After allocate, the event added did not match with what was tried to be added. Calendar was changed.');
	Line 1664: 								throw new OtherException('After allocate, the event that should have been added was not added. Calendar was not changed. ');;
	Line 1673: 							throw new OtherException('Warning: event added outside of normal business hours');
	Line 1677: 							throw new OtherException('Unable to reschedule events during insert.  Inserted event anyways.  Calendar changed. Manual adjustment necessary.');
	Line 1687: 				throw new OtherException('Invalid request to perform on. Calendar not changed.');
	Line 1695: 			throw new OtherException('Invalid service reference. Calendar not changed.');



The various logging levels are:
//1 == debug, 2 == info, 4 == warning, 8 == error, 16 == fatal.  Max is 31.  Defaults to warning, error, and fatal logged to file.

to turn logging on and off there is a variable called LOGGING_ON in the file.

LOGGING_OUTPUT_FILE variable determines location of log file output

A few reminders to you about how this service works:

Remember that when my code cannot find a spot to insert, it will always still insert. 
For anytime, morning, and afternoon events it will insert at 5AM.  For non movable events it will insert at the requested time, even in the case where there is already an event there.
You get an <error> in both of these cases.  If you do not manually fix this problem after (overlapping events) then you will have problems.  And freeing events will not work anytime you try to free an event that overlaps with another event.

Just to note, a less verbose form of these messages are also returned to you as a response from my service, enclosed in <error></error> tags

Also, remember NEVER to overlap events in the calendar.  It wasn't designed to handle overlapping events.  The code expects only one event at any point in time (except the start and end of events can share the same time). If you have more than one it will break stuff.

Also, remember that if you have morning, afternoon, or anytime events, they can and likely will be moved whenever you add a new event to a day.  So for example, even if you add a event that same day, all the other event times (that are not non-movable) will change.




examples of requests (decoded already):


allocate:

<request>
<type>allocate</type>
<name>8u8apg9lvs8r8gra8bcuuaj240@group.calendar.google.com</name>
<date>2015-09-22T14:00:00-04:00</date>
<duration>60</duration>
<location>123 Front Street</location>
<description>The coolest thing ever</description>
</request>

free:

<request>
<type>free</type>
<name>8u8apg9lvs8r8gra8bcuuaj240@group.calendar.google.com</name>
<date>2015-09-22T14:00:00-04:00</date>
</request>

