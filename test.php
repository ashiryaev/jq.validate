<?php

interface Observable {
	public function addObserver(Observer $Observer, $eventType);
	public function fireEvent($eventType);
}

interface Observer {
	public function notify($arguments);
}

class ErrorLogger implements Observer {
	
	public function notify($arguments = array()) {
		print 'Error occur. Write to log.';
	}
}

class DataWriter implements Observer {
	
	public function notify($arguments = array()) {
		print 'It is OK. Write to DB.';
	}
}

class Validator implements Observable {
	const EMAIL_VALID = 1;
	const EMAIL_INVALID = 2;
	
	protected $_observers = array();
	
	public function addObserver(Observer $Observer, $eventType) {
		$this->_observers[$eventType][] = $Observer;
	}
	
	public function validate($email) {
		if (filter_var($email, FILTER_VALIDATE_EMAIL))  {
			$this->fireEvent(self::EMAIL_VALID);
		} else {
			$this->fireEvent(self::EMAIL_INVALID);
		}
	}
	
	public function fireEvent($eventType) {
		if (isset($this->_observers[$eventType])) {
			foreach ($this->_observers[$eventType] as $Observer) {
				$Observer->notify();
			}
		}
	}
}

$Validator = new Validator();
$Validator->addObserver(new ErrorLogger(), Validator::EMAIL_INVALID);
$Validator->addObserver(new DataWriter(), Validator::EMAIL_VALID);

$email = 'ad@rt';
$Validator->validate($email);


