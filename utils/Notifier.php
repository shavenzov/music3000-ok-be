<?php
  
  require_once __DIR__ . '/../Services/API.php';
  
  class Notifier extends API
  {
    public function run()
	{
	   $this->addBonus();
	}
	
	public function addBonus()
	{
	  $r = mysql_query( "select id, money from {$this->users_table} where money<{$this->PUBLICATION_PRICE}" ); 
	  
	  if ( ! $r )
	   {
	     die ( mysql_error() . ' ' . mysql_errno() );
	   }
	   
	  if ( mysql_num_rows( $r ) > 0 )
	  {
	    while( $row = mysql_fetch_assoc( $r ) )
		{
		  //Проталкиваем сообщение о изменении кол-ва монет  
	      try
		  {
		    $add = $this->PUBLICATION_PRICE - $row[ 'money' ];  
		  
		    $this->addCommand( $row[ 'id' ], $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );
			$this->addCommand( $row[ 'id' ], $this->COMMAND_SHOW_MESSAGE, "Тебе начислен бонус. Поздравляем!::money={$add};type={$this->MESSAGE_TYPE_BONUS};" ); 
		  }
		  catch ( Exception $e )
		  {
		    die ( mysql_error() . ' ' . mysql_errno() );
		  }
		}
	  }
	  
	  $r = mysql_query( "update {$this->users_table} set money={$this->PUBLICATION_PRICE} where money<{$this->PUBLICATION_PRICE}" );
	  
	   if ( ! $r )
	   {
	     die ( mysql_error() . ' ' . mysql_errno() );
	   }  
	}
  }
  
  $n = new Notifier();
  $n->run();
  		
  
?>