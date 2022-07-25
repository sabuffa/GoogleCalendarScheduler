<?php

//this is google api files
require_once 'vendor/autoload.php';

//session_name('RSGCRequest');
//session_start();

//credentials for oauth with service without domain authority (where I assume the calendars have been shared with the service.  in other words it is the same as domain authority but will fail if calendar is not there whereas the other will not)
define('TEST_SERVICE_ACCOUNT_EMAIL', '1324354657687-13243546576879807968ahsjdkfury46@developer.gserviceaccount.com');
define('TEST_SERVICE_ACCOUNT_PKCS12_FILE_PATH', 'C:\location\My Project-ahsg3647ehdg.p12');



define('CALENDAR_SCOPE', 'https://www.googleapis.com/auth/calendar');

define('DEBUG_MODE', false);
define('OFFLINE_MODE', false);
define('SERVICE_MODE', true);
define('USE_FILE_REQUESTS', false);
define('TEST_REQUEST_FILE', './testRequest.xml');

define('startOfDay', 9); //9AM  - must be positive integer
define('dayDuration', 8); //hours - must be positive integer
define('afternoon', 12); //12PM  - must be positive integer
define('defaultEventPadding', 0); //minutes
define('dumbTime', 5); //5AM - must be positive integer
define('ALLOW_INSERT_INTO_PAST', true); //TODO: might not work right if set to false and you insert past end of day (when end of day is in the past). Hasn't been tested yet
define('ALWAYS_INSERT', true); //will always insert event
define('DST_ADJUST', false); //whether or not to assume the input date is correct for the current timezone/dst passed in.  If true, then we assume not and adjust by 1 hour.
define('LOGGER_ON', true); //whether or not to log requests/results to log file
define('LOGGING_LEVEL', 28); //1 == debug, 2 == info, 4 == warning, 8 == error, 16 == fatal.  Max is 31
define('LOGGING_OUTPUT_FILE', './RSGCRequestLog.txt');
define('ALLOW_INSERT_TWILIGHT', true);
define('ALLOW_DELETE_TWILIGHT', true);

//this will have the timezone that everything is calculated with.  Will be set to whatever timezone is passed into this script
//also will be the current time that the request was made (when this script was invoked)
$currentTime = new DateTime(null, new DateTimeZone('UTC'));

//this is the day that I am currently working with.  The day I would be inserting/removing/updating from for a particular request
//default to current day/time
$currentDay = new DateTime(null, new DateTimeZone('UTC'));

//using this as a workaround for now.
function getDST($tzId, $time = null) {
    if($time == null){
        $time = gmdate('U');
    } 

    $tz = new DateTimeZone($tzId);

    $transition = $tz->getTransitions($time);
    //print_r($transition);
    return $transition[0]['isdst'];
}

//NOTE: the offset I am going here in all the endOfDay etc. functions doesn't work for one location in the world: Lord Howe island, Australia.  Not worrying about it for now
function goingOutOfDST($date = null) {
	global $currentTime;
	global $currentDay;
	
	if ($date == null) {
		$date = clone $currentDay;
	}
	
	$isCurrentlyDST = getDST($currentTime->getTimezone()->getName(), $currentTime->getTimestamp());
	$willBeDST = getDST($date->getTimezone()->getName(), $date->getTimestamp());
	
	return $isCurrentlyDST && !$willBeDST;
}

function goingIntoDST($date = null) {
	global $currentTime;
	global $currentDay;
	
	if ($date == null) {
		$date = clone $currentDay;
	}
	
	$isCurrentlyDST = getDST($currentTime->getTimezone()->getName(), $currentTime->getTimestamp());
	$willBeDST = getDST($date->getTimezone()->getName(), $date->getTimestamp());
	
	return !$isCurrentlyDST && $willBeDST;
}

function currentDay() {
	global $currentDay;
	return $currentDay;
}

function dumbTime() {
	$result = clone currentDay();
	$adjustedDumbTime = dumbTime;
	
	if (DST_ADJUST) {
		if (goingIntoDST()) {
			$adjustedDumbTime -= 1;
		} else if (goingOutOfDST()) {
			$adjustedDumbTime += 1;
		}
	}
	
	$result->setTime($adjustedDumbTime, 0, 0);
	
	/*if (DEBUG_MODE) {
		echo 'dumb time: ' . $result->format(DATE_RFC3339) . '<br/>';
	}*/
	
	return $result;
}

function endOfDay() {
	$result = clone currentDay();
	$adjustedStartOfDay = startOfDay;
	
	if (DST_ADJUST) {
		if (goingIntoDST()) {
			$adjustedStartOfDay -= 1;
		} else if (goingOutOfDST()) {
			$adjustedStartOfDay += 1;
		}
	}

	$result->setTime($adjustedStartOfDay + dayDuration, 0, 0);
	
	/*if (DEBUG_MODE) {
		echo 'end of day: ' . $result->format(DATE_RFC3339) . '<br/>';
	}*/
	
	return $result;
}


function startOfDay() {
	$result = clone currentDay();
	$adjustedStartOfDay = startOfDay;
	
	if (DST_ADJUST) {
		if (goingIntoDST()) {
			$adjustedStartOfDay -= 1;
		} else if (goingOutOfDST()) {
			$adjustedStartOfDay += 1;
		}
	}
	
	$result->setTime($adjustedStartOfDay, 0, 0);
	
	/*if (DEBUG_MODE) {
		echo 'startOfDay: ' . $result->format(DATE_RFC3339) . '<br/>';
	}*/
	
	return $result;
}

function afternoon() {
	$result = clone currentDay();
	$adjustedAfternoon = afternoon;

	if (DST_ADJUST) {
		if (goingIntoDST()) {
			$adjustedAfternoon -= 1;
		} else if (goingOutOfDST()) {
			$adjustedAfternoon += 1;
		}
	}
	
	$result->setTime(afternoon, 0, 0);
	
	/*if (DEBUG_MODE) {
		echo 'afternoon: ' . $result->format(DATE_RFC3339) . '<br/>';
	}*/
	
	return $result;
}

if (!defined('OOP')) {
	if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
	   $log = $currentTime->format(DATE_RFC3339) . "Service call received:\n";
	   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
	}
    //procedural style.  File will run automatically.  To turn off (so you can call the functions outside of this file) define OOP in parent file
    $result = RequestHandler::HandleRequest();
	
	if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
		$log = $currentTime->format(DATE_RFC3339) . "ServiceCallResult:\n" . $result . "\n";
		file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
	}
}

class RequestHandler {

