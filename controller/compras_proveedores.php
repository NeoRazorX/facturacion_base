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

require_model('pais.php');
require_model('proveedor.php');

class compras_proveedores extends fs_controller
{
   public $mostrar;
   public $offset;
   public $pais;
   public $proveedor;
   public $resultados;
   
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
         $proveedor = FALSE;
         if($_POST['cifnif'] != '')
         {
            $proveedor = $this->proveedor->get_by_cifnif($_POST['cifnif']);
            if($proveedor)
            {
               $this->new_advice('Ya existe un proveedor con el '.FS_CIFNIF.' '.$_POST['cifnif']);
               $this->query = $_POST['cifnif'];
            }
         }
         
         if(!$proveedor)
         {
            $proveedor = new proveedor();
            $proveedor->codproveedor = $proveedor->get_new_codigo();
            $proveedor->nombre = $_POST['nombre'];
            $proveedor->razonsocial = $_POST['nombre'];
            $proveedor->cifnif = $_POST['cifnif'];
            $proveedor->acreedor = isset($_POST['acreedor']);
            
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
               if( $dirproveedor->save() )
               {
                  header('location: '.$proveedor->url());
               }
               else
                  $this->new_error_msg("¡Imposible guardar la dirección el proveedor!");
            }
            else
               $this->new_error_msg("¡Imposible guardar el proveedor!");
         }
      }
      
      $this->mostrar = 'todo';
      if( isset($_GET['mostrar']) )
      {
         $this->mostrar = $_GET['mostrar'];
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      if($this->query != '')
      {
         $this->resultados = $this->proveedor->search($this->query, $this->offset);
      }
      else
      {
         if($this->mostrar == 'acreedores')
         {
            $this->resultados = $this->proveedor->all($this->offset, TRUE);
         }
         else
            $this->resultados = $this->proveedor->all($this->offset);
      }
   }
   
   public function paginas()
   {
      $url = $this->url()."&query=".$this->query
                 ."&offset=".($this->offset+FS_ITEM_LIMIT);
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      $total = 0;
      if($this->mostrar == 'acreedores')
      {
         $total = $this->total_acreedores();
      }
      else
      {
         $total = $this->total_proveedores();
      }
      
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
   
   public function total_proveedores()
   {
      $data = $this->db->select("SELECT COUNT(codproveedor) as total FROM proveedores;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   public function total_acreedores()
   {
      $data = $this->db->select("SELECT COUNT(codproveedor) as total FROM proveedores WHERE acreedor;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
}
