<?php

require_once __DIR__ . '/SessionManager.php';

class API extends SessionManager
 {
    protected $UNKNOWN_ERROR = -1;
	
    protected $PROJECT_WITH_THIS_NAME_ALREADY_EXISTS = -2; //Проект с таким именем уже существует
	protected $NOT_CORRECT_PROJECT_DATA = -3; //Данные проекта не корректные или содержат ошибку
	
    protected $MAX_PROJECTS_FOR_BASIC_MODE_EXCEEDED_ERROR = -5; //Максимальное количество миксов, для базового аккаунта исчерпано
	protected $MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR        = -10; //Маскимальное количество проектов в день превышено
	
	protected $NOT_ENOUGH_MONEY_ERROR = -15; //Недостаточно монет для выполнения операции
	protected $PRICE_INDEX_NOT_EXISTS = -20; //Указан неверный индекс операции
    
	protected $USER_NOT_REGISTERED_ERROR = - 98; //Пользователь не зарегистрирован
	protected $USER_ALREADY_REGISTERED_ERROR = - 100; //Пользователь уже зарегистрирован
	
    const MAX_PROJECTS = 16; //Максимальное количество проектов для одного пользователя ( Для не PRO пользователей )
	const MAX_PROJECTS_PER_DAY = 10; //Максимальное количество миксов которое может создавать пользователь в день
 
	protected $PUBLICATION_PRICE = 10; //Цена в монетах за одну публикацию
	protected $BONUS_PERCENT = 15; //Коэффициент для определения бонуса в процентах
	protected $INVITE_USER_BONUS = 10; //Бонус за приглашенного друга в монетах
	
	const secret = "umFAlFfSC6htif9qGuXX";
	
	const project_fields = 'id,UNIX_TIMESTAMP( updated ) as updated,UNIX_TIMESTAMP( created ) as created,owner,name,genre,userGenre,tempo,duration,description,access,readonly';
	const user_fields = 'id, UNIX_TIMESTAMP( registered ) as registered, UNIX_TIMESTAMP( loged_in ) as loged_in, net_user_id, money';
	
	private $numbers = array( 'первый', 'второй', 'третий', 'четвертый', 'пятый', 'шестой', 'седьмой', 'восьмой', 'девятый', 'десятый', 'одиннадцатый', 'двенадцатый', 'тринадцатый', 'четырнадцатый', 'пятнадцатый', 'шестнадцатый', 'семнадцатый', 'восемнадцатый', 'девятнадцатый', 'двадцатый' );
	
	//Секунд в одном дне							
	private $SECONDS_IN_DAY = 86400;							
	
	//Возвращает идентификатор пользователя в системе
	public function connect( $net, $netUserID, $secret )
	{ 
	  if ( $secret != md5( $net . $netUserID . self::secret ) )
	   return $this->ERROR;
	   
	  //Проверяем зарегистрирован ли уже такой пользователь
	  $userInfo = $this->getNetUserInfo( $netUserID, self::user_fields );
	  
	  if ( $userInfo == null )
	   return $this->USER_NOT_REGISTERED_ERROR; 
	 
	   //Обновляем информацию о времени логина
	   $r = mysql_query( "update " . $this->users_table . " set loged_in=NOW() where id={$userInfo['id']}" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	   $userInfo[ "session_id" ] = $this->startSession( $userInfo );
	   
	   return $this->addStaticParams( $userInfo ); 
	}
	
	//Бонус, вновь зарегистрированные пользователи получают BONUS_COINS монет
	private $BONUS_COINS = 100; //монет
	
	//Регистрирует нового пользователя
	public function register( $net, $netUserID, $secret )
	{
	  if ( $secret != md5( $net . $netUserID . self::secret ) )
	   return $this->ERROR;
	      
	  //Проверяем зарегистрирован ли уже такой пользователь
	  $userInfo = $this->getNetUserInfo( $netUserID );
	  
	  if ( $userInfo != null )
	   return $this->USER_ALREADY_REGISTERED_ERROR;
	  
	    $r = mysql_query( "insert into " . $this->users_table . " ( registered, loged_in, net_user_id, money, time ) values ( NOW(),NOW(),'{$netUserID}', {$this->BONUS_COINS}, 0 )" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  //Получаем информацию о вновь зарегистрированном пользователе
	  $userInfo = $this->getNetUserInfo( $netUserID, self::user_fields );
      
	  if ( $userInfo == null )
	   return $this->ERROR;
		
	   $userInfo[ "session_id" ] = $this->startSession( $userInfo );
	   
	   return $this->addStaticParams( $userInfo );
	}
	
	private function addStaticParams( $info )
	{
	  $info[ 'publicationPrice' ] = $this->PUBLICATION_PRICE;
	  $info[ 'bonusPercent' ] = $this->BONUS_PERCENT;
	  $info[ 'inviteUserBonus' ] = $this->INVITE_USER_BONUS;
	  
	  return $info;
	}
	
	protected function getUserInfo( $user_id, $fields = 'id' )
	{
	  $r = mysql_query( "select {$fields} from {$this->users_table} where id={$user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	  
	  if ( mysql_num_rows( $r ) > 0 )	
	   return mysql_fetch_assoc( $r );
	   
	  return null; 
	}
	
	protected function getNetUserInfo( $net_user_id, $fields = 'id' )
	{
	  $r = mysql_query( "select {$fields} from {$this->users_table} where net_user_id={$net_user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	  
	  if ( mysql_num_rows( $r ) > 0 )	
	   return mysql_fetch_assoc( $r );	
		
	  return null;
	}
	
	protected function netUserExists( $net_user_id )
	{
	  $r = mysql_query( "select id from {$this->users_table} where net_user_id = {$net_user_id}" );
	  
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_num_rows( $r ) > 0;	
	}
	
	//Сортирует список пользователей по дате последнего обновления последнего микса
	//Возвращает массив отсортированных идентификаторов с дополнительной информацией
	public function orderUserList( $session_id, $net_user_ids )
	{
	  if ( ! $this->sessionExists( $session_id ) )
	   {
		  return $this->SESSION_NOT_FOUND_ERROR;
	   }   
	
	  $result = array();   
	  
	  //Узнаем сколько миксов у каждого из пользователей и узнаем дату последнего обновления последнего из миксов
	  foreach( $net_user_ids as $net_user_id )
	  {
		  $userInfo = $this->getNetUserInfo( $net_user_id );
		  
		  if ( $userInfo )
		  {
		    $r = mysql_query( "select UNIX_TIMESTAMP( updated ) as updated from {$this->projects_table} where owner={$userInfo[ 'id' ]} order by updated desc limit 0,1" );
		
		    if ( ! $r )
		     throw new Exception( mysql_error(), mysql_errno() );
		 
		    $row = mysql_fetch_assoc( $r );
			 
		    if ( $row[ 'updated' ] == null )
		    {
			   $row[ 'updated' ] = 0;
		    }
		  
		    $row[ 'uid' ] = $net_user_id;
		
		    $result[] = $row;
		  }
	  }
	  
	  //Сортируем полученный массив по полю updated
	  function cmp( $a, $b )
      {
        $upd1 = (int) $a[ 'updated' ];
		$upd2 = (int) $b[ 'updated' ];
		
	    if ( $upd1 == $upd2 ) return 0;
		
		return $upd1 < $upd2 ? 1 : -1;
      }
	  
	  usort( $result, 'cmp' );
	  
	  return $result;
	}
	
	public function browseProjectsByNetUserID( $session_id, $net_user_id, $offset, $limit )
	{
	  $userInfo = $this->getNetUserInfo( $net_user_id );
	  
	  if ( $userInfo )
	  {
	    return $this->browseProjects( $session_id, $userInfo['id'], $offset, $limit );
	  }
	  
	  $result = array();
	  $result[ 'count' ] = 0;
	  $result[ 'data' ] = array();
	  
	  return $result;
	}
	
	//Возвращает список проектов сохраненных пользователем
	public function browseProjects( $session_id, $user_id, $offset, $limit )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
	  
	  if ( $user_id == null )
	  {
	    $user_id = $sessionData[ 'user_id' ];
	  }
	   
	  $query = "select " . self::project_fields . " from {$this->projects_table} where";
	  
	  $where = " (owner={$user_id})";
	  
	  //Запрашиваем список миксов другого пользователя
	  if ( $user_id != $sessionData[ 'user_id' ] )
	  {
	    $where .= " and ((access='friends') or (access='all'))";
	  }
	  
	  $query .= $where . " order by updated desc";
	  
	  if ( $limit > 0 )
	  {
	    $query .= ' limit ' . $offset . ',' . $limit;
	  }
		//echo( $query );
	  $r = mysql_query( $query );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $data = array();
	  
	  while ( $row = mysql_fetch_assoc( $r ) )
	  {
	    $data[] = $row;
	  }
	  
	  $r = mysql_query( 'select count(*) as count from ' . $this->projects_table . " where {$where}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  
	  $result = array();
	  $result[ 'count' ] = mysql_fetch_assoc( $r );
	  $result[ 'data' ] = $data;
		
	  
	  return $result;
	}
	
	public function browseExamples( $session_id, $offset, $limit )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
	  
	  $query = "select " . self::project_fields . " from " . $this->examples_table . " order by updated desc";
	  
	  if ( $limit > 0 )
	  {
	    $query .= ' limit ' . $offset . ',' . $limit;
	  }
	 	
	  $r = mysql_query( $query );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $data = array();
	  
	  while ( $row = mysql_fetch_assoc( $r ) )
	  {
	    $data[] = $row;
	  }
	  
	  $r = mysql_query( 'select count(*) as count from ' . $this->examples_table );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  
	  $result = mysql_fetch_assoc( $r );
	  $result[ 'data' ] = $data;
		
	  
	  return $result;
	}
	
	private function getProjectInfoByName( $user_id, $projectName )
	{
	  /*$sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }*/
	
	  $r = mysql_query( "select " . self::project_fields . " from " . $this->projects_table . " where ( owner = " . $user_id . " ) and ( UPPER( name ) = UPPER( '" . mysql_real_escape_string( $projectName ) . "' ) ) " );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_fetch_assoc( $r );	
	}
	
	private function getProjectInfoByID( $projectID )
	{
	  /*if ( ! $this->sessionExists( $session_id ) )
	   {
		  return $this->SESSION_NOT_FOUND_ERROR;
	   } */  
	
	  $r = mysql_query( "select " . self::project_fields . " from " . $this->projects_table . " where id = " . $projectID );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_fetch_assoc( $r );
	}
	
	public function removeProject( $session_id, $projectID )
	{
	   if ( ! $this->sessionExists( $session_id ) )
	   {
		  return $this->SESSION_NOT_FOUND_ERROR;
	   }
		
	   $projectInfo = $this->getProjectInfoByID( $projectID );
	   
	   if ( ! $projectInfo )
	    throw new Exception( 'Internal error', 100 );
	   
	   $r = mysql_query( "delete from " . $this->projects_table . " where id = " . $projectID );
	}
	
	public function resolveName( $session_id, $projectName )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
	  
	  $i = 0;
	  $newProjectName = $projectName;
	  
	  do
		{
		  $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $newProjectName );
		  $i ++;
		  
		  if ( $projectInfo )
		  {
		    $newProjectName = $projectName . ' ' . $i;
		  } 
		}
		while ( $projectInfo );
		
		return $newProjectName;
	}
	
	//Возвращает доступное название проекта по умолчанию
	public function getDefaultProjectName( $session_id )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	  if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
		
	     //Проверяем не исчерпал ли этот пользователь лимит на миксы
	     $code = $this->getProjectsLimitations( $sessionData[ 'user_id' ] );
		 
		 if ( $code != $this->OK )
		 {
		   return $code;
		 }
		
		$i = 0;
		
		do
		{
		  if  ( count( $this->numbers ) > $i )
		   {
		     $projectName = 'Мой ' . $this->numbers[ $i ] . ' микс';
		   }
		   else
		   {
		     $projectName = 'Мой микс #' . ( $i + 1 ); 
		   }
		
		  $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $projectName );
		  $i ++;
		}
		while( $projectInfo );
		
		
		return $projectName;
	}
	
	//Проверяет корректность XML данных проекта
	private function isValidProjectData( $data )
	{
	  $dom = new DOMDocument('1.0', 'utf-8');
	  return @$dom->loadXML( $data );
	}
	
	//Обновляет информацию о проекте
	//Доступные поля
	//$info->id
	//$info->name
	//$info->tempo
	//$info->genre
	//$info->duration
	//$info->description
	public function updateProject( $session_id, $info, $data )
	{
	   $sessionData = $this->getSessionData( $session_id );
	  
	   if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
		
		$projectInfo = $this->getProjectInfoByID( $info->id );
		
		if ( ! $projectInfo  )
		 throw new Exception( 'Internal error', 100 );
		
		if ( strtoupper( $projectInfo[ "name" ] ) != strtoupper( $info->name ) )
		{
		  //Проверяем есть ли проект с таким именем
		  $projectInfo2 = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
		  
		  if ( $projectInfo2 ) //Если есть, то ничего больше не делаем и возвращаем false
		   {
		     return $this->PROJECT_WITH_THIS_NAME_ALREADY_EXISTS;
		   }
		}
		
		$info->userGenre = (int)$info->userGenre;
		$info->readonly = (int)$info->readonly;
		$info = $this->mysql_escape_object( $info );
		
		$query = "update {$this->projects_table} set updated=NOW(),name='{$info->name}',tempo={$info->tempo},genre='{$info->genre}',userGenre={$info->userGenre},duration={$info->duration},description='{$info->description}',access='{$info->access}',readonly={$info->readonly}";
		
		if ( isset( $data ) )
		{
		  if ( $this->isValidProjectData($data) === false )
		  {
		    return $this->NOT_CORRECT_PROJECT_DATA;
		  }
		
		  $data = mysql_real_escape_string($data);
		  $query .= ",data='{$data}'";
		}
		
		$query .= " where id={$info->id}";
		
		$r = mysql_query( $query  );
		
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		return $projectInfo[ 'id' ];  
	}
	
	//Количество секунд в одном дне
	const SECONDS_IN_DAY = 86400;
	
	//Возвращает количество миксов созданных пользователем в течении одного дня
	private function getNumProjectsCreatedToday( $user_id )
	{
	  $r = mysql_query( "select count( * ) as count from {$this->projects_table} where (owner={$user_id}) and (UNIX_TIMESTAMP() - UNIX_TIMESTAMP( created ) < " . self::SECONDS_IN_DAY . ")" );
	  
	  if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
	  $row = mysql_fetch_assoc( $r );
	  
	  return $row[ 'count' ];
	}
	
	//Возвращает общее количество миксов созданных пользователем
	private function getUserProjectsCount( $user_id )
	{
	  $r = mysql_query( 'select count( * ) as count from ' . $this->projects_table . ' where owner=' . $user_id );
	  
	  if ( ! $r )
	  {
	    throw new Exception( mysql_error(), mysql_errno() ); 
	  }
	  
	  $row = mysql_fetch_assoc( $r );
	  
	  return $row[ 'count' ];
	}
	
	//Проверяет может ли пользователь создавать новые миксы
	//Возвращает MAX_PROJECTS_FOR_BASIC_MODE_EXCEEDED_ERROR = 5, если Максимальное количество миксов, для базового аккаунта исчерпано
	//Возвращает MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR = 10, если Маскимальное количество проектов в день превышено
	// OK - если никаких ограничений нет
	private function getProjectsLimitations( $user_id )
	{
	  //Ограничение на максимальное количество создаваемых проектов в день
	  if ( $this->getNumProjectsCreatedToday( $user_id ) >= self::MAX_PROJECTS_PER_DAY )
	  {
		    return $this->MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR;
	  }
	     
		return $this->OK;  
	}
	
	//Проверяет не исчерпал ли пользователь
	//1. Максимальное количество миксов
	public function getLimitations( $session_id )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	  if ( $sessionData === false )
	  {
	    return $this->SESSION_NOT_FOUND_ERROR;
	  }
	  
	  $result = new stdClass();
	  $result->projects = $this->getProjectsLimitations( $sessionData[ 'user_id' ] );
	  
	  return $result;
	}

	//Сохраняет проект первый раз, возвращает идентификатор проекта
	//Доступные поля
	//$info->name
	//$info->tempo
	//$info->genre
	//$info->duration
	//$info->description
	public function saveProject( $session_id, $info, $data  )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	   if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
       
	   $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
	   
	   if ( $projectInfo == null ) //Проект ранее не сохранялся
	   {
		 //Проверяем не исчерпал ли этот пользователь лимит на миксы
	     $code = $this->getProjectsLimitations( $sessionData[ 'user_id' ] );
		 
		 if ( $code != $this->OK )
		 {
		   return $code;
		 }
		 
		 if ( $this->isValidProjectData($data) === false )
		 {
		  return $this->NOT_CORRECT_PROJECT_DATA;
		 }
		 
		 $info->userGenre = (int)$info->userGenre;
		 $info->readonly = (int)$info->readonly;
	     $info = $this->mysql_escape_object( $info );
		  
		  $data = mysql_real_escape_string( $data );
		
	     $r = mysql_query( "insert into {$this->projects_table} (updated,created,owner,name,genre,userGenre,tempo,duration,description,data,access,readonly) values (NOW(),NOW(),{$sessionData[ 'user_id' ]},'{$info->name}','{$info->genre}',{$info->userGenre},{$info->tempo},{$info->duration},'{$info->description}','{$data}','{$info->access}',{$info->readonly})" );
		 
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		 $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
	   }
	   else return $this->PROJECT_WITH_THIS_NAME_ALREADY_EXISTS;
	   
	   return $projectInfo[ 'id' ];
	}
	
	//Загружает проект
	public function getProject( $session_id, $projectID, $source )
	{
	  if ( ! $this->sessionExists( $session_id ) )
	   {
		  return $this->SESSION_NOT_FOUND_ERROR;
	   }  
	
	  if ( $source == 0 ) //Открываем пример
	  {
	    $table = $this->examples_table;
	  }
	  else //Открываем проект пользователя
	  { 
		 $table = $this->projects_table;
	  }
	
	   $r = mysql_query( "select data from " . $table . " where id=" . $projectID );
	   
	   if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
	   
	   if ( mysql_num_rows( $r ) === 0 )
	    throw new Exception( 'Internal error', 100 );
	
	   $row = mysql_fetch_assoc( $r );	
		
	   return $row[ 'data' ];	
	}
	
	//Фиксирует публикацию микса
	public function publish( $session_id, $projectID )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	   if ( $sessionData === false )
	   {
	     return $this->SESSION_NOT_FOUND_ERROR;
	   }
	   
	   $userInfo = $this->getUserInfo( $sessionData[ 'user_id' ], 'money,net_user_id' );
	   
	   //Проверяем достаточно ли монет для операции
	   if ( $userInfo[ 'money' ] < $this->PUBLICATION_PRICE )
	   {
		  return $this->NOT_ENOUGH_MONEY_ERROR;
	   }
	   
	   //Снимаем необходимое количество монет
	   $balance = $userInfo[ 'money' ] - $this->PUBLICATION_PRICE;
	   
	   $r = mysql_query( "update {$this->users_table} set money={$balance} where id={$sessionData[ 'user_id' ]}" );
	   if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		 
	   //Проталкиваем сообщение о изменении кол-ва монет  
	   $this->addCommand( $sessionData[ 'user_id' ], $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );  
		
	   //Логируем 
	   $r = mysql_query( "insert into {$this->publications_table} (date,user_id,net_user_id,project_id) values(NOW(),'{$sessionData['user_id']}','{$userInfo['net_user_id']}',{$projectID})" );
	   if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
	   
	   return $this->OK; 	  
	}
	
	private function addServerUpdateInfo( $object )
	{
	      //Проверяем на наличие обновлений
		  $object->update = 0;
		  
		  $r = mysql_query( "select id, enabled from " . $this->updates_table . " order by id desc limit 0,1" );
		  
		  if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
		  if ( mysql_num_rows( $r ) > 0 )
		   {
		     $row = mysql_fetch_assoc( $r );
			 
			 if ( $row[ 'enabled' ] == 1 )
			  {
			    $r = mysql_query( "select UNIX_TIMESTAMP( end ) as end, reason from " . $this->updates_table . " where id={$row['id']}" );
				
				if ( ! $r )
		         throw new Exception( mysql_error(), mysql_errno() );
				
				$row = mysql_fetch_assoc( $r );
				
				$object->end    = $row[ 'end' ];
				$object->reason = $row[ 'reason' ];
				$object->update = 1;
			  }
		   }
	}
			
	public function touch( $session_id, $time )
	{
	  $sessionData = $this->getSessionData( $session_id, 'commands, user_id' );
	  
	  if ( $sessionData === false )
	  {
	     return $this->SESSION_NOT_FOUND_ERROR;
	  }
	      
	      $result = new stdClass();
		  
		  //Если есть данные, то проталкиваем данные клиенту
		  if ( $sessionData[ 'commands' ] == 1 )
		  {
		    $this->pushCommands( $sessionData[ 'user_id' ], $result );
		  }
		  
		  //Добавляем информацию по поводу обновления сервера
		  $this->addServerUpdateInfo( $result );
		  //Обновляем время клиента 
		  $this->updateSession( $session_id, $time );
		   
		  return $result;	
	}
	
	//Проверяет есть ли запись с приглашением этого пользователя
	//Возвращает 0 - если такой записи нету
	//Возвращает 1 - если такая запись есть и она не подтверждена
	//Возвращает 2 - если такая запись есть и она подтверждена
	private function invitationExists( $uid, $inviter_id )
	{
	   $r = mysql_query( "select confirmed from {$this->invitations_table} where (uid='{$uid}')and(user_id={$inviter_id})" );
	   
	   if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
	   
	   $confirmed = mysql_num_rows( $r ) == 0 ? 0 : 1;	   
		   
	   while( $row = mysql_fetch_assoc( $r ) )
	   { 
	     if ( $row[ 'confirmed' ] == 1 )
		 {
		   $confirmed = 2;
		   break;
		 }
	   }
		   
	   return $confirmed;
	}
	
	//Приглашает пользователей в друзья
	//uids - список идентификаторов приглашенных пользователей (OK)
	public function inviteFriends( $session_id, $uids )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	  if ( $sessionData === false )
	  {
	     return $this->SESSION_NOT_FOUND_ERROR;
	  }
	  
	  foreach( $uids as $uid )
	  {
	    if ( $this->invitationExists( $uid, $sessionData[ 'user_id' ] ) == 0 )
		{
		   $r = mysql_query( "insert into {$this->invitations_table} (date,user_id,uid,confirmed) values(NOW(),{$sessionData[ 'user_id' ]},'{$uid}',0)" );
		
		   if ( ! $r )
		    throw new Exception( mysql_error(), mysql_errno() ); 
		} 
	  }
	  
	  return $this->OK;
	}
	
	//начисляет бонус пригласившему этого пользователя
	//uid - идентификатор приглашенного пользователя (OK)
	//inviter_id - идентификатор пригласившего пользователя
	public function doUserInvitedAction( $session_id, $uid, $inviter_id )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	 
	  if ( $sessionData === false )
	  {
	     return $this->SESSION_NOT_FOUND_ERROR;
	  }
	  
	  if ( $this->invitationExists( $uid, $inviter_id ) == 1 )
	  {
	    //Устанавливаем статус подтверждения
		$r = mysql_query( "update {$this->invitations_table} set confirmed=1 where (uid='{$uid}')and(user_id={$inviter_id})" );
		
		if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
	  
	    //Добавляем монеты на счёт пригласившего
		$r = mysql_query( "update {$this->users_table} set money=money+{$this->INVITE_USER_BONUS} where id={$inviter_id}" );
		
		if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
		//Проталкиваем сообщения
		$this->addCommand( $inviter_id, $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );
		$this->addCommand( $inviter_id, $this->COMMAND_SHOW_MESSAGE, "Ты получил бонус за приглашенного друга.::money={$this->INVITE_USER_BONUS};type={$this->MESSAGE_TYPE_FRIEND_INVITED};" ); 
	  
	    return $this->OK;
	  }
	  
	  return $this->ERROR;
	}
 }
 /*
 $z = new API();
 $t->name = '123';
 $t->genre = 'na';
 $t->tempo = 90;
 $t->duration = 0;
 $t->id = 65;
 $t->description = '';
 
 $z->removeProject( 2, 304 );
 */
?>