<?php

class WiringPi {

	const OUTPUT = 1;
	const INPUT = 0;
	const HIGH = 1;
	const LOW = 0;

	static function wiringPiSetup() {
		return wiringPiSetup();
	}

	static function wiringPiSetupSys() {
		return wiringPiSetupSys();
	}

	static function wiringPiSetupGpio() {
		return wiringPiSetupGpio();
	}

	static function pullUpDnControl($pin,$pud) {
		pullUpDnControl($pin,$pud);
	}

	static function pinMode($pin,$mode) {
		pinMode($pin,$mode);
	}

	static function digitalWrite($pin,$value) {
		digitalWrite($pin,$value);
	}

	static function pwmWrite($pin,$value) {
		pwmWrite($pin,$value);
	}

	static function digitalRead($pin) {
		return digitalRead($pin);
	}

	static function shiftOut($dPin,$cPin,$order,$val) {
		shiftOut($dPin,$cPin,$order,$val);
	}

	static function shiftIn($dPin,$cPin,$order) {
		return shiftIn($dPin,$cPin,$order);
	}

	static function delay($howLong) {
		delay($howLong);
	}

	static function delayMicroseconds($howLong) {
		delayMicroseconds($howLong);
	}

	static function millis() {
		return millis();
	}

	static function serialOpen($device,$baud) {
		return serialOpen($device,$baud);
	}

	static function serialClose($fd) {
		serialClose($fd);
	}

	static function serialPutchar($fd,$c_) {
		serialPutchar($fd,$c_);
	}

	static function serialPuts($fd,$s) {
		serialPuts($fd,$s);
	}

	static function serialDataAvail($fd) {
		return serialDataAvail($fd);
	}

	static function serialGetchar($fd) {
		return serialGetchar($fd);
	}

	static function serialPrintf($fd,$message) {
		serialPrintf($fd,$message);
	}

}

class WiringPiExec extends WiringPi {
	
	const GPIO_command = "/usr/local/bin/gpio";
	
	static function wiringPiSetup(){
	}
	
	static function pinMode($pin,$mode) {
		exec(self::GPIO_command.' mode '.intval($pin).' '.($mode==self::OUTPUT?'out':'in'));
	}

	static function digitalWrite($pin,$value) {
		exec(self::GPIO_command.' write '.intval($pin).' '.($value==self::HIGH?'1':'0'));
	}
}