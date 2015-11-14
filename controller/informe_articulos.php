<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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
require_model('impuesto.php');
require_model('linea_factura_cliente.php');
require_model('linea_factura_proveedor.php');
require_model('regularizacion_stock.php');

class informe_articulos extends fs_controller
{
   public $articulo;
   public $codimpuesto;
   public $desde;
   public $documento;
   public $hasta;
   public $impuesto;
   private $offset;
   public $pestanya;
   public $referencia;
   public $resultados;
   public $sin_vender;
   public $stats;
   public $tipo_stock;
   public $top_ventas;
   public $top_compras;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->share_extension();
      
      $this->pestanya = 'stats';
      if( isset($_GET['tab']) )
      {
         $this->pestanya = $_GET['tab'];
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      if( isset($_REQUEST['buscar_referencia']) )
      {
         $this->buscar_referencia();
      }
      else if($this->pestanya == 'stats')
      {
         $this->articulo = new articulo();
         $this->stats = $this->stats();
         
         $linea_fac_cli = new linea_factura_cliente();
         $linea_fac_pro = new linea_factura_proveedor();
         
         $this->top_ventas = $this->top_articulo_faccli();
         $this->sin_vender = $this->sin_vender();
         $this->top_compras = $this->top_articulo_facpro();
      }
      else if($this->pestanya == 'stock')
      {
         /// forzamos la comprobación de la tabla stock
         $stock = new stock();
         
         $this->tipo_stock = 'todo';
         if( isset($_GET['tipo']) )
         {
            $this->tipo_stock = $_GET['tipo'];
         }
         
         if($this->tipo_stock == 'reg')
         {
            /// forzamos la comprobación de la tabla stocks
            $reg = new regularizacion_stock();
            
            $this->resultados = $this->regularizaciones_stock($this->offset);
         }
         else if( isset($_GET['download']) )
         {
            $this->template = FALSE;
            
            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"stock.csv\"");
            echo "almacen,referencia,descripcion,stock,stockmin,stockmax\n";
            
            $offset = 0;
            $resultados = $this->stock($offset, $this->tipo_stock);
            while( count($resultados) > 0 )
            {
               foreach($resultados as $res)
               {
                  echo '"'.$res['codalmacen'].'","'.$res['referencia'].'","'.$res['descripcion'].'","'.
                          $res['cantidad'].'","'.$res['stockmin'].'","'.$res['stockmax']."\"\n";
                  $offset++;
               }
               
               $resultados = $this->stock($offset, $this->tipo_stock);
            }
         }
         else
            $this->resultados = $this->stock($this->offset, $this->tipo_stock);
      }
      else if($this->pestanya == 'impuestos')
      {
         $this->impuesto = new impuesto();
         
         $this->codimpuesto = '';
         if( isset($_REQUEST['codimpuesto']) )
         {
            $this->codimpuesto = $_REQUEST['codimpuesto'];
         }
         
         /// ¿Hacemos cambio?
         if( isset($_POST['new_codimpuesto']) )
         {
            if($_POST['new_codimpuesto'] != '')
            {
               $sql = "UPDATE articulos SET codimpuesto = ".$this->impuesto->var2str($_POST['new_codimpuesto']);
               if($this->codimpuesto == '')
               {
                  $sql .= " WHERE codimpuesto IS NULL";
               }
               else
               {
                  $sql .= " WHERE codimpuesto = ".$this->impuesto->var2str($this->codimpuesto);
               }
               
               if( $this->db->exec($sql) )
               {
                  $this->new_message('cambios aplicados correctamente.');
               }
               else
               {
                  $this->new_error_msg('Error al aplicar los cambios.');
               }
            }
         }
         
         /// buscamos en la tabla
         $sql = "SELECT * FROM articulos";
         if($this->codimpuesto == '')
         {
            $sql .= " WHERE codimpuesto IS NULL";
         }
         else
         {
            $sql .= " WHERE codimpuesto = ".$this->impuesto->var2str($this->codimpuesto);
         }
         $this->resultados = array();
         $data = $this->db->select_limit($sql.' ORDER BY referencia ASC', 1000, 0);
         if($data)
         {
            foreach($data as $d)
               $this->resultados[] = new articulo($d);
         }
      }
      else if($this->pestanya == 'search')
      {
         $this->referencia = '';
         $this->desde = Date('1-m-Y');
         $this->hasta = Date('d-m-Y', mktime(0, 0, 0, date("m")+1, date("1")-1, date("Y")));
         $this->documento = 'facturascli';
         
         if( isset($_POST['referencia']) )
         {
            $this->referencia = $_POST['referencia'];
            $this->desde = $_POST['desde'];
            $this->hasta = $_POST['hasta'];
            $this->documento = $_POST['documento'];
            
            $this->resultados = $this->resultados_articulo($this->referencia, $this->desde, $this->hasta, $this->documento);
            if(!$this->resultados)
            {
               $this->new_message('Sin resultados.');
            }
         }
         else if( isset($_GET['ref']) )
         {
            $this->referencia = $_GET['ref'];
            
            $this->resultados = $this->resultados_articulo($this->referencia, $this->desde, $this->hasta, $this->documento);
            if(!$this->resultados)
            {
               $this->new_message('Sin resultados.');
            }
         }
         else
            $this->resultados = array();
      }
   }
   
   private function stats()
   {
      $stats = array(
          'total' => 0,
          'con_stock' => 0,
          'bloqueados' => 0,
          'publicos' => 0,
          'factualizado' => Date('d-m-Y', strtotime(0) )
      );
      
      $aux = $this->db->select("SELECT GREATEST( COUNT(referencia), 0) as art,
         GREATEST( SUM(case when stockfis > 0 then 1 else 0 end), 0) as stock,
         GREATEST( SUM(".$this->db->sql_to_int('bloqueado')."), 0) as bloq,
         GREATEST( SUM(".$this->db->sql_to_int('publico')."), 0) as publi,
         MAX(factualizado) as factualizado FROM articulos;");
      if($aux)
      {
         $stats['total'] = intval($aux[0]['art']);
         $stats['con_stock'] = intval($aux[0]['stock']);
         $stats['bloqueados'] = intval($aux[0]['bloq']);
         $stats['publicos'] = intval($aux[0]['publi']);
         $stats['factualizado'] = Date('d-m-Y', strtotime($aux[0]['factualizado']) );
      }
      
      return $stats;
   }
   
   private function top_articulo_faccli()
   {
      $toplist = $this->cache->get_array('faccli_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $desde = date('d-m-Y', strtotime('-1month'));
         $sql = "SELECT referencia, SUM(cantidad) as unidades, SUM(pvptotal) as total "
                 . "FROM lineasfacturascli WHERE idfactura IN (SELECT idfactura FROM facturascli WHERE fecha >= "
                 . $articulo->var2str($desde).") GROUP BY referencia ORDER BY total DESC";
         
         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
               {
                  if( floatval($l['unidades']) > 1 )
                  {
                     $toplist[] = array(
                         'articulo' => $art0,
                         'unidades' => floatval($l['unidades']),
                         'total' => floatval($l['total'])
                     );
                  }
               }
            }
         }
         $this->cache->set('faccli_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function sin_vender()
   {
      $toplist = $this->cache->get_array('top_articulos_sin_vender');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT * FROM articulos WHERE stockfis > 0 AND sevende AND
            referencia NOT IN (SELECT referencia FROM lineasfacturascli
            WHERE idfactura IN (SELECT idfactura FROM facturascli
            WHERE fecha >= ".$articulo->var2str(Date('1-m-Y')).")) ORDER BY stockfis DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
               $toplist[] = new articulo($l);
         }
         $this->cache->set('top_articulos_sin_vender', $toplist);
      }
      return $toplist;
   }
   
   private function top_articulo_facpro()
   {
      $toplist = $this->cache->get('facpro_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT referencia, SUM(cantidad) as compras
            FROM lineasfacturasprov GROUP BY referencia ORDER BY compras DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
                  $toplist[] = array($art0, intval($l['compras']));
            }
         }
         $this->cache->set('facpro_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function stock($offset = 0, $tipo = 'todo')
   {
      $slist = array();
      
      $sql = "SELECT codalmacen,s.referencia,a.descripcion,s.cantidad,a.stockmin,a.stockmax "
              . "FROM stocks s, articulos a WHERE s.referencia = a.referencia";
      
      if($tipo == 'min')
      {
         $sql .= " AND s.cantidad < a.stockmin";
      }
      else if($tipo == 'max')
      {
         $sql .= " AND a.stockmax > 0 AND s.cantidad > a.stockmax";
      }
      
      $sql .= " ORDER BY referencia ASC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $slist[] = $d;
      }
      
      return $slist;
   }
   
   private function regularizaciones_stock($offset = 0)
   {
      $slist = array();
      
      $sql = "SELECT s.codalmacen,s.referencia,a.descripcion,r.cantidadini,r.cantidadfin,r.nick,r.motivo,r.fecha,r.hora "
              . "FROM stocks s, articulos a, lineasregstocks r WHERE r.idstock = s.idstock AND s.referencia = a.referencia";
      $sql .= " ORDER BY fecha DESC, hora DESC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $slist[] = $d;
      }
      
      return $slist;
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock;
      
      if($this->offset>'0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock;
      
      if(count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   private function buscar_referencia()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $articulo = new articulo();
      $json = array();
      foreach($articulo->search($_REQUEST['buscar_referencia']) as $art)
      {
         $json[] = array('value' => $art->referencia, 'data' => $art->referencia);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_referencia'], 'suggestions' => $json) );
   }
   
   private function resultados_articulo($ref, $desde, $hasta, $tabla)
   {
      $rlist = array();
      $agente = new agente();
      $agentes = $agente->all();
      
      $nombre = 'nombre';
      if($tabla == 'facturascli')
      {
         $nombre = 'nombrecliente';
      }
      
      $data = $this->db->select("SELECT f.idfactura,fecha,codigo,pagada,".$nombre.",codagente,cantidad,pvpunitario,dtopor,pvptotal,iva "
              . "FROM ".$tabla." f, lineas".$tabla." l WHERE f.idfactura = l.idfactura "
              . "AND l.referencia = ".$this->empresa->var2str($ref)." AND fecha >= ".$this->empresa->var2str($desde)
              ." AND fecha <= ".$this->empresa->var2str($hasta)." ORDER BY fecha DESC;");
      if($data)
      {
         foreach($data as $d)
         {
            $linea = array(
                'idfactura' => intval($d['idfactura']),
                'url' => '',
                'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                'codigo' => $d['codigo'],
                'pagada' => ($d['pagada'] == 't' OR $d['pagada'] == '1'),
                'nombre' => $d[$nombre],
                'agente' => $d['codagente'],
                'cantidad' => floatval($d['cantidad']),
                'pvpunitario' => floatval($d['pvpunitario']),
                'dtopor' => floatval($d['dtopor']),
                'pvptotal' => floatval($d['pvptotal']),
                'total' => floatval($d['pvptotal']) * (100 + floatval($d['iva'])) / 100
            );
            
            if($tabla == 'facturascli')
            {
               $linea['url'] = 'index.php?page=ventas_factura&id='.$linea['idfactura'];
            }
            else if($tabla == 'facturasprov')
            {
               $linea['url'] = 'index.php?page=compras_factura&id='.$linea['idfactura'];
            }
            
            /// rellenamos el nombre del agente
            if( is_null($linea['agente']) )
            {
               $linea['agente'] = '-';
            }
            else
            {
               foreach($agentes as $ag)
               {
                  if($ag->codagente == $linea['agente'])
                  {
                     $linea['agente'] = $ag->get_fullname();
                     break;
                  }
               }
            }
            
            $rlist[] = $linea;
         }
      }
      
      return $rlist;
   }
   
   private function share_extension()
   {
      /// añadimos la extensión a artículos
      $extensiones = array(
          array(
              'name' => 'informe_articulo',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_articulo',
              'type' => 'tab_button',
              'text' => '<span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> &nbsp; Informe',
              'params' => '&tab=search'
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
}
