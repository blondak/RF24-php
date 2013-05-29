<?php
require '/WiringPi.php';

class RF24L01 {
	/* Memory Map */
	const CONFIG = 0x00;
	const EN_AA = 0x01;
	const EN_RXADDR = 0x02;
	const SETUP_AW = 0x03;
	const SETUP_RETR = 0x04;
	const RF_CH = 0x05;
	const RF_SETUP = 0x06;
	const STATUS = 0x07;
	const OBSERVE_TX = 0x08;
	const CD = 0x09;
	const RX_ADDR_P0 = 0x0A;
	const RX_ADDR_P1 = 0x0B;
	const RX_ADDR_P2 = 0x0C;
	const RX_ADDR_P3 = 0x0D;
	const RX_ADDR_P4 = 0x0E;
	const RX_ADDR_P5 = 0x0F;
	const TX_ADDR = 0x10;
	const RX_PW_P0 = 0x11;
	const RX_PW_P1 = 0x12;
	const RX_PW_P2 = 0x13;
	const RX_PW_P3 = 0x14;
	const RX_PW_P4 = 0x15;
	const RX_PW_P5 = 0x16;
	const FIFO_STATUS = 0x17;
	const DYNPD = 0x1C;
	const FEATURE = 0x1D;

	/* Bit Mnemonics */
	const MASK_RX_DR = 6;
	const MASK_TX_DS = 5;
	const MASK_MAX_RT = 4;
	const EN_CRC = 3;
	const CRCO = 2;
	const PWR_UP = 1;
	const PRIM_RX = 0;
	const ENAA_P5 = 5;
	const ENAA_P4 = 4;
	const ENAA_P3 = 3;
	const ENAA_P2 = 2;
	const ENAA_P1 = 1;
	const ENAA_P0 = 0;
	const ERX_P5 = 5;
	const ERX_P4 = 4;
	const ERX_P3 = 3;
	const ERX_P2 = 2;
	const ERX_P1 = 1;
	const ERX_P0 = 0;
	const AW = 0;
	const ARD = 4;
	const ARC = 0;
	const PLL_LOCK = 4;
	const RF_DR = 3;
	const RF_PWR = 6;
	const RX_DR = 6;
	const TX_DS = 5;
	const MAX_RT = 4;
	const RX_P_NO = 1;
	const TX_FULL = 0;
	const PLOS_CNT = 4;
	const ARC_CNT = 0;
	const TX_REUSE = 6;
	const FIFO_FULL = 5;
	const TX_EMPTY = 4;
	const RX_FULL = 1;
	const RX_EMPTY = 0;
	const DPL_P5 = 5;
	const DPL_P4 = 4;
	const DPL_P3 = 3;
	const DPL_P2 = 2;
	const DPL_P1 = 1;
	const DPL_P0 = 0;
	const EN_DPL = 2;
	const EN_ACK_PAY = 1;
	const EN_DYN_ACK = 0;

	/* Instruction Mnemonics */
	const R_REGISTER = 0x00;
	const W_REGISTER = 0x20;
	const REGISTER_MASK = 0x1F;
	const ACTIVATE = 0x50;
	const R_RX_PL_WID = 0x60;
	const R_RX_PAYLOAD = 0x61;
	const W_TX_PAYLOAD = 0xA0;
	const W_ACK_PAYLOAD = 0xA8;
	const FLUSH_TX = 0xE1;
	const FLUSH_RX = 0xE2;
	const REUSE_TX_PL = 0xE3;
	const NOP = 0xFF;

	/* Non-P omissions */
	const LNA_HCURR = 0;

	/* P model memory Map */
	const RPD = 0x09;

	/* P model bit Mnemonics */
	const RF_DR_LOW = 5;
	const RF_DR_HIGH = 3;
	const RF_PWR_LOW = 1;
	const RF_PWR_HIGH = 2;
}

if (!function_exists('_BV')){
	function _BV($o){
		return 1 << $o;
	}
}

if (!function_exists('__millis')){
	function __millis(){
		return microtime(true)*1000;
	}
}

class RF24 {

