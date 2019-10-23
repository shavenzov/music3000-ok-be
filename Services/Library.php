<?php

   require_once __DIR__ . '/SessionManager.php';
   
   class Library extends SessionManager
   {
      const MIN_TEMPO = 30;
	  const MAX_TEMPO = 350;
	  const LIBRARY_ID = 'MAIN';
	  
	  private function correctParams( $params )
	  {
	    if ( $params === null )
		 {
		   $params = new stdClass();
		 }
	  
	     if ( isset( $params->name ) )
		 {
		   $params->name = trim( $params->name ); 
		 }
		 
		 if ( isset( $params->tempoFrom ) )
		 {
		   if ( $params->tempoFrom < self::MIN_TEMPO )
		    {
			  $params->tempoFrom = self::MIN_TEMPO;
			}
		 }
		 else
		 {
		   $params->tempoFrom = self::MIN_TEMPO;
		 }
		 
		 if ( isset( $params->tempoTo ) )
		 {
		   if ( $params->tempoTo > self::MAX_TEMPO )
		   {
		     $params->tempoTo = self::MAX_TEMPO;
		   }
		 }
		 else
		 {
		   $params->tempoTo = self::MAX_TEMPO;
		 }
		 
		 return $params;
	  }
	  
	  private function getSearchQueryString( $params )
	  {
	    if ( $params == null ) return '';  
	    
		$params = $this->mysql_escape_object( $params );
		
		$queryParts = array();
		  
	    $count = 0;
		
	    //Поиск по названию
		if ( isset( $params->name ) && ( strlen( $params->name ) > 0 ) )
		{
		  $whereStr = "( name like '%" . $params->name . "%' )";
		  $queryParts[] = $whereStr;
		}
		
		//Формируем список жанров
		$whereStr = ''; 
		
		 if ( isset( $params->genres ) )
		 {
		    $count = count( $params->genres );  
	  
		    if ( $count > 0 )
		    {
		     
		       $whereStr .= '('; 
		 
			   $i = 0;
			   foreach( $params->genres as $val )
		        {
		          $whereStr .= "( genre = '" . $val . "' )";
			      if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
				  $i ++;
		        }
			
			  $whereStr.= ' )';
			  
			  $queryParts[] = $whereStr;
		    }
		 }
		
		//Формируем список категорий
		$whereStr = '';  
		 
	    if ( isset( $params->categories ) )
		{
		   $count = count( $params->categories );  
	  
		   if ( $count > 0 )
		   {
		     
		     $whereStr .= '('; 
		 
		     $i = 0;
		     foreach( $params->categories as $val )
		     {
		      $whereStr .= "( category = '" . $val . "' )";
			  if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
			  $i ++;
		      }
			
			$whereStr .= ' )';
			$queryParts[] = $whereStr;
		   }
		 }
		 
		//Формируем список ключей
		$whereStr = ''; 
		
		if ( isset( $params->keys ) )
		 {
		   $count = count( $params->keys );  
	  
		   if ( $count > 0 )
		   {
		     
		    $whereStr .= '('; 
		 
		     $i = 0;
		     foreach( $params->keys as $val )
		   {
		      $whereStr .= "( mkey = '" . $val . "' )";
			  if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
			  $i ++;
		    }
			
			$whereStr .= ' )';
			$queryParts[] = $whereStr;
		 }
		 
		 }
		
		//Устанавливаем диапазон темпа  
		$whereStr = ''; 
		
		if ( isset( $params->tempoFrom ) )
		 {
		   $whereStr .= "( tempo >= " . $params->tempoFrom . " )";
		   $queryParts[] = $whereStr;
		 }
		 
		 $whereStr = '';
		 
		 if ( isset( $params->tempoTo ) )
		 {
		   $whereStr .= "( tempo <= " . $params->tempoTo . " )";
		   $queryParts[] = $whereStr;
		 }
		
		//Объединяем все параметры 
		$whereStr = ''; 
		$i = 0; 
		$count = count( $queryParts );
		
		foreach( $queryParts as $val )
		{
		  $whereStr .= $val;
		  if ( $i < ( $count - 1 ) ) $whereStr .= ' and ';
		  $i ++;
		}
		
		return $whereStr;
	  }
	   
	  public function getSearchParams( $session_id, $params, $getGenres, $getCategories, $getTempos, $getKeys  ) 
	  {
	    if ( ! $this->sessionExists( $session_id ) )
	     {
		  return $this->SESSION_NOT_FOUND_ERROR;
	     }  
	  
	    $params = $this->correctParams( $params );
	    $whereStr = $this->getSearchQueryString( $params );   
	    //echo( $whereStr );
	    //list of genres
	    
		$result = new stdClass();
		
		if ( $getGenres )
		 {
		   if ( strlen( $whereStr ) == 0 )
		   {
		     $r = mysql_query( 'select genre from ' . $this->library_table . ' group by genre' );
		   }
		   else
		   {
		     $r = mysql_query( 'select genre from ' . $this->library_table . ' where ' . $whereStr . ' group by genre' );
		   }
		   
		   if ( ! $r )
		    throw new Exception( mysql_error(), mysql_errno() );
		 
		   $result->genres = array();
		 
		   while ( $row = mysql_fetch_assoc( $r ) )
		    {
		      $result->genres[] = $row[ 'genre' ];
		    }
		 }
		
	    if ( $getCategories )
	    {
		   //list of categorys
		if ( strlen( $whereStr ) == 0 )
		 {
		   $r = mysql_query( 'select category from ' . $this->library_table . ' group by category' );
		 }
		 else
		 {
		    $r = mysql_query( 'select category from ' . $this->library_table . ' where ' . $whereStr . ' group by category' );
		 }
		
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		 
		 $result->categories = array();
		 
		 while ( $row = mysql_fetch_assoc( $r ) )
		 {
		  $result->categories[] = $row[ 'category' ];
		 }
		}
		
		if ( $getKeys )
	    {
		   //list of keys
		if ( strlen( $whereStr ) == 0 )
		 {
		   $r = mysql_query( 'select mkey from ' . $this->library_table . ' group by mkey' );
		 }
		 else
		 {
		    $r = mysql_query( 'select mkey from ' . $this->library_table . ' where ' . $whereStr . ' group by mkey' );
		 }
		
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		 
		 $result->keys = array();
		 
		 while ( $row = mysql_fetch_assoc( $r ) )
		 {
		   //Отфильтровываем ненужные результаты
		   if ( $this->isItValidKey( $row[ 'mkey' ] ) )  
		    {
			  $result->keys[] = $row[ 'mkey' ];
			}
		 }
		}
		
		if ( $getTempos )
		{
		   if ( strlen( $whereStr ) == 0 )
		{
		  $r = mysql_query( 'select tempo from ' . $this->library_table . ' group by tempo' );
		}
		else
		{
		  $r = mysql_query( 'select tempo from ' . $this->library_table . ' where ' . $whereStr . ' group by tempo' );
		}
	    
		if ( ! $r )
		 throw new Exception( mysql_error(), mysql_errno() );
		 
		$result->tempos = array();
		 
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $result->tempos[] = $row[ 'tempo' ];
		}
		}
		
		 return $result;
	  }
	  
	  private function isItValidKey( $key )
	  {
	    return ( $key != 'None' ) && ( $key != 'Unkn' ) && ( $key != 'Unknown' );
	  }  
	  
	  private $AUDIO_HOST = 'musconstructor.com';
	  
	  /*
	    Возвращает ссылку на аудио файл сэмпла ( низкое качество )
	  */
	  private function getLQAudioLink( $hash )
	  {
	    return $this->getProtocol() . "{$this->AUDIO_HOST}/audio/samples/lq/{$hash}.mp3";
	  }
	  
	  /*
	    Возвращает ссылку на аудио файл сэмпла ( оригинальное качество )
	  */
	  private function getHQAudioLink( $hash, $type )
	  {
	    return $this->getProtocol() . "{$this->AUDIO_HOST}/audio/samples/hq/{$hash}.{$type}"; 
	  }
	  
	  //Возвращает информацию о семпле по его идентификатору
	  public function getInfo( $session_id, $ids )
	  {
	     if ( ! $this->sessionExists( $session_id ) )
	     {
		  return $this->SESSION_NOT_FOUND_ERROR;
	     }   
	     
		 $ids = $this->mysql_escape_object( $ids );
		 
	     $where = '';
		 $i = 0;
		 
		 foreach( $ids as $val )
		 {
		    $where .= "( hash = '" . $val . "' )";
		    if ( $i < ( count( $ids ) - 1 ) )
			{
			  $where .= ' or ';
			}
			$i ++;
		 }
		
		 $r = mysql_query( 'select hash, name, author, type, tempo, duration, genre, category, mkey from ' . $this->library_table . ' where ' . $where );
		 
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		
		$result = new stdClass();
		$result->data = array();
		  
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $row[ 'lqurl' ] = $this->getLQAudioLink( $row[ 'hash' ] );
		  $row[ 'hqurl' ] = $this->getHQAudioLink( $row[ 'hash' ], $row[ 'type' ] );
		  
		  if ( ! $this->isItValidKey( $row[ 'mkey' ] ) )
		  {
		    $row[ 'mkey'] = null;
		  }
		  
		  //Добавляем идентификатор библиотеки
		  $row[ 'source_id' ] = self::LIBRARY_ID;
		  
		  $result->data[] = $row;
		}
		
		return $result;
	  }
	  
	  //Возвращает результаты поиска
	  public function search( $session_id, $params )
	  {
        if ( ! $this->sessionExists( $session_id ) )
	     {
		  return $this->SESSION_NOT_FOUND_ERROR;
	     }
	  
	    $params = $this->correctParams( $params );   
	   
		$whereStr = $this->getSearchQueryString( $params );
		
		if ( ! isset( $params->offset ) )
		 $params->offset = 0;
		 
		if ( ! isset( $params->limit ) )
		 $params->limit = 100;
		 
		if ( ! isset( $params->orderBy ) )
		 $params->orderBy = 'name';
		 
		if ( ! isset( $params->order ) ) //Параметр не установлен
		{
		  $params->order = 'asc';
		}
		else
		{
		  $o = strtolower( $params->order ); //Параметр имеет недопустимое значение
		  
		  if ( ( $o != 'desc' ) && ( $o != 'asc' ) )
		  {
		    $params->order = 'asc';
		  }
		}
		
		//total results
		if ( strlen( $whereStr ) == 0 )
		 {
		   $r = mysql_query( "select count(*) as count from {$this->library_table}" );
		 }
		 else
		 {
		   $r = mysql_query( "select count(*) as count from {$this->library_table} where {$whereStr}" ); 
		 }
		
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		$row = mysql_fetch_assoc( $r );
		$result = new stdClass();
		$result->count = $row[ 'count' ];  
		
		//data
		if ( strlen( $whereStr ) == 0 )
		 {
		   $r = mysql_query( "select hash, name, author, type, tempo, duration, genre, category, mkey from {$this->library_table} order by {$params->orderBy} {$params->order} limit {$params->offset},{$params->limit}" );
		 }
		 else
		 {
		   $r = mysql_query( "select hash, name, author, type, tempo, duration, genre, category, mkey from {$this->library_table} where {$whereStr} order by {$params->orderBy} {$params->order} limit {$params->offset},{$params->limit}" );
		 }
		
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		
		$result->data = array();
		//$result->q = $whereStr;
		  
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $row[ 'lqurl' ] = $this->getLQAudioLink( $row[ 'hash' ] );
		  $row[ 'hqurl' ] = $this->getHQAudioLink( $row[ 'hash' ], $row[ 'type' ] );
		  
		  if ( ! $this->isItValidKey( $row[ 'mkey' ] ) )
		  {
		    $row[ 'mkey'] = null;
		  }
		  
		  //Добавляем идентификатор библиотеки
		  $row[ 'source_id' ] = self::LIBRARY_ID;
		  
		  $result->data[] = $row;
		}
		
		return $result;
	  }
	  
   }
   
   //$z = new MainAPI();
   //echo 'http://' . $_SERVER[ 'HTTP_HOST' ] .  '/mp3/' . 'filename' . '.mp3';
   /*
   $g->categories = array(  );
   $g->genres = array( 'Dubstep' );
   $g->tempoFrom = 0;
   $g->tempoTo = 255;
   $g->limit = 20;
   //print_r( $z->getGenresAndCategories( null ) );
   print_r( $z->search( $g ) ); 
   */
  
?>