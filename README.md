RF24-php
========

PHP library for comunicate with RF24


Requirements
============
php-spi https://github.com/frak/php_spi
php-wiringpi https://github.com/WiringPi/WiringPi-PHP (requires php with root privileges /u have used suexec with wrapper "sudo php5-cgi"/)


Usage
=====

<?php
	$spi = new \Spi(0, 0, array(
	    'speed' => 8000000,
	));

	$wiring = new \WiringPi();
	$wiring->wiringPiSetup();

	$radio = new \RF24($spi, 6 , 9, $wiring);

	$radio->begin();
	$radio->setRetries(15,15);
	$radio->setPALevel(\RF24::RF24_PA_MAX);
	$radio->setChannel(0x4c);
	$radio->openWritingPipe(array(0xF0, 0xF0, 0xF0, 0xF0, 0xE1));
	$radio->openReadingPipe(1, array(0xF0, 0xF0, 0xF0, 0xF0, 0xD2));
	$radio->setDataRate(\RF24::RF24_250KBPS);
	$radio->stopListening();

	$radio->stopListening();
	$data = array(0x01, 0x02, 0x03, 0x04);
	$ok = $this->radio->write($data, count($data));
	$radio->startListening();
	if ($ok){
		$timeout = false;
		$started_waiting_at = __millis();
		while ( ! $this->radio->available() && ! $timeout ) {
			usleep(5000);
			if (__millis() - $started_waiting_at > 5000 )
			$timeout = true;
		}
		if (!$timeout){
			list($result, $data) = $this->radio->read(32);
			if ($result){
				var_dump($data);
			}
		}
	}

