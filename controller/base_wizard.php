<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('almacen.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('forma_pago.php');
require_model('pais.php');
require_model('serie.php');

/**
 * Description of base_wizard
 *
 * @author carlos
 */
class base_wizard extends fs_controller
{
   public $almacen;
   public $divisa;
   public $ejercicio;
   public $forma_pago;
   public $pais;
   public $serie;
   public $step;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Asistente de instalación', 'admin', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->check_menu();
      
      $this->almacen = new almacen();
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->forma_pago = new forma_pago();
      $this->pais = new pais();
      $this->serie = new serie();
      
      $fsvar = new fs_var();
      $this->step = $fsvar->simple_get('install_step');
      if( $this->step < 2 OR isset($_GET['restart']) )
      {
         $this->step = 2;
      }
      
      if(FS_DEMO)
      {
         $this->new_advice('En el modo demo no se pueden hacer cambios en esta página.');
         $this->new_advice('Si te gusta FacturaScripts y quieres saber más, consulta la '
                 . '<a href="https://www.facturascripts.com/comm3/index.php?page=community_questions">sección preguntas</a>.');
      }
      else if( isset($_POST['nombrecorto']) )
      {
         /// guardamos los datos de la empresa
         $this->empresa->nombre = $_POST['nombre'];
         $this->empresa->nombrecorto = $_POST['nombrecorto'];
         $this->empresa->cifnif = $_POST['cifnif'];
         $this->empresa->administrador = $_POST['administrador'];
         $this->empresa->codpais = $_POST['codpais'];
         $this->empresa->provincia = $_POST['provincia'];
         $this->empresa->ciudad = $_POST['ciudad'];
         $this->empresa->direccion = $_POST['direccion'];
         $this->empresa->codpostal = $_POST['codpostal'];
         $this->empresa->telefono = $_POST['telefono'];
         $this->empresa->fax = $_POST['fax'];
         $this->empresa->web = $_POST['web'];
         
         if( $this->empresa->save() )
         {
            $this->new_message('Datos guardados correctamente.');
            
            /// avanzamos el asistente
            $this->step = 3;
            
            if($this->empresa->codpais == 'ESP' OR $this->empresa->codpais == 'ES')
            {
               /// si es España nos podemos ahorrar un paso
               $this->empresa->coddivisa = 'EUR';
               $this->empresa->save();
               $this->step = 4;
            }
            
            $fsvar->simple_save('install_step', $this->step);
         }
         else
            $this->new_error_msg ('Error al guardar los datos.');
      }
      else if( isset($_POST['coddivisa']) )
      {
         $this->empresa->coddivisa = $_POST['coddivisa'];
         
         if( $this->empresa->save() )
         {
            foreach($GLOBALS['config2'] as $i => $value)
            {
               if( isset($_POST[$i]) )
               {
                  $GLOBALS['config2'][$i] = $_POST[$i];
               }
            }
            
            $file = fopen('tmp/'.FS_TMP_NAME.'config2.ini', 'w');
            if($file)
            {
               foreach($GLOBALS['config2'] as $i => $value)
               {
                  if( is_numeric($value) )
                  {
                     fwrite($file, $i." = ".$value.";\n");
                  }
                  else
                  {
                     fwrite($file, $i." = '".$value."';\n");
                  }
               }
               
               fclose($file);
            }
            
            $this->new_message('Datos guardados correctamente.');
            
            /// avanzamos el asistente
            $this->step = 4;
            $fsvar->simple_save('install_step', $this->step);
         }
         else
            $this->new_error_msg ('Error al guardar los datos.');
      }
      else if( isset($_POST['codejercicio']) )
      {
         $this->empresa->contintegrada = isset($_POST['contintegrada']);
         $this->empresa->codejercicio = $_POST['codejercicio'];
         $this->empresa->codserie = $_POST['codserie'];
         $this->empresa->codpago = $_POST['codpago'];
         $this->empresa->codalmacen = $_POST['codalmacen'];
         $this->empresa->recequivalencia = isset($_POST['recequivalencia']);
         
         if( $this->empresa->save() )
         {
            foreach($GLOBALS['config2'] as $i => $value)
            {
               if( isset($_POST[$i]) )
               {
                  $GLOBALS['config2'][$i] = $_POST[$i];
               }
            }
            
            $file = fopen('tmp/'.FS_TMP_NAME.'config2.ini', 'w');
            if($file)
            {
               foreach($GLOBALS['config2'] as $i => $value)
               {
                  if( is_numeric($value) )
                  {
                     fwrite($file, $i." = ".$value.";\n");
                  }
                  else
                  {
                     fwrite($file, $i." = '".$value."';\n");
                  }
               }
               
               fclose($file);
            }
            
            $this->new_message('Datos guardados correctamente.');
            
            /// avanzamos el asistente
            $this->step = 5;
            $fsvar->simple_save('install_step', $this->step);
         }
         else
            $this->new_error_msg ('Error al guardar los datos.');
      }
   }
   
   private function check_menu()
   {
      if( !$this->page->get('ventas_articulos') )
      {
         if( file_exists(__DIR__) )
         {
            /// activamos las páginas del plugin
            foreach( scandir(__DIR__) as $f)
            {
               if( is_string($f) AND strlen($f) > 0 AND !is_dir($f) AND $f != __CLASS__.'.php' )
               {
                  $page_name = substr($f, 0, -4);
                  
                  require_once __DIR__.'/'.$f;
                  $new_fsc = new $page_name();
                  
                  if( !$new_fsc->page->save() )
                  {
                     $this->new_error_msg("Imposible guardar la página ".$page_name);
                  }
                  
                  unset($new_fsc);
               }
            }
         }
         else
         {
            $this->new_error_msg('No se encuentra el directorio '.__DIR__);
         }
         
         $this->load_menu(TRUE);
      }
   }
   
   /**
    * Timezones list with GMT offset
    * 
    * @return array
    * @link http://stackoverflow.com/a/9328760
    */
   public function get_timezone_list()
   {
      $zones_array = array();
      
      $timestamp = time();
      foreach(timezone_identifiers_list() as $key => $zone) {
         date_default_timezone_set($zone);
         $zones_array[$key]['zone'] = $zone;
         $zones_array[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
      }
      
      return $zones_array;
   }
   
   /**
    * Lista de opciones para NF0
    * @return type
    */
   public function nf0()
   {
      return array(0, 1, 2, 3, 4);
   }
   
   /**
    * Lista de opciones para NF1
    * @return type
    */
   public function nf1()
   {
      return array(
          ',' => 'coma',
          '.' => 'punto',
          ' ' => '(espacio en blanco)'
      );
   }
   
   /**
    * Devuelve la lista de elementos a traducir
    * @return type
    */
   public function traducciones()
   {
      $clist = array();
      $include = array(
          'factura','facturas','factura_simplificada','factura_rectificativa',
          'albaran','albaranes','pedido','pedidos','presupuesto','presupuestos',
          'provincia','apartado','cifnif','iva','irpf','numero2'
      );
      
      foreach($GLOBALS['config2'] as $i => $value)
      {
         if( in_array($i, $include) )
            $clist[] = array('nombre' => $i, 'valor' => $value);
      }
      
      return $clist;
   }
}
