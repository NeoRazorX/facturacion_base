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

require_model('articulo.php');
require_model('familia.php');
require_model('fabricante.php');
require_model('impuesto.php');
require_model('tarifa.php');

class ventas_articulos extends fs_controller
{
   public $allow_delete;
   public $bloqueados;
   public $buscar;
   public $codfamilia;
   public $con_stock;
   public $familia;
   public $fabricante; 
   public $codfabricante;   
   public $impuesto;
   public $mostrar;
   public $offset;
   public $resultados;
   public $total_resultados;
   public $tarifa;
   public $refauto;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'ventas', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $articulo = new articulo();
      $this->familia = new familia();
      $this->fabricante = new fabricante();
      $this->impuesto = new impuesto();
      $this->tarifa = new tarifa();
      
      
      
      //cargamos la configuración del estado de referencia.
       $fsvar = new fs_var();
       $this->refauto = $fsvar->array_get(
         array(
            'refauto' => 0,
         ),
         FALSE
      );
       
       //modificamos la referencia auto en función del último valor.
       if( isset($_POST['ref_auto']))
       {
           $this->refauto['refauto'] = 1;
       }
       if(isset($_POST['referencia']))
       {
           $this->refauto['refauto'] = 0;
       }
       $fsvar->array_save($this->refauto);
       
      $this->mostrar = '';
      if( isset($_POST['codtarifa']) )
      {
         /// crear/editar tarifa
         $tar0 = $this->tarifa->get($_POST['codtarifa']);
         if( !$tar0 )
         {
            $tar0 = new tarifa();
            $tar0->codtarifa = $_POST['codtarifa'];
         }
         $tar0->nombre = $_POST['nombre'];
         $tar0->aplicar_a = $_POST['aplicar_a'];
         $tar0->set_x( floatval($_POST['dtopor']) );
         $tar0->set_y( floatval($_POST['inclineal']) );
         $tar0->mincoste = isset($_POST['mincoste']);
         $tar0->maxpvp = isset($_POST['maxpvp']);
         $this->mostrar = 'tarifa';
         if( $tar0->save() )
         {
            $this->new_message("Tarifa guardada correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible guardar la tarifa!");
      }
      else if( isset($_GET['delete_tarifa']) )
      {
         /// eliminar tarifa
         $tar0 = $this->tarifa->get($_GET['delete_tarifa']);
         if($tar0)
         {
            if( $tar0->delete() )
            {
               $this->new_message("Tarifa borrada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible borrar la tarifa!");
         }
         else
            $this->new_error_msg("¡La tarifa no existe!");
      }
            else if( isset($_POST['referencia']) AND isset($_POST['codfamilia']) AND isset($_POST['codimpuesto']) )
      {
         /// nuevo artículo
         $this->save_codimpuesto( $_POST['codimpuesto'] );
         
         $art0 = $articulo->get($_POST['referencia']);
         if($art0)
         {
            $this->new_error_msg('Ya existe el artículo <a href="'.$art0->url().'">'.$art0->referencia.'</a>');
         }
         else
         {
            if($_POST['referencia'] == '')
            {
               $articulo->referencia = $articulo->get_new_referencia();
            }
            else
            {
               $articulo->referencia = $_POST['referencia'];
            }
            $articulo->descripcion = $_POST['descripcion'];
            $articulo->nostock = isset($_POST['nostock']);
            
            if($_POST['codfamilia'] != '')
            {
               $articulo->codfamilia = $_POST['codfamilia'];
            }
            
            if($_POST['codfabricante'] != '')
            {
               $articulo->codfabricante = $_POST['codfabricante'];
            }
            
            $articulo->set_pvp( floatval($_POST['pvp']) );
            $articulo->set_impuesto($_POST['codimpuesto']);
            if( $articulo->save() )
            {
               header('location: '.$articulo->url());
            }
            else
               $this->new_error_msg("¡Error al crear el articulo!");
         }
      }
      else if( isset($_GET['delete']) )
      {
         /// eliminar artículo
         $art = $articulo->get($_GET['delete']);
         if($art)
         {
            if( $art->delete() )
            {
               $this->new_message("Articulo ".$art->referencia." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Error al eliminarl el articulo!");
         }
      }
      
      //datos para búsquedas:
      
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = ($_REQUEST['offset']);
      }
      
      $this->codfabricante = '';
      if( isset($_REQUEST['codfabricante']) )
      {
         $this->codfabricante = $_REQUEST['codfabricante'];
      }
      
      $this->codfamilia = '';
      if( isset($_REQUEST['codfamilia']) )
      {
         $this->codfamilia = $_REQUEST['codfamilia'];
      }
      
      
      //obtenemos el orden
      $this->order = ' referencia ASC';
      $this->orden = 'refmin';
      if( isset($_GET['orden']) )
      {
         $this->orden = $_GET['orden'];
         if($_GET['orden'] == 'stockmin')
         {
            $this->order = ' stockfis ASC';
         }
         else if($_GET['orden'] == 'stockmax')
         {
            $this->order = ' stockfis DESC';
         }
         else if($_GET['orden'] == 'refmin')
         {
            $this->order = ' referencia ASC';
         }
         else if($_GET['orden'] == 'refmax')
         {
            $this->order = ' referencia DESC';
         }
         else if($_GET['orden'] == 'descmin')
         {
            $this->order = ' descripcion ASC';
         }
         else if($_GET['orden'] == 'descmax')
         {
            $this->order = ' descripcion DESC';
         }
        setcookie('ventas_articulos_orden', $this->orden, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_articulos']) )
      {
         $this->orden = $_COOKIE['ventas_articulos'];
      }
      
      //cogemos valor de stock:
      $this->stock = '';
      if( isset($_REQUEST['stock']))
      {
          $this->stock = $_REQUEST['stock']; 
      }
      
      //Publicos
      $this->publico = '';
      if( isset($_REQUEST['publico']))
      {
          $this->publico = $_REQUEST['stock']; 
      }
      
      //bloqueados / obsoletos
      $this->bloqueados = '';
      if( isset($_REQUEST['bloqueados']))
      {
          $this->bloqueados = $_REQUEST['bloqueados']; 
      }
      
      if( isset($_REQUEST['mostrar']) )
      {
         $this->mostrar = ($_REQUEST['mostrar']);
      }
 
      $this->search_articulos();   
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['public']) )
      {
         $extra .= '&public=TRUE';
      }
      else if( isset($_GET['solo_stock']) )
      {
         $extra .= '&solo_stock=TRUE';
      }
      else if($this->buscar)
      {
         if($this->query != '')
         {
            $extra .= '&query='.$this->query;
         }
         
         if($this->codfamilia != '')
         {
            $extra .= '&codfamilia='.$this->codfamilia;
         }
         
         if($this->codfabricante != '')
         {
            $extra .= '&codfabricante='.$this->codfabricante;
         }
         
         if($this->con_stock)
         {
            $extra .= '&con_stock=TRUE';
         }
         
         if($this->bloqueados)
         {
            $extra .= '&bloqueados=TRUE';
         }
      }
      
      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['public']) )
      {
         $extra .= '&public=TRUE';
      }
      else if( isset($_GET['solo_stock']) )
      {
         $extra .= '&solo_stock=TRUE';
      }
      else if($this->buscar)
      {
         if($this->query != '')
         {
            $extra .= '&query='.$this->query;
         }
         
         if($this->codfamilia != '')
         {
            $extra .= '&codfamilia='.$this->codfamilia;
         }
         
         if($this->codfabricante != '')
         {
            $extra .= '&codfabricante='.$this->codfabricante;
         }
         
         if($this->con_stock)
         {
            $extra .= '&con_stock=TRUE';
         }
         
         if($this->bloqueados)
         {
            $extra .= '&bloqueados=TRUE';
         }
      }
      