	static function HandleRequest() {
		global $currentTime;
	    $errorResult = "<result>Error</result>";
	    $okResult = "<result>ok</result>";
		if (isset($_SERVER['REQUEST_METHOD'])) {
			switch($_SERVER['REQUEST_METHOD']) {
			//requirements are for parsing the url, so must be GET requests
			case 'GET': 
				{
					$query;
					
					if (USE_FILE_REQUESTS) {
						//overwrite query with filename
						$query = TEST_REQUEST_FILE;
					} else {
						//all good. Grab the request query
						$query = urldecode($_SERVER['QUERY_STRING']);
						if (strpos($query, 'error') !== false) {
							//error returned from request. 
							return $errorResult.'<error>Unable to contact calendar API because of: ' . $query . '</error>';
						}
					}		 
					if (DEBUG_MODE) {
						echo 'Here is the query string:' . $query . '<br/>';
					}
					
					$service = null;		 
					
					try {
						//validate the request query
						$xmlDoc = null;
						if (RSGCRequestParser::isValid($query, $xmlDoc)) {
							//parse the request query
							$RSGCRequestObj = RSGCRequestParser::parse($query, $xmlDoc, false);
							
							if ($RSGCRequestObj->isValid()) {
								try {
									if (!OFFLINE_MODE && SERVICE_MODE) {					   					   					   
										//this is service account without domain wide authority, but calendar is shared with service user					   
										$service = ServiceBuilder::buildService(TEST_SERVICE_ACCOUNT_PKCS12_FILE_PATH, TEST_SERVICE_ACCOUNT_EMAIL, CALENDAR_SCOPE, TEST_SERVICE_ACCOUNT_EMAIL);
									}
									
									if ((OFFLINE_MODE) || !is_null($service)) {
										if (DEBUG_MODE) {
											echo 'successfully built service object' . '<br/>';
										}				
										
										//log the current request here - set to exception level so it will always be logged
										if (LOGGER_ON && ((LOGGING_LEVEL & 16) == 16)) {
											$log = $currentTime->format(DATE_RFC3339) . "valid request being performed: " . $query . "\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
										}
										try {
											$RSGCRequestObj->performRequest($service); 
										} catch (OtherException $e) {
											//no logging here it will be done internal for more granularity
											return $errorResult.'<error>Failed to perform request properly: ' . $e->getMessage() . '</error>';
										} catch (Exception $e) {
											if (LOGGER_ON && ((LOGGING_LEVEL & 16) == 16)) {
												$log = $currentTime->format(DATE_RFC3339) . ": Exception thrown from Google.  Calendar possibly changed.  Exception was:" . $e->getMessage() . " Request was: " . $query . "\n";
												file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
											}
											return $errorResult.'<error>Google or generic exception caught when trying to perform request.  Calendar possibly changed. Exception was: ' . $e->getMessage() . '</error>';
										}
									} else {
										if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
											$log = $currentTime->format(DATE_RFC3339) . ": Failed to build service object.  Calendar not changed. Request was: " . $query . "\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
										}
										return $errorResult.'<error>failed to build service object.  Calendar not changed.</error>';
									}
								} catch(Exception $e) {
									if (LOGGER_ON && ((LOGGING_LEVEL & 16) == 16)) {
										$log = $currentTime->format(DATE_RFC3339) . ": Exception caught when trying to build service.  Calendar not changed. Exception was: " . $e->getMessage() . "\nRequest was: " . $query . "\n";
										file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									}
									return $errorResult.'<error>Exception caught when trying to build service.  Calendar not changed. Exception was: ' . $e->getMessage() . '</error>';
								}
							} else {
								if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
									$log = $currentTime->format(DATE_RFC3339) . ": Invalid request sent from setmore.  Calendar not changed. Request was: " . $query . "\n";
									file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
								}
								return $errorResult.'<error>invalid request object. Calendar not changed.</error>';
							}
						} else {
							if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
								$log = $currentTime->format(DATE_RFC3339) . ": Invalid request sent from setmore.  Calendar not changed.  Request was: " . $query . "\n";
								file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
							}
							return $errorResult.'<error>invalid request.  Calendar not changed.</error>';
						}			 
					} catch (ParsingException $parseError) {
						if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
							$log = $currentTime->format(DATE_RFC3339) . ": Parsing error. Invalid request sent from setmore.  Calendar not changed. Error: " . $parseError->getMessage() . " Request was: " . $query . "\n";
							file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
						}
						return $errorResult.'<error>Failed to parse request.  Calendar not changed. Error: ' . $parseError->getMessage() . '</error>';
					} catch (Exception $e) {
						if (LOGGER_ON && ((LOGGING_LEVEL & 16) == 16)) {
							$log = $currentTime->format(DATE_RFC3339) . ": Exception caught when trying to parse request. Calendar not changed.  Exception: " . $e->getMessage() . " Request was: " . $query . "\n";
							file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
						}
						return $errorResult.'<error>Exception caught when trying to parse request. Calendar not changed.  Exception: ' . $e->getMessage() . '</error>';
					}
				}
				break;
			default:
				//not good.  Don't handle request
				if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
					$log = $currentTime->format(DATE_RFC3339) . ": Invalid request sent from setmore.  Calendar not changed.  Request was: " . $query . "\n";
					file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
				}
				return $errorResult.'<error>bad request from setmore.  Calendar not changed.</error>';
			}
		} else {
			if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
				$log = $currentTime->format(DATE_RFC3339) . ": _SERVER not set. Calendar not changed.\n";
				file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
			}
		   return $errorResult.'<error>_SERVER not set. Calendar not changed</error>';
		}
		return $okResult;
	}

}

class ServiceBuilder {  	
	/**
	* Build and returns a service object authorized with a service account
	* that acts on behalf of the given user.
	*
	* @param pk12FilePath path on server to pk12 file
	* @param serviceAccountEmail address of service account
	* @param service service type making object for	 
	* @param userEmail The email of the user.
	* @return Google_Service_Calendar service object.
	*/	 
	static function buildService($pk12FilePath, $serviceAccountEmail, $serviceString, $userEmail) {
		$client = new Google_Client();
		if (isset($_SESSION['service_token'])) {
			$client->setAccessToken($_SESSION['service_token']);
		}
		$key = file_get_contents($pk12FilePath);
		$auth = new Google_Auth_AssertionCredentials(
		$serviceAccountEmail,
		array($serviceString),
		$key);
		//$auth->sub = $userEmail;
		$client->setAssertionCredentials($auth);
		
		if ($client->getAuth()->isAccessTokenExpired()) {
			$client->getAuth()->refreshTokenWithAssertion($auth);
		}
		$_SESSION['service_token'] = $client->getAccessToken();
		
		//for now only to calendar service.  Can be extended later for more services
		return new Google_Service_Calendar($client);
	}	
}


class Services {
	static function LogEventsToFile($eventArray) {
	    $log = "";
		if (is_null($eventArray)) {
		   return;	
		}
		
		foreach ($eventArray as $event) {
		   if (is_null($event)) {
			   continue;   
		   }
		   if (is_array($event)) {
			   foreach($event as $actualEvent) {
			       if (is_null($actualEvent)) {
				      continue;   
				   }
			       $log = $log . "\nEvent:\n";
				   $log = $log . "Summary: " . $actualEvent->getSummary() . "\n";
				   $log = $log . "StartTime: " . $actualEvent->getStart()->getDateTime() . "\n";
				   $log = $log . "EndTime: " . $actualEvent->getEnd()->getDateTime() . "\n";
				   $log = $log . "Description: " . $actualEvent->getDescription() . "\n";
				   $log = $log . "Location: " . $actualEvent->getLocation() . "\n\n";
				   //title 
				   //description
				   //start date
				   //end date
				   //location - doesn't need to be logged right now because it is already in the summary
				}   
			} else {
			   $log = $log . "\nEvent:\n";
			   $log = $log . "Summary: " . $event->getSummary() . "\n";
			   $log = $log . "StartTime: " . $event->getStart()->getDateTime() . "\n";
			   $log = $log . "EndTime: " . $event->getEnd()->getDateTime() . "\n";
			   $log = $log . "Description: " . $event->getDescription() . "\n";
			   $log = $log . "Location: " . $event->getLocation() . "\n\n";
			}	
		}
		
		file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
	}
	
	static function datesOverlap($start_one,$end_one,$start_two,$end_two) {

		if(($start_one < $end_two && $end_one > $start_two) || ($start_one == $start_two && $end_one == $end_two)) { //If the dates overlap
			return  1; //min($end_one,$end_two)->diff(max($start_two,$start_one))-> + 1; //return how many days overlap
		}

		return 0; //Return 0 if there is no overlap
	}
	
	static function sortArray(&$array) {
	    usort($array, function($a, $b) {
		  $first = DateTime::createFromFormat(DATE_RFC3339, $a->getStart()->getDateTime());
		  $second = DateTime::createFromFormat(DATE_RFC3339, $b->getStart()->getDateTime());
		   
		  if ($first == $second) {
	         return 0;
	      }
		   
	      return $first > $second ? 1 : -1;
		});
	}
}

class ParsingException extends Exception
{
	// Redefine the exception so message isn't optional
	public function __construct($message, $code = 0, Exception $previous = null) {    
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
		
		if (DEBUG_MODE) {
			echo 'Exception created for parsing.  Reason: ' . $message . '<br/>';
		}
	}

	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}";
	}
	
	public function toXML() {
		return '<error>' . $this->message . '</error>';
	}
}

class OtherException extends Exception
{
	// Redefine the exception so message isn't optional
	public function __construct($message, $code = 0, Exception $previous = null) {    
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
		
		if (DEBUG_MODE) {
			echo 'Exception created for parsing.  Reason: ' . $message . '<br/>';
		}
	}

	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}";
	}
	
	public function toXML() {
		return '<error>' . $this->message . '</error>';
	}
}

class RSGCRequestParser {   
	
	static function handle_xml_error($error, $xml, $throwException = true)
	{
		if (is_null($xml)) {
			$return = 'Unknown Error';
			if ($throwException) {
				throw new ParsingException($return);
			}
			return $return;
		}
		
		$return  = $xml[0] . '-' . "\n";

		switch ($error->level) {
		case LIBXML_ERR_WARNING: {
				$return .= "Warning $error->code: ";
				break;
			}
		case LIBXML_ERR_ERROR: {
				$return .= "Error $error->code: ";
				break;
			}
		case LIBXML_ERR_FATAL: {
				$return .= "Fatal Error $error->code: ";
				break;
			}
		default: {
				$return .= "Unknown Error $error->code: ";
				break;			   
			}
		}

		$return .= trim($error->message) .
		"\n  Line: $error->line" .
		"\n  Column: $error->column";

		if ($error->file) {
			$return .= "\n  File: $error->file";
		}

		if ($throwException) {
			throw new ParsingException($return);
		}
		return $return;
	}

	static function isValid($request, &$xmlDoc = null) {
		return RSGCRequestParser::validate($request, $xmlDoc);
	}

