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
   public $codfamilia;
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
         $this->mostrar = '';
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
      /// nuevo artículo
            else if( isset($_POST['referencia']) AND isset($_POST['codfamilia']) AND isset($_POST['codimpuesto']) )
      {
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
      
      //Tarifa a usar:
      $this->cod_tarifa = '';
      if( isset($_REQUEST['cod_tarifa']) )
      {
         $this->cod_tarifa = ($_REQUEST['cod_tarifa']);
         setcookie('cod_tarifa', $this->cod_tarifa, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['cod_tarifa']) )
         $this->cod_tarifa = $_COOKIE['cod_tarifa']; 
      
      //obtenemos el orden
      $this->orden = '';
      $this->order =' referencia ASC';
      if( isset($_GET['orden']))
      {
        $this->orden = $_GET['orden'];
      }
      else if( isset($_COOKIE['ventas_articulos_orden']))
      { 
        $this->orden = $_COOKIE['ventas_articulos_orden'];
      }
      else
      {
          $this->orden = 'descmin';
      }
      
      //aplicamos el orden 
      if($this->orden == 'stockmin')
        {
           $this->order = ' stockfis ASC';  
        }
      else if($this->orden == 'stockmax')
        {
           $this->order = ' stockfis DESC'; 
        }
      else if($this->orden == 'refmin')
        {
           if( strtolower(FS_DB_TYPE) == 'postgresql')
            {
               $this->order =" case
                                   when substring(referencia from '^\d+$') is null then 9999
                                   else cast(referencia as integer)
                                 end ASC,
                                 referencia";
            }
            else
            {
                $this->order = ' cast(referencia as decimal(38,10)) ASC';
            } 
        }
      else if($this->orden == 'refmax')
        {
           if( strtolower(FS_DB_TYPE) == 'postgresql')
            {
               $this->order =" case
                                   when substring(referencia from '^\d+$') is null then 9999
                                   else cast(referencia as integer)
                                 end DESC,
                                 referencia ";
            }
            else
            {
                $this->order = ' cast(referencia as decimal(38,10)) DESC';
            }           
        }
        else if($this->orden == 'descmin')
            {
               $this->order = ' descripcion ASC';  
            }
        else if($this->orden == 'descmax')
            {
               $this->order = ' descripcion DESC'; 
            }
        else if($this->orden == 'preciomin')
            {
               //cogemos datos de la tarifa para ordenar: 
                $tarifa = $this->tarifa->get($this->cod_tarifa);
                if($tarifa)
                {
                   if ($tarifa->aplicar_a == 'coste')
                   {
                       $this->order = ' preciocoste ASC';
                   }
                   else
                   {
                       $this->order = ' pvp ASC';
                   }
                }
                else
                   {
                       $this->order = ' pvp ASC';
                   }
               
            }
        else if($this->orden == 'preciomax')
            {
               //cogemos datos de la tarifa para ordenar: 
                $tarifa = $this->tarifa->get($this->cod_tarifa);
                if($tarifa)
                {
                   if ($tarifa->aplicar_a == 'coste')
                   {
                       $this->order = ' preciocoste DESC';
                   }
                   else
                   {
                       $this->order = ' pvp DESC';
                   }
                }
                else
                   {
                       $this->order = ' pvp DESC';
                   }
               
            }
        setcookie('ventas_articulos_orden', $this->orden, time()+FS_COOKIES_EXPIRE);
      
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
          $this->publico = $_REQUEST['publico']; 
      }
      
      //bloqueados / obsoletos
      $this->bloqueados = '';
      if( isset($_REQUEST['bloqueados']))
      {
          $this->bloqueados = $_REQUEST['bloqueados']; 
      }
      
      //que mostrar
      if( isset($_REQUEST['mostrar']) )
      {
         $this->mostrar = ($_REQUEST['mostrar']);
      }
 
      $this->search_articulos();   
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
         $sql .= $where."stockfis > 0";
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
            foreach($data2 as $i)
            {
               $this->resultados[] = new articulo($i);
            }
            //aplicar tarifa
            $tarifa = $this->tarifa->get($this->cod_tarifa);
            if($tarifa)
            {
                $tarifa->set_precios($this->resultados);
            }
         }
       
      }
   }
   
   public function paginas()
   {

      $url = $this->url()."&query=".$this->query
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
