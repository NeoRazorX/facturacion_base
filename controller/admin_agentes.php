<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('agente.php');

class admin_agentes extends fs_controller
{
   public $agente;
   public $ciudad;
   public $offset;
   public $orden;
   public $provincia;
   public $resultados;
   public $total_resultados;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Empleados', 'admin');
   }
   
   protected function private_core()
   {
      $this->agente = new agente();
      
      if( isset($_POST['sdnicif']) )
      {
         $age0 = new agente();
         $age0->codagente = $age0->get_new_codigo();
         $age0->nombre = $_POST['snombre'];
         $age0->apellidos = $_POST['sapellidos'];
         $age0->dnicif = $_POST['sdnicif'];
         $age0->telefono = $_POST['stelefono'];
         $age0->email = $_POST['semail'];
         if( $age0->save() )
         {
            $this->new_message("Empleado ".$age0->codagente." guardado correctamente.");
            header('location: '.$age0->url());
         }
         else
            $this->new_error_msg("¡Imposible guardar el empleado!");
      }
      else if( isset($_GET['delete']) )
      {
         $age0 = $this->agente->get($_GET['delete']);
         if($age0)
         {
            if( FS_DEMO )
            {
               $this->new_error_msg('En el modo <b>demo</b> no se pueden eliminar empleados. Otro usuario podría estar usándolo.');
            }
            else if( $age0->delete() )
            {
               $this->new_message("Empleado ".$age0->codagente." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el empleado!");
         }
         else
            $this->new_error_msg("¡Empleado no encontrado!");
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      $this->ciudad = '';
      if( isset($_REQUEST['ciudad']) )
      {
         $this->ciudad = $_REQUEST['ciudad'];
      }
      
      $this->provincia = '';
      if( isset($_REQUEST['provincia']) )
      {
         $this->provincia = $_REQUEST['provincia'];
      }
      
      $this->orden = 'nombre ASC';
      if( isset($_REQUEST['orden']) )
      {
         $this->orden = $_REQUEST['orden'];
      }
      
      $this->buscar();
   }
   
   public function paginas()
   {
      $url = $this->url()."&query=".$this->query
                 ."&ciudad=".$this->ciudad
                 ."&provincia=".$this->provincia
                 ."&orden=".$this->orden;
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      /// añadimos todas la página
      while($num < $this->total_resultados)
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
   
   public function ciudades()
   {
      $final = array();
      
      if( $this->db->table_exists('agentes') )
      {
         $ciudades = array();
         $sql = "SELECT DISTINCT ciudad FROM agentes ORDER BY ciudad ASC;";
         if($this->provincia != '')
         {
            $sql = "SELECT DISTINCT ciudad FROM agentes WHERE lower(provincia) = "
                    .$this->agente->var2str($this->provincia)." ORDER BY ciudad ASC;";
         }
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $ciudades[] = $d['ciudad'];
            }
         }
         
         /// usamos las minúsculas para filtrar
         foreach($ciudades as $ciu)
         {
            if($ciu != '')
            {
               $final[ mb_strtolower($ciu, 'UTF8') ] = $ciu;
            }
         }
      }
      
      return $final;
   }
   
   public function provincias()
   {
      $final = array();
      
      if( $this->db->table_exists('agentes') )
      {
         $provincias = array();
         $sql = "SELECT DISTINCT provincia FROM agentes ORDER BY provincia ASC;";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $provincias[] = $d['provincia'];
            }
         }
         
         foreach($provincias as $pro)
         {
            if($pro != '')
            {
               $final[ mb_strtolower($pro, 'UTF8') ] = $pro;
            }
         }
      }
      
      return $final;
   }
   
   private function buscar()
   {
      $this->total_resultados = 0;
      $query = mb_strtolower( $this->agente->no_html($this->query), 'UTF8' );
      $sql = " FROM agentes";
      $and = ' WHERE ';
      
      if( is_numeric($query) )
      {
         $sql .= $and."(codagente LIKE '%".$query."%'"
                 . " OR dnicif LIKE '%".$query."%'"
                 . " OR telefono LIKE '".$query."%')";
         $and = ' AND ';
      }
      else
      {
         $buscar = str_replace(' ', '%', $query);
         $sql .= $and."(lower(nombre) LIKE '%".$buscar."%'"
                 . " OR lower(apellidos) LIKE '%".$buscar."%'"
                 . " OR lower(dnicif) LIKE '%".$buscar."%'"
                 . " OR lower(email) LIKE '%".$buscar."%')";
         $and = ' AND ';
      }
      
      if($this->ciudad != '')
      {
         $sql .= $and."lower(ciudad) = ".$this->agente->var2str( mb_strtolower($this->ciudad, 'UTF8') );
         $and = ' AND ';
      }
      
      if($this->provincia != '')
      {
         $sql .= $and."lower(provincia) = ".$this->agente->var2str( mb_strtolower($this->provincia, 'UTF8') );
         $and = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(codagente) as total".$sql.';');
      if($data)
      {
         $this->total_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->orden, FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new agente($d);
            }
         }
      }
   }
}