	const RF24_PA_MIN = 0;
	const RF24_PA_LOW = 1;
	const RF24_PA_HIGH = 2;
	const RF24_PA_MAX = 3;
	const RF24_PA_ERROR = 4;
	private $RF24_PA = array('PA_MIN', 'PA_LOW', 'PA_HIGH', 'PA_MAX');

	const RF24_1MBPS = 0;
	const RF24_2MBPS = 1;
	const RF24_250KBPS = 2;
	private $RF24_DataRates = array('1MBPS','2MBPS','250KBPS');

	const RF24_CRC_DISABLED = 0;
	const RF24_CRC_8 = 1;
	const RF24_CRC_16 = 2;
	private $RF24_CRCLengths = array ('Disabled', '8 bits', '16 bits'); 

	const LOW = 0;
	const HIGH = 1;

	const max_payload_size = 32;
	const timeout = 500;

	private $SPI;

	/** @var int "Chip Enable" pin, activates the RX or TX role */
	private $ce_pin;

	/** @var int SPI Chip select */
	private $csn_pin;

	/** @var bool 2Mbs data rate in use? */
	private $wide_band;

	/** @var bool False for RF24L01 and true for RF24L01P */
	private $p_variant;

	/** @var  Fixed size of payloads */
	private $payload_size;

	/** @var bool Whether there is an ack payload waiting */
	private $ack_payload_available;

	/** @var bool Whether dynamic payloads are enabled. */
	private $dynamic_payloads_enabled;

	/** @var int Dynamic size of pending ack payload. */
	private $ack_payload_length;

	/** @var int64 Last address set on pipe 0 for reading. */
	private $pipe0_reading_address;

	private $wiringPi;

	/**
	 * @param SPI $SPI
	 * @param int $cepin
	 * @param int $csnpin
	 * @param \WiringPi $wiringPi
	 */
	public function __construct($SPI, $cepin, $csnpin, \WiringPi $wiringPi){
		$this->SPI = $SPI;
		$this->ce_pin = $cepin;
		$this->csn_pin = $csnpin;
		$this->wiringPi = $wiringPi;
		$this->wide_band = true;
		$this->p_variant = false;
		$this->payload_size = 32;
		$this->ack_payload_available = false;
		$this->dynamic_payloads_enabled = false;
		$this->pipe0_reading_address = 0;
	}

	protected function log($message){
//		echo $message;
	} 

	protected function init_csn(){
		$this->wiringPi->pinMode($this->csn_pin, WiringPi::OUTPUT);
	}

	protected function init_ce(){
		$this->wiringPi->pinMode($this->ce_pin, WiringPi::OUTPUT);
	}

	protected function csn($mode){
		$this->wiringPi->digitalWrite($this->csn_pin, $mode);
	}

	protected function ce($level){
		$this->wiringPi->digitalWrite($this->ce_pin, $level);
	}

	public function begin(){
		$this->init_csn();
		$this->init_ce();
		$this->ce(self::LOW);
		$this->csn(self::HIGH);
		usleep(5000);
		$this->resetcfg();
		$this->setRetries(0x04, 0x0f);
		$this->setPALevel( self::RF24_PA_MAX ) ;
		if( $this->setDataRate( self::RF24_250KBPS ) ){
			$this->p_variant = true ;
		}
		$this->setDataRate( self::RF24_1MBPS ) ;
		$this->setCRCLength( self::RF24_CRC_16 ) ;
		$this->write_register(RF24L01::DYNPD, 0);
		$this->write_register(RF24L01::STATUS,_BV(RF24L01::RX_DR) | _BV(RF24L01::TX_DS) | _BV(RF24L01::MAX_RT) );
		$this->setChannel(0x4c);
		$this->flush_rx();
		$this->flush_tx();
	}

	public function resetcfg(){
		$this->write_register(0x00, 0x0f);
	}

