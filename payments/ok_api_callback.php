<?php

require_once __DIR__ . '/../Services/API.php';

class OKPaymentsCallback extends API
{
  const UNKNOWN_ERROR = 1; //Неизвестная ошибка
  const SERVICE_ERROR = 2; //Сервис временно недоступен
  const UNKNOWN_METHOD = 3; //Method does not exist
  const CALLBACK_INVALID_PAYMENT_ERROR = 1001; //Платеж неверный и не может быть обработан 
  const SYSTEM_ERROR = 9999; //Критический системный сбой, который невозможно устранить
  const PARAM_SIGNATURE_ERROR = 104; //Неверная подпись
  const INVALID_PARAMS = 101;//Parameter application_key not specified or invalid

  //Секретный ключ приложения
  private $APPLICATION_SECRET_KEY = 'F3251E431E9D63B73995F49E'; 
  //Публичный ключ приложения
  private $APPLICATION_KEY = 'CBAQOPCMABABABABA';
  
  //Запрос
  private $input;
  
  //Информация о пользователе
  private $userInfo;
  
  //В случае ошибки, тут будет записаны
  private $errorCode = 0; //Код ошибки
  private $errorMessage;  //Описание ошибки
  
  private function oopsError( $code, $msg )
  {
    $this->errorCode    = $code;
	$this->errorMessage = $msg;
  }
  
  
  //Проверяет подпись запроса, анализирует пришедшие данные
  //Возвращает true  - если все ok
  //           false - ошибка
  public function load() 
  {
    $this->input = $_REQUEST;
    
    if ( $this->input[ 'method' ] != "callbacks.payment" )
	{
	  $this->oopsError( self::UNKNOWN_METHOD, 'Method does not exist' );
	  return false;
	}
	
	if ( $this->input[ 'application_key' ] != $this->APPLICATION_KEY )
	{
	  $this->oopsError( self::INVALID_PARAMS, 'Parameter application_key not specified or invalid' );
	  
	  return false;
	}
	
	// Проверка подписи
    $sig = $this->input[ 'sig' ];
    unset( $this->input[ 'sig'] );
    ksort( $this->input );
    $str = '';
    foreach ( $this->input as $k => $v )
	{
      $str .= $k . '=' . $v;
    }

    if ( $sig != md5( $str . $this->APPLICATION_SECRET_KEY ) )
	{
	  $this->oopsError( self::PARAM_SIGNATURE_ERROR, 'Ошибка подписи' );
	
      return false;								
    }
	
	try
	{
	  $this->userInfo = $this->getNetUserInfo( $this->input[ 'uid' ], 'id' );
	
	//Проверяем зарегистрирован ли такой пользователь
	  if ( $this->userInfo == null )
	  {
	    $this->oopsError( self::INVALID_PARAMS, "Пользователь с uid= {$this->input[ 'uid' ]} не зарегистрирован"  );
	  
	    return false;
	   }
	}
	catch ( Exception $e )
	{
	  $this->oopsError( self::UNKNOWN_ERROR, 'DB Error' );
	  return false;
	}
	
	return true;
  }
  
  //Префикс идентификатора товара монет ( coins_1000 ) и т.д.
  private $ITEM_ID_PREFIX = 'coins';
  
  //Парсит идентификатор товара и определяет его стоимость
  //количество монет - если все ok
  //false - если идентификатор некорректный
  private function extractNumCoins( $item )
  {
     $item_id = explode( '_', $item );
	
	//Проверяем корректность идентификатора товара
	if ( count( $item_id ) < 2 )
	{
	   $this->oopsError( self::INVALID_PARAMS, 'Неверный идентификатор товара' );
	   return false;
	}
	
	if ( $item_id[ 0 ] != $this->ITEM_ID_PREFIX )
	{
	  $this->oopsError( self::INVALID_PARAMS, 'Неверный идентификатор товара' );
	   return false;
	}
	
	if ( ! ctype_digit( $item_id[ 1 ] ) )
	{
	  $this->oopsError( self::INVALID_PARAMS, 'Неверный идентификатор товара' );
	  return false;
	}
	
	return (int) $item_id[ 1 ]; 
  }
  
  public function process()
  {
    if ( $this->load() )
	 {
	   $this->processOrder();
	 }
	 
	$this->flush(); 
  }
  
  //Автоматически обрабатывает запрос
  public function processOrder() 
  {
    $numCoins = $this->extractNumCoins( $this->input['product_code'] );
	
	if ( $numCoins === false )
	 {
	   return false;
	 }
	 
	//Учитывать ли бонус
	$useBonus = false;
	if ( isset( $this->input[ 'extra_attributes' ] ) )
	{
	  $data = json_decode( $this->input[ 'extra_attributes' ] );
	  if ( isset( $data->bonus ) )
	  {
	   $useBonus = $data->bonus;
	  }
	}
	
	//Вычисляем количество монет которое необходимо зачислить на счет с учетом бонуса
	$bonus = 0;
	
	if ( $useBonus )
	{
	  $bonus = floor( $numCoins * ( $this->BONUS_PERCENT / 100 ) );
	}
	
	$totalCoins = $numCoins + $bonus;
	
	$r = mysql_query( "insert into {$this->payments_table} (time,user_id,net_user_id,transaction_id,product_code,price,coins,bonus) values ('{$this->input['transaction_time']}',{$this->userInfo['id']},'{$this->input['uid']}','{$this->input['transaction_id']}','{$this->input['product_code']}',{$this->input['amount']},{$totalCoins},{$bonus})" );
	if ( ! $r )
	{
	  $this->oopsError( self::UNKNOWN_ERROR, 'DB Error' );
	  return false;
	}
	
	//Зачисляем
	$r = mysql_query( "update {$this->users_table} set money=money+{$totalCoins} where id={$this->userInfo['id']}" );
	
	if ( ! $r )
	{
	  $this->oopsError( self::UNKNOWN_ERROR, 'DB Error' );
	  return false;
	}
	
	try
	{
	 //Проталкиваем сообщение о зачислении монет
	  $this->addCommand( $this->userInfo['id'], $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );
	}
	catch ( Exception $e )
	{
	  $this->oopsError( self::UNKNOWN_ERROR, 'DB Error' );
	  return false;
	}

	return true;
  }
  
  //Посылает ответ серверу
  public function flush() 
  {
    header('Content-Type: application/xml; charset=utf-8');
	
	if ( $this->errorCode != 0 )
	{
	    header( "invocation-error: {$this->errorCode}" ); 
	}
	
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	
	if ( $this->errorCode == 0 )
	{
	  echo '<callbacks_payment_response xmlns="http://api.forticom.com/1.0/">true</callbacks_payment_response>';
	}
	else
	{
	  echo "<ns2:error_response xmlns:ns2='http://api.forticom.com/1.0/'><error_code>{$this->errorCode}</error_code><error_msg>{$this->errorMessage}</error_msg></ns2:error_response>";
	}
  }
}

$p = new OKPaymentsCallback();
$p->process();
?> 