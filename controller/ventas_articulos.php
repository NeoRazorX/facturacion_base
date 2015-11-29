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
   public $b_bloqueados;
   public $b_codfabricante;
   public $b_codfamilia;
   public $b_codtarifa;
   public $b_constock;
   public $b_orden;
   public $b_publicos;
   public $b_url;
   public $familia;
   public $fabricante;
   public $impuesto;
   public $mostrar_tab_tarifas;
   public $offset;
   public $resultados;
   public $total_resultados;
   public $tarifa;
   
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
      
      /**
       * Si hay alguna extensión de tipo config y texto no_tab_tarifas,
       * desactivamos la pestaña tarifas.
       */
      $this->mostrar_tab_tarifas = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_tab_tarifas')
         {
            $this->mostrar_tab_tarifas = FALSE;
            break;
         }
      }
      
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
      
      
      /// obtenemos los datos para la búsqueda
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }
      
      $this->b_codfamilia = '';
      if( isset($_REQUEST['b_codfamilia']) )
      {
         $this->b_codfamilia = $_REQUEST['b_codfamilia'];
      }
      
      $this->b_codfabricante = '';
      if( isset($_REQUEST['b_codfabricante']) )
      {
         $this->b_codfabricante = $_REQUEST['b_codfabricante'];
      }
      
      $this->b_constock = isset($_REQUEST['b_constock']);
      $this->b_bloqueados = isset($_REQUEST['b_bloqueados']);
      $this->b_publicos = isset($_REQUEST['b_publicos']);
      
      $this->b_codtarifa = '';
      if( isset($_REQUEST['b_codtarifa']) )
      {
         $this->b_codtarifa = ($_REQUEST['b_codtarifa']);
         setcookie('b_codtarifa', $this->b_codtarifa, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['b_codtarifa']) )
      {
         $this->b_codtarifa = $_COOKIE['b_codtarifa']; 
      }
      
      $this->b_orden = 'refmin';
      if( isset($_REQUEST['b_orden']) )
      {
         $this->b_orden = $_REQUEST['b_orden'];
         setcookie('ventas_articulos_orden', $this->b_orden, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_articulos_orden']) )
      {
         $this->b_orden = $_COOKIE['ventas_articulos_orden'];
      }
      
      $this->b_url = $this->url()."&query=".$this->query
              ."&b_codfabricante=".$this->b_codfabricante
              ."&b_codfamilia=".$this->b_codfamilia
              ."&b_codtarifa=".$this->b_codtarifa;
      
      if($this->b_constock)
      {
         $this->b_url .= '&b_constock=TRUE';
      }
      
      if($this->b_bloqueados)
      {
         $this->b_url .= '&b_bloqueados=TRUE';
      }
      
      if($this->b_publicos)
      {
         $this->b_url .= '&b_publicos=TRUE';
      }
      
      $this->search_articulos();   
   }

   private function search_articulos()
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->empresa->no_html( strtolower($this->query) );
      $sql = ' FROM articulos ';
      $where = ' WHERE ';
      
      if($this->query != '')
      {
         $sql .= $where;
         if( is_numeric($query) )
         {
            $sql .= "(referencia = ".$this->empresa->var2str($query)
                    . " OR referencia LIKE '%".$query."%'"
                    . " OR equivalencia LIKE '%".$query."%'"
                    . " OR descripcion LIKE '%".$query."%'"
                    . " OR codbarras = '".$query."')";
         }
         else
         {
            $buscar = str_replace(' ', '%', $query);
            $sql .= "(lower(referencia) = ".$this->empresa->var2str($query)
                    . " OR lower(referencia) LIKE '%".$buscar."%'"
                    . " OR lower(equivalencia) LIKE '%".$buscar."%'"
                    . " OR lower(descripcion) LIKE '%".$buscar."%')";
         }
         $where = ' AND ';
      }
      
      if($this->b_codfamilia != '')
      {
         $sql .= $where."codfamilia = ".$this->empresa->var2str($this->b_codfamilia);
         $where = ' AND ';
      }
      
      if($this->b_codfabricante != '')
      {
         $sql .= $where."codfabricante = ".$this->empresa->var2str($this->b_codfabricante);
         $where = ' AND ';
      }
      
      if($this->b_constock)
      {
         $sql .= $where."stockfis > 0";
         $where = ' AND ';
      }
      
      if($this->b_publicos)
      {
         $sql .= $where."publico = TRUE";
         $where = ' AND ';
      }
      
      if($this->b_bloqueados)
      {
         $sql .= $where."bloqueado = TRUE";
         $where = ' AND ';
      }
      else
      {
         $sql .= $where."bloqueado = FALSE";
         $where = ' AND ';
      }
      
      $order = 'referencia DESC';
      switch($this->b_orden)
      {
         case 'stockmin':
            $order = 'stockfis ASC';
            break;
         
         case 'stockmax':
            $order = 'stockfis DESC';
            break;
         
         case 'refmax':
            if( strtolower(FS_DB_TYPE) == 'postgresql' )
            {
               $order = 'referencia DESC';
            }
            else
            {
                $order = 'lower(referencia) DESC';
            }
            break;
         
         case 'descmin':
            $order = 'descripcion ASC';
            break;
         
         case 'descmax':
            $order = 'descripcion DESC';
            break;
         
         case 'preciomin':
            /// cogemos datos de la tarifa para ordenar:
            $tarifa = $this->tarifa->get($this->b_codtarifa);
            if($tarifa)
            {
               if($tarifa->aplicar_a == 'coste')
               {
                  $order = 'preciocoste ASC';
               }
               else
               {
                  $order = 'pvp ASC';
               }
            }
            else
            {
               $order = 'pvp ASC';
            }
            break;
         
         case 'preciomax':
            /// cogemos datos de la tarifa para ordenar:
            $tarifa = $this->tarifa->get($this->b_codtarifa);
            if($tarifa)
            {
               if($tarifa->aplicar_a == 'coste')
               {
                  $order = 'preciocoste DESC';
               }
               else
               {
                  $order = 'pvp DESC';
               }
            }
            else
            {
               $order = 'pvp DESC';
            }
            break;
         
         default:
         case 'refmin':
            if( strtolower(FS_DB_TYPE) == 'postgresql' )
            {
               $order = 'referencia ASC';
            }
            else
            {
               $order = 'lower(referencia) ASC';
            }
            break;
      }
      
      $data = $this->db->select("SELECT COUNT(referencia) as total".$sql);
      if($data)
      {
         $this->total_resultados = intval($data[0]['total']);
         
         /// ¿Descargar o mostrar en pantalla?
         if( isset($_GET['download']) )
         {
            /// desactivamos el motor de plantillas
            $this->template = FALSE;
            
            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"articulos.csv\"");
            echo "referencia;codfamilia;codfabricante;descripcion;pvp;iva;codbarras;stock;coste\n";
            
            $offset2 = 0;
            $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, 1000, $offset2);
            while($data2)
            {
               $resultados = array();
               foreach($data2 as $i)
               {
                  $resultados[] = new articulo($i);
               }
               
               if($this->b_codtarifa != '')
               {
                  /// aplicamos la tarifa
                  $tarifa = $this->tarifa->get($this->b_codtarifa);
                  if($tarifa)
                  {
                     $tarifa->set_precios($resultados);
                     
                     /// si la tarifa añade descuento, lo aplicamos al precio
                     foreach($resultados as $i => $value)
                     {
                        $resultados[$i]->pvp -= $value->pvp*$value->dtopor/100;
                     }
                  }
               }
               
               /// escribimos los datos de los artículos
               foreach($resultados as $art)
               {
                  echo $art->referencia.';';
                  echo $art->codfamilia.';';
                  echo $art->codfabricante.';';
                  echo $this->fix_html($art->descripcion).';';
                  echo $art->pvp.';';
                  echo $art->get_iva().';';
                  echo trim($art->codbarras).';';
                  echo $art->stockfis.';';
                  echo $art->preciocoste()."\n";
                  
                  $offset2++;
               }
               
               $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, 1000, $offset2);
            }
         }
         else
         {
            $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, FS_ITEM_LIMIT, $this->offset);
            if($data2)
            {
               foreach($data2 as $i)
               {
                  $this->resultados[] = new articulo($i);
               }
               
               if($this->b_codtarifa != '')
               {
                  /// aplicamos la tarifa
                  $tarifa = $this->tarifa->get($this->b_codtarifa);
                  if($tarifa)
                  {
                     $tarifa->set_precios($this->resultados);
                     
                     /// si la tarifa añade descuento, lo aplicamos al precio
                     foreach($this->resultados as $i => $value)
                     {
                        $this->resultados[$i]->pvp -= $value->pvp*$value->dtopor/100;
                     }
                  }
               }
            }
         }
      }
   }
   
   public function paginas()
   {
      $url = $this->b_url.'&b_orden='.$this->b_orden;
      
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
      
      if( count($paginas) > 1 )
      {
         return $paginas;
      }
      else
      {
         return array();
      }
   }
   
   private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', "'", $newt);
      $newt = str_replace('&#39;', "'", $newt);
      $newt = str_replace(';', '.', $newt);
      return trim($newt);
   }
}
