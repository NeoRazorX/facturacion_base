<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_once 'plugins/facturacion_base/extras/xlsxwriter.class.php';
require_model('almacen.php');
require_model('articulo.php');
require_model('cliente.php');
require_model('familia.php');
require_model('impuesto.php');
require_model('linea_factura_cliente.php');
require_model('linea_factura_proveedor.php');
require_model('regularizacion_stock.php');
require_model('stock.php');

class informe_articulos extends fbase_controller
{
   public $agente;
   public $almacen;
   public $articulo;
   public $cantidades;
   public $codagente;
   public $codalmacen;
   public $codfamilia;
   public $codimpuesto;
   public $desde;
   public $documento;
   public $familia;
   public $hasta;
   public $impuesto;
   public $minimo;
   public $offset;
   public $pestanya;
   public $referencia;
   public $resultados;
   public $sin_vender;
   public $stats;
   public $stock;
   public $tipo_stock;
   public $top_ventas;
   public $top_compras;
   public $url_recarga;
   public $meses;
   private $tablas;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'informes');
   }

   protected function private_core()
   {
      parent::private_core();

      $this->agente = new agente();
      $this->almacen = new almacen();
      $this->documento = 'facturascli';
      $this->familia = new familia();
      $this->stock = new stock();
      $this->tablas = $this->db->list_tables();
      $this->url_recarga = FALSE;

      $this->meses = array();
      $this->meses[1] = 'ene';
      $this->meses[2] = 'feb';
      $this->meses[3] = 'mar';
      $this->meses[4] = 'abr';
      $this->meses[5] = 'may';
      $this->meses[6] = 'jun';
      $this->meses[7] = 'jul';
      $this->meses[8] = 'ago';
      $this->meses[9] = 'set';
      $this->meses[10] = 'oct';
      $this->meses[11] = 'nov';
      $this->meses[12] = 'dic';

      $tab = \filter_input(INPUT_GET, 'tab');
      $this->pestanya = ($tab)?$tab:'stats';

      $this->codalmacen = FALSE;
      $codalmacen_p = \filter_input(INPUT_POST, 'codalmacen');
      $codalmacen_g = \filter_input(INPUT_GET, 'codalmacen');
      $codalmacen = ($codalmacen_p)?$codalmacen_p:$codalmacen_g;
      $this->codalmacen = ($codalmacen)?$codalmacen:$this->codalmacen;

      $this->desde = \date('01-m-Y');
      $desde_p = \filter_input(INPUT_POST, 'desde');
      $desde_g = \filter_input(INPUT_GET, 'desde');
      $desde = ($desde_p)?$desde_p:$desde_g;
      $this->desde = ($desde)?$desde:$this->desde;

      $this->hasta = \date('t-m-Y');
      $hasta_p = \filter_input(INPUT_POST, 'hasta');
      $hasta_g = \filter_input(INPUT_GET, 'hasta');
      $hasta = ($hasta_p)?$hasta_p:$hasta_g;
      $this->hasta = ($hasta)?$hasta:$this->hasta;

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
         $this->stocks();
      }
      else if($this->pestanya == 'impuestos')
      {
         $this->impuestos();
      }
      else if($this->pestanya == 'varios')
      {
         $this->varios();
      }
   }

   public function stocks()
   {
      $this->tipo_stock = 'todo';
      $tipo_stock = \filter_input(INPUT_GET, 'tipo');
      $recalcular = \filter_input(INPUT_GET, 'recalcular');
      if($tipo_stock)
      {
         $this->tipo_stock = $tipo_stock;
      }
      else if($recalcular)
      {
         $this->recalcular_stock();
      }

      if($this->tipo_stock == 'reg')
      {
         /// forzamos la comprobación de la tabla stocks
         $reg = new regularizacion_stock();

         $this->resultados = $this->regularizaciones_stock($this->offset);
      }
      else if( isset($_GET['download']) )
      {
         $archivo = \filter_input(INPUT_GET, 'archivo');
         $this->download_stock($archivo);
      }
      else
      {
         $this->resultados = $this->stock($this->offset, $this->tipo_stock);
      }
   }

   public function impuestos()
   {
      $this->impuesto = new impuesto();
      $this->codimpuesto = '';
      if( isset($_REQUEST['codimpuesto']) )
      {
         $this->codimpuesto = $_REQUEST['codimpuesto'];
      }

      /// ¿Hacemos cambio?
      $this->cambia_impuesto();
   }

   public function varios()
   {
      $this->cantidades = FALSE;
      $this->codfamilia = '';
      $this->minimo = '';

      $this->referencia = '';
      if( isset($_GET['ref']) )
      {
         $this->referencia = $_GET['ref'];
      }

      $this->resultados = array();
      if( isset($_POST['informe']) )
      {
         if($_POST['informe'] == 'listadomov')
         {
            $this->referencia = $_POST['referencia'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->codagente = $_POST['codagente'];

            $this->informe_movimientos();
         }
         else if($_POST['informe'] == 'facturacion')
         {
            $this->documento = $_POST['documento'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->cantidades = ($_POST['cantidades'] == 'TRUE');
            $this->minimo = $_POST['minimo'];

            $this->informe_facturacion();
         }
         else if($_POST['informe'] == 'ventascli')
         {
            $this->referencia = $_POST['referencia'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->minimo = $_POST['minimo'];

            $this->informe_ventascli();
         }
      }
   }

   private function recalcular_stock()
   {
      $articulo = new articulo();
      $continuar = FALSE;
      $offset = intval($_GET['offset']);

      $almacenes = (!$this->codalmacen)?$this->almacen->all():array($this->almacen->get($this->codalmacen));
      foreach($articulo->all($offset, 30) as $art)
      {
         if(!$art->nostock AND !$art->bloqueado)
         {
            foreach($almacenes as $alm)
            {
               $this->stock->saldo_articulo($art->referencia, $alm->codalmacen);
            }
         }
         $continuar = TRUE;
         $offset++;
      }

      if($continuar)
      {
         if($this->codalmacen)
         {
            $this->new_message('Recalculando stock de artículos del almacen '.$this->codalmacen.' <i class="fa fa-spinner fa-pulse fa-fw"></i>... '.$offset);
         }
         else
         {
            $this->new_message('Recalculando stock de artículos <i class="fa fa-spinner fa-pulse fa-fw"></i>...'.$offset);
         }
         $this->url_recarga = $this->url().'&tab=stock&recalcular=TRUE&offset='.$offset.'&codalmacen='.$this->codalmacen;
      }
      else
      {
         $this->new_advice('Finalizado &nbsp; <span class="fa fa-ok" aria-hidden="true"></span>');
      }
   }

   private function download_stock($archivo)
   {
      $this->template = FALSE;
      $offset = 0;
      $resultados = $this->stock($offset, $this->tipo_stock);
      $lista = array();
      while( count($resultados) > 0 )
         {
            foreach($resultados as $res)
            {
               $linea = array();
               $linea[] = $res['codalmacen'];
               $linea[] = $res['referencia'];
               $linea[] = $res['descripcion'];
               $linea[] = $res['cantidad'];
               $linea[] = $res['stockmin'];
               $linea[] = $res['stockmax'];
               $lista[] = $linea;
               $offset++;
            }

            $resultados = $this->stock($offset, $this->tipo_stock);
         }
      if($archivo == 'CSV')
      {
         $cabecera = "almacen,referencia,descripcion,stock,stockmin,stockmax\n";
         $this->generar_archivo('stock',$cabecera,$lista,'csv');
      }
      elseif($archivo == 'XLSX')
      {
         $header = array(
            'Almacen' => 'string',
            'Referencia' => '@',
            'Descripción' => 'string',
            'Stock' => '0',
            'Mínimo' => '0',
            'Máximo' => '0'
         );
         $this->generar_archivo('stock',$header,$lista,'xlsx');
      }
   }

   private function cambia_impuesto()
   {
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
         {
            $this->resultados[] = new articulo($d);
         }
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

      $sql = "SELECT GREATEST( COUNT(referencia), 0) as art,"
              ." GREATEST( SUM(case when stockfis > 0 then 1 else 0 end), 0) as stock,"
              ." GREATEST( SUM(case when bloqueado then 1 else 0 end), 0) as bloq,"
              ." GREATEST( SUM(case when publico then 1 else 0 end), 0) as publi,"
              ." MAX(factualizado) as factualizado FROM articulos;";

      $aux = $this->db->select($sql);
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
      /// buscamos el resultado en caché
      $toplist = $this->cache->get_array('faccli_top_articulos');
      if( !$toplist OR isset($_POST['desde']) )
      {
         $toplist = array();
         $articulo = new articulo();
         $sql = "SELECT l.referencia, SUM(l.cantidad) as unidades, SUM(l.pvptotal/f.tasaconv) as total"
                 . " FROM lineasfacturascli l, facturascli f"
                 . " WHERE l.idfactura = f.idfactura AND l.referencia IS NOT NULL"
                 . " AND f.fecha >= ". $articulo->var2str($this->desde)
                 . " AND f.fecha <= ". $articulo->var2str($this->hasta)
                 . " GROUP BY referencia"
                 . " ORDER BY unidades DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
               {
                  $toplist[] = array(
                      'articulo' => $art0,
                      'unidades' => floatval($l['unidades']),
                      'total' => $this->euro_convert( floatval($l['total']) ),
                      'beneficio' => $this->euro_convert( floatval($l['total']) ) - ( floatval($l['unidades']) * $art0->preciocoste() )
                  );
               }
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('faccli_top_articulos', $toplist, 300);
      }

      return $toplist;
   }

   private function sin_vender()
   {
      $toplist = $this->cache->get_array('top_articulos_sin_vender');
      if(!$toplist)
      {
         $articulo = new articulo();
         $sql = "SELECT * FROM articulos WHERE sevende = true"
                 . " AND bloqueado = false AND stockfis > 0 AND referencia NOT IN "
                 . "(SELECT DISTINCT(referencia) FROM lineasfacturascli WHERE referencia IS NOT NULL"
                 . " AND idfactura IN (SELECT idfactura FROM facturascli"
                 . " WHERE fecha >= ".$articulo->var2str(Date('1-1-Y'))."))"
                 . " ORDER BY stockfis DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $toplist[] = new articulo($l);
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('top_articulos_sin_vender', $toplist);
      }

      return $toplist;
   }

   private function top_articulo_facpro()
   {
      $toplist = $this->cache->get('facpro_top_articulos');
      if( !$toplist OR isset($_POST['desde']) )
      {
         $articulo = new articulo();
         $sql = "SELECT l.referencia, SUM(l.cantidad) as compras FROM lineasfacturasprov l, facturasprov f"
                 . " WHERE l.idfactura = f.idfactura AND l.referencia IS NOT NULL"
                 . " AND f.fecha >= ". $articulo->var2str($this->desde)
                 . " AND f.fecha <= ". $articulo->var2str($this->hasta)
                 . " GROUP BY referencia"
                 . " ORDER BY compras DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
               {
                  $toplist[] = array($art0, intval($l['compras']));
               }
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('facpro_top_articulos', $toplist);
      }

      return $toplist;
   }

   private function stock($offset = 0, $tipo = 'todo')
   {
      $slist = array();

      $sql = "SELECT codalmacen,s.referencia,a.descripcion,s.cantidad,a.stockmin,a.stockmax"
              . " FROM stocks s, articulos a WHERE s.referencia = a.referencia AND nostock = FALSE and bloqueado = FALSE ";

      if($tipo == 'min')
      {
         $sql .= " AND s.cantidad < a.stockmin";
      }
      else if($tipo == 'max')
      {
         $sql .= " AND a.stockmax > 0 AND s.cantidad > a.stockmax";
      }
      else if($tipo == 'constock')
      {
         $sql .= " AND s.cantidad != 0 ";
      }
      else if($tipo == 'sinstock')
      {
         $sql .= " AND s.cantidad = 0 ";
      }

      if($this->codalmacen)
      {
         $sql .= " AND s.codalmacen = " . $this->empresa->var2str($this->codalmacen);
      }

      $sql .= " ORDER BY referencia ASC";

      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
         {
            $slist[] = $d;
         }
      }

      return $slist;
   }

   private function regularizaciones_stock($offset = 0)
   {
      $slist = array();

      $sql = "SELECT s.codalmacen,s.referencia,a.descripcion,r.cantidadini,r.cantidadfin,r.nick,r.motivo,r.fecha,r.hora "
              . "FROM stocks s, articulos a, lineasregstocks r WHERE r.idstock = s.idstock AND s.referencia = a.referencia";
      if($this->codalmacen)
      {
         $sql.=" AND codalmacen = ".$this->empresa->var2str($this->codalmacen);
      }
      $sql .= " ORDER BY fecha DESC, hora DESC";

      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
         {
            $slist[] = $d;
         }
      }

      return $slist;
   }

   public function anterior_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock.'&codalmacen='.$this->codalmacen;

      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }

      return $url;
   }

   public function siguiente_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock.'&codalmacen='.$this->codalmacen;

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
         $json[] = array('value' => $art->referencia.' '.$art->descripcion(60), 'data' => $art->referencia);
      }

      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_referencia'], 'suggestions' => $json) );
   }

   private function informe_facturacion()
   {
      $sumar = 'pvptotal';
      if($this->cantidades)
      {
         $sumar = 'cantidad';
      }

      $sql_date = "YEAR(f.fecha) as anho, MONTH(f.fecha) as mes";
      if(FS_DB_TYPE == 'POSTGRESQL')
      {
         $sql_date = "date_part('year',f.fecha) as anho,date_part('month',f.fecha) as mes";
      }
      $sql = "SELECT l.referencia,".$sql_date.",SUM(".$sumar.") as total"
              . " FROM lineas".$this->documento." l JOIN ".$this->documento." f ON (f.idfactura = l.idfactura) "
              . " WHERE anulada = FALSE "
              . " AND referencia IS NOT NULL AND referencia != ''"
              . " AND fecha >= ".$this->empresa->var2str(\date('Y-m-d',strtotime($this->desde)))
              . " AND fecha <= ".$this->empresa->var2str(\date('Y-m-d',strtotime($this->hasta)));

      if( is_numeric($this->minimo) )
      {
         $sql .= " AND ".$sumar." >= ".$this->empresa->var2str($this->minimo);
      }

      if($this->codfamilia != '')
      {
         $sql .= " AND referencia IN (SELECT referencia FROM articulos"
                 . " WHERE codfamilia IN (";
         $coma = '';
         foreach($this->get_subfamilias($this->codfamilia) as $fam)
         {
            $sql .= $coma.$this->empresa->var2str($fam);
            $coma = ',';
         }
         $sql .= "))";
      }

      $sql .= " GROUP BY referencia,anho,mes ORDER BY anho,mes";
      $data = $this->db->select($sql);
      $rango_anhos = array();
      $informacion_anho = array();
      $informacion_anho_total = array();
      if($data)
      {
         foreach($data as $d)
         {
            if(!isset($informacion_anho_total[$d['referencia']][$d['anho']]))
            {
               $informacion_anho_total[$d['referencia']][$d['anho']] = 0;
               $stats_year[$d['referencia']][$d['anho']] = 0;
            }
            $rango_anhos[$d['anho']] = $d['anho'];
            $informacion_anho[$d['referencia']][$d['anho']][$d['mes']] = $d['total'];
            $informacion_anho_total[$d['referencia']][$d['anho']] += $d['total'];
         }

         $stats_year = array();
         //Calculamos la variación anual
         foreach($informacion_anho_total as $ref => $data)
         {
            foreach($data as $anho=>$total)
            {
               $last_stats = 0;
               $last_year = $anho-1;
               if(isset($stats_year[$ref][$last_year]))
               {
                  $last_stats = round((($total/$informacion_anho_total[$ref][$last_year])*100)-100,0);
               }
               $stats_year[$ref][$anho] = $last_stats;
            }
         }

         $this->template = FALSE;
         $articulo = new articulo();
         $lista = array();
         foreach ($informacion_anho as $ref => $info)
            {
               $desc_art = $articulo->get($ref);
               foreach ($rango_anhos as $y)
               {
                  $linea = array();
                  $linea[] = $ref;
                  $linea[] = $desc_art->descripcion;
                  $linea[] = $y;
                  foreach ($this->meses as $m => $dm)
                  {

                     $linea[] = (isset($informacion_anho[$ref][$y][$m]))?$informacion_anho[$ref][$y][$m]:0;
                  }
                  if(isset($informacion_anho_total[$ref][$y]))
                  {
                     $linea[] = $informacion_anho_total[$ref][$y];
                     $escribir = true;
                  }
                  else
                  {
                     $escribir = false;
                  }
                  $linea[] = (isset($stats_year[$ref][$y]))?$stats_year[$ref][$y].'%':'0%';
                  if($escribir)
                  {
                     $lista[] = $linea;
                  }
               }
            }
         $generar = \filter_input(INPUT_POST, 'generar');
         if( $generar == 'csv' )
         {
            $cabecera = "referencia;descripcion;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
            $this->generar_archivo('informe_facturacion', $cabecera, $lista, 'csv');
         }
         elseif($generar=='xlsx')
         {
            $header = array(
               'Referencia' => '@',
               'Descripcion' => 'string',
               'Año' => '0',
            );
            foreach($this->meses as $mes)
            {
               $header[$mes] = '0';
            }
            $header['Total']='0';
            $header['%VAR']='@';
            $this->generar_archivo('informe_facturacion', $header, $lista, 'xlsx');
         }
      }
      else
      {
         $this->new_message('Sin resultados.');
      }
   }

   private function get_subfamilias($cod)
   {
      $familias = array($cod);

      $data = $this->db->select("SELECT codfamilia,madre FROM familias WHERE madre = ".$this->empresa->var2str($cod).";");
      if($data)
      {
         foreach($data as $d)
         {
            foreach($this->get_subfamilias($d['codfamilia']) as $subf)
            {
               $familias[] = $subf;
            }
         }
      }

      return $familias;
   }

   private function informe_movimientos()
   {
      if($this->codfamilia)
      {
         $familia = $this->familia->get($this->codfamilia);
         if($familia)
         {
            foreach($familia->get_articulos() as $art)
            {
               foreach( $this->stock->get_movimientos($art->referencia, $this->desde, $this->hasta, $this->codalmacen, $this->codagente) as $mov )
               {
                  $this->resultados[] = $mov;
               }
            }
         }
         else
         {
            $this->new_advice('Familia no encontrada.');
         }
      }
      else if($this->referencia == '')
      {
         $this->new_advice('Selecciona una referencia o una familia.');
      }
      else
      {
         $this->resultados = $this->stock->get_movimientos($this->referencia, $this->desde, $this->hasta, $this->codalmacen, $this->codagente);
      }
      $generar = \filter_input(INPUT_POST, 'generar');
      if( $generar )
      {
         $this->template = FALSE;
         $lista = array();
         foreach($this->resultados as $value)
         {
            $linea = array();
            $linea[] = $value['referencia'];
            $linea[] = $value['codalmacen'];
            $linea[] = $value['origen'];
            $linea[] = fs_fix_html($value['clipro']);
            $linea[] = $value['movimiento'];
            $linea[] = $value['precio'];
            $linea[] = $value['dto'];
            $linea[] = $value['final'];
            $linea[] = \date('Y-m-d',strtotime($value['fecha']));
            $lista[] = $linea;
         }
         if($generar == 'csv')
         {
            $cabecera = "referencia;almacen;documento;cliente/proveedor;movimiento;precio;%dto;cantidad final;fecha\n";
            $this->generar_archivo('listado_movimientos',$cabecera,$lista,'csv');
         }
         elseif($generar == 'xlsx')
         {
            $header = array(
               'Referencia' => '@',
               'Almacen' => 'string',
               'Documento' => 'string',
               'Cliente/Proveedor' => 'string',
               'Movimiento' => '0',
               'Precio' => 'price',
               'Descuento' => '0',
               'Cantidad' => '0',
               'Fecha' => 'date'
            );
            $this->generar_archivo('listado_movimientos', $header, $lista, 'xlsx');
         }
         else if(!$this->resultados)
         {
            $this->template = TRUE;
            $this->new_message('Sin resultados.');
         }
      }
   }

   private function informe_ventascli()
   {
      $sql_date = "YEAR(f.fecha) as anho, MONTH(f.fecha) as mes";
      if(FS_DB_TYPE == 'POSTGRESQL')
      {
         $sql_date = "date_part('year',f.fecha) as anho,date_part('month',f.fecha) as mes";
      }
      $sql = "SELECT l.referencia,f.codcliente,".$sql_date.",SUM(l.cantidad) as total"
              . " FROM lineasfacturascli l JOIN facturascli f ON (f.idfactura = l.idfactura) "
              . " WHERE anulada = FALSE AND l.referencia IS NOT NULL"
              . " AND f.fecha >= ".$this->empresa->var2str(\date('Y-m-d',strtotime($_POST['desde'])))
              . " AND f.fecha <= ".$this->empresa->var2str(\date('Y-m-d',strtotime($_POST['hasta'])));

      if($this->referencia != '')
      {
         $sql .= " AND l.referencia = ".$this->empresa->var2str($this->referencia);
      }
      else if($this->codfamilia != '')
      {
         $sql .= " AND l.referencia IN (SELECT referencia FROM articulos"
                 . " WHERE codfamilia IN (";
         $coma = '';
         foreach($this->get_subfamilias($this->codfamilia) as $fam)
         {
            $sql .= $coma.$this->empresa->var2str($fam);
            $coma = ',';
         }
         $sql .= "))";
      }

      if($_POST['minimo'] != '')
      {
         $sql .= " AND l.cantidad > ".$this->empresa->var2str($_POST['minimo']);
      }

      $sql .= " GROUP BY l.referencia,f.codcliente,anho,mes ORDER BY l.referencia ASC, f.codcliente ASC, anho, mes;";
      $data = $this->db->select($sql);
      $rango_anhos = array();
      $informacion_anho = array();
      $informacion_anho_total = array();
      if($data)
      {
         foreach ($data as $d)
         {
            if (!isset($informacion_anho_total[$d['referencia']][$d['codcliente']][$d['anho']]))
            {
               $informacion_anho_total[$d['referencia']][$d['codcliente']][$d['anho']] = 0;
               $stats_year[$d['referencia']][$d['codcliente']][$d['anho']] = 0;
            }
            $rango_anhos[$d['anho']] = $d['anho'];
            $informacion_anho[$d['referencia']][$d['codcliente']][$d['anho']][$d['mes']] = $d['total'];
            $informacion_anho_total[$d['referencia']][$d['codcliente']][$d['anho']] += $d['total'];
         }

         $stats_year = array();
         //Calculamos la variación anual
         foreach ($informacion_anho_total as $ref => $clientes)
         {
            foreach($clientes as $cli=>$data)
            {
               foreach ($data as $anho => $total)
               {
                  $last_stats = 0;
                  $last_year = $anho - 1;
                  if (isset($stats_year[$ref][$cli][$last_year]))
                  {
                     $last_stats = round((($total / $informacion_anho_total[$ref][$cli][$last_year]) * 100) - 100, 0);
                  }
                  $stats_year[$ref][$cli][$anho] = $last_stats;
               }
            }
         }

         //Generamos las lineas a escribir
         $this->template = FALSE;
         $articulo = new articulo();
         $cliente = new cliente();
         $generar = \filter_input(INPUT_POST, 'generar');
         $lista = array();
         foreach ($informacion_anho as $ref => $clientes)
         {
            $desc_art = $articulo->get($ref);
            foreach($clientes as $cli => $info)
            {
               $cli_info = $cliente->get($cli);
               foreach ($rango_anhos as $y)
               {
                  $linea = array();
                  $linea[] = $ref;
                  $linea[] = $desc_art->descripcion;
                  $linea[] = $cli;
                  $linea[] = $cli_info->nombre;
                  $linea[] = $y;
                  foreach ($this->meses as $m => $dm)
                  {

                     $linea[] = (isset($informacion_anho[$ref][$cli][$y][$m]))?$informacion_anho[$ref][$cli][$y][$m]:0;
                  }
                  if(isset($informacion_anho_total[$ref][$cli][$y]))
                  {
                     $linea[] = $informacion_anho_total[$ref][$cli][$y];
                     $escribir = true;
                  }
                  else
                  {
                     $escribir = false;
                  }
                  $linea[] = (isset($stats_year[$ref][$cli][$y]))?$stats_year[$ref][$cli][$y].'%':'0%';
                  if($escribir)
                  {
                     $lista[] = $linea;
                  }
               }
            }
         }
         if($generar== 'csv')
         {
            $cabecera = "referencia;descripcion;codcliente;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
            $this->generar_archivo('informe_ventas_unidades', $cabecera, $lista, 'csv');
         }
         elseif($generar == 'xlsx')
         {
            $header = array(
               'Referencia' => '@',
               'Descripcion' => 'string',
               'Cliente' => '@',
               'Nombre' => 'string',
               'Año' => '0',
            );
            foreach($this->meses as $mes)
            {
               $header[$mes] = '0';
            }
            $header['Total']='0';
            $header['%VAR']='@';
            $this->generar_archivo('informe_ventas_unidades', $header, $lista, 'xlsx');
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
      }
   }

   public function generar_archivo($archivo,$cabecera,$datos,$tipo='csv')
   {
      if($tipo=='csv')
      {
         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"$archivo.csv\"");
         echo $cabecera;
         foreach($datos as $l)
         {
            $lin = implode(';',$l);
            echo $lin."\n";
         }
      }
      elseif($tipo=='xlsx')
      {
         header("Content-Disposition: attachment; filename=\"".$archivo."_".time().".xlsx\"");
         header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
         header('Content-Transfer-Encoding: binary');
         header('Cache-Control: must-revalidate');
         header('Pragma: public');
         foreach($this->meses as $mes)
         {
            $header[$mes] = '0';
         }
         $header['Total']='0';
         $header['%VAR']='@';

         $writer = new XLSXWriter();
         $writer->writeSheetHeader('Ventas Unidades', $cabecera);
         foreach($datos as $l)
         {
            $writer->writeSheetRow('Ventas Unidades',$l);
         }
         $writer->writeToStdOut();
      }
   }
}
