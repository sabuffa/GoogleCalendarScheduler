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

if (!defined('OOP')) {
	//procedural style.  File will run automatically.  To turn off (so you can call the functions outside of this file) define OOP in parent file
	echo RequestHandler::HandleRequest();
}

class RequestHandler {

	static function HandleRequest() {
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
							return '<error>Unable to contact calendar API because of: ' . $query . '</error>';
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
										
										try {
											$RSGCRequestObj->performRequest($service); 
										} catch (OtherException $e) {
											return '<error>Failed to perform request: ' . $e->getMessage() . '</error>';
										} catch (Exception $e) {
											return '<error>Exception caught when trying to perform request: ' . $e->getMessage() . '</error>';
										}
									} else {
										return '<error>failed to build service object</error>';
									}
								} catch(Exception $e) {
									return '<error>Exception caught when trying to build service: ' . $e->getMessage() . '</error>';
								}
							} else {
								return '<error>invalid request object</error>';
							}
						} else {
							return '<error>invalid request</error>';
						}			 
					} catch (ParsingException $parseError) {
						return '<error>Failed to parse request: ' . $parseError->getMessage() . '</error>';
					} catch (Exception $e) {
						return '<error>Exception caught when trying to parse request: ' . $e->getMessage() . '</error>';
					}
				}
				break;
			default:
				//not good.  Don't handle request
				return '<error>bad request</error>';
			}
		} else {
		   return '<error>_SERVER not set</error>';
		}
		return 'OK';
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
	static function datesOverlap($start_one,$end_one,$start_two,$end_two) {

		if($start_one < $end_two && $end_one > $start_two) { //If the dates overlap
			return  1; //min($end_one,$end_two)->diff(max($start_two,$start_one))-> + 1; //return how many days overlap
		}

		return 0; //Return 0 if there is no overlap
	}
}