      if( count($this->resultados) == FS_ITEM_LIMIT )
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function total_articulos()
   {
      $data = $this->db->select("SELECT COUNT(referencia) as total FROM articulos;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   private function search_articulos()
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->empresa->no_html( strtolower($this->query) );
      $sql = " FROM articulos ";
      $where = 'WHERE ';
      
      if($this->query != '')
      {
         $sql .= $where;
         if( is_numeric($query) )
         {
            $sql .= "(referencia LIKE '%".$query."%'"
                    . "OR descripcion LIKE '%".$query."%'"
                    . ")";
         }
         else
         {
            $sql .= "(lower(referencia) LIKE '%".str_replace(' ', '%', $query)."%'"
                    . "OR lower(descripcion) LIKE '%".$query."%'"
                    . ")";
         }
         $where = ' AND ';
      }
      
      if($this->codfamilia != '')
      {
         $sql .= $where."codfamilia = ".$this->empresa->var2str($this->codfamilia);
         $where = ' AND ';
      }
      
      if($this->codfabricante != '')
      {
         $sql .= $where."codfabricante = ".$this->empresa->var2str($this->codfabricante);
         $where = ' AND ';
      }
      
      if($this->stock != '')
      {
         $sql .= $where."stockfis != 0";
         $where = ' AND ';
      }
      
      if($this->publico != '')
      {
         $sql .= $where."publico = TRUE";
         $where = ' AND ';
      }
      
      if($this->bloqueados != '')
      {
         $sql .= $where."bloqueado = TRUE";
         $where = ' AND ';
      }
      else
      {
         $sql .= $where."bloqueado = FALSE";
         $where = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(referencia) as total".$sql);
      if($data)
      {
         $this->total_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->order, FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new articulo($d);
            }
         }
      }
   }
   
   public function paginas()
   {

      $url = $this->url()."&query=".$this->query
              ."&orden=".$this->orden
              ."&stock=".$this->stock
              ."&publico=".$this->publico
              ."&bloqueados=".$this->bloqueados
              ."&codfabricante=".$this->codfabricante
              ."&codfamilia=".$this->codfamilia;
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      
      $total = $this->total_resultados;
   
      
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
      
      return $paginas;
   }
}