	protected function flush_rx(){
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer(array(RF24L01::FLUSH_RX)));
		$this->csn(self::HIGH);
		return $status;	
	}

	protected function flush_tx(){
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer(array(RF24L01::FLUSH_TX)));
		$this->csn(self::HIGH);
		return $status;
	}

	protected function write_register($reg, $buf, $len = NULL){
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer( array(RF24L01::W_REGISTER | ( RF24L01::REGISTER_MASK & $reg ) )));
		if (($len == NULL) && ( is_int($buf))){
			$this->log(sprintf("%s(0x%02x,0x%02x) => 0x%02x\r\n" ,__METHOD__, $reg, $buf, $status));
			$this->SPI->transfer(array($buf));
		} else {
			if (!is_array($buf)){
				if (is_string($buf)){
					$buf = str_split($buf);
				} else {
					$buf = array($buf);
				}
			}
			if ($len !== NULL){
				$buf = array_slice($buf, 0, $len) + array_fill(0, $len, 0);
			}
			$this->log(sprintf("%s(0x%02x, [%s] , %d) => 0x%02x\r\n" , __METHOD__, $reg, join(',', array_map(function($a){return sprintf("0x%02x", $a);}, $buf)), $len, $status));
			$this->SPI->transfer($buf);
		}
		$this->csn(self::HIGH);
		return $status;
	}

	protected function read_register($reg){
		$this->csn(self::LOW);
		$this->SPI->transfer(array(RF24L01::R_REGISTER | (RF24L01::REGISTER_MASK & $reg)));
		$result = current($this->SPI->transfer(array(0xff)));
		$this->log(sprintf("%s(0x%02x) => 0x%02x\r\n", __METHOD__, $reg, $result));
		$this->csn(self::HIGH);
		return $result;
	}

	protected function read_register_buf($reg, $len){
		$buf = array();
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer( array(RF24L01::R_REGISTER | ( RF24L01::REGISTER_MASK & $reg ) )));
		while ( $len-- )
			$buf[] = current($this->SPI->transfer(array(0xff)));
		$this->log(sprintf("%s(0x%02x, %d) => [ %s ]\r\n", __METHOD__, $reg, func_get_arg(1), join(',', array_map(function($a){return sprintf("0x%02x", $a);}, $buf))));
		$this->csn(self::HIGH);
		return array($status, $buf);
	}

	protected function read_registers($reg, $qty){
		$result = array();
		while ($qty--){
			$result[] = $this->read_register($reg++);
		}
		return $result;
	}

	protected function get_registers_buf($reg, $len, $cnt = 1){
		$result = array();
		while ($cnt--){
			list($status, $buf) = $this->read_register_buf($reg++, $len);
			$result = array_merge($result, $buf);
		}
		return $result;
	}

	public function setPALevel($level) {
		$setup = $this->read_register(RF24L01::RF_SETUP);
		$setup &= ~(1 << (RF24L01::RF_PWR_LOW) | 1 << (RF24L01::RF_PWR_HIGH));
		switch ($level){
			case self::RF24_PA_MAX	: $setup |= (1<<RF24L01::RF_PWR_LOW) | (1<<RF24L01::RF_PWR_HIGH); break;
			case self::RF24_PA_HIGH	: $setup |= (1<<RF24L01::RF_PWR_HIGH); break;
			case self::RF24_PA_LOW	: $setup |= (1<<RF24L01::RF_PWR_LOW); break;
			case self::RF24_PA_MIN	: break;
			default: $setup |= (1<<RF24L01::RF_PWR_LOW) | (1<<RF24L01::RF_PWR_HIGH);
		}
		$this->write_register( RF24L01::RF_SETUP, $setup);
	}

	public function setDataRate($speed){
		$result = false;
		$setup = $this->read_register(RF24L01::RF_SETUP) ;
		$this->wide_band = false;
		$setup &= ~(_BV(RF24L01::RF_DR_LOW) | _BV(RF24L01::RF_DR_HIGH)) ;
		switch ($speed){
			case self::RF24_250KBPS	: $this->wide_band = false; $setup |= _BV( RF24L01::RF_DR_LOW); break;
			case self::RF24_2MBPS	: $this->wide_band = true; $setup |= _BV(RF24L01::RF_DR_HIGH); break;
			default : $this->wide_band = false;
		}
		$this->write_register(RF24L01::RF_SETUP, $setup);
		if ($this->read_register(RF24L01::RF_SETUP == $setup)) {
			$result = true;
		} else {
			$this->wide_band = false;
		}
		return $result;
	}

	public function setCRCLength($length){
		$config = $this->read_register(RF24L01::CONFIG) & ~( _BV(RF24L01::CRCO) | _BV(RF24L01::EN_CRC)) ;
		switch ($length){
			case self::RF24_CRC_DISABLED : break;
			case self::RF24_CRC_8 : $config |= _BV(RF24L01::EN_CRC);
			default : $config |= _BV(RF24L01::EN_CRC); $config |= _BV(RF24L01::CRCO);
		}  
		$this->write_register( RF24L01::CONFIG, $config ) ;
	}

	public function disableCRC(){
		$this->setCRCLength(self::RF24_CRC_DISABLED);
	}

	public function setChannel($channel){
		$max_channel = 127;
		$this->write_register(RF24L01::RF_CH,min($channel,$max_channel));
	}

	public function setRetries($delay, $count){
		$this->write_register(RF24L01::SETUP_RETR,($delay&0xf)<<RF24L01::ARD | ($count&0xf)<<RF24L01::ARC);
	}

	public function openWritingPipe($value){
		$this->write_register(RF24L01::RX_ADDR_P0, array_reverse($value), 5);
		$this->write_register(RF24L01::TX_ADDR, array_reverse($value), 5);
		$this->write_register(RF24L01::RX_PW_P0,min($this->payload_size, self::max_payload_size));
	}

	private $child_pipes = array (
		RF24L01::RX_ADDR_P0, 
		RF24L01::RX_ADDR_P1,
		RF24L01::RX_ADDR_P2,
		RF24L01::RX_ADDR_P3,
		RF24L01::RX_ADDR_P4,
		RF24L01::RX_ADDR_P5
	);

	private $child_payload_size = array(
		RF24L01::RX_PW_P0,
		RF24L01::RX_PW_P1,
		RF24L01::RX_PW_P2,
		RF24L01::RX_PW_P3,
		RF24L01::RX_PW_P4,
		RF24L01::RX_PW_P5
	);

	private $child_pipe_enable = array(
		RF24L01::ERX_P0, 
		RF24L01::ERX_P1, 
		RF24L01::ERX_P2, 
		RF24L01::ERX_P3, 
		RF24L01::ERX_P4, 
		RF24L01::ERX_P5
	);
	public function openReadingPipe($child, $address){
		$address = array_reverse($address);
		if ($child == 0)
		$this->pipe0_reading_address = $address;
		if ($child <= 6){
			$this->write_register($this->child_pipes[$child], $address, $child < 2 ? 5 : 1);
		$this->write_register($this->child_payload_size[$child], min($this->payload_size, self::max_payload_size));
			$this->write_register(RF24L01::EN_RXADDR, $this->read_register(RF24L01::EN_RXADDR) | _BV($this->child_pipe_enable[$child]));
		}
	}

	public function startListening(){
		$this->write_register(RF24L01::CONFIG, $this->read_register(RF24L01::CONFIG) | _BV(RF24L01::PWR_UP) | _BV(RF24L01::PRIM_RX));
		$this->write_register(RF24L01::STATUS, _BV(RF24L01::RX_DR) | _BV(RF24L01::TX_DS) | _BV(RF24L01::MAX_RT) );
		if (is_array($this->pipe0_reading_address))
			$this->write_register(RF24L01::RX_ADDR_P0, $this->pipe0_reading_address, 5);
		$this->flush_rx();
		$this->flush_tx();
		$this->ce(self::HIGH);
		usleep(130);
	}

	public function stopListening(){
		$this->ce(self::LOW);
		$this->flush_tx();
		$this->flush_rx();
	}

	public function powerDown(){
		$this->write_register(RF24L01::CONFIG, $this->read_register(RF24L01::CONFIG) & ~_BV(RF24L01::PWR_UP));
	}

	public function powerUp(){
		$this->write_register(RF24L01::CONFIG, $this->read_register(RF24L01::CONFIG) | _BV(RF24L01::PWR_UP));
	}

	public function whatHappened(){
		$status = $this->write_register(RF24L01::STATUS,_BV(RF24L01::RX_DR) | _BV(RF24L01::TX_DS) | _BV(RF24L01::MAX_RT) );
		return array ($status & _BV(RF24L01::TX_DS), $status & _BV(RF24L01::MAX_RT), $status & _BV(RF24L01::RX_DR));
	}

	public function decodeObserve_tx($value){
		return array(
			'OBSERVE_TX' => $value,
			'POLS_CNT'	=> ($value >> RF24L01::PLOS_CNT) & 0b1111,
			'ARC_CNT' => ($value >> RF24L01::ARC_CNT) & 0b1111,
		);
	}

	public function write( $buf, $len = NULL)
	{
		$result = false;
		if ($len == NULL){
			$len = count($buf);
		}
		$this->startWrite($buf, $len);
		$observe_tx = array();
		$sent_at = __millis();
		do {
			list($status, $observe_tx) = $this->read_register_buf(RF24L01::OBSERVE_TX, 1);
			$this->log($this->printDetails(array('OBSERVE_TX' => $this->decodeObserve_tx(current($observe_tx))), true));
			$this->log($this->printDetails(array('STATUS' => $this->decodeStatus($status)), true));
		} while( ! ( $status & ( _BV(RF24L01::TX_DS) | _BV(RF24L01::MAX_RT) ) ) && ( __millis() - $sent_at < self::timeout ) );
		list($tx_ok, $tx_fail, $ack_payload_available) = $a = $this->whatHappened();
		$result = $tx_ok;
		if ( $ack_payload_available ){
			$ack_payload_length = $this->getDynamicPayloadSize();
		}
		$this->powerDown();
		$this->flush_tx();
		return $result;
	}

	public function read_payload($len){
		$data_len = min($len,$this->payload_size);
		$blank_len = $this->dynamic_payloads_enabled ? 0 : $this->payload_size - $data_len;
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer( array(RF24L01::R_RX_PAYLOAD )));
		$data = array();
		while ( $data_len-- )
			$data[]= current($this->SPI->transfer(array(0xff)));
		while ( $blank_len-- )
			$this->SPI->transfer(array(0xff));
		$this->csn(self::HIGH);
		return array($status, $data);
	}

	public function read($len){ 
		list($status, $buf) = $this->read_payload($len);
		return array($this->read_register(RF24L01::FIFO_STATUS) & _BV(RF24L01::RX_EMPTY), $buf);
	}

	public function getDynamicPayloadSize(){
		$this->csn(self::LOW);
		$this->SPI->transfer( array(RF24L01::R_RX_PL_WID ));
		$result = current($this->SPI->transfer(array(0xff)));
		$this->csn(self::HIGH);
		return $result;
	}

	public function available($pipe_num = NULL){
		$status = $this->get_status();
		$result = ( $status & _BV(RF24L01::RX_DR) );
		if ($result){
			if ( $pipe_num )
				$pipe_num = ( $status >> RF24L01::RX_P_NO ) & 0b111;
			$this->write_register(RF24L01::STATUS,_BV(RF24L01::RX_DR) );
			if ( $status & _BV(RF24L01::TX_DS) ){
				$this->write_register(RF24L01::STATUS,_BV(RF24L01::TX_DS));
			}
		}
		return $result;
	}

	public function startWrite( $buf, $len = NULL){
		if ($len == null)
			$len = count($buf);
		$this->write_register(RF24L01::CONFIG, ( $this->read_register(RF24L01::CONFIG) | _BV(RF24L01::PWR_UP) ) & ~_BV(RF24L01::PRIM_RX) );
		usleep(150);
		$this->write_payload( $buf, $len );
		$this->ce(self::HIGH);
		usleep(15);
		$this->ce(self::LOW);
	}

	protected function write_payload($buf, $len = NULL){
		if ($len == NULL)
			$len = count($buf);
		$data_len = min($len, $this->payload_size);
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer( array(RF24L01::W_TX_PAYLOAD) ));
		$buf = array_slice($buf, 0, $data_len);
		if (!$this->dynamic_payloads_enabled){
			$buf += array_fill(0, $this->payload_size, 0);
		}
		$this->SPI->transfer($buf);
		$this->csn(self::HIGH);
		return $status;
	}

	public function get_status(){
		$this->csn(self::LOW);
		$status = current($this->SPI->transfer( array(RF24L01::NOP )));
		$this->csn(self::HIGH);
		return $status;
	}

	public function decodeStatus($status = NULL){	
		if ($status == NULL)
			$status = $this->get_status();
		return array(
			'status' => $status,
			'RX_DR' => ($status & _BV(RF24L01::RX_DR))?1:0,
			'RX_DS' => ($status & _BV(RF24L01::TX_DS))?1:0,
			'MAX_RT' => ($status & _BV(RF24L01::MAX_RT))?1:0,
			'RX_P_NO' => (($status >> RF24L01::RX_P_NO) & 0b111),
			'TX_FULL' => ($status & _BV(RF24L01::TX_FULL))?1:0
		);
	}

	public function printDetails($value = NULL, $return = false){
		$result = "";
		if ($value == NULL)
			$value = $this->getDetails();
		foreach($value as $k=>$v){
			if (is_numeric($v)){
				$result .= sprintf("%-20s= 0x%02x\r\n", $k, $v);
			} elseif (is_array($v)){
				$result .= sprintf("%-20s= [ %s ]\r\n", $k, join(', ', array_map(function($a, $k){ return sprintf("%s0x%02x", is_int($k)?"":"$k = ", $a);}, $v, array_keys($v))));
			} else {
				$result .= sprintf("%-20s= %s\r\n", $k, $v);
			}
		}
		if ($return)
			return $result;
		echo $result;
	}

	public function getDataRate(){
		switch ($this->read_register(RF24L01::RF_SETUP) & (_BV(RF24L01::RF_DR_LOW) | _BV(RF24L01::RF_DR_HIGH))) {
			case _BV(RF24L01::RF_DR_LOW) : return self::RF24_250KBPS;
			case _BV(RF24L01::RF_DR_HIGH) : return self::RF24_2MBPS;
			default : return  self::RF24_1MBPS;
		}
	}

	public function getCRCLength(){
		$config = $this->read_register(RF24L01::CONFIG) & ( _BV(RF24L01::CRCO) | _BV(RF24L01::EN_CRC));
		if ( $config & _BV(RF24L01::EN_CRC ) ){
			if ( $config & _BV(RF24L01::CRCO) )
				return self::RF24_CRC_16;
			else
				return self::RF24_CRC_8;
		}
		return self::RF24_CRC_DISABLED;
	}
	
	public function getPALevel(){
		switch ($this->read_register(RF24L01::RF_SETUP) & (_BV(RF24L01::RF_PWR_LOW) | _BV(RF24L01::RF_PWR_HIGH))) {
			case (_BV(RF24L01::RF_PWR_LOW) | _BV(RF24L01::RF_PWR_HIGH)) : return self::RF24_PA_MAX;
			case _BV(RF24L01::RF_PWR_HIGH)	: return self::RF24_PA_HIGH;
			case _BV(RF24L01::RF_PWR_LOW)	: return self::RF24_PA_LOW;
			default : return self::RF24_PA_MIN;
		}
	}

	public function testCarrier(){
		return ( $this->read_register(RF24L01::CD) & 1 );
	}

	public function testRPD(){
		return ( $this->read_register(RF24L01::RPD) & 1 ) ;
	}

	public function getDetails(){
		return array(
			'ce_pin' => $this->ce_pin,
			'Status' => $this->decodeStatus(),
			'RX_ADDR_P0-1' => $this->get_registers_buf(RF24L01::RX_ADDR_P0, 5, 2),
			'RX_ADDR_P2-5' => $this->read_registers(RF24L01::RX_ADDR_P2, 4),
			'TX_ADDR' => $this->get_registers_buf(RF24L01::TX_ADDR,5),
			'RX_PW_P0-6' => $this->read_registers(RF24L01::RX_PW_P0, 6),
			'EN_AA' => $this->read_register(RF24L01::EN_AA),
			'EN_RXADDR' => $this->read_register(RF24L01::EN_RXADDR),
			'RF_CH' => $this->read_register(RF24L01::RF_CH),
			'RF_SETUP' => $this->read_register(RF24L01::RF_SETUP),
			'CONFIG' => $this->read_register(RF24L01::CONFIG),
			'DYNPD/FEATURE' => $this->read_registers(RF24L01::DYNPD,2),
			'Data Rate' => $this->RF24_DataRates[$this->getDataRate()],
			'Model' => "nRF24L01".($this->p_variant?"+":""),
			'CRC Length' => $this->RF24_CRCLengths[$this->getCRCLength()],
			'PA Power' => $this->RF24_PA[$this->getPALevel()]
		);
	}
}