class ParsingException extends Exception
{
	// Redefine the exception so message isn't optional
	public function __construct($message, $code = 0, Exception $previous = null) {    
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
		
		if (DEBUG_MODE) {
			echo 'Exception created for parsing.  Reason: ' . $message; //. '<br/>';
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
			echo 'Exception created for parsing.  Reason: ' . $message;// . '<br/>';
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
		
		return true;
	}

	//parses the url
	static function parse($request, &$xmlDoc = null, $doValidationFirst = false) {      
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
				case 'initDate': {
						$request->setRequestType(RequestType::INIT_DATE);
						break;
					} case 'initBlock': {
						$request->setRequestType(RequestType::INIT_BLOCK);
						break;
					} case 'allocate': {
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
			case RequestType::INIT_DATE:
			case RequestType::ALLOCATE:
			case RequestType::FREE: {
					if (count($xmlDoc->date) > 1) {
						throw new ParsingException('only one date is allowed per request for initDate, allocate, and free requests');
					}			  
					break;
				} case RequestType::INIT_BLOCK: {
					if (count($xmlDoc->date) > 2) {
						throw new ParsingException('only two dates allowed for initBlock requests.  Start date and end date');
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
				$tempResource2 = clone $resource;			
				
				if ($date == '') {
					throw new ParsingException('empty date');
				}
				$startDate = DateTime::createFromFormat(DATE_RFC3339, $date); //, new DateTimeZone('AMERICA/CHICAGO'));
				
				if ($startDate === false) {
					throw new ParsingException('invalid date format');
				}
				
				$dateRangeStart = clone $startDate;
				$dateRangeStart->setTime(8, 0); //8AM minimum
				$dateRangeEnd = clone $dateRangeStart;
				$dateRangeEnd->add(new DateInterval('PT12H'));			
				
				switch($request->getRequestType()) {
				case RequestType::INIT_DATE: 
				case RequestType::INIT_BLOCK: {					   
						//set time to 7:30
						$startDate->setTime(7, 30);
						
						//add 30M
						$endDate = clone $startDate;
						$endDate->add(new DateInterval('PT30M'));
						$tempResource->setEmail(''); //overwrite email to be nothing
						$tempResource->setColor('0'); //default to 0 for now, which means inherit from the calendar color
						$tempResource->setStartDate($startDate);
						$tempResource->setEndDate($endDate);				   					   					   
						
						$startDate2 = clone $dateRangeStart;
						$endDate2 = clone $dateRangeEnd;
						
						$tempResource2->setColor('8'); //gray
						$tempResource2->setEmail(''); //override to be nothing
						$tempResource2->setStartDate($startDate2);
						$tempResource2->setEndDate($endDate2);
						break;
					} case RequestType::ALLOCATE: {
						//allocation has duration

						if ($xmlDoc->duration == '') {  //this would also be caught by the if below for filter by int
							throw new ParsingException('no duration to match with date!');
						}
						
						if (!filter_var($xmlDoc->duration, FILTER_VALIDATE_INT) === false && intval($xmlDoc->duration) <= 0) {
							throw new ParsingException('invalid duration specified');
						}
						
						$endDate = clone $startDate;
						$endDate->add(new DateInterval('PT'.$xmlDoc->duration.'M'));
						if (Services::datesOverlap($startDate, $endDate, $dateRangeStart, $dateRangeEnd) == 0 || $endDate > $dateRangeEnd) {
							throw new ParsingException('input date for allocation not within valid range of 8AM-8PM');
						}                    					
						
						$tempResource->setStartDate($startDate);
						$tempResource->setEndDate($endDate);			
						break;					
					} case RequestType::FREE: {
						//check if date in range 8AM - 8PM, otherwise invalid
						$endDate = clone $startDate;
						$endDate->add(new DateInterval('PT1S')); //just add 1 minute for now to end date
						if (Services::datesOverlap($startDate, $endDate, $dateRangeStart, $dateRangeEnd) == 0) {
							throw new ParsingException('input date for freeing not within valid range of 8AM-8PM');
						}
						
						$tempResource->setStartDate($startDate);
						$tempResource->setEndDate($endDate);
						break;
					}			   
				default: {
						throw new ParsingException('Invalid RequestType set!  Internal Error.');
					}
				}
				//check for overlap of input data - only useful really for initBlock right now.  Perhaps in the future if I allow multiple allocate/free in one request it will be more useful
				//checks for overlapping/duplicate dates in request
				foreach ($request->getResourceList() as $resource) {
					if (Services::datesOverlap($tempResource->getStartDate(), $tempResource->getEndDate(), $resource->getStartDate(), $resource->getEndDate()) != 0) {
						throw new ParsingException('input dates overlap');
					}
					
					if ($request->getRequestType() == RequestType::INIT_DATE || $request->getRequestType() == RequestType::INIT_BLOCK) {
						if (Services::datesOverlap($tempResource2->getStartDate(), $tempResource2->getEndDate(), $resource->getStartDate(), $resource->getEndDate()) != 0) {
							throw new ParsingException('input dates overlap');
						}
					}				 				 
				}
				
				//this code block added to support only two dates (start and end) for date range initBlock, instead of original way 
				//it was programmed which is date...date..date... as specified in requirements
				if ($request->getRequestType() == RequestType::INIT_BLOCK && $count == 2) {
					//check to see if end date is after start date	
                    $resourceList = $request->getResourceList();					
					$resource1 = $resourceList[0];
					if ($resource1->getStartDate() >= $tempResource->getStartDate()) {
						throw new ParsingException('end of date range is before start of date range');
					}
					//loop through all dates in between start date and end date and add data for them
					$difference = $resource1->getStartDate()->diff($tempResource->getStartDate());
					
					for ($dayCount = 1; $dayCount < $difference->d; $dayCount++) {
						$tempResourceList = $request->getResourceList();
						$tempResource3 = $tempResourceList[0];
						$tempStartDate = clone $tempResource3->getStartDate();

						//set time to 7:30
						$tempStartDate->setTime(7, 30);
						$tempStartDate->add(new DateInterval('P'.$dayCount.'D'));
						
						//add 30M
						$tempEndDate = clone $tempStartDate;
						$tempEndDate->add(new DateInterval('PT30M'));
						$tempTempResource = clone $tempResource3;
						
						$tempTempResource->setEmail(''); //overwrite email to be nothing
						$tempTempResource->setColor('0'); //default to 0 for now, which means inherit from the calendar color
						$tempTempResource->setStartDate($tempStartDate);
						$tempTempResource->setEndDate($tempEndDate);				   					   					   
						
						$tempStartDate2 = clone $tempStartDate;
						$tempStartDate2->setTime(8, 0);					   
						$tempEndDate2 = clone $tempStartDate2;
						$tempEndDate2->add(new DateInterval('PT12H'));
						
						$tempTempResource2 = clone $tempResource3;
						$tempTempResource2->setColor('8'); //gray
						$tempTempResource2->setEmail(''); //override to be nothing
						$tempTempResource2->setStartDate($tempStartDate2);
						$tempTempResource2->setEndDate($tempEndDate2);
						
						$request->addResource($tempTempResource);
						$request->addResource($tempTempResource2);
					}
				}
				
				$request->addResource($tempResource);
				
				if ($request->getRequestType() == RequestType::INIT_DATE || $request->getRequestType() == RequestType::INIT_BLOCK) {
					$request->addResource($tempResource2);  
				}
				
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
	const INIT_DATE = 1;
	const INIT_BLOCK = 2;
	const ALLOCATE = 3;
	const FREE = 4;
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

	//do the action
	function performRequest(&$service) {
		//ASSUME ALL EVENTS ARE SORTED IN REQUEST ALREADY
		if (OFFLINE_MODE || !is_null($service)) {
			if ($this->_isValid && $this->_action != RequestType::NONE) {								
				if (!$this->_resourceList) {
					throw new OtherException('Internal error: attempted to perform request with no resources');
				}					
				
				$firstResource = $this->_resourceList[0];
				$firstDate = $firstResource->getStartDate();
				$calendarId = $firstResource->getName(); //just use calendar id/name from first date
				
				//check to see if that calendar exists
				if (!OFFLINE_MODE) {
					$calendarList = $service->calendarList->listCalendarList();
					$foundMatchingCalendar = false;
					foreach ($calendarList->getItems() as $calendarListEntry) {						
						if ($calendarListEntry->getId() == $calendarId) {
							$foundMatchingCalendar = true;
							break;
						}
					}
					if (!$foundMatchingCalendar) {
						throw new OtherException('no calendar found that matches the id provided');
					}					   
				}
				
				$lastDate = end($this->_resourceList)->getEndDate();

				if (!OFFLINE_MODE) {
					//note: the free and allocate sections would need to be redone if allowing more than one allocate/free in a single request
					if ($this->_action == RequestType::FREE) {
						//get the events that overlap with the event
						$currentEvents = $service->events->listEvents($calendarId, array('timeMin' => $firstDate->format(DATE_RFC3339), 'timeMax' => $lastDate->format(DATE_RFC3339)));
						
						$eventArray = $currentEvents->getItems();
						if (!(!$eventArray)) {
							//assume only one event returned							
							$event = $eventArray[0];
							
							if ($event->getColorId() == '8') {
								throw new OtherException('tried to free space that is already free.  Not allowed');
							}
							
							//event is a real event
							$currentStartDate = DateTime::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
							$currentEndDate = DateTime::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());
							
							//grab events on either side
							
							//if current start date is 8AM then don't grab preceding event
							//if current end date is 8PM then don't grab following event
							$tempDate = clone $currentStartDate;
							$tempDate->setTime(8, 0);
							$tempDate2 = clone $currentEndDate;
							$tempDate2->SetTime(20, 0);
							
							$precedingEvent = NULL;
							$followingEvent = NULL;
							
							if ($currentStartDate > $tempDate) {
								//get preceding event (just subtract 1 minute from start)
								$minTime = clone $currentStartDate;
								$minTime->sub(new DateInterval('PT1M'));
								$precedingEvents = $service->events->listEvents($calendarId, array('timeMin' => $minTime->format(DATE_RFC3339), 'timeMax' => $currentStartDate->format(DATE_RFC3339)));
								
								if (DEBUG_MODE) {
									echo 'looking for preceding event.  StartTimeUsed: ' . $minTime->format(DATE_RFC3339) . ' EndTimeUsed: ' . $currentStartDate->format(DATE_RFC3339) . '<br/>';
								}
								if (!(!$precedingEvents->getItems())) {
									//assume only one event
									$preceding = $precedingEvents->getItems();
									$precedingEvent = $preceding[0];
									
									if (DEBUG_MODE) {
										echo 'Got preceding event<br/>';
									}
								} else {
									//should never happen
									throw new OtherException('Internal error: there was no event found before event trying to be freed.  This should not happen');
								}
							} //else no preceding event
							
							if ($currentEndDate < $tempDate2) {
								//get following event
								$maxTime = clone $currentEndDate;
								$maxTime->add(new DateInterval('PT1M'));
								$followingEvents = $service->events->listEvents($calendarId, array('timeMin' => $currentEndDate->format(DATE_RFC3339), 'timeMax' => $maxTime->format(DATE_RFC3339)));
								
								if (DEBUG_MODE) {
									echo 'looking for following event.  StartTimeUsed: ' . $currentEndDate->format(DATE_RFC3339) . ' EndTimeUsed: ' . $maxTime->format(DATE_RFC3339) . '<br/>';
								}
								
								if (!(!$followingEvents->getItems())) {
									//assume only one event
									$following = $followingEvents->getItems();
									$followingEvent = $following[0];
									
									if (DEBUG_MODE) {
										echo 'Got following event<br/>';
									}
								} else {
									throw new OtherException('there was no event found after event trying to be freed');
								}
							}	//else no following event						 							 
							
							$startTime = new Google_Service_Calendar_EventDateTime();
							$endTime = new Google_Service_Calendar_EventDateTime();
							
							if (!is_null($precedingEvent) && !is_null($followingEvent) &&
									($precedingEvent->getColorId() == '8' && $followingEvent->getColorId() == '8')) {
								//if precedingEvent and following event are gray, then merge all three events into gray
								$startTime->setDateTime($precedingEvent->getStart()->getDateTime());
								$endTime->setDateTime($followingEvent->getEnd()->getDateTime());
								
								if (DEBUG_MODE) {
									echo 'preceding and following event are gray. Removing them<br/>';
								}
								//remove all three events
								$service->events->delete($calendarId, $precedingEvent->getId());
								$service->events->delete($calendarId, $followingEvent->getId());
							} else if (!is_null($precedingEvent) && $precedingEvent->getColorId() == '8') {
								//if only preceding event is gray, then merge preceding and current event into gray
								$startTime->setDateTime($precedingEvent->getStart()->getDateTime());
								$endTime->setDateTime($currentEndDate->format(DATE_RFC3339));
								
								if (DEBUG_MODE) {
									echo 'preceding event is gray.  Removing it<br/>';
								}
								//remove preceding and current event
								$service->events->delete($calendarId, $precedingEvent->getId());
							} else if (!is_null($followingEvent) && $followingEvent->getColorId() == '8') {
								//if only following event is gray, then merge current event and following event into gray
								$startTime->setDateTime($currentStartDate->format(DATE_RFC3339));
								$endTime->setDateTime($followingEvent->getEnd()->getDateTime());
								
								if (DEBUG_MODE) {
									echo 'following event is gray.  Removing it<br/>';
								}
								//remove current and following event
								$service->events->delete($calendarId, $followingEvent->getId());
							} else {
								//if preceding and following events are not gray, then just replace current event with gray	
								$startTime->setDateTime($currentStartDate->format(DATE_RFC3339));
								$endTime->setDateTime($currentEndDate->format(DATE_RFC3339));								
								//remove current event (done below)                               							
							}
							
							if (DEBUG_MODE) {
								echo 'removing current event<br/>';
							}
							
							//delete current event
							$service->events->delete($calendarId, $event->getId());
							
							//add the new gray event
							$eventArray = array(
							//  'summary' => (string)$item->getSummary(),									 
							'start' => array(
							'dateTime' => $startTime->getDateTime(), //format: 2015-05-28T09:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
							),
							'end' => array(
							'dateTime' => $endTime->getDateTime(), //format: 2015-05-28T17:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
							),
							'colorId' => '8',  //hardcode to gray
							);
							
							$newEvent = new Google_Service_Calendar_Event($eventArray);
							$service->events->insert($calendarId, $newEvent);	
							
							if (DEBUG_MODE) {
								printf('Event added: %s<br/>', $event->htmlLink);	
							}

						} else {
							throw new OtherException('attempting to free an event to time that was not initialized.  Not allowed');
						}
					} else if ($this->_action == RequestType::ALLOCATE) {
						
						//get the events that overlap with the event
						$currentEvents = $service->events->listEvents($calendarId, array('timeMin' => $firstDate->format(DATE_RFC3339), 'timeMax' => $lastDate->format(DATE_RFC3339)));	

						if (!(!$currentEvents->getItems())) {
							//check all the events to see if they are gray.  If not then it is not allowed
							
							//store overlapping gray events and check for overlap of real events
							$overlappingGrayEvents = array();
							
							foreach($currentEvents->getItems() as $item) {		
								$currentStartDate = DateTime::createFromFormat(DATE_RFC3339, $item->getStart()->getDateTime());
								$currentEndDate = DateTime::createFromFormat(DATE_RFC3339, $item->GetEnd()->getDateTime());
								
								if (Services::datesOverlap($firstDate, $lastDate, $currentStartDate, $currentEndDate) != 0) {
									//date overlaps
									if ($item->getColorId() != '8') {
										throw new OtherException('there is a conflicting event that overlaps this time allocation not allowed');
									} else {
										//ok for this event - add to list of overlapping events									  
										$overlappingGrayEvents[] = clone $item;
									}
								} else {
									//should be impossible to get here.  If not something is wrong with this code
									throw new OtherException('Internal error: Found no overlap of events that are within date range');
								}
							}

							//go through and modify the gray events properly
							foreach($currentEvents->getItems() as $item) {
								$currentStartDate = DateTime::createFromFormat(DATE_RFC3339, $item->getStart()->getDateTime());
								$currentEndDate = DateTime::createFromFormat(DATE_RFC3339, $item->GetEnd()->getDateTime());
								
								if (($firstDate < $currentEndDate && $lastDate > $currentEndDate) || 
										($firstDate > $currentStartDate && $lastDate == $currentEndDate)) {
									//case 1 - start inside
									//$currentEndDate = clone $firstDate;
									//modify end date to be same as new start date
									$end = new Google_Service_Calendar_EventDateTime();
									$end->setDateTime($firstDate->format(DATE_RFC3339));
									$item->setEnd($end);
									$service->events->update($calendarId, $item->getId(), $item);
								} else if (($firstDate == $currentStartDate && $lastDate < $currentEndDate) ||
										($firstDate < $currentStartDate && $lastDate < $currentEndDate)) {
									//case 2 - inside with start touching
									//$currentStartDate = clone $lastDate;
									//modify start date to be same as new end date	
									$start = new Google_Service_Calendar_EventDateTime();
									$start->setDateTime($lastDate->format(DATE_RFC3339));
									$item->setStart($start);
									$service->events->update($calendarId, $item->getId(), $item);
								} else if ($firstDate <= $currentStartDate && $lastDate >= $currentEndDate) {
									//remove gray space
									$service->events->delete($calendarId, $item->getId());																	
								} else if ($firstDate > $currentStartDate && $lastDate < $currentEndDate) {
									//case 7 - inside
									
									//split gray into two events
									//event 1 has end date of $firstDate and start date of $currentStartDate
									//event 2 has start date of $lastDate and end date of $currentEndDate
									$tempItem = clone $item;
									$end = new Google_Service_Calendar_EventDateTime();
									$end->setDateTime($firstDate->format(DATE_RFC3339));
									$item->setEnd($end);
									$service->events->update($calendarId, $item->getId(), $item);
									
									$eventArray = array(
									//  'summary' => $item->getSummary(),
									'start' => array(
									'dateTime' => $lastDate->format(DATE_RFC3339), //format: 2015-05-28T09:00:00-07:00',
									//	'timeZone' => 'America/Chicago',
									),
									'end' => array(
									'dateTime' => $tempItem->getEnd()->getDateTime(), //format: 2015-05-28T17:00:00-07:00',
									//	'timeZone' => 'America/Chicago',
									),
									'colorId' => '8',  //hardcode to gray
									);
									
									$event = new Google_Service_Calendar_Event($eventArray);
									$service->events->insert($calendarId, $event);
								} else {
									throw new OtherException('The events are not overlapping when they should overlap with gray space');
								}
							}


							//now add in the real event
							$eventArray = array(
							'summary' => $firstResource->getLocation(),
							'location' => $firstResource->getLocation(),
							'description' => $firstResource->getDescription(),
							'attendees' => array(
								array('email' => $calendarId), //default to calendar id for now
							 ),
							'start' => array(
							'dateTime' => $firstDate->format(DATE_RFC3339), //format: 2015-05-28T09:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
							),
							'end' => array(
							'dateTime' => $lastDate->format(DATE_RFC3339), //format: 2015-05-28T17:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
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
							
							$optionalParameters = array('sendNotifications' => true);
							
							$event = new Google_Service_Calendar_Event($eventArray);
							$service->events->insert($calendarId, $event, $optionalParameters);	
							if (DEBUG_MODE) {
								printf('Event added: %s<br/>', $event->htmlLink);								
							}
							
						}	else {
							throw new OtherException('attempting to add an event to time that was not initialized.  Not allowed');
						}						  
					} else {		
						//initialization works a little bit more generically.  Still not great, but good enough for now					   
						
						//get all events that overlap with the pending events to be added/removed
						$currentEvents = $service->events->listEvents($calendarId, array('timeMin' => $firstDate->format(DATE_RFC3339), 'timeMax' => $lastDate->format(DATE_RFC3339)));
						
						if (!(!$currentEvents->getItems())) {
							//since I'm assuming only one date added per allocate/one date removed per free, this is not so bad					   
							
							//build up the comparison container
							$emptySpaceArray = array();
							foreach ($this->_resourceList as $newItem) {
								foreach($currentEvents->getItems() as $item) {
									//create time		
									
									//check for duplicates/overlapping
									if (Services::datesOverlap($newItem->getStartDate(), $newItem->getEndDate(), DateTime::createFromFormat(DATE_RFC3339, $item->getStart()->getDateTime()), DateTime::createFromFormat(DATE_RFC3339, $item->getEnd()->getDateTime())) != 0) {
										//there is an overlapping date somewhere...																
										throw new OtherException('dates overlap (stopping on first unallowed occurrence) newDate: start: ' . $newItem->getStartDate()->format(DATE_RFC3339) . 'end: ' . $newItem->getEndDate()->format(DATE_RFC3339) . 'oldDate: start: ' . $item->getStart()->getDateTime() . 'end: ' . $item->getEnd()->getDateTime());
									}
								}							   
							}						   
						}
						
						//if got here which means that there was no overlap (or in offline mode)!
						//if you want it to loop through and change what it can, then this could be changed in the future to be inside the other loop
						foreach ($this->_resourceList as $resource) {
							
							$eventArray = array(
							//  'summary' => $resource->getEmail(),
							'location' => '',
							'description' => '',
							'start' => array(
							'dateTime' => $resource->getStartDate()->format(DATE_RFC3339), //format: 2015-05-28T09:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
							),
							'end' => array(
							'dateTime' => $resource->getEndDate()->format(DATE_RFC3339), //format: 2015-05-28T17:00:00-07:00',
							//	'timeZone' => 'America/Chicago',
							),
							'colorId' => (string)$resource->getColor(),
							);
							
							$event = new Google_Service_Calendar_Event($eventArray);
							
							if (!OFFLINE_MODE) {									
								$event = $service->events->insert($calendarId, $event);
								
								if (DEBUG_MODE) {
									printf('Event created: %s<br/>', $event->htmlLink);						
								}																			
							}
						}	
					}
				}								
			} else {
				//invalid request to perform on
				throw new OtherException('Invalid request to perform on');
			}
		} else {
			//invalid service reference
			throw new OtherException('Invalid service reference');
		}	  
	}

	private $_isValid; //will be set to valid when the parser has completed it
	private $_action; //the action to perform
	private $_location; //location being set
	private $_resourceList; //array of resources to be inserted/removed/altered
};

class ResourceType {

	function __construct() {
		$this->_name = '';
		$this->_color = '';
		$this->_email = '';
		$this->_description = '';
		$this->_location = '';
	}

	function __destruct() { }

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
	
	private $_startDate; //date that action is operating on.  Only support one day for now
	private $_endDate; //end date that action is operating on.  Only support one day for now
	
	
	private $_name; //name of resource.  i.e. calendar id
	private $_color; //color of resource  -- //through experimentation determined that these are the colors: 0 - none, 1 - blue, 2 - green, 3 - purple, 4 - red, 5 - yellow, 6 - orange, 7 - turquoise, 8 - gray, 9 - bold blue, 10 - bold green, 11 - bold red
	private $_email; //email address of user
	private $_description; //description of event - used for description field on allocated (real) events
	private $_location; //location of event - used for title and location fields on allocated (real) events
};
