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

require_model('agente.php');
require_model('articulo.php');
require_model('cliente.php');
require_model('factura_cliente.php');

class ventas_facturas extends fs_controller
{
   public $agente;
   public $articulo;
   public $buscar_lineas;
   public $cliente;
   public $codagente;
   public $codserie;
   public $desde;
   public $estado;
   public $factura;
   public $hasta;
   public $huecos;
   public $lineas;
   public $mostrar;
   public $num_resultados;
   public $offset;
   public $order;
   public $resultados;
   public $serie;
   public $total_resultados;
   public $total_resultados_comision;
   public $total_resultados_txt;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Facturas', 'ventas');
   }
   
   protected function private_core()
   {
      $this->agente = new agente();
      $this->factura = new factura_cliente();
      $this->huecos = array();
      $this->serie = new serie();
      
      $this->mostrar = 'todo';
      if( isset($_GET['mostrar']) )
      {
         $this->mostrar = $_GET['mostrar'];
         setcookie('ventas_fac_mostrar', $this->mostrar, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_fac_mostrar']) )
      {
         $this->mostrar = $_COOKIE['ventas_fac_mostrar'];
      }
      
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }
      
      $this->order = 'fecha DESC';
      if( isset($_GET['order']) )
      {
         if($_GET['order'] == 'fecha_desc')
         {
            $this->order = 'fecha DESC';
         }
         else if($_GET['order'] == 'fecha_asc')
         {
            $this->order = 'fecha ASC';
         }
         else if($_GET['order'] == 'vencimiento_desc')
         {
            $this->order = 'vencimiento DESC';
         }
         else if($_GET['order'] == 'vencimiento_asc')
         {
            $this->order = 'vencimiento ASC';
         }
         else if($_GET['order'] == 'total_desc')
         {
            $this->order = 'total DESC';
         }
         
         setcookie('ventas_fac_order', $this->order, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_fac_order']) )
      {
         $this->order = $_COOKIE['ventas_fac_order'];
      }
      
      if( isset($_POST['buscar_lineas']) )
      {
         $this->buscar_lineas();
      }
      else if( isset($_REQUEST['buscar_cliente']) )
      {
         $this->buscar_cliente();
      }
      else if( isset($_GET['ref']) )
      {
         $this->template = 'extension/ventas_facturas_articulo';
         
         $articulo = new articulo();
         $this->articulo = $articulo->get($_GET['ref']);
         
         $linea = new linea_factura_cliente();
         $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
      }
      else
      {
         $this->share_extension();
         $this->huecos = $this->factura->huecos();
         $this->cliente = FALSE;
         $this->codagente = '';
         $this->codserie = '';
         $this->desde = '';
         $this->estado = '';
         $this->hasta = '';
         $this->num_resultados = '';
         $this->total_resultados = array();
         $this->total_resultados_comision = 0;
         $this->total_resultados_txt = '';
         
         if( isset($_GET['delete']) )
         {
            $this->delete_factura();
         }
         else
         {
            if( !isset($_GET['mostrar']) AND (isset($_REQUEST['codagente']) OR isset($_REQUEST['codcliente']) OR isset($_REQUEST['codserie'])) )
            {
               /**
                * si obtenermos un codagente, un codcliente o un codserie pasamos direcatemente
                * a la pestaña de búsqueda, a menos que tengamos un mostrar, que
                * entonces nos indica donde tenemos que estar.
                */
               $this->mostrar = 'buscar';
            }
            
            if( isset($_REQUEST['codcliente']) )
            {
               if($_REQUEST['codcliente'] != '')
               {
                  $cli0 = new cliente();
                  $this->cliente = $cli0->get($_REQUEST['codcliente']);
               }
            }
            
            if( isset($_REQUEST['codagente']) )
            {
               $this->codagente = $_REQUEST['codagente'];
            }
            
            if( isset($_REQUEST['codserie']) )
            {
               $this->codserie = $_REQUEST['codserie'];
            }
            
            if( isset($_REQUEST['desde']) )
            {
               $this->desde = $_REQUEST['desde'];
               $this->hasta = $_REQUEST['hasta'];
               $this->estado = $_REQUEST['estado'];
            }
         }
         
         /// añadimos segundo nivel de ordenación
         $order2 = '';
         if( substr($this->order, -4) == 'DESC' )
         {
            $order2 = ', hora DESC, numero DESC';
         }
         else
         {
            $order2 = ', hora ASC, numero ASC';
         }
         
         if($this->mostrar == 'sinpagar')
         {
            $this->resultados = $this->factura->all_sin_pagar($this->offset, FS_ITEM_LIMIT, $this->order.$order2);
            
            if($this->offset == 0)
            {
               /// calculamos el total, pero desglosando por divisa
               $this->total_resultados = array();
               $this->total_resultados_txt = 'Suma total de esta página:';
               foreach($this->resultados as $fac)
               {
                  if( !isset($this->total_resultados[$fac->coddivisa]) )
                  {
                     $this->total_resultados[$fac->coddivisa] = array(
                         'coddivisa' => $fac->coddivisa,
                         'total' => 0
                     );
                  }
                  
                  $this->total_resultados[$fac->coddivisa]['total'] += $fac->total;
               }
            }
         }
         else if($this->mostrar == 'buscar')
         {
            $this->buscar($order2);
         }
         else
            $this->resultados = $this->factura->all($this->offset, FS_ITEM_LIMIT, $this->order.$order2);
      }
   }
   
   public function url($busqueda = FALSE)
   {
      if($busqueda)
      {
         $codcliente = '';
         if($this->cliente)
         {
            $codcliente = $this->cliente->codcliente;
         }
         
         $url = $this->url()."&mostrar=".$this->mostrar
                 ."&query=".$this->query
                 ."&codserie=".$this->codserie
                 ."&codagente=".$this->codagente
                 ."&codcliente=".$codcliente
                 ."&desde=".$this->desde
                 ."&estado=".$this->estado
                 ."&hasta=".$this->hasta;
         
         return $url;
      }
      else
      {
         return parent::url();
      }
   }
   
   private function buscar_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $cli0 = new cliente();
      $json = array();
      foreach($cli0->search($_REQUEST['buscar_cliente']) as $cli)
      {
         $json[] = array('value' => $cli->nombre, 'data' => $cli->codcliente);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_cliente'], 'suggestions' => $json) );
   }
   
   public function paginas()
   {
      $url = $this->url(TRUE);
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      if($this->mostrar == 'sinpagar')
      {
         $total = $this->total_sinpagar();
      }
      else if($this->mostrar == 'buscar')
      {
         $total = $this->num_resultados;
      }
      else
      {
         $total = $this->total_registros();
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
   
   public function buscar_lineas()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/ventas_lineas_facturas';
      
      $this->buscar_lineas = $_POST['buscar_lineas'];
      $linea = new linea_factura_cliente();
      
      if( isset($_POST['codcliente']) )
      {
         $this->lineas = $linea->search_from_cliente2($_POST['codcliente'], $this->buscar_lineas, $_POST['buscar_lineas_o']);
      }
      else
      {
         $this->lineas = $linea->search($this->buscar_lineas);
      }
   }
   
   private function share_extension()
   {
      /// añadimos las extensiones para clientes, agentes y artículos
      $extensiones = array(
          array(
              'name' => 'facturas_cliente',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_cliente',
              'type' => 'button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas',
              'params' => ''
          ),
          array(
              'name' => 'facturas_agente',
              'page_from' => __CLASS__,
              'page_to' => 'admin_agente',
              'type' => 'button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
              'params' => ''
          ),
          array(
              'name' => 'facturas_articulo',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_articulo',
              'type' => 'tab_button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
              'params' => ''
          ),
      );
      foreach($extensiones as $ext)
      {
         $fsext0 = new fs_extension($ext);
         if( !$fsext0->save() )
         {
            $this->new_error_msg('Imposible guardar los datos de la extensión '.$ext['name'].'.');
         }
      }
   }
   
   public function total_sinpagar()
   {
      $data = $this->db->select("SELECT COUNT(idfactura) as total FROM facturascli WHERE pagada = false;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   private function total_registros()
   {
      $data = $this->db->select("SELECT COUNT(idfactura) as total FROM facturascli;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   private function buscar($order2)
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->agente->no_html( mb_strtolower($this->query, 'UTF8') );
      $sql = " FROM facturascli ";
      $where = 'WHERE ';
      
      if($this->query != '')
      {
         $sql .= $where;
         if( is_numeric($query) )
         {
            $sql .= "(codigo LIKE '%".$query."%' OR numero2 LIKE '%".$query."%' "
                    . "OR observaciones LIKE '%".$query."%' OR cifnif LIKE '".$query."%')";
         }
         else
         {
            $sql .= "(lower(codigo) LIKE '%".$query."%' OR lower(numero2) LIKE '%".$query."%' "
                    . "OR lower(cifnif) LIKE '".$query."%' "
                    . "OR lower(observaciones) LIKE '%".str_replace(' ', '%', $query)."%')";
         }
         $where = ' AND ';
      }
      
      if($this->codagente != '')
      {
         $sql .= $where."codagente = ".$this->agente->var2str($this->codagente);
         $where = ' AND ';
      }
      
      if($this->cliente)
      {
         $sql .= $where."codcliente = ".$this->agente->var2str($this->cliente->codcliente);
         $where = ' AND ';
      }
      
      if($this->codserie != '')
      {
         $sql .= $where."codserie = ".$this->agente->var2str($this->codserie);
         $where = ' AND ';
      }
      
      if($this->desde != '')
      {
         $sql .= $where."fecha >= ".$this->agente->var2str($this->desde);
         $where = ' AND ';
      }
      
      if($this->hasta != '')
      {
         $sql .= $where."fecha <= ".$this->agente->var2str($this->hasta);
         $where = ' AND ';
      }
      
      if($this->estado == 'pagadas')
      {
         $sql .= $where."pagada";
         $where = ' AND ';
      }
      else if($this->estado == 'impagadas')
      {
         $sql .= $where."pagada = false";
         $where = ' AND ';
      }
      else if($this->estado == 'anuladas')
      {
         $sql .= $where."anulada = true";
         $where = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(idfactura) as total".$sql);
      if($data)
      {
         $this->num_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->order.$order2, FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new factura_cliente($d);
            }
         }
         
         $data2 = $this->db->select("SELECT coddivisa,SUM(total) as total".$sql." GROUP BY coddivisa");
         if($data2)
         {
            $this->total_resultados_txt = 'Suma total de los resultados:';
            
            foreach($data2 as $d)
            {
               $this->total_resultados[] = array(
                   'coddivisa' => $d['coddivisa'],
                   'total' => floatval($d['total'])
               );
            }
         }
         
         if($this->codagente !== '')
         {
            /// calculamos la comisión del empleado
            $data2 = $this->db->select("SELECT SUM(neto*porcomision/100) as total".$sql);
            if($data2)
            {
               $this->total_resultados_comision = floatval($data2[0]['total']);
            }
         }
      }
   }
   
   private function delete_factura()
   {
      $fact = $this->factura->get($_GET['delete']);
      if($fact)
      {
         if( $fact->delete() )
         {
            /// Restauramos el stock
            $art0 = new articulo();
            foreach($fact->get_lineas() as $linea)
            {
               if( is_null($linea->idalbaran) )
               {
                  $articulo = $art0->get($linea->referencia);
                  if($articulo)
                  {
                     $articulo->sum_stock($fact->codalmacen, $linea->cantidad);
                  }
               }
            }
            
            $this->new_message("Factura de venta ".$fact->codigo." eliminada correctamente.", TRUE);
            $this->clean_last_changes();
         }
         else
            $this->new_error_msg("¡Imposible eliminar la factura!");
      }
      else
         $this->new_error_msg("Factura no encontrada.");
   }
}
