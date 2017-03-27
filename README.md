README:

Google Calendar Scheduler

There are multiple ways for you to run this code.  Either:

1. include the file in another php file (it will then run automatically, procedural style)
2. define OOP (define('OOP', true)) and then include the file and then call RequestHandler::HandleRequest() (OO style) (see index.php as example)
3. call the file directly like in the browser (localhost://Scheduler.php?<xml> (it will run automatically, procedural style)

You can either put the xml into the url at the end or turn on the USE_FILE_REQUESTS and load an xml file instead.  Defaulted to read from the url currently

You will need to define TEST_SERVICE_ACCOUNT_EMAIL and TEST_SERVICE_ACCOUNT_PKCS12_FILE_PATH for the system to work (see info below about defines)

The defines at the top of the file are important (these are fake in this readme):

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
When set to true this will cause the system to parse the request from an xml file instead of the uri

define('TEST_REQUEST_FILE', 'testRequest.xml');
This is the file path on your server to the xml file that is to be parsed when performing a request


- All requests should (but not required) be contained inside a <request> </request> tag
- the supported tag types are as follows:
	- <request>
	- <type>
	- <name>
	- <date>
	- <duration>

-<type> tags are to identify the request type.  The following request types are allowed:
	- initDate
	- initBlock
	- allocate
	- free
	
- each request can have only one <type> </type> tag.  In other words, only one request type per request

- initDate initializes one date on one calendar for use with the calendar system

- initBlock initialized a range of dates on one calendar with the calendar system

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

- if request type is initDate, allocate, or free there can only be one <date> </date> tag.  The time is required.  For initDate the time is not used so it can be set to any valid time

- if request type is initBlock there must be two <date> </date> tags.  The first date must correspond to start date and the second to the end date or the system will return an error.  The start date must be before or equal to the end date.  Like initDate, the time is required but not used so it can be set to any valid time

- it is expected that all calendars being operated on by the api will be of the proper form (no empty space because of manual changes) or the system will return an error

- any missing, already existing/overlapping, or dates that extend past 8PM or before 8AM found in a calendar will return an error

- for allocation and freeing, only a date with a time between 8AM-8PM is allowed, otherwise the system will return an error

- allocation must have only one <date> </date> tag and it is required
- allocation must have only one <duration> </duration> tag and it is required.  It is in a unit of minutes
- if the duration makes an event go past 8pm it is an invalid event and the system will return an error

- all events should be at least 30 minutes long and any empty space between events should be at least 15 minutes long to minimize the drawing problem in google calendars.  These durations are not enforced by the api

- when freeing an event, the time part of the date only needs to be within the duration of the event being freed for the system to find the event and remove it, but it would be best to use the start time of the event

- Errors will be returned (not echoed to screen/browser) as a string

- every error string will start with <error> and end with </error>, so it can be converted to xml if wanted

Most errors will be returned that will start with the following:
- Exception caught when trying to build service: <exception>
- Exception caught when trying to parse request: <exception>
- Failed to parse request: <exception>
- Failed to perform request: <exception>
- Exception caught when trying to perform request: <exception>

The other errors returned are either in the following list or are system (not defined by me) errors:

global errors:

- bad request
- invalid request
- invalid request object
- failed to build service object

The content of <exception> will usually be a hard-coded string from the followng lists:

Parsing errors:
- only one request type tag allowed per request
- invalid request type
- empty request type
- one one name tag allowed per request
- uneven matching of dates to durations for an allocate request.  That is not allowed
- only one date is allowed per request for initDate, allocate and free requests
- only two dates allowed for initBlock requests.  Start date and end date
- empty date
- invalid date format
- no duration to match with date!
- invalid duration specified
- input date for allocation not within valid range of 8AM-8PM
- input date for freeing not within valid range of 8AM-8PM
- Invalid RequestType set! Internal error.
- input dates overlap
- end of date range is before start of date range

Request Processing errors:
- Internal error: attempted to perform request with no resources
- no calendar found that matches the id provided
- tried to free space that is already free.  Not allowed
- Internal error: there was no event found before event trying to be freed.  This should not happen
- there was no event found after event trying to be freed
- attempting to free an event to time that was not initialized.  Not allowed
- there is a conflicting event that overlaps this time allocation not allowed
- Internal error: Found no overlap of events that are within date range
- The events are not overlapping when they should overlap with gray space
- attempting to add an event to time that was not initialized.  Not allowed
- dates overlap (stopping on first unallowed occurence)
- invalid request to perform on
- invalid service reference