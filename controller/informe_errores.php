<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('almacen.php');
require_model('asiento.php');
require_model('direccion_cliente.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('pais.php');
require_model('serie.php');


class informe_errores extends fs_controller
{
   public $ajax;
   public $ejercicio;
   public $errores;
   public $informe;
   public $mostrar_cancelar;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Errores', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->ajax = FALSE;
      $this->ejercicio = new ejercicio();
      $this->errores = array();
      $this->informe = array(
          'model' => 'asiento',
          'duplicados' => isset($_POST['duplicados']),
          'offset' => 0,
          'pages' => 0,
          'show_page' => 0,
          'started' => FALSE,
          'all' => FALSE,
          'ejercicio' => ''
      );
      $this->mostrar_cancelar = FALSE;
      
      if( isset($_GET['cancelar']) )
      {
         if( file_exists('tmp/'.FS_TMP_NAME.'informe_errores.txt') )
         {
            unlink('tmp/'.FS_TMP_NAME.'informe_errores.txt');
         }
      }
      else if( file_exists('tmp/'.FS_TMP_NAME.'informe_errores.txt') ) /// continua examinando
      {
         $file = fopen('tmp/'.FS_TMP_NAME.'informe_errores.txt', 'r+');
         if($file)
         {
            /*
             * leemos el archivo tmp/informe_errores.txt donde guardamos los datos
             * y extraemos la configuración y los errores de la "página" seleccionada
             */
            $linea = explode( ';', trim(fgets($file)) );
            if( count($linea) == 8 )
            {
               $this->informe['model'] = $linea[0];
               $this->informe['duplicados'] = ($linea[1]==1);
               $this->informe['offset'] = intval($linea[2]);
               $this->informe['pages'] = intval($linea[3]);
               
               if( isset($_POST['show_page']) )
               {
                  $this->informe['show_page'] = intval($_POST['show_page']);
               }
               else if( isset($_GET['show_page']) )
               {
                  $this->informe['show_page'] = intval($_GET['show_page']);
               }
               else
                  $this->informe['show_page'] = intval($linea[4]);
               
               $this->informe['started'] = ($linea[5]==1);
               $this->informe['all'] = ($linea[6]==1);
               $this->informe['ejercicio'] = $linea[7];
            }
            
            if( isset($_REQUEST['ajax']) )
            {
               $this->ajax = TRUE;
               
               /// leemos los errores de la "página" seleccionada
               $numlinea = 0;
               while( !feof($file) )
               {
                  $linea = explode( ';', trim(fgets($file)) );
                  if( count($linea) == 7 )
                  {
                     if($numlinea > $this->informe['show_page']*FS_ITEM_LIMIT AND $numlinea <= (1+$this->informe['show_page'])*FS_ITEM_LIMIT)
                     {
                        $this->errores[] = array(
                            'error' => $linea[0],
                            'model' => $linea[1],
                            'ejercicio' => $linea[2],
                            'id' => $linea[3],
                            'url' => $linea[4],
                            'fecha' => $linea[5],
                            'fix' => ($linea[6]==1)
                        );
                     }
                  }
                  
                  $numlinea++;
               }
               
               $new_results = $this->test_models();
               if($new_results)
               {
                  foreach($new_results as $nr)
                  {
                     fwrite($file, join(';', $nr)."\n" );
                     $numlinea++;
                  }
               }
               
               $this->informe['pages'] = intval($numlinea/FS_ITEM_LIMIT);
               
               /// guardamos la configuración
               rewind($file);
               fwrite($file, join(';', $this->informe)."\n------\n" );
            }
            else
               $this->mostrar_cancelar = TRUE;
            
            fclose($file);
         }
      }
      else if( isset($_POST['modelo']) ) /// empieza a examinar
      {
         $file = fopen('tmp/'.FS_TMP_NAME.'informe_errores.txt', 'w');
         if($file)
         {
            $this->mostrar_cancelar = TRUE;
            
            if($_POST['modelo'] == 'todo')
            {
               $this->informe['model'] = 'tablas';
               $this->informe['started'] = TRUE;
               $this->informe['all'] = TRUE;
            }
            else if($_POST['modelo'] != '')
            {
               $this->informe['model'] = $_POST['modelo'];
               $this->informe['started'] = TRUE;
            }
            
            if( isset($_POST['ejercicio']) )
            {
               $this->informe['ejercicio'] = $_POST['ejercicio'];
            }
            
            if( isset($_GET['show_page']) )
            {
               $this->informe['show_page'] = intval($_GET['show_page']);
            }
            
            /// guardamos esta configuración
            fwrite($file, join(';', $this->informe)."\n--------------------------------------------------------\n" );
            fclose($file);
         }
      }
   }
   
   private function test_models()
   {
      $last_errores = array();
      
      switch( $this->informe['model'] )
      {
         default:
            /// tablas
            $this->test_tablas();
            break;
         
         case 'asiento':
            $asiento = new asiento();
            $asientos = $asiento->all($this->informe['offset']);
            if($asientos)
            {
               if($this->informe['offset'] == 0)
               {
                  foreach($this->check_partidas_erroneas() as $err)
                  {
                     $last_errores[] = $err;
                  }
               }
               
               foreach($asientos as $asi)
               {
                  if($asi->codejercicio == $this->informe['ejercicio'])
                  {
                     if($this->informe['all'])
                        $this->informe['model'] = 'factura cliente';
                     else
                        $this->informe['model'] = 'fin';
                     $this->informe['offset'] = 0;
                     break;
                  }
                  else if( !$asi->full_test($this->informe['duplicados']) )
                  {
                     $last_errores[] = array(
                         'error' => 'Fallo en full_test()',
                         'model' => $this->informe['model'],
                         'ejercicio' => $asi->codejercicio,
                         'id' => $asi->numero,
                         'url' => $asi->url(),
                         'fecha' => $asi->fecha,
                         'fix' => $asi->fix()
                     );
                  }
               }
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else if($this->informe['all'])
            {
               $this->informe['model'] = 'factura cliente';
               $this->informe['offset'] = 0;
            }
            else
            {
               $this->informe['model'] = 'fin';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'factura cliente':
            $factura = new factura_cliente();
            $facturas = $factura->all($this->informe['offset']);
            if($facturas)
            {
               foreach($facturas as $fac)
               {
                  if($fac->codejercicio == $this->informe['ejercicio'])
                  {
                     if($this->informe['all'])
                        $this->informe['model'] = 'factura proveedor';
                     else
                        $this->informe['model'] = 'fin';
                     $this->informe['offset'] = 0;
                     break;
                  }
                  else if( !$fac->full_test($this->informe['duplicados']) )
                  {
                     $last_errores[] = array(
                         'error' => 'Fallo en full_test()',
                         'model' => $this->informe['model'],
                         'ejercicio' => $fac->codejercicio,
                         'id' => $fac->codigo,
                         'url' => $fac->url(),
                         'fecha' => $fac->fecha,
                         'fix' => FALSE
                     );
                  }
               }
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else if($this->informe['all'])
            {
               $this->informe['model'] = 'factura proveedor';
               $this->informe['offset'] = 0;
            }
            else
            {
               $this->informe['model'] = 'fin';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'factura proveedor':
            $factura = new factura_proveedor();
            $facturas = $factura->all($this->informe['offset']);
            if($facturas)
            {
               foreach($facturas as $fac)
               {
                  if($fac->codejercicio == $this->informe['ejercicio'])
                  {
                     if($this->informe['all'])
                        $this->informe['model'] = 'albaran cliente';
                     else
                        $this->informe['model'] = 'fin';
                     $this->informe['offset'] = 0;
                     break;
                  }
                  else if( !$fac->full_test($this->informe['duplicados']) )
                  {
                     $last_errores[] = array(
                         'error' => 'Fallo en full_test()',
                         'model' => $this->informe['model'],
                         'ejercicio' => $fac->codejercicio,
                         'id' => $fac->codigo,
                         'url' => $fac->url(),
                         'fecha' => $fac->fecha,
                         'fix' => FALSE
                     );
                  }
               }
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else if($this->informe['all'])
            {
               $this->informe['model'] = 'albaran cliente';
               $this->informe['offset'] = 0;
            }
            else
            {
               $this->informe['model'] = 'fin';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'albaran cliente':
            $albaran = new albaran_cliente();
            $albaranes = $albaran->all($this->informe['offset']);
            if($albaranes)
            {
               foreach($albaranes as $alb)
               {
                  if($alb->codejercicio == $this->informe['ejercicio'])
                  {
                     if($this->informe['all'])
                        $this->informe['model'] = 'albaran proveedor';
                     else
                        $this->informe['model'] = 'fin';
                     $this->informe['offset'] = 0;
                     break;
                  }
                  else if( !$alb->full_test($this->informe['duplicados']) )
                  {
                     $last_errores[] = array(
                         'error' => 'Fallo en full_test()',
                         'model' => $this->informe['model'],
                         'ejercicio' => $alb->codejercicio,
                         'id' => $alb->codigo,
                         'url' => $alb->url(),
                         'fecha' => $alb->fecha,
                         'fix' => FALSE
                     );
                  }
               }
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else if($this->informe['all'])
            {
               $this->informe['model'] = 'albaran proveedor';
               $this->informe['offset'] = 0;
            }
            else
            {
               $this->informe['model'] = 'fin';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'albaran proveedor':
            $albaran = new albaran_proveedor();
            $albaranes = $albaran->all($this->informe['offset']);
            if($albaranes)
            {
               foreach($albaranes as $alb)
               {
                  if($alb->codejercicio == $this->informe['ejercicio'])
                  {
                     $this->informe['model'] = 'fin';
                     $this->informe['offset'] = 0;
                     break;
                  }
                  else if( !$alb->full_test($this->informe['duplicados']) )
                  {
                     $last_errores[] = array(
                         'error' => 'Fallo en full_test()',
                         'model' => $this->informe['model'],
                         'ejercicio' => $alb->codejercicio,
                         'id' => $alb->codigo,
                         'url' => $alb->url(),
                         'fecha' => $alb->fecha,
                         'fix' => FALSE
                     );
                  }
               }
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else
            {
               $this->informe['model'] = 'dirclientes';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'dirclientes':
            $dircli0 = new direccion_cliente();
            $direcciones = $dircli0->all($this->informe['offset']);
            if($direcciones)
            {
               foreach($direcciones as $dir)
               {
                  /// simplemente guardamos para que se eliminen espacios de ciudades, provincias, etc...
                  $dir->save();
               }
               
               $this->informe['offset'] += FS_ITEM_LIMIT;
            }
            else
            {
               $this->informe['model'] = 'fin';
               $this->informe['offset'] = 0;
            }
            break;
         
         case 'fin':
            break;
      }
      
      return $last_errores;
   }
   
   public function all_pages()
   {
      $allp = array();
      $show_p = $this->informe['show_page'];
      /// cargamos todas las páginas
      for($i = 0; $i<=$this->informe['pages']; $i++)
         $allp[] = array('page' => $i, 'num' => $i+1, 'selected' => ($i==$show_p));
      /// ahora descartamos
      foreach($allp as $j => $value)
      {
         if( ($value['num']>1 AND $j<$show_p-3 AND $value['num']%10) OR ($j>$show_p+3 AND $j<$i-1 AND $value['num']%10) )
            unset($allp[$j]);
      }
      return $allp;
   }
   
   private function check_partidas_erroneas()
   {
      $errores = array();
      $asient0 = new asiento();
      
      foreach($this->ejercicio->all() as $eje)
      {
         $sql = "SELECT * FROM co_partidas WHERE idasiento IN
            (SELECT idasiento FROM co_asientos WHERE codejercicio = ".$eje->var2str($eje->codejercicio).")
            AND idsubcuenta NOT IN (SELECT idsubcuenta FROM co_subcuentas WHERE codejercicio = ".$eje->var2str($eje->codejercicio).");";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $asiento = $asient0->get($d['idasiento']);
               if($asiento)
               {
                  $errores[] = array(
                      'error' => 'Subcuenta '.$d['codsubcuenta'].' no pertenece al mismo ejercicio que el asiento',
                      'model' => 'asiento',
                      'ejercicio' => $eje->codejercicio,
                      'id' => $asiento->numero,
                      'url' => $asiento->url(),
                      'fecha' => $asiento->fecha,
                      'fix' => FALSE
                  );
               }
            }
         }
      }
      
      return $errores;
   }
   
   private function test_tablas()
   {
      $recargar = FALSE;
      
      if($this->informe['offset'] == 0)
      {
         /// comprobamos la tabla familias
         if( $this->db->table_exists('familias') )
         {
            $data = $this->db->select("SELECT * FROM familias WHERE madre IS NOT NULL AND madre NOT IN (SELECT codfamilia FROM familias);");
            if($data)
            {
               foreach($data as $d)
               {
                  $familia = new familia($d);
                  $familia->madre = NULL;
                  $familia->save();
               }
            }
         }
         
         /// comprobamos la tabla de articulos de proveedor
         if( $this->db->table_exists('articulosprov') )
         {
            $this->db->exec("DELETE FROM articulosprov WHERE codproveedor NOT IN (SELECT codproveedor FROM proveedores);");
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else if($this->informe['offset'] == 1)
      {
         /// comprobamos la tabla de articulos de proveedor
         if( $this->db->table_exists('articulosprov') )
         {
            $this->db->exec("UPDATE articulosprov SET refproveedor = referencia WHERE refproveedor IS NULL;");
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else if($this->informe['offset'] == 2)
      {
         /// comprobamos la tabla de stock
         if( $this->db->table_exists('stocks') )
         {
            /**
             * Esta consulta produce un error si no hay datos erroneos, pero da igual
             */
            $this->db->exec("DELETE FROM stocks s WHERE NOT EXISTS "
                    . "(SELECT referencia FROM articulos a WHERE a.referencia = s.referencia);");
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else if($this->informe['offset'] == 3)
      {
         /// comprobamos la tabla de regulaciones de stock
         if( $this->db->table_exists('lineasregstocks') )
         {
            $this->db->exec("DELETE FROM lineasregstocks WHERE idstock NOT IN (SELECT idstock FROM stocks);");
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else if($this->informe['offset'] == 4)
      {
         /// comprobamos la tabla de subcuentas de proveedores
         if( $this->db->table_exists('co_subcuentasprov') )
         {
            $this->db->exec("DELETE FROM co_subcuentasprov WHERE codproveedor NOT IN (SELECT codproveedor FROM proveedores);");
         }
         
         /// comprobamos la tabla de direcciones de proveedores
         if( $this->db->table_exists('dirproveedores') )
         {
            $this->db->exec("DELETE FROM dirproveedores WHERE codproveedor NOT IN (SELECT codproveedor FROM proveedores);");
         }
         
         /// comprobamos la tabla de subcuentas de clientes
         if( $this->db->table_exists('co_subcuentascli') )
         {
            $this->db->exec("DELETE FROM co_subcuentascli WHERE codcliente NOT IN (SELECT codcliente FROM clientes);");
         }
         
         /// comprobamos la tabla de direcciones de clientes
         if( $this->db->table_exists('dirclientes') )
         {
            $this->db->exec("DELETE FROM dirclientes WHERE codcliente NOT IN (SELECT codcliente FROM clientes);");
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else if($this->informe['offset'] == 5)
      {
         $almacen = new almacen();
         if( !$almacen->all() )
         {
            $this->db->exec( $almacen->install() );
         }
         
         $divisa = new divisa();
         if( !$divisa->all() )
         {
            $this->db->exec( $divisa->install() );
         }
         
         $formap = new forma_pago();
         if( !$formap->all() )
         {
            $this->db->exec( $formap->install() );
         }
         
         $pais = new pais();
         if( !$pais->all() )
         {
            $this->db->exec( $pais->install() );
         }
         
         $serie = new serie();
         if( !$serie->all() )
         {
            $this->db->exec( $serie->install() );
         }
         
         $recargar = TRUE;
         $this->informe['offset'] += 1;
      }
      else
      {
         /// comprobamos la tabla de articulos de proveedor
         if( $this->db->table_exists('articulosprov') )
         {
            /// buscamos duplicados
            $data = $this->db->select("SELECT codproveedor,refproveedor,COUNT(*) as count FROM articulosprov GROUP BY codproveedor,refproveedor HAVING COUNT(*) > 1;");
            if($data)
            {
               foreach($data as $d)
               {
                  $data2 = $this->db->select("SELECT * FROM articulosprov WHERE codproveedor = '".$d['codproveedor']."' AND refproveedor = '".$d['refproveedor']."';");
                  if($data2)
                  {
                     $this->db->exec("DELETE FROM articulosprov WHERE id = ".$this->empresa->var2str($data2[1]['id']).";");
                  }
               }
               
               $recargar = TRUE;
               $this->informe['offset'] += 1;
            }
         }
      }
      
      if(!$recargar)
      {
         if($this->informe['all'])
         {
            $this->informe['model'] = 'asiento';
         }
         else
            $this->informe['model'] = 'fin';
         
         $this->informe['offset'] = 0;
      }
   }
}
