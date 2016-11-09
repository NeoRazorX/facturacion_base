<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('pais.php');
require_model('proveedor.php');

class compras_proveedores extends fs_controller
{
   public $num_resultados;
   public $offset;
   public $orden;
   public $pais;
   public $proveedor;
   public $resultados;
   public $tipo;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Proveedores / Acreedores', 'compras', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->pais = new pais();
      $this->proveedor = new proveedor();
      
      if( isset($_GET['delete']) )
      {
         /// eliminar proveedor
         $proveedor = $this->proveedor->get($_GET['delete']);
         if($proveedor)
         {
            if(FS_DEMO)
            {
               $this->new_error_msg('En el modo demo no se pueden eliminar proveedores.
                  Otros usuarios podrían necesitarlos.');
            }
            else if( $proveedor->delete() )
            {
               $this->new_message('Proveedor eliminado correctamente.');
            }
            else
               $this->new_error_msg('Ha sido imposible borrar el proveedor.');
         }
         else
            $this->new_message('Proveedor no encontrado.');
      }
      else if( isset($_POST['cifnif']) )
      {
         /// nuevo proveedor
         $proveedor = new proveedor();
         $proveedor->codproveedor = $proveedor->get_new_codigo();
         $proveedor->nombre = $_POST['nombre'];
         $proveedor->razonsocial = $_POST['nombre'];
         $proveedor->tipoidfiscal = $_POST['tipoidfiscal'];
         $proveedor->cifnif = $_POST['cifnif'];
         $proveedor->acreedor = isset($_POST['acreedor']);
         $proveedor->personafisica = isset($_POST['personafisica']);
         
         if( $proveedor->save() )
         {
            $dirproveedor = new direccion_proveedor();
            $dirproveedor->codproveedor = $proveedor->codproveedor;
            $dirproveedor->descripcion = "Principal";
            $dirproveedor->codpais = $_POST['pais'];
            $dirproveedor->provincia = $_POST['provincia'];
            $dirproveedor->ciudad = $_POST['ciudad'];
            $dirproveedor->codpostal = $_POST['codpostal'];
            $dirproveedor->direccion = $_POST['direccion'];
            $dirproveedor->apartado = $_POST['apartado'];
            
            if( $dirproveedor->save() )
            {
               if($this->empresa->contintegrada)
               {
                  /// forzamos crear la subcuenta
                  $proveedor->get_subcuenta($this->empresa->codejercicio);
               }
               
               /// redireccionamos a la página del proveedor
               header('location: '.$proveedor->url());
            }
            else
               $this->new_error_msg("¡Imposible guardar la dirección el proveedor!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el proveedor!");
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      $this->orden = 'nombre ASC';
      if( isset($_REQUEST['orden']) )
      {
         $this->orden = $_REQUEST['orden'];
      }
      
      $this->tipo = '';
      if( isset($_REQUEST['tipo']) )
      {
         $this->tipo = $_REQUEST['tipo'];
      }
      
      $this->buscar();
   }
   
   private function buscar()
   {
      $this->total_resultados = 0;
      $query = mb_strtolower( $this->proveedor->no_html($this->query), 'UTF8' );
      $sql = " FROM proveedores";
      $and = ' WHERE ';
      
      if( is_numeric($query) )
      {
         $sql .= $and."(codproveedor LIKE '%".$query."%'"
                 . " OR cifnif LIKE '%".$query."%'"
                 . " OR telefono1 LIKE '".$query."%'"
                 . " OR telefono2 LIKE '".$query."%'"
                 . " OR observaciones LIKE '%".$query."%')";
         $and = ' AND ';
      }
      else
      {
         $buscar = str_replace(' ', '%', $query);
         $sql .= $and."(lower(nombre) LIKE '%".$buscar."%'"
                 . " OR lower(razonsocial) LIKE '%".$buscar."%'"
                 . " OR lower(cifnif) LIKE '%".$buscar."%'"
                 . " OR lower(observaciones) LIKE '%".$buscar."%'"
                 . " OR lower(email) LIKE '%".$buscar."%')";
         $and = ' AND ';
      }
      
      if($this->tipo == 'acreedores')
      {
         $sql .= $and."acreedor = true";
         $and = ' AND ';
      }
      else if($this->tipo == 'noacreedores')
      {
         $sql .= $and."acreedor = false";
         $and = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(codproveedor) as total".$sql.';');
      if($data)
      {
         $this->num_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->orden, FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new proveedor($d);
            }
         }
      }
   }
   
   public function paginas()
   {
      $url = $this->url()."&query=".$this->query
              ."&tipo=".$this->tipo
              ."&orden=".$this->orden;
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      $total = 0;
      $total = $this->num_resultados;
      
      /// añadimos todas la página
      while($num < $total)
      {
         $paginas[$i] = array(
             'url' => $url."&offset=".($i*FS_ITEM_LIMIT),
             'num' => $i + 1,
             'actual' => ($num == $this->offset)
         );
         
         if($num == $this->offset)
         {
            $actual = $i;
         }
         
         $i++;
         $num += FS_ITEM_LIMIT;
      }
      
      /// ahora descartamos
      foreach($paginas as $j => $value)
      {
         $enmedio = intval($i/2);
         
         /**
          * descartamos todo excepto la primera, la última, la de enmedio,
          * la actual, las 5 anteriores y las 5 siguientes
          */
         if( ($j>1 AND $j<$actual-5 AND $j!=$enmedio) OR ($j>$actual+5 AND $j<$i-1 AND $j!=$enmedio) )
         {
            unset($paginas[$j]);
         }
      }
      
      if( count($paginas) > 1 )
      {
         return $paginas;
      }
      else
      {
         return array();
      }
   }
}