	//make sure the request is valid
	//must be called before parse to make sure request is valid
	static function validate($request, &$xmlDoc) {
		global $currentTime;
		//disable logging of errors
		libxml_use_internal_errors(true);
		//do parsing to return request type
		$xmlDoc;
		
		if (USE_FILE_REQUESTS) {
			$xmlDoc = simplexml_load_file($request);
		} else {
			$xmlDoc = simplexml_load_string($request);
		}

		$xml = explode("\n", $request);
		
		if ($xmlDoc === false) {
			$errors = libxml_get_errors();		
			
			foreach($errors as $error) {
				//this will only throw the first error
				RSGCRequestParser::handle_xml_error($error, $xml);
			}
			return false;
		}
		
		//NOTE: to make this parser easier perhaps validate against a schema in future
		if (DEBUG_MODE) {
			echo 'Here is the valid xml read in:     ' . $xmlDoc->asXML() . '<br/>';
		}	  	 
		
		if (LOGGER_ON && ((LOGGING_LEVEL & 1) == 1)) {
		   $logData = $currentTime->format(DATE_RFC3339) . " request: " . $xmlDoc->asXML() . "\n";
		   file_put_contents(LOGGING_OUTPUT_FILE, $logData, FILE_APPEND);	
		}
		
		return true;
	}

	//parses the url
	static function parse($request, &$xmlDoc = null, $doValidationFirst = false) {      
	    global $currentTime;
		
		if ($doValidationFirst) {	  
			if (!RSGCRequestParser::isValid($request, $xmlDoc)) {
				//return empty request if not valid.  Will be set to isValid = false			
				return new RSGCRequest();
			}
		} 
		if (!is_null($xmlDoc)) {			
			//build request object
			$request = new RSGCRequest();
			$resource = new ResourceType();
			
			//add this code for now as validation in lieu of schema validation
			//handle request type first
			
			if (count($xmlDoc->type) > 1) {
				throw new ParsingException('only one request type tag allowed per request');
			}
			
			if ($xmlDoc->type != '') {			
				switch(filter_var($xmlDoc->type, FILTER_SANITIZE_STRING)) {
                    case 'allocate': {
						$request->setRequestType(RequestType::ALLOCATE);
						break;
					} case 'free': {
						$request->setRequestType(RequestType::FREE);
						break;
					} default: {
						throw new ParsingException('Invalid request type');
					}
				}
			} else {
				throw new ParsingException('Empty request type');
			}
			
			if (count($xmlDoc->name) > 1) {
				throw new ParsingException('only one name tag allowed per request');
			}		
			//handle calendar id name
			if ($xmlDoc->name != '') {
				$resource->setName(filter_var($xmlDoc->name, FILTER_SANITIZE_STRING));
			} else {
				throw new ParsingException('empty calendar name');
			}
			
			//DISABLING FOR NOW AS IT SERVES NO PURPOSE
			/*if (count($xmlDoc->email) > 1) {
		throw new ParsingException('only one email tag allowed per request');
		}
		
		if (!filter_var($xmlDoc->email, FILTER_VALIDATE_EMAIL) === false) {
		$resource->setEmail((string)$xmlDoc->email);
		} else {
		throw new ParsingException('invalid email set');
		}*/
			
			/*  DISABLING THIS FOR NOW AS COLOR SERVES NOT MUCH USEFUL PURPOSE BECAUSE EVENTS NEED TO MATCH THE CALENDAR
		if (count($xmlDoc->color) > 1) {
		throw new ParsingException('only one color tag allowed per request');
		}

		//cannot choose gray as color because it's used as id for empty space
		if (!filter_var($xmlDoc->color, FILTER_VALIDATE_INT) === false && intval($xmlDoc->color) > 0 && intval($xmlDoc->color) < 12 && intval($xmlDoc->color) != 8) {
		$resource->setColor((string)$xmlDoc->color);
		} else {
		throw new ParsingException('invalid color set');
		}	*/				
			
			if ($request->getRequestType() != RequestType::ALLOCATE && count($xmlDoc->eventType) > 0) {
				throw new ParsingException('event type only allowed for allocate requests');
			}
			
			if (count($xmlDoc->eventType) > 1) {
				throw new ParsingException('only one event type tag allowed per allocate request');
			}

			if ($xmlDoc->eventType != '') {			
				switch(filter_var($xmlDoc->eventType, FILTER_SANITIZE_STRING)) {
                    case 'anytime': {
						$resource->setIdentifier(' - any');
						break;
					} case 'morning': {
						$resource->setIdentifier(' - morning');
						break;
					} case 'afternoon': {
						$resource->setIdentifier(' - afternoon');
						break;
					} default: {
						throw new ParsingException('Invalid event type');
					}
				}
			}
			
			if ($request->getRequestType() != RequestType::ALLOCATE && count($xmlDoc->description) > 0) {
			   throw new ParsingException('description only allowed on allocate requests');
			}
			
			if (count($xmlDoc->description) > 1) {
			   throw new ParsingException('only one description tag allowed per allocate request');
			}									
			
			//I will allow an empty description or no description at all for allocate requests
			if (count($xmlDoc->description) > 0) {
			   $resource->setDescription(filter_var($xmlDoc->description, FILTER_SANITIZE_STRING));
			}
			
			if ($request->getRequestType() != RequestType::ALLOCATE && count($xmlDoc->jobticket_url) > 0) {
				throw new ParsingException('jobticket_url only allowed on allocate requests');
			}
			
			if (count($xmlDoc->jobticket_url) > 1) {
				throw new ParsingException('only one jobticket_url tag allowed per allocate request');
			}
			
			if (count($xmlDoc->jobticket_url) > 0) {
				$resource->setURL(filter_var($xmlDoc->jobticket_url, FILTER_SANITIZE_URL));
			}
			
			if ($request->getRequestType() == RequestType::ALLOCATE && count($xmlDoc->date) != count($xmlDoc->duration)) {
				throw new ParsingException('uneven matching of dates to durations for an allocate request.  That is not allowed');
			}
			
			if ($request->getRequestType() != RequestType::ALLOCATE && count($xmlDoc->location) > 0) {
			   throw new ParsingException('location only allowed on allocate requests');
			}
			
			if (count($xmlDoc->location) > 1) {
			   throw new ParsingException('only one location tag allowed per allocate request');
			}
			
			//I will allow an empty location or no location at all for allocate requests
			if (count($xmlDoc->location) > 0) {
			   $resource->setLocation(filter_var($xmlDoc->location, FILTER_SANITIZE_STRING));
			}
			
			//for now only allow one date for initDate, allocate, and free per request
			switch ($request->getRequestType()) {
			case RequestType::ALLOCATE:
			case RequestType::FREE: {
					if (count($xmlDoc->date) > 1) {
						throw new ParsingException('only one date is allowed per request for allocate, and free requests');
					}			  
					break;
				} default:
				//do nothing
				break;
			}
			
			if ($request->getRequestType() != RequestType::ALLOCATE && count($xmlDoc->duration) > 0) {
			   throw new ParsingException('duration only allowed on allocate requests');
			}
			//for now only allow one duration
			if (count($xmlDoc->duration) > 1) {
				throw new ParsingException('only one duration is allowed per allocate request');
			}
			
			
			//this loops to support multiple dates in one request possibly in the future.  Functionality unfinished
			$count = 0;
			foreach ($xmlDoc->date as $date) {
				$count += 1;		
				$startDate;
				$endDate;
				$tempResource = clone $resource;		
				
				if ($date == '') {
					throw new ParsingException('empty date');
				}
				
				//$startDate = DateTime::createFromFormat(DATE_RFC3339, $date); - old format
				$startDate = DateTime::createFromFormat('Y-m-d H:i:s T', $date);
				
				if ($startDate === false) {
					throw new ParsingException('invalid date format');
				}
				
				$timezoneOffsetSeconds = timezone_offset_get($startDate->getTimezone(), $startDate);
								
				if (DEBUG_MODE) {
					echo 'The timezone name passed in is ' . $startDate->getTimezone()->getName() . '<br/>';
					echo 'The timezone offset for the request passed in is ' . $timezoneOffsetSeconds/3600.0 . ' hours <br/>';
				}
								
				//set the current time to proper timezone
				$currentTime->setTimezone($startDate->getTimezone());
				
				if (DEBUG_MODE) {
					echo 'current time received from server after conversion is: ' . $currentTime->format(DATE_RFC3339) . '<br/>';
				}
				
				global $currentDay;
				$currentDay = clone $startDate;
								
				if (DST_ADJUST) {
					//need to possibly adjust timezone
					if (goingOutOfDST()) {
						//switched out of DST - event being inserted is after november 5 2am
						echo 'current time is in DST and event being added is not in DST. Adding 1 hour to event<br/>';
						$startDate->add(new DateInterval('PT1H'));
						
						//set the current day again
						$currentDay = clone $startDate;
					} else if (goingIntoDST()) {
						//switched into DST - event being inserted is after march 12 2am
						echo 'current time is not in DST and event being added is in DST.  Removing 1 hour from event<br/>';
						$startDate->sub(new DateInterval('PT1H'));
						
						//set the current day again
						$currentDay = clone $startDate;
					} else {
						//in same timezone so nothing has to be done
						echo 'current time is in same DST as event being added so no DST ajustment needed<br/>';
					}
				}
				
				if ($tempResource->getIdentifier() == ' - any' || $tempResource->getIdentifier() == ' - morning') {
					//start time is start of day - ignore time set on request
					/*if ($tempResource->getIdentifier() == ' - morning' && $startDate >= $afternoon) {
						throw new ParsingException('morning event cannot have a start time in the afternoon');
					}*/
					$startDate = startOfDay();
					if (!ALLOW_INSERT_INTO_PAST && $startDate < $currentTime) {
						$startDate = clone $currentTime;						
						$startDate->setTime(startOfDay()->format('H'), startOfDay()->format('i'), startOfDay()->format('s'));
					}
				} else if ($tempResource->getIdentifier() == ' - afternoon') {
					//start time is start of afternoon - ignore time set on request
					/*if ($startDate < $afternoon) {
						throw new ParsingException('afternoon event cannot have a start time in the morning');
					}*/
					$startDate = afternoon(); //set to 12PM
					if (!ALLOW_INSERT_INTO_PAST && $startDate < $currentTime) {
						$startDate = clone $currentTime;						
						$startDate->setTime(afternoon()->format('H'), afternoon()->format('i'), afternoon()->format('s'));
					}
				} else {
					//non movable event
					if (!ALLOW_INSERT_INTO_PAST && $startDate < $currentTime) {
						throw new ParsingException('cannot insert an event into calendar that occurs in the past.  Change your time.');
					}
				}
				
				switch($request->getRequestType()) {
                    case RequestType::ALLOCATE: {
						//allocation has duration

						if ($xmlDoc->duration == '') {  //this would also be caught by the if below for filter by int
							throw new ParsingException('no duration to match with date!');
						}
						
						if (!filter_var($xmlDoc->duration, FILTER_VALIDATE_INT) === false && intval($xmlDoc->duration) <= 0) {
							throw new ParsingException('invalid duration specified');
						}
						
						$endDate = clone $startDate;
						$endDate->add(new DateInterval('PT'.$xmlDoc->duration.'M'));
						//get current time from server						
						
						if (($startDate <= endOfDay() && $endDate > endOfDay()) || $endDate > endOfDay() || $startDate < startOfDay()) {
							//these are special "outside normal time" events
						    //should never get "anytime", "morning", or "afternoon" events in here
							
							
							if ($tempResource->getIdentifier() != '') {
								throw new OtherException('received a movable event with a start time outside normal business hours.  Internal error');
							} else {
								if (!ALLOW_INSERT_TWILIGHT) {
									throw new ParsingException('start time for allocation not within valid time range of ' . startOfDay()->format(DATE_RFC3339) . ' to ' . endOfDay()->format(DATE_RFC3339));
								}
								//set these events as special 'twilight' events
								$tempResource->setTwilightStatus(true);
							}							
						}                    					
						
						$tempResource->setStartDate($startDate);
						$tempResource->setEndDate($endDate);			
						break;					
					} case RequestType::FREE: {
						//check if date in range 9AM - 5PM, otherwise invalid
						$endDate = clone $startDate;
						$endDate->add(new DateInterval('PT1S')); //just add 1 second for now to end date
						
						if (!ALLOW_DELETE_TWILIGHT) {
							if (Services::datesOverlap($startDate, $endDate, startOfDay(), endOfDay()) == 0) {
								throw new ParsingException('time for freeing not within valid time range of ' . startOfDay()->format(DATE_RFC3339) . ' to ' . endOfDay()->format(DATE_RFC3339));
							}
						}
						
						$tempResource->setStartDate($startDate);
						$tempResource->setEndDate($endDate);
						break;
					} default: {
						throw new ParsingException('Invalid RequestType set!  Internal Error.');
					}
				}
				
				$request->addResource($tempResource);	
			}		 		  
			
			$request->setValid(true);
			return $request;
		}	   	
		
		return new RSGCRequest();
	}

};

