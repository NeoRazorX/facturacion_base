<?php


require_model('divisa.php');
require_model('partida.php');
require_model('subcuenta.php');

class mayorizar_subc extends fs_controller
{
   public $allow_delete;

   
     
   
   
   

  
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Mayorizar_Subcuentas', 'contabilidad', FALSE, FALSE);
   }
   
   protected function private_core()
   {
   
   
   
   }
   
   
 }  
		