class RequestType {
	const __default = self::NONE;

	const NONE = 0;
	const ALLOCATE = 3;
	const FREE = 4;
};

class EventType {
	const __default = self::NONE;
	
	const NONE = 0;
	const ANYTIME = 1;
	const MORNING = 2;
	const AFTERNOON = 3;
};

class RSGCRequest {

	function __construct($valid = false, $action = RequestType::NONE) {
		$this->_isValid = $valid;
		$this->_action = $action;
		$this->_resourceList = array();
	}

	function __destruct() { }      

	function setValid($valid) { $this->_isValid = $valid; }
	function isValid() { return $this->_isValid; }

	function getRequestType() { return $this->_action; }
	function setRequestType($type) { $this->_action = $type; }

	function addResource($resource) { 
		if (DEBUG_MODE) {
			echo 'adding a resource to the request <br/>';
			echo 'start date: ' . $resource->getStartDate()->format(DATE_RFC3339) . '<br/>';
			echo 'end date: ' . $resource->getEndDate()->format(DATE_RFC3339) . '<br/>';
		}
		$this->_resourceList[] = $resource; 
	}

	function getResourceList() { return $this->_resourceList; }	
	
	function createEvent($calendarId, $event) {		
		return new Google_Service_Calendar_Event(array(
		'summary' => $event->getSummary(),
		'location' => $event->getLocation(),
		'description' => $event->getDescription(),
		'attendees' => array(
			array('email' => $calendarId), //default to calendar id for now
		 ),
		'start' => array(
		'dateTime' => $event->getStart()->getDateTime(), //format: 2015-05-28T09:00:00+00:00',
			//'timeZone' => 'America/Chicago',
			//'timeZone' => $event->getTimezone()->getName(),
		),
		'end' => array(
		'dateTime' => $event->getEnd()->getDateTime(), //format: 2015-05-28T17:00:00+00:00',
			//'timeZone' => 'America/Chicago',
			//'timeZone' => $event->getTimezone()->getName(),
		),
		//'colorId' => $firstResource->getColor(),  DEFAULT COLOR FOR NOW TO MATCH THE CALENDAR COLOR
		'guestsCanInviteOthers' => true,
		'guestsCanSeeOtherGuests' => true,
		'reminders' => array(
			'useDefault' => false,
			'overrides' => array(
			  array('method' => 'email', 'minutes' => 30), //default to 30 minutes for now
			  array('method' => 'popup', 'minutes' => 30), //default to 30 minutes for now
			),
		  ),
		));
	}
	
	function findSlotForEvent($newDayEventsArray, $event, $startTime, $cutoffTime) {
			   //calculate duration of event

			   $eventStartDate = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
			   $eventEndDate = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());
			   
			   $duration = date_diff($eventEndDate, $eventStartDate, true);
			   if (DEBUG_MODE) {
				  echo 'start date of event: ' . $eventStartDate->format(DATE_RFC3339) . '<br/>';
				  echo 'end date of event: ' . $eventEndDate->format(DATE_RFC3339) . '<br/>';
		  	      echo 'duration of event we\'re looking for a slot for: ' . $duration->format('%R%h%R%i%R%s') . '<br/>';
			   }
			   $slotFound = false;
			   //reset time to 9AM
			   $currentDateTime = clone $startTime;
			   
			  //compare against all the available slots using a greedy algorithm (first available slot is where I put it, even if that isn't the optimal slot)			 
			  foreach ($newDayEventsArray as $newEvent) {
				  $newEventStartDate = DateTime::createFromFormat(DATE_RFC3339, $newEvent->getStart()->getDateTime());
                  
				  $slotSize = date_diff($newEventStartDate, $currentDateTime, true);
				  if (DEBUG_MODE) {
					  echo 'comparing to event with start time: ' . $newEventStartDate->format(DATE_RFC3339) . '<br/>';
					  echo 'slot size: ' . $slotSize->format('%R%h%R%i%R%s') . '<br/>';
					  echo 'duration: ' . $duration->format('%R%h%R%i%R%s') . '<br/>';
				  }
				  if (date_create('@0')->add($slotSize)->getTimestamp() >= date_create('@0')->add($duration)->getTimestamp()) {
					  //found a slot for this event
					  $slotFound = true;
					  if (DEBUG_MODE) {
						  echo 'found a slot for event.  Stopping search<br/>';
					  }
					  break;
				  } else {
					  if (DEBUG_MODE) {
						  echo 'did not find a slot yet.  Moving marker to end of event and continuing<br/>';
					  }
					  //did not find a slot for this event.  "move the chains" and continue
					  $currentDateTime = DateTime::createFromFormat(DATE_RFC3339, $newEvent->getEnd()->getDateTime());
				  }
			  }
			  
			  if (!$slotFound) {
				  //check currentDateTime once more and compare to end of day
				  $slotSize = date_diff($cutoffTime, $currentDateTime, true);
				  if (DEBUG_MODE) {
					  echo 'slot size: ' . $slotSize->format('%R%h%R%i%R%s') . '<br/>';
				  }
				  if (date_create('@0')->add($slotSize)->getTimestamp() >= date_create('@0')->add($duration)->getTimestamp()) {
					  $slotFound = true;
					  if (DEBUG_MODE) {
						  echo 'found a slot for event after the end of all existing events.  Or this is the first event added<br/>';
					  } 
				  } else {
					  if (DEBUG_MODE) {
						  echo 'could not find a slot at the very end of the range.<br/>';
					  }
				  }
			  }
				  
			  if (!$slotFound || $currentDateTime >= $cutoffTime) {
				  if (DEBUG_MODE) {
					  if ($slotFound && $currentDateTime >= $cutoffTime) {
						  echo 'current time is past end of day which is not allowed<br/>';
					  } else if (!$slotFound) {
						  echo 'did not find a slot for event<br/>';
					  }
				  }
				  if (!ALWAYS_INSERT) {
					  throw new OtherException('algorithm failed to find an available slot for an existing event.');
				  } else {
					 if (DEBUG_MODE) {
						 echo 'inserting anyways<br/>';
					 }
				     return false;
				  }
			  }
			  			 
			  $event->getStart()->setDateTime($currentDateTime->format(DATE_RFC3339));
			  $endTime = clone $currentDateTime;
			  $endTime->add($duration);
			  echo 'Duration: ' . $duration->format('%R%h%R%i%R%s') . '<br/>';
			  $event->getEnd()->setDateTime($endTime->format(DATE_RFC3339));
			  if (DEBUG_MODE) {
				  echo 'the event times after being fit into a slot: begin: ' . $currentDateTime->format(DATE_RFC3339) . ' end: ' . $endTime->format(DATE_RFC3339) . '<br/>';
			  }
			  return true;
	}
	
	function adjustEvents2($eventToBeAdded, $currentDayEventsArray, &$newDayEventsArray) {

	   global $currentTime;
	   $nonMovableEvents = array();
	   $anytimeEvents = array();
	   $morningEvents = array();
	   $afternoonEvents = array();
	   $anytimeEventType = false;
	   $morningEventType = false;
	   $afternoonEventType = false;
	   $nonMovableEventType = false;
	   
	   //determine the type of event attempting to be added
	   if (strpos($eventToBeAdded->getSummary(), ' - any') !== false) {
		   //anytime event
		   $anytimeEventType = true;
	   } else if (strpos($eventToBeAdded->getSummary(), ' - morning') !== false) {
		   //morning event
		   $morningEventType = true;
	   } else if (strpos($eventToBeAdded->getSummary(), ' - afternoon') !== false) {
		   //afternoon event
		   $afternoonEventType = true;
	   } else {
		   //non movable event
		   $nonMovableEventType = true;
	   }
	   
	   
       //start by going through all the events and sort them
	   foreach ($currentDayEventsArray as $event) {
		    if (strpos($event->getSummary(), ' - any') !== false) {
				//anytime event
				if (DEBUG_MODE) {
					echo 'found anytime event in calendar<br/>';
				}
				$anytimeEvents[/*DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())*/] = clone $event;
			} else if (strpos($event->getSummary(), ' - morning') !== false) {
				//morning event
				if (DEBUG_MODE) {
					echo 'found morning event in calendar<br/>';
				}
		        $morningEvents[/*DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())*/] = clone $event;
			} else if (strpos($event->getSummary(), ' - afternoon') !== false) {
				//afternoon event
				if (DEBUG_MODE) {
					echo 'found afternoon event in calendar<br/>';
				}
				$afternoonEvents[/*DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())*/] = clone $event;
			} else {
				//non movable event
				if (DEBUG_MODE) {
					echo 'found non movable event in calendar<br/>';
				}
				$nonMovableEvents[/*DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())*/] = clone $event;
				
				if (DEBUG_MODE) {
					echo 'adding an existing non-movable event to the timeline</br>';
				}
				//since these are non movable add them into the new events array immediately
				$newDayEventsArray[/*DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())*/] = clone $event;
				//don't need to sort here as they should already be sorted
			}
	   }
	   
	    $firstDate = DateTime::createFromFormat(DATE_RFC3339, $eventToBeAdded->getStart()->getDateTime()); 
		$lastDate = DateTime::createFromFormat(DATE_RFC3339, $eventToBeAdded->getEnd()->getDateTime());
		//add padding to last date
		$lastDate->add(new DateInterval('PT' . defaultEventPadding . 'M'));
		$eventToBeAdded->getEnd()->setDateTime($lastDate->format(DATE_RFC3339));
        $duration = date_diff($lastDate, $firstDate, true);	 	  
        $start = startOfDay();
        if (!ALLOW_INSERT_INTO_PAST) {
			$start = clone $currentTime;
			$startDate->setTime(startOfDay()->format('H'), startOfDay()->format('i'), startOfDay()->format('s'));			
		}
		
	   $failedInsert = false;

	   if ($nonMovableEventType) {
		   //new event to be added is non movable event type.  Attempt to add it into the calendar
		   foreach ($nonMovableEvents as $event) {
			  $currentStartDateNonMovable = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
		      $currentEndDateNonMovable = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());
		      
			  if (Services::datesOverlap($firstDate, $lastDate, $currentStartDateNonMovable, $currentEndDateNonMovable) != 0) {

				 if (!ALWAYS_INSERT) {
				    throw new OtherException('event trying to be added overlaps with an existing event that cannot be moved.');
				 } else {
					 $failedInsert = true;
					 if (DEBUG_MODE) {
					    echo 'non movable event trying to be inserted overlaps with existing event. Inserting anyway<br/>';
				     }
				 }
              }
		   }
		   if (DEBUG_MODE) {
			   echo 'inserting new non-movable event into timeline</br>';
		   }
		   //if it got here it didn't overlap anything important so go ahead and add and then resort
		   //this will be overwritten at the bottom in the case of a failed insert, which is fine
		   $newDayEventsArray[] = clone $eventToBeAdded;
		   Services::sortArray($newDayEventsArray);
	   } else if ($morningEventType) {
		   $morningEvents[] = clone $eventToBeAdded;
		   Services::sortArray($morningEvents);
	   } else if ($afternoonEventType) {
		   $afternoonEvents[] = clone $eventToBeAdded;
		   Services::sortArray($afternoonEvents);
	   } else if ($anytimeEventType) {
		   $anytimeEvents[] = clone $eventToBeAdded;
		   Services::sortArray($anytimeEvents);
	   }

		   
		   //NOTE: if the current time is past all the events then nothing will happen
		   
		   //now attempt to add all the existing events in using a greedy algorithm - this won't catch all possible combinations
		   //and ones that it fails to catch will require manual adjustment
		   //TODO: have system report an exception for the case where it is unable to manually schedule
		   //but also schedule the event regardless at some unspecified time (like 5am) so it is noticeable on the calendar
		   //$currentDateTime;
		   	   
			//NOTE: if not allowing to insert events into the past then the $start will likely be wrong
			//TODO: need to figure out what to do with the event that straddles the start, to include or not?
		   if (!$failedInsert) {
			   if (DEBUG_MODE) {
				   echo 'attempting to find slots for morning events<br/>';
			   }
			   $morningTimedEvents = array();
			   foreach ($newDayEventsArray as $event) {
				   $eventStartTime = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
				   if ($eventStartTime < afternoon()) {
					   $morningTimedEvents[] = clone $event;
				   }
			   }
			   foreach ($morningEvents as $event) {
				   //need to overwrite to be 9AM so they get inserted properly
				   $eventStartDate = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
			       $eventEndDate = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());			   
			       $currentDuration = date_diff($eventEndDate, $eventStartDate, true);
				   $eventStartDate = clone $start;
				   $eventEndDate = clone $eventStartDate;
				   $eventEndDate->add($currentDuration);
				   $event->getStart()->setDateTime($eventStartDate->format(DATE_RFC3339));
				   $event->getEnd()->setDateTime($eventEndDate->format(DATE_RFC3339));
				   //only compare against events that are before afternoon
				  
				   if ($this->findSlotForEvent($morningTimedEvents, $event, $start, afternoon())) {
					   $morningTimedEvents[] = clone $event;
					   $newDayEventsArray[] = clone $event;
					   Services::sortArray($morningTimedEvents);
					   Services::sortArray($newDayEventsArray);
				   } else {
					   $failedInsert = true;
					   break;

				   }
			   }
               
		   }

		   if (!$failedInsert) {
			   if (DEBUG_MODE) {
				   echo 'attempting to find slots for afternoon events<br/>';
			   }
			   $afternoonTimedEvents = array();
			   foreach ($newDayEventsArray as $event) {
				   $eventStartTime = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
				   if ($eventStartTime >= afternoon()) {
					   $afternoonTimedEvents[] = clone $event;
				   }
			   }
			   foreach ($afternoonEvents as $event) {
				   //need to overwrite so they are inserted correctly
				   $eventStartDate = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
			       $eventEndDate = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());			   
			       $currentDuration = date_diff($eventEndDate, $eventStartDate, true);
				   $eventStartDate = clone ($start > afternoon() ? $start : afternoon());
				   $eventEndDate = clone $eventStartDate;
				   $eventEndDate->add($currentDuration);
				   $event->getStart()->setDateTime($eventStartDate->format(DATE_RFC3339));
				   $event->getEnd()->setDateTime($eventEndDate->format(DATE_RFC3339));
				   //only compare against events that are after afternoon TODO
				   if ($this->findSlotForEvent($afternoonTimedEvents, $event, $start > afternoon() ? $start : afternoon(), endOfDay())) {
					   $afternoonTimedEvents[] = clone $event;
					   $newDayEventsArray[] = clone $event;
					   Services::sortArray($afternoonTimedEvents);
					   Services::sortArray($newDayEventsArray);
				   } else {
					   $failedInsert = true;
					   break;
				   }
			   }
		   }
		   
		   if (!$failedInsert) {
			   if (DEBUG_MODE) {
				   echo 'attempting to find slots for anytime events<br/>';
			   }
			   foreach ($anytimeEvents as $event) {
				   $eventStartDate = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
			       $eventEndDate = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());			   
			       $currentDuration = date_diff($eventEndDate, $eventStartDate, true);
				   $eventStartDate = clone $start;
				   $eventEndDate = clone $eventStartDate;
				   $eventEndDate->add($currentDuration);
				   $event->getStart()->setDateTime($eventStartDate->format(DATE_RFC3339));
				   $event->getEnd()->setDateTime($eventEndDate->format(DATE_RFC3339));
				   if ($this->findSlotForEvent($newDayEventsArray, $event, $start, endOfDay())) {
					   $newDayEventsArray[] = clone $event;
					   Services::sortArray($newDayEventsArray);
				   } else {
					   $failedInsert = true;
					   break;
				   }
			   }
		   }
		   
		   if ($failedInsert && ALWAYS_INSERT) {
			   if (DEBUG_MODE) {
				   echo 'failed to insert/rearrange events.  But supposed to always insert so adding event<br/>';
			   }
			   //failed to find a slot for an event - add new event anyway
			   $newDayEventsArray = array(); //$currentDayEventsArray;
			   if ($morningEventType || $afternoonEventType || $anytimeEventType) {
			       //set time to something dumb so it is noticeable
				   //doesn't currently support non 1-hour timezone offsets (e.g. 1.5hrs) .  That's ok for now
				   $eventToBeAdded->getStart()->setDateTime($firstDate->setTime(dumbTime()->format('H'), dumbTime()->format('i'))->format(DATE_RFC3339));
				   $eventToBeAdded->getEnd()->setDateTime($firstDate->add($duration)->format(DATE_RFC3339));
				   $newDayEventsArray[] = clone $eventToBeAdded;
			   } else {
				   $newDayEventsArray[] = clone $eventToBeAdded;
			   }
			   //Services::sortArray($newDayEventsArray);
			   return false;
		   }
		   
	   return true;
	}

	//do the action
	function performRequest(&$service) {
		global $currentTime;
		global $currentDay;
		//ASSUME ALL EVENTS ARE SORTED IN REQUEST ALREADY
		//NOTE: only works with one event right now.  Not multiple events in a request
		if (OFFLINE_MODE || !is_null($service)) {
			if ($this->_isValid && $this->_action != RequestType::NONE) {								
				if (!$this->_resourceList) {
					if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
						$log = $currentTime->format(DATE_RFC3339) . ": Internal error: attempted to perform request with no resources.  Nothing was changed on calendar.\n";							
						file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
					}
					throw new OtherException('Internal error: attempted to perform request with no resources.  Nothing was changed on calendar.');
				}					
				
				$firstResource = $this->_resourceList[0];
				$currentTime->setTimezone($firstResource->getStartDate()->getTimezone());
				$currentDay = clone $firstResource->getstartDate();
				
				$firstDate = $firstResource->getStartDate();
				$calendarId = $firstResource->getName(); //just use calendar id/name from first date
				
				//check to see if that calendar exists
				if (!OFFLINE_MODE) {
					$calendarList = $service->calendarList->listCalendarList();
					
					$foundMatchingCalendar = false;
					foreach ($calendarList->getItems() as $calendarListEntry) {	
                        if (DEBUG_MODE) {
							echo 'calendar id: ' . $calendarListEntry->getId() . '<br/>';
						}					
						if ($calendarListEntry->getId() == $calendarId) {
							$foundMatchingCalendar = true;
							break;
						}
					}
					if (!$foundMatchingCalendar) {
						if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
							$log = $currentTime->format(DATE_RFC3339) . ": No calendar found that matches the id " . $calendarId . " that was provided.  No changes made to calendar.\n";
							file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
						}
						throw new OtherException('no calendar found that matches the id provided.  Calendar not changed.');
					}					   
				}
				
				$lastDate = $this->_resourceList[0]->getEndDate();

				if (!OFFLINE_MODE) {
					//note: the free and allocate sections would need to be redone if allowing more than one allocate/free in a single request
					if ($this->_action == RequestType::FREE) {
						//get the events that overlap with the event
						$currentEvents = $service->events->listEvents($calendarId, array('timeMin' => $firstDate->format(DATE_RFC3339), 'timeMax' => $lastDate->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
						
						$eventArray = $currentEvents->getItems();

						//if (DEBUG_MODE) {
						//	echo 'events received from google: ' . print_r($eventArray) . '<br/>';
						//}
												
						if (!(!$eventArray)) {
							if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
							   $log = $currentTime->format(DATE_RFC3339) . "Current Events retrieved from Google before freeing:\n";
							   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
							   Services::LogEventsToFile($currentEvents);
							}							
							
							$event = $eventArray[0];	

							if (count($eventArray) > 1) {
								if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
									$log = $currentTime->format(DATE_RFC3339) . ": Problem found before freeing.  Calendar not changed. More than one event found during the specified time for freeing.  Service only deletes the first one in the array, which may not be the one expected.  Service expects no overlapping events when freeing.  Events found were:\n";							
									file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									Services::LogEventsToFile($eventArray);
								}
								
								throw new OtherException('Problem found before freeing.  Calendar not changed. More than one event found during the specified time.  This is not allowed.  Manually correct this in calendar');
							}
		
							if (DEBUG_MODE) {
								echo 'removing event<br/>';
							}
							
							//delete event
							$service->events->delete($calendarId, $event->getId());
				
							//check events from google again to verify they were deleted - this should return count - 1
							$currentEventsPostFree = $service->events->listEvents($calendarId, array('timeMin' => $firstDate->format(DATE_RFC3339), 'timeMax' => $lastDate->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
						    
							$resultEventsArray = $currentEventsPostFree->getItems();
							if (count($resultEventsArray) != count($eventArray) - 1) {
								//the event was not deleted like expected
								if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
									$log = $currentTime->format(DATE_RFC3339) . ": After freeing, the event that should have been deleted in Google Calendar was not. Calendar changed. Events found were:";
									file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									Services::LogEventsToFile($resultEventsArray);
								}
								
								throw new OtherException('After freeing, the event that should have been deleted in Google Calendar was not. Calendar changed.');
							}

						} else {
							if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
								$log = $currentTime->format(DATE_RFC3339) . ": No event found in Google Calendar to free. Request from setmore was bad.  Calendar not changed.  Date trying to free was: " . $firstDate->format(DATE_RFC3339) . " end date was: " . $lastDate->format(DATE_RFC3339) . "\n";
								file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
							}
							throw new OtherException('No event to be freed.  Request from setmore was bad. Calendar not changed.');
						}
					} else if ($this->_action == RequestType::ALLOCATE) {							
						$currentDayEvents = $service->events->listEvents($calendarId, array('timeMin' => startOfDay()->format(DATE_RFC3339), 'timeMax' => endOfDay()->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
						$newEventsArray = array();
						$tempCurrentDayEventsArray = $currentDayEvents->getItems();

		                if (count($tempCurrentDayEventsArray) == 0) {
							//add padding since it won't ever go into the other code
		                    $lastDate->add(new DateInterval('PT' . defaultEventPadding . 'M'));
						}
						//if (DEBUG_MODE) {
						//	echo 'events received from google: ' . print_r($tempCurrentDayEventsArray) . '<br/>';
						//}
										
						$currentDayEventsArray = array();
						//go through and remove all the 'twilight' events if there are any since they don't count during adjustment
						for ($arrayCounter = 0; $arrayCounter < count($tempCurrentDayEventsArray); $arrayCounter++) {
						   $eventStart = DateTime::createFromFormat(DATE_RFC3339, $tempCurrentDayEventsArray[$arrayCounter]->getStart()->getDateTime());
						   $eventEnd = DateTime::createFromFormat(DATE_RFC3339, $tempCurrentDayEventsArray[$arrayCounter]->getEnd()->getDateTime());
						   if (!(($eventStart <= endOfDay() && $eventEnd > endOfDay()) || $eventEnd > endOfDay() || $eventStart < startOfDay())) {
							   //event is not a "twilight" event
							   $currentDayEventsArray[] = $tempCurrentDayEventsArray[$arrayCounter];
						   }							
						}
						
						$eventArray = array(
						'summary' => $firstResource->getLocation() . $firstResource->getIdentifier(),
						'location' => $firstResource->getLocation(),
						'description' => $firstResource->getDescription() . ' ' . $firstResource->getURL(),
						'attendees' => array(
							array('email' => $calendarId), //default to calendar id for now
						 ),
						'start' => array(
						'dateTime' => $firstDate->format(DATE_RFC3339), //format: 2015-05-28T09:00:00-07:00',
							//'timeZone' => 'America/Chicago',
							//'timeZone' => currentDay()->getTimezone()->getName(),
						),
						'end' => array(
						'dateTime' => $lastDate->format(DATE_RFC3339), //format: 2015-05-28T17:00:00-07:00',
							//'timeZone' => 'America/Chicago',
							//'timeZone' => currentDay()->getTimezone()->getName(),
						),
						//'colorId' => $firstResource->getColor(),  DEFAULT COLOR FOR NOW TO MATCH THE CALENDAR COLOR
						'guestsCanInviteOthers' => true,
						'guestsCanSeeOtherGuests' => true,
						'reminders' => array(
							'useDefault' => false,
							'overrides' => array(
							  array('method' => 'email', 'minutes' => 30), //default to 30 minutes for now
							  array('method' => 'popup', 'minutes' => 30), //default to 30 minutes for now
							),
						  ),
						);
						
						$optionalParameters = array('sendNotifications' => false); //set to false for now until I can test more
						
						$event = new Google_Service_Calendar_Event($eventArray);
					
					    //TODO: add code so that not deleting events that don't move at all
						//only deleting events that move
						$failureInAdjustment = false;

					    if (count($currentDayEventsArray) > 0 && !$firstResource->getTwilightStatus()) {

							   $eventsArray = array();
							   if (!ALLOW_INSERT_INTO_PAST) {
							       foreach ($currentDayEventsArray as $event) {
									   if (DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime()) > $currentTime) {
										   $eventsArray[] = clone $event;
									   }
								   }
							   } else {
								   $eventsArray = $currentDayEventsArray;
							   }
							   							  
							   
							   	if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
								   $log = $currentTime->format(DATE_RFC3339) . ": Current Events retrieved from Google before allocate:\n";
								   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
								   Services::LogEventsToFile($currentDayEventsArray);
								}
								
								
							    //if this fails it will just return the new event, so don't delete the old events
							    $failureInAdjustment = !$this->adjustEvents2($event, $eventsArray, $newEventsArray);
							
						   
						    if (count($newEventsArray) > 0) {
								//TODO: make this smarter so that if it encounters errors it will attempt to undo the changes to the calendar
								//and return it to normal
								
								if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
								   $log = $currentTime->format(DATE_RFC3339) . ": Events attempting to be added to Google:\n";
								   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
								   Services::LogEventsToFile($newEventsArray);
								}
								
								//add new events first so that if something goes wrong inserting new events we still have all the old ones

								foreach ($newEventsArray as $newEvent) {
									if (DEBUG_MODE) {
										echo 'adding event with start ' . $newEvent->getStart()->getDateTime() . ' and end ' . $newEvent->getEnd()->getDateTime() . '<br/>';
									}
									//add all the events now that they have been moved around									
									$resultEvent = $service->events->insert($calendarId, $this->createEvent($calendarId, $newEvent), $optionalParameters);
																
									//compares event that service tried to add with one that was added
									if (($resultEvent->getSummary() != $newEvent->getSummary()) ||
									    ($resultEvent->getDescription() != $newEvent->getDescription()) ||
										($resultEvent->getLocation() != $newEvent->getLocation()) ||
										(DateTime::createFromFormat(DATE_RFC3339, $resultEvent->getStart()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $newEvent->getStart()->getDateTime())) ||
										(DateTime::createFromFormat(DATE_RFC3339, $resultEvent->getEnd()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $newEvent->getEnd()->getDateTime()))) {
										//something was not the same when it should have been
										//in this case, continue inserting anyway, don't throw exception for now
										if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
											$log = $currentTime->format(DATE_RFC3339) . ": After allocate, the event added did not match with what was tried to be added.  Calendar was changed. Event tried to add:\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
											Services::LogEventsToFile(array($event));
											$log2 = "\nEvent that was found:\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
											Services::LogEventsToFile(array($resultEvent));
										}
									}
									
									//compare event that used to be there with what was added? What's the value there?  I wouldn't know if it was wrong
									//but I CAN compare the number of events to the old number.  It should be +1
								}
								
								$currentDayEventsPostAllocate = $service->events->listEvents($calendarId, array('timeMin' => startOfDay()->format(DATE_RFC3339), 'timeMax' => endOfDay()->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
								$currentDayEventsPostAllocateArray = $currentDayEventsPostAllocate->getItems();
							
								//not sure if this can ever occur based on the API documentation - this would be real bad if this happens
								//also only works here for one event at a time inserted
								//this doesn't work right now
								/*$eventCount = $failureInAdjustment ? (count($currentDayEventsArray) + 1) : (count($currentDayEventsArray) * 2 + 1);
								if (count($currentDayEventsPostAllocateArray) != $eventCount) {
									if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
										$log = $currentTime->format(DATE_RFC3339) . ": After allocate, the number of events are wrong.  Calendar changed. Events were:\n";
										file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
										Services::LogEventsToFile($currentDayEventsArray);
										$log2 = "\nNew Events are:\n";
										file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
										Services::LogEventsToFile($currentDayEventsPostAllocateArray);
									}
									throw new OtherException('After allocate, the number of events in the calendar are wrong. Calendar changed.');;
								}*/
								
								if (!$failureInAdjustment) {
									if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
									   $log = $currentTime->format(DATE_RFC3339) . ": Events attempting to be deleted from Google:\n";
									   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									   Services::LogEventsToFile($eventsArray);
									}
									
									//only delete old events if everything went correctly, since system returns only new event if problem occurred
									foreach ($eventsArray as $oldEvent) {
										if (DEBUG_MODE) {
											echo 'deleting event <br/>';
										}
										//delete all the old events now
										$service->events->delete($calendarId, $oldEvent->getId());
										//for performance reasons, not going to verify after every delete.  Just verify once after all the deleting is done
									}
									
									$currentDayEventsPostAllocateDelete = $service->events->listEvents($calendarId, array('timeMin' => startOfDay()->format(DATE_RFC3339), 'timeMax' => endOfDay()->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
									$currentDayEventsPostAllocateDeleteArrayTemp = $currentDayEventsPostAllocateDelete->getItems();
									
									$currentDayEventsPostAllocateDeleteArray = array();
									
									//go through and remove all the 'twilight' events if there are any since they don't count during adjustment
									for ($arrayCounter = 0; $arrayCounter < count($currentDayEventsPostAllocateDeleteArrayTemp); $arrayCounter++) {
									   $eventStart = DateTime::createFromFormat(DATE_RFC3339, $currentDayEventsPostAllocateDeleteArrayTemp[$arrayCounter]->getStart()->getDateTime());
									   $eventEnd = DateTime::createFromFormat(DATE_RFC3339, $currentDayEventsPostAllocateDeleteArrayTemp[$arrayCounter]->getEnd()->getDateTime());
									   if (!(($eventStart <= endOfDay() && $eventEnd > endOfDay()) || $eventEnd > endOfDay() || $eventStart < startOfDay())) {
										   //event is not a "twilight" event
										   $currentDayEventsPostAllocateDeleteArray[] = $currentDayEventsPostAllocateDeleteArrayTemp[$arrayCounter];
									   }							
									}
									
									if (count($currentDayEventsPostAllocateDeleteArray) != count($newEventsArray)) {
										if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
											$log = $currentTime->format(DATE_RFC3339) . ": After allocate delete, the number of events are wrong.  Calendar changed. Events were:\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
											Services::LogEventsToFile($currentDayEventsArray);
											$log2 = "\nNew Events are:\n";
											file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
											Services::LogEventsToFile($currentDayEventsPostAllocateDeleteArray);
										}
										
										throw new OtherException('After allocate delete, the number of events in the calendar are wrong. Calendar changed.');;
									}
									
									$foundIssue = false;
									//assume both arrays are sorted
									for( $i = 0; $i < count($newEventsArray); $i++ ) {
										$postEvent = $currentDayEventsPostAllocateDeleteArray[$i];
										$newPostEvent = $newEventsArray[$i];
										
										if (($postEvent->getSummary() != $newPostEvent->getSummary()) ||
											($postEvent->getDescription() != $newPostEvent->getDescription()) ||
											($postEvent->getLocation() != $newPostEvent->getLocation()) ||
											(DateTime::createFromFormat(DATE_RFC3339, $postEvent->getStart()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $newPostEvent->getStart()->getDateTime())) ||
											(DateTime::createFromFormat(DATE_RFC3339, $postEvent->getEnd()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $newPostEvent->getEnd()->getDateTime()))) {
											//something was not the same when it should have been
										
											if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
												$log = $currentTime->format(DATE_RFC3339) . ": After allocate delete, the event added did not match with what was tried to be added.  Calendar was changed. Event tried to add:\n";
												file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
												Services::LogEventsToFile(array($postEvent));
												$log2 = "\nEvent that was found:\n";
												file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
												Services::LogEventsToFile(array($newPostEvent));
											}
											
											$foundIssue = true;
										}
									}
									if ($foundIssue) {
										throw new OtherException('After allocate delete, the event added did not match with what was tried to be added. Calendar changed.');
									}
									
								} else {
									if (LOGGER_ON && ((LOGGING_LEVEL & 4) == 4)) {
									  if (ALWAYS_INSERT) {
										$log = $currentTime->format(DATE_RFC3339) . ": No events being deleted from Google after allocate because the system could not reschedule the events satisfactorily. However, new event still added because ALWAYS_INSERT is turned on. Events were:\n";
									  } else {
										$log = $currentTime->format(DATE_RFC3339) . ": No events being deleted from Google after allocate because the system could not reschedule the events satisfactorily. Events were:\n";
									  }
									  file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
								  	  Services::LogEventsToFile($currentDayEventsArray);
									  $log2 = "\nNew Events are:\n";
									  file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
									  Services::LogEventsToFile($currentDayEventsPostAllocateArray);
									}
								}
								

						   } else {
							   if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
								   $log = $currentTime->format(DATE_RFC3339) . ": Internal error: no events in system to insert after rescheduling.  Calendar not changed.\n";
								   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
							   }
							   throw new OtherException('Internal error: no events to insert.  Calendar not changed.');
						   }
						} else {
							if (DEBUG_MODE) {
								echo 'adding event for first time this day with start ' . $event->getStart()->getDateTime() . ' and end ' . $event->getEnd()->getDateTime() . '<br/>';
							}
							
							if (LOGGER_ON && ((LOGGING_LEVEL & 2) == 2)) {
							   $log = $currentTime->format(DATE_RFC3339) . ": First event attempting to be added for the day:\n";
							   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
							   Services::LogEventsToFile(array($event));
							}
							
							$resultEvent = $service->events->insert($calendarId, $event, $optionalParameters);
							
							if (($resultEvent->getSummary() != $event->getSummary()) ||
								($resultEvent->getDescription() != $event->getDescription()) ||
								($resultEvent->getLocation() != $event->getLocation()) ||
								(DateTime::createFromFormat(DATE_RFC3339, $resultEvent->getStart()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime())) ||
								(DateTime::createFromFormat(DATE_RFC3339, $resultEvent->getEnd()->getDateTime()) != DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime()))) {
								//something was not the same when it should have been
							
								if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
									$log = $currentTime->format(DATE_RFC3339) . ": After allocate, the event added did not match with what was tried to be added.  Calendar was changed. Event tried to add:\n";
									file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									Services::LogEventsToFile(array($event));
									$log2 = "\nEvent that was added:\n";
									file_put_contents(LOGGING_OUTPUT_FILE, $log2, FILE_APPEND);
									Services::LogEventsToFile(array($resultEvent));
								}

								throw new OtherException('After allocate, the event added did not match with what was tried to be added. Calendar was changed.');
							}
							
							$currentDayEventsPostAllocate = $service->events->listEvents($calendarId, array('timeMin' => startOfDay()->format(DATE_RFC3339), 'timeMax' => endOfDay()->format(DATE_RFC3339), 'orderBy' => 'startTime', 'singleEvents' => TRUE, 'timeZone' => currentDay()->getTimezone()->getName()));
							$currentDayEventsPostAllocateArray = $currentDayEventsPostAllocate->getItems();
							
							//not sure if this can ever occur based on the API documentation
							//this doesn't work right now
							/*if (count($currentDayEventsPostAllocateArray) != 1) {
								if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
									$log = $currentTime->format(DATE_RFC3339) . ": After allocate, the event that should have been added was not added.  Calendar was not changed. Event was:\n";
									file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
									Services::LogEventsToFile(array($event));
								}
								throw new OtherException('After allocate, the event that should have been added was not added. Calendar was not changed. ');;
							}*/
						}					
						
						if (DEBUG_MODE) {
							printf('Event added: %s<br/>', $event->htmlLink);								
						}	

						if ($firstResource->getTwilightStatus()) {
							throw new OtherException('Warning: event added outside of normal business hours');
						}
						
                        if ($failureInAdjustment && ALWAYS_INSERT) {
							throw new OtherException('Unable to reschedule events during insert.  Inserted event anyways.  Calendar changed. Manual adjustment necessary.');
						} 						
					}
				}								
			} else {
				//invalid request to perform on
				if (LOGGER_ON && ((LOGGING_LEVEL & 4) == 4)) {
				   $log = $currentTime->format(DATE_RFC3339) . ": Invalid request to be performed on.  Calendar not changed.\n";
				   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
				}
				throw new OtherException('Invalid request to perform on. Calendar not changed.');
			}
		} else {
			//invalid service reference
			if (LOGGER_ON && ((LOGGING_LEVEL & 8) == 8)) {
			   $log = $currentTime->format(DATE_RFC3339) . ": Invalid service reference when trying to perform request.  Calendar not changed.\n";
			   file_put_contents(LOGGING_OUTPUT_FILE, $log, FILE_APPEND);
			}
			throw new OtherException('Invalid service reference. Calendar not changed.');
		}	  
	}

	private $_isValid; //will be set to valid when the parser has completed it
	private $_action; //the action to perform
	private $_resourceList; //array of resources to be inserted/removed/altered
};

class ResourceType {

	function __construct() {
		$this->_name = '';
		$this->_color = '';
		$this->_email = '';
		$this->_description = '';
		$this->_location = '';
		$this->_identifier = '';
		$this->_url = '';
		$this->_isTwilight = false;
	}

	function __destruct() { }

	public function getIdentifier() { return $this->_identifier; }
	public function setIdentifier($identifier) { $this->_identifier = $identifier; }
	public function getName() { return $this->_name; }
	public function setName($name) {$this->_name = $name; }
	public function getColor() { return $this->_color; }
	public function setColor($color) { $this->_color = $color; }
	public function getEmail() { return $this->_email; }
	public function setEmail($email) { $this->_email = $email; }
	public function getStartDate() { return $this->_startDate; }
	public function setStartDate($startDate) { $this->_startDate = $startDate; }
	public function getEndDate() { return $this->_endDate; }
	public function setEndDate($endDate) { $this->_endDate = $endDate; }
	public function getDescription() { return $this->_description; }
	public function setDescription($description) { $this->_description = $description; }
	public function setLocation($location) { $this->_location = $location; }
	public function getLocation() { return $this->_location; }
	public function setURL($URL) { $this->_url = $URL; }
	public function getURL() { return $this->_url; }
	public function getTwilightStatus() { return $this->_isTwilight; }
	public function setTwilightStatus($twilight) { $this->_isTwilight = $twilight; }
	
	private $_startDate; //date that action is operating on.  Only support one day for now
	private $_endDate; //end date that action is operating on.  Only support one day for now
	
	private $_identifier; //id used for event type
	private $_name; //name of resource.  i.e. calendar id
	private $_color; //color of resource  -- //through experimentation determined that these are the colors: 0 - none, 1 - blue, 2 - green, 3 - purple, 4 - red, 5 - yellow, 6 - orange, 7 - turquoise, 8 - gray, 9 - bold blue, 10 - bold green, 11 - bold red
	private $_email; //email address of user
	private $_description; //description of event - used for description field on allocated (real) events
	private $_location; //location of event - used for title and location fields on allocated (real) events
	private $_url; //url put into description
	private $_isTwilight; //whether or not the event is a "twilight" event which means outside normal business hours
};